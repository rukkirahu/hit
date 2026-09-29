<?php

declare(strict_types=1);

namespace Hit\Db;

readonly class Dto {
	public const PREFIX = '';
	public const PRIMARY = 'id';

	/**
	 * For overriding in extended classes
	 */
	public function with(...$args): static {
		return $this;
	}

	/**
	 * @param array<array-key,mixed> $data
	 * @return static
	 */
	public static function from(array $data): static {
		return new static()->with(...$data);
	}

	/**
	 * @param array<string,mixed> $data
	 * @return static
	 */
	public static function __set_state(array $data): static {
		return self::from($data);
	}

	/**
	 * @return array<string,mixed>
	 */
	public function toArray(): array {
		/** @var array<string,mixed> */
		return array_filter(get_object_vars($this), 'is_scalar');
	}

	/**
	 * @param mixed $value
	 * @return static|null
	 */
	public static function match(mixed $value): ?static {
		return match (true) {
			$value instanceof static => $value,
			is_array($value) => static::from($value),
			default => null,
		};
	}
}
