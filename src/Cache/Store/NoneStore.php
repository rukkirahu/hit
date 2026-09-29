<?php

declare(strict_types=1);

namespace Hit\Cache\Store;

/**
 * No caching cache adapter
 */
class NoneStore implements StoreInterface {
	public function exists(string $key): bool {
		return false;
	}

	public function get(string $key): mixed {
		return null;
	}

	public function set(string $key, array|string $value, int $expires = 0): bool {
		return false;
	}

	public function delete(string $key): bool {
		return false;
	}
}
