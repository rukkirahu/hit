<?php

declare(strict_types=1);

namespace Tests\Support;

use Hit\Cache\Store\StoreInterface;

/**
 * Minimal in-memory StoreInterface used to observe caching behaviour across
 * multiple Discovery instantiations (simulating cache expiry/eviction).
 */
class FakeStore implements StoreInterface {
	private array $store = [];

	public function exists(string $key): bool {
		return array_key_exists($key, $this->store);
	}

	public function get(string $key): mixed {
		return $this->store[$key] ?? null;
	}

	public function set(string $key, array|string $value, int $expires = 0): bool {
		$this->store[$key] = $value;

		return true;
	}

	public function delete(string $key): bool {
		unset($this->store[$key]);

		return true;
	}
}
