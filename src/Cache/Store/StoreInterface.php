<?php

declare(strict_types=1);

namespace Hit\Cache\Store;

/**
 * Cache Adapter interface
 */
interface StoreInterface {
	/**
	 * Check if a cache exists with the key
	 */
	public function exists(string $key): bool;

	/**
	 * Get contents of a cache by key
	 */
	public function get(string $key): mixed;

	/**
	 * Set contents of a cache
	 * @param string|array $value
	 */
	public function set(string $key, array|string $value, int $expires = 0): bool;

	/**
	 * delete contents of a cache by key
	 */
	public function delete(string $key): bool;
}
