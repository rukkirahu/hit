<?php

declare(strict_types=1);

namespace Hit\Validator;

/**
 * Validation Class. Used for validation of model data
 *
 * Offers different validation methods.
 */
// @mago-expect lint:kan-defect
class Validation {
	/**
	 * Some complex patterns needed in multiple places
	 * @var array<string,string>
	 */
	protected static array $_pattern = [
		'hostname' => '(?:[_a-z0-9][-_a-z0-9]*\.)*(?:[a-z0-9][-a-z0-9]{0,62})\.(?:(?:[a-z]{2}\.)?[a-z]{2,})',
	];

	/**
	 * Checks that a string contains something other than whitespace
	 * @param mixed $data Value to check
	 * @return boolean Success
	 */
	public static function required(mixed $data): bool {
		if ($data === null || $data === '' || $data === 0 && $data !== '0') {
			return false;
		}

		return is_string($data) ? self::_check($data, '/\S+/m') : true;
	}

	/**
	 * Checks that a string length is within s specified range.
	 * @param mixed $data Value to check for length
	 * @param array<int> $minmax [min, max] Minimum and maximum value in range (inclusive)
	 * @return boolean Success
	 */
	public static function between(mixed $data, array $minmax): bool {
		if (!is_string($data)) {
			return false;
		}
		$length = strlen($data);

		return $length >= $minmax[0] && $length <= $minmax[1];
	}

	/**
	 * Used to compare 2 numeric values.
	 */
	public static function comparison(int $data1, string $operator = '', int $data2 = 0): bool {
		$operator = str_replace([' ', "\t", "\n", "\r", "\0", "\x0B"], '', strtolower($operator));

		switch ($operator) {
			case 'isgreater':
			case '>':
				if ((int) $data1 > $data2) {
					return true;
				}
				break;
			case 'isless':
			case '<':
				if ((int) $data1 < $data2) {
					return true;
				}
				break;
			case 'greaterorequal':
			case '>=':
				if ((int) $data1 >= $data2) {
					return true;
				}
				break;
			case 'lessorequal':
			case '<=':
				if ((int) $data1 <= $data2) {
					return true;
				}
				break;
			case 'equalto':
			case '==':
				if ((int) $data1 === $data2) {
					return true;
				}
				break;
			case 'notequal':
			case '!=':
				if ((int) $data1 !== $data2) {
					return true;
				}
				break;
			default:
				throw new \InvalidArgumentException("You must define the {$operator} parameter");
		}

		return false;
	}

	/**
	 * Validates for an email address.
	 * Only uses getmxrr() checking for deep validation if PHP 5.3.0+ is used, or
	 * any PHP version on a non-windows distribution
	 * @param string $data Value to check
	 * @return boolean Success
	 */
	public static function email(mixed $data, bool $deep = false): bool {
		// Log::debug('email validator: ' . $data. ' deep: ' . strval($deep));
		if (!filter_var($data, FILTER_VALIDATE_EMAIL)) {
			// Log::debug('email validator: ' . $data. ' false');
			return false;
		}

		if ($deep) {
			// Log::debug('email validator: deep');
			if (preg_match('/@(' . self::$_pattern['hostname'] . ')$/i', $data, $regs)) {
				if (function_exists('getmxrr')) {
					$res = getmxrr($regs[1], $mxhosts);

					// Log::debug('getmxrr: ' . var_export($res, true) . ' ' . var_export($mxhosts, true));
					return $res && count($mxhosts) > 0 && $mxhosts[0];
				}
				if (function_exists('checkdnsrr')) {
					return checkdnsrr($regs[1] . '.', 'MX');
				}

				return $regs[1] != gethostbyname($regs[1]);
			}

			return false;

			// Log::debug(json_encode($regs, JSON_UNESCAPED_SLASHES));
		}

		return true;
	}

	/**
	 * Check that value is exactly $comparedTo.
	 * @param mixed $data Value to check
	 * @param mixed $comparedTo Value to compare
	 * @return boolean Success
	 */
	public static function equalTo(mixed $data, mixed $comparedTo): bool {
		return $data === $comparedTo;
	}

	/**
	 * Check that value is not exactly $comparedTo.
	 * @param mixed $data Value to check
	 * @param mixed $comparedTo Value to compare
	 * @return boolean Success
	 */
	public static function notEqualTo(mixed $data, mixed $comparedTo): bool {
		return $data !== $comparedTo;
	}

