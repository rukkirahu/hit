<?php

namespace Hit\Lingo;

class Translator {
	/** @var array<string, array<string, string>> Keyed by lang code */
	private array $translations = [];

	private bool $dirty = false;

	public function __construct(
		private readonly string $lang,
		private readonly string $path,
	) {
		$this->translations[$lang] = $this->loadFile($lang);
	}

	/** @param string[] $langs */
	public function enableDevMode(array $langs): void {
		foreach ($langs as $lang) {
			$this->translations[$lang] = $this->loadFile($lang);
		}

		register_shutdown_function(function () {
			if ($this->dirty) {
				$this->saveAll();
			}
		});
	}

	public function get(string $key, string ...$args): string {
		if (count($args) > 9) {
			throw new \RuntimeException('Lingo::get() supports a maximum of 9 positional arguments');
		}

		if (!array_key_exists($key, $this->translations[$this->lang])) {
			foreach ($this->translations as $lang => $_) {
				if (array_key_exists($key, $this->translations[$lang])) {
					continue;
				}

				$this->translations[$lang][$key] = $key;
			}
			$this->dirty = true;
		}

		$value = $this->translations[$this->lang][$key];

		if ($args) {
			$search = [];
			$replace = [];
			// Replace in reverse order (%9 first) to avoid %1 matching the start of %10 etc.
			for ($i = count($args); $i >= 1; $i--) {
				$search[] = '%' . $i;
				$replace[] = $args[$i - 1];
			}
			$value = str_replace($search, $replace, $value);
		}

		return $value;
	}

	public function isDirty(): bool {
		return $this->dirty;
	}

	public function save(): void {
		if ($this->dirty) {
			$this->saveAll();
		}
	}

	/** @return array<string, string> */
	private function loadFile(string $lang): array {
		$file = $this->getFilePath($lang);

		if (!file_exists($file)) {
			return [];
		}

		$data = require $file;

		if (!is_array($data)) {
			return [];
		}

		/** @var array<string, string> */
		return array_filter($data, static fn($v, $k) => is_string($v) && is_string($k), ARRAY_FILTER_USE_BOTH);
	}

	private function saveAll(): void {
		foreach ($this->translations as $lang => $data) {
			$this->writeFile($lang, $data);
		}
	}

	private function writeFile(string $lang, array $translations): void {
		$file = $this->getFilePath($lang);
		$exported = var_export($translations, true);
		file_put_contents($file, "<?php\n\nreturn " . $exported . ";\n");
	}

	private function getFilePath(string $lang): string {
		return rtrim($this->path, '/') . '/lingo.' . $lang . '.php';
	}
}
