<?php

declare(strict_types=1);

namespace Hit\Router\Routes;

use InvalidArgumentException;
use RuntimeException;

final readonly class RouteDefinition {
	public function __construct(
		public string $method,
		public string $uri,
		public string $action,
		public int $accept,
		public int $acl,
	) {
		if ($this->method === '') {
			throw new InvalidArgumentException('Route method cannot be empty');
		}
		if (str_starts_with($this->uri, '/')) {
			throw new InvalidArgumentException("Route URI must be relative: '{$this->uri}'");
		}
		if ($this->action === '') {
			throw new InvalidArgumentException('Route action cannot be empty');
		}
	}

	public static function fromArray(mixed $data): self {
		if (
			!is_array($data)
			|| !is_string($data['method'] ?? null)
			|| !is_string($data['uri'] ?? null)
			|| !is_string($data['action'] ?? null)
			|| !is_int($data['accept'] ?? null)
			|| !is_int($data['acl'] ?? null)
		) {
			throw new RuntimeException('Invalid route definition in cache');
		}

		return new self($data['method'], $data['uri'], $data['action'], $data['accept'], $data['acl']);
	}

	public function toArray(): array {
		return [
			'method' => $this->method,
			'uri' => $this->uri,
			'action' => $this->action,
			'accept' => $this->accept,
			'acl' => $this->acl,
		];
	}
}
