<?php

declare(strict_types=1);

namespace Hit\Db\Sql;

class Value {
	/** @var array<string,mixed> */
	private array $values = [];
	private static int $attributeCounter = 0;

	public function set(mixed $value): string {
		$name = self::getNextAttributeName();
		$this->values[$name] = $value;

		return $name;
	}

	/**
	 * Render a raw value for inlining into SQL, binding strings as named
	 * placeholders.
	 */
	public function parse(mixed $value): string {
		if ($value === null) {
			return 'NULL';
		}
		if (is_bool($value)) {
			return $value ? '1' : '0';
		}
		if (is_int($value) || is_float($value)) {
			return (string) $value;
		}
		if ($value instanceof Expression) {
			throw new \Exception('Expressions cannot be used as bound values');
		}
		if (is_string($value)) {
			return $this->set($value);
		}

		throw new \Exception('Unsupported value type: ' . gettype($value));
	}

	/**
	 * @return array<string,mixed>
	 */
	public function all(): array {
		return $this->values;
	}

	public static function getNextAttributeName(): string {
		$index = self::$attributeCounter++;
		$length = 2;
		$range = 26 ** $length;

		while ($index >= $range) {
			$index -= $range;
			$length++;
			$range = 26 ** $length;
		}

		$name = '';
		for ($position = 0; $position < $length; $position++) {
			$name = chr(97 + ($index % 26)) . $name;
			$index = intdiv((int) $index, 26);
		}

		return ':' . $name;
	}
}
