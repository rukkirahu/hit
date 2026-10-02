<?php

declare(strict_types=1);

namespace Hit\Router\Routes;

use InvalidArgumentException;
use RuntimeException;

final readonly class CompiledRoute {
	public function __construct(
		public RouteDefinition $definition,
		public string $pattern,
	) {}

	public static function compile(RouteDefinition $definition, string $base): self {
		$pattern = '~^' . preg_quote($base, '~') . str_replace('~', '\\~', $definition->uri) . '$~';
		set_error_handler(static fn(): bool => true);
		try {
			if (preg_match($pattern, '') === false) {
				throw new InvalidArgumentException("Invalid route pattern: {$definition->uri}");
			}
		}
		finally {
			restore_error_handler();
		}

		return new self($definition, $pattern);
	}

	public static function fromArray(mixed $data): self {
		if (!is_array($data) || !is_string($data['pattern'] ?? null)) {
			throw new RuntimeException('Invalid compiled route in cache');
		}

		return new self(RouteDefinition::fromArray($data['definition'] ?? null), $data['pattern']);
	}

	public function toArray(): array {
		return [
			'definition' => $this->definition->toArray(),
			'pattern' => $this->pattern,
		];
	}
}
