<?php

declare(strict_types=1);

namespace Hit\Cache\Store;

/**
 * Redis cache
 */
class RedisStore implements StoreInterface {
	public function __construct(
		private \Redis $Redis,
		private int $timeout = 3600,
	) {}

	public function exists(string $key): bool {
		return (bool) $this->Redis->exists($key);
	}

	public function get(string $key): mixed {
		$value = $this->Redis->get($key);
		if ($value === false) {
			return null;
		}

		$decoded = json_decode((string) $value, true);
		if (json_last_error() !== JSON_ERROR_NONE) {
			throw new \RuntimeException('Cache decode error: ' . json_last_error_msg());
		}

		return $decoded;
	}

	public function set(string $key, array|string $value, int $expires = 0): bool {
		$ttl = $expires ?: $this->timeout;

		$encoded = json_encode($value);
		if ($encoded === false) {
			throw new \RuntimeException('Cache encode error: ' . json_last_error_msg());
		}

		return (bool) $this->Redis->setex($key, $ttl, $encoded);
	}

	public function delete(string $key): bool {
		return (bool) $this->Redis->del($key);
	}
}
