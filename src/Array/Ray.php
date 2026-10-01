<?php

declare(strict_types=1);

namespace Hit\Array;

// @mago-expect lint:kan-defect
class Ray {
	/**
	 * Flatten a multi-dimensional associative array with dots.
	 * @param array<array-key, mixed> $array
	 */
	private static function dotInto(array $array, string $prepend, array &$out): void {
		foreach ($array as $k => $v) {
			if (is_array($v) && $v !== []) {
				self::dotInto($v, $prepend . $k . '.', $out);
			}
			else {
				$out[$prepend . $k] = $v;
			}
		}
	}

	public static function dot(array $array, string $prepend = ''): array {
		$out = [];
		self::dotInto($array, $prepend, $out);

		return $out;
	}

	/**
	 * Convert a flatten "dot" notation array into an expanded array.
	 * @param array<string,mixed> $array
	 * @return array<string,mixed>
	 */
	public static function undot(array $array): array {
		/** @var array<string, mixed> $results */
		$results = [];

		foreach ($array as $key => $value) {
			static::set($results, $key, $value);
		}

		return $results;
	}

	/**
	 * Remove keys from an array, supporting dot notation.
	 * @param array<string, mixed> $array
	 * @param array<string> $keys
	 */
	public static function remove(array &$array, array $keys): void {
		if (count($keys) === 0) {
			return;
		}

		foreach ($keys as $key) {
			// if the exact key exists in the top-level, remove it
			if (array_key_exists($key, $array)) {
				unset($array[$key]);

				continue;
			}

			$parts = explode('.', $key);

			// walk a local cursor down each pass, leaving $array bound to the caller
			$cursor = &$array;

			while (count($parts) > 1) {
				$part = array_shift($parts);

				if (isset($cursor[$part]) && is_array($cursor[$part])) {
					$cursor = &$cursor[$part];
				}
				else {
					continue 2;
				}
			}

			unset($cursor[array_shift($parts)]);
			unset($cursor);
		}
	}

	/**
	 * Get an item from an array using "dot" notation.
	 * @param array<array-key,mixed> $array
	 */
	public static function get(array $array, string $key, mixed $default = null): mixed {
		if (array_key_exists($key, $array)) {
			return $array[$key];
		}

		$keys = explode('.', $key);

		foreach ($keys as $key) {
			if (is_array($array) && array_key_exists($key, $array)) {
				$array = &$array[$key];
			}
			else {
				return $default;
			}
		}

		return $array;
	}

	public static function getStr(array $array, string $key, string $default = ''): string {
		return (string) self::get($array, $key, $default);
	}

	/**
	 * Check if an item or items exist in an array using "dot" notation.
	 * @param array<array-key,mixed> $array
	 * @param string|array<string> $keys
	 */
	public static function has(array $array, string|array $keys): bool {
		$keys = (array) $keys;

		if (!$array || $keys === []) {
			return false;
		}

		foreach ($keys as $key) {
			$subKeyArray = $array;

			if (array_key_exists($key, $array)) {
				continue;
			}

			foreach (explode('.', $key) as $segment) {
				if (is_array($subKeyArray) && array_key_exists($segment, $subKeyArray)) {
					$subKeyArray = $subKeyArray[$segment];
				}
				else {
					return false;
				}
			}
		}

		return true;
	}

	/**
	 * Determines if an array is associative.
	 * @param array<string|int,mixed> $array
	 */
	public static function isAssoc(array $array): bool {
		if (function_exists('array_is_list')) {
			return !array_is_list($array);
		}

		$keys = \array_keys($array);

		return \array_keys($keys) !== $keys;
	}

	/**
	 * Determines if an array is a list.
	 * @param array<string|int,mixed> $array
	 */
	public static function isList(array $array): bool {
		if (function_exists('array_is_list')) {
			return \array_is_list($array);
		}

		return !self::isAssoc($array);
	}

	/**
	 * Get a subset of the items from the given array.
	 * @param array<string,mixed> $array
	 * @param string|array<string> $keys
	 * @return array<string,mixed>
	 */
	public static function only(array $array, string|array $keys): array {
		return \array_intersect_key($array, \array_flip((array) $keys));
	}

	/**
	 * Convert the array into a query string.
	 * @param  array<string,mixed>  $array
	 */
	public static function query(array $array): string {
		return http_build_query($array, '', '&', PHP_QUERY_RFC3986);
	}

	/**
	 * Set an array item to a given value using "dot" notation.
	 * If no key is given to the method, the entire array will be replaced.
	 * @param array<string, mixed> $data
	 */
	public static function set(array &$data, string $keys, mixed $value = null): void {
		$keys = explode('.', $keys);

		$key = array_pop($keys);
		// /** @var array<string, mixed> &$ref */
		$ref = &$data;

		foreach ($keys as $k) {
			if (!array_key_exists($k, $ref) || !is_array($ref[$k])) {
				$ref[$k] = [];
			}

			$ref = &$ref[$k];
		}
		$ref[$key] = $value;
	}

	/**
	 * Return array with values from source under renamed keys
	 * @param array<string,mixed> $array
	 * @param array<int|string,null|string|callable(mixed,mixed):array{string,mixed}> $map
	 * @return array<string,mixed>
	 * @example - Ray::remap($array, ['id'=>'order_id','name'=>'customer_name','price'], true)
	 */
	public static function remap(array $array, array $map, bool $ignoreNullValues = true): array {
		$destination = [];
		foreach ($map as $key => $destKey) {
			if (is_null($destKey)) {
				continue;
			}

			if (is_callable($destKey)) {
				list($destKey, $value) = (array) $destKey($key, $array);
			}
			else {
				$value = self::get($array, is_int($key) ? (string) $destKey : $key);
			}

			if (!($ignoreNullValues && is_null($value))) {
				self::set($destination, (string) $destKey, $value);
			}
		}

		return $destination;
	}
}