	/**
	 * Check that value has a valid file extension.
	 * @param mixed $data Value to check
	 * @param array<string> $extensions file extensions to allow. By default extensions are 'gif', 'jpeg', 'png', 'jpg'
	 * @return boolean Success
	 */
	public static function extension(mixed $data, array $extensions = ['gif', 'jpeg', 'png', 'jpg']): bool {
		if (!is_string($data)) {
			return false;
		}
		$extension = strtolower(pathinfo($data, PATHINFO_EXTENSION));
		foreach ($extensions as $value) {
			if ($extension === strtolower($value)) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Validation of an IP address.
	 * @param mixed $data The string to test.
	 * @param string $type The IP Protocol version to validate against
	 * @return boolean Success
	 */
	public static function ip(mixed $data, string $type = 'ipv4'): bool {
		if (!is_string($data)) {
			return false;
		}
		$type = strtolower($type);
		$flags = 0;
		if ($type === 'ipv4') {
			$flags = FILTER_FLAG_IPV4;
		}
		if ($type === 'ipv6') {
			$flags = FILTER_FLAG_IPV6;
		}

		return (bool) filter_var($data, FILTER_VALIDATE_IP, ['flags' => $flags]);
	}

	/**
	 * Checks whether the length of a string is greater or equal to a minimal length.
	 * @param mixed $data The string to test
	 * @param integer $min The minimal string length
	 * @return boolean Success
	 */
	public static function minLength(mixed $data, int $min): bool {
		if (!is_string($data)) {
			return false;
		}

		return strlen($data) >= $min;
	}

	/**
	 * Checks whether the length of a string is smaller or equal to a maximal length..
	 * @param mixed $data The string to test
	 * @param integer $max The maximal string length
	 * @return boolean Success
	 */
	public static function maxLength(mixed $data, int $max): bool {
		if (!is_string($data)) {
			return false;
		}

		return strlen($data) <= $max;
	}

	/**
	 * Checks if a value is numeric.
	 * @param string $data Value to check
	 * @return boolean Success
	 */
	public static function numeric(mixed $data): bool {
		return is_numeric($data);
	}

	/**
	 * Validate that a number is in specified range.
	 * if $lower and $upper are not set, will return true if
	 * $data is a legal finite on this platform
	 * @param mixed $data Value to check
	 * @param ?array<int> $options
	 * @return boolean Success
	 */
	public static function range(mixed $data, ?array $options = null): bool {
		if (!is_numeric($data)) {
			return false;
		}
		$lower = (int) $options[0];
		$upper = (int) $options[1];
		if ($upper <= $lower) {
			throw new \Exception('upper must be greater than lower');
		}

		return $data > $lower && $data < $upper;
	}

	/**
	 * Checks if a value is in a given list.
	 * @param mixed $data Value to check
	 * @param array<mixed> $list List to check against
	 * @return boolean Success
	 */
	public static function inList(mixed $data, array $list): bool {
		if (!is_scalar($data)) {
			return false;
		}

		return in_array($data, $list) || array_key_exists($data, $list);
	}

	/**
	 * Checks that a value is a valid uuid - http://tools.ietf.org/html/rfc4122
	 * @param mixed $data Value to check
	 * @return boolean Success
	 */
	public static function uuid(mixed $data): bool {
		if (!is_string($data)) {
			return false;
		}
		$regex = '/^[a-fA-F0-9]{8}-[a-fA-F0-9]{4}-[1-5][a-fA-F0-9]{3}-[89aAbB][a-fA-F0-9]{3}-[a-fA-F0-9]{12}$/';

		return self::_check($data, $regex);
	}

	/**
	 * Runs a regular expression match.
	 * @param string $data Value to check against the $regex expression
	 * @param string $regex Regular expression
	 * @return boolean Success of match
	 */
	protected static function _check(string $data, string $regex): bool {
		if (preg_match($regex, $data)) {
			return true;
		}

		return false;
	}

	/**
	 * Check that a value is a valid phone number.
	 * @param mixed $data Value to check (string or array)
	 * @return boolean Success
	 */
	public static function phone(mixed $data): bool {
		if (!is_string($data)) {
			return false;
		}

		//TODO: update regexp
		return self::_check($data, '/^[0-9\-+ ]{7,15}$/');
	}

	public static function password(
		#[\SensitiveParameter]
		string $password,
		bool $uppercase = false,
		bool $lowercase = false,
		bool $numbers = false,
		bool $special = false,
		int $length = 0,
	): bool {
		$pattern = '/^'; //(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[\W_]).{8,}$/';
		if ($uppercase) {
			$pattern .= '(?=.*[A-Z])';
		}
		if ($lowercase) {
			$pattern .= '(?=.*[a-z])';
		}
		if ($numbers) {
			$pattern .= '(?=.*\d)';
		}
		if ($special) {
			$pattern .= '(?=.*[\W_])';
		}
		if ($length > 0) {
			$pattern .= '.{' . $length . ',}';
		}
		$pattern .= '$/';

		return self::_check($password, $pattern);
	}
}
