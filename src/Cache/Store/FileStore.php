<?php

declare(strict_types=1);

namespace Hit\Cache\Store;

/**
 * File based cache Adapter
 * new object requires directory name where cached values are stored
 */
class FileStore implements StoreInterface {
	public function __construct(
		private string $path,
		private int $timeout = 86_400,
	) {
		$this->init();
	}

	private function init(): void {
		$this->path = rtrim($this->path, ' \\/') . '/';
		if (!is_dir($this->path)) {
			if (!mkdir($this->path, 0o755, true) && !is_dir($this->path)) {
				throw new \Exception("Unable to create cache dir [{$this->path}]");
			}
		}
	}

	public function exists(string $key): bool {
		return (bool) $this->get($key);
	}

	public function get(string $key): mixed {
		$file = $this->getFileName($key);
		if (file_exists($file)) {
			$data = include $file;
			if (!is_array($data)) {
				throw new \Exception('Cache not accessible');
			}
			if (is_array($data) && array_key_exists('data', $data) && intval($data['expires'] ?? 0) >= time()) {
				return $data['data'];
			}
		}

		return null;
	}

	/**
	 * Set contents of a cache.
	 */
	public function set(string $key, array|string $value, int $expires = 0): bool {
		$file = $this->getFileName($key);
		$expires = $expires ?: $this->timeout;

		$dir = dirname($file);
		if (!is_dir($dir) && !mkdir($dir, 0o755, true) && !is_dir($dir)) {
			throw new \Exception("Unable to create cache dir [{$dir}]");
		}

		$code = '<?php return ' . var_export(['expires' => $expires + time(), 'data' => $value], true) . ';';

		// Atomic write (prevents partial reads)
		$tempFile = $file . '.tmp.' . bin2hex(random_bytes(8));
		if (file_put_contents($tempFile, $code) === false) {
			throw new \Exception('Unable to write to cache file');
		}

		// Atomic move
		if (!rename($tempFile, $file)) {
			if (!unlink($tempFile)) {
				throw new \Exception('Unable to remove temporary cache file');
			}
		}

		return true;
	}

	/**
	 * Delete contents of a cache by key
	 */
	public function delete(string $key): bool {
		$file = $this->getFileName($key);
		if (!file_exists($file)) {
			return false;
		}

		return unlink($file);
	}

	/**
	 * Get file name for key
	 */
	private function getFileName(string $key): string {
		$name = self::sanitizeKey($key);
		if (!$name) {
			throw new \Exception("Unable to access cache value [{$key}]");
		}

		return $this->path . $name . '.php';
	}

	/**
	 * Sanitizes key name, removes unwanted characters
	 */
	private static function sanitizeKey(string $key): string {
		//let's try to keep readable file names and ability to create subdirectories in cache dir with keys like 'model/name'
		return str_replace("\\", '/', preg_replace('/[^A-Z0-9.@\-_\/]/iu', '_', $key) ?: '');
	}
}
