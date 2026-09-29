<?php

declare(strict_types=1);

namespace Hit\Cache;

use Hit\Cache\Store\StoreInterface;

class Cache {
	private static ?StoreInterface $cacheInstance = null;

	/**
	 * Registers the proxy instance
	 * @param StoreInterface $instance
	 */
	public static function proxy(StoreInterface $instance): void {
		self::$cacheInstance = $instance;
	}

	public static function instance(): ?StoreInterface {
		return self::$cacheInstance;
	}

	private static function _instance(): StoreInterface {
		return self::$cacheInstance ?? throw new \RuntimeException('Cache not initialized');
	}

	public static function get(string $key): mixed {
		return self::_instance()->get($key);
	}

	/**
	 * Get cache value as string. Returns empty string on miss.
	 */
	public static function getStr(string $key): string {
		return strval(self::_instance()->get($key));
	}

	/**
	 * Get cache value as int. Returns 0 on miss.
	 */
	public static function getInt(string $key): int {
		return intval(self::_instance()->get($key));
	}

	/**
	 * Get cache value as bool. Returns false on miss.
	 */
	public static function getBool(string $key): bool {
		return boolval(self::_instance()->get($key));
	}

	/**
	 * Get cache value as array. Returns empty array on miss.
	 */
	public static function getArray(string $key): array {
		$arr = self::_instance()->get($key);

		return is_array($arr) ? $arr : [];
	}

	public static function set(string $key, array|string $value, int $ttl = 3600): bool {
		return self::_instance()->set($key, $value, $ttl);
	}

	public static function delete(string $key): bool {
		return self::_instance()->delete($key);
	}

	public static function exists(string $key): bool {
		return self::_instance()->exists($key);
	}
}
