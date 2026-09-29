<?php

declare(strict_types=1);

namespace Hit\Util;

/**
 * Library of string functions for manipulating object Names
 *
 * Inspired by CakePHP
 * @version 1.2  2020-01-19 18:50
 * @author Indrek Pärnamaa <indrek@rukkirahu.ee>
 */
// @mago-expect lint:kan-defect
class Inflector {
	/**
	 * Method cache array.
	 * @var array<string,array<string,string>> $cache
	 */
	public static array $cache = [];

	/**
	 * Cache inflected values, and return if already available
	 *
	 * @param string $type Inflection type
	 * @param string $key Original value
	 * @param string $value Inflected value
	 * @return string Inflected value, from cache
	 */
	protected static function cache(string $type, string $key, string $value = ''): string {
		if ($value) {
			self::$cache[$type][$key] = (string) $value;

			return $value;
		}

		return (string) (self::$cache[$type][$key] ?? '');
	}

	/**
	 * Returns the given string cleaned of current namespace name (if any) and non-US characters except [-_/:@,.]
	 *
	 * @param string $str Word to clean
	 * @return string
	 */
	public static function clean(string $str): string {
		$result = self::cache(__FUNCTION__, $str);
		if (!$result) {
			$str = trim($str, "\\ ");
			$slashPos = strrpos($str, '\\');
			if ($slashPos) { //&& '\\' == $str[0]
				$str = substr($str, $slashPos + 1);
			}
			$result = preg_replace("/[^\w\-_\/:@,\.]/", '', $str) ?? $str;
			self::cache(__FUNCTION__, $str, $result);
		}

		return $result;
	}

	/**
	 * Returns the given lower_case_and_underscored_word or lower.case.and.dotted.word as a CamelCased (Pascal case) word
	 *
	 * @param string $str Word to camelize
	 * @param array<string> $splitPattern Camelize after given markers.
	 * @return string Camelized word. LikeThisWord.
	 */
	public static function camelize(string $str, array $splitPattern = ['.', '_', '-']): string {
		$result = self::cache(__FUNCTION__, $str);
		if ($result === '') {
			$result = str_replace(' ', '', ucwords(str_replace($splitPattern, ' ', $str))) ?? $str;
			$result and self::cache(__FUNCTION__, $str, $result);
		}

		return $result;
	}

	/**
	 * Returns the given camelCasedWord as an underscored_word. Just uppercase word will be converted to just lowercase word
	 *
	 * @param string $str Camel-cased word to be "underscored"
	 * @return string Underscore-syntax version of the $str
	 */
	public static function underscore(string $str): string {
		$result = self::cache(__FUNCTION__, $str);
		if ($result === '') {
			$result = preg_replace(['/([A-Z]+)([A-Z][a-z])/', '/([a-z\d])([A-Z])/'], ['\\1_\\2', '\\1_\\2'], $str) ?? $str;
			$result and ($result = str_replace('__', '_', strtolower($result)));
			$result and self::cache(__FUNCTION__, $str, $result);
		}

		return $result;
	}

	/**
	 * Inflect a word to a human readable string with only the first word capitalized and the rest lowercase
	 *
	 * @param string $str
	 * @return string
	 */
	public static function humanize(string $str): string {
		return self::cache(__FUNCTION__, $str) ?: self::cache(__FUNCTION__, $str, ucfirst(str_replace('_', ' ', self::underscore($str))));
	}

	/**
	 * Return $str in singular form. Simplified
	 *
	 * @param string $str Word in plural
	 * @return string Word in singular
	 */
	public static function singularize(string $str): string {
		$result = self::cache(__FUNCTION__, $str);
		if ($result === '') {
			$replacements = [
				'/(s)tatuses$/i' => '\1\2tatus',
				'/^(.*)(menu)s$/i' => '\1\2',
				'/(alias)(es)*$/i' => '\1',
				'/([ftw]ax)es/i' => '\1',
				'/(cris|ax|test)es$/i' => '\1is',
				'/(o)es$/i' => '\1',
				'/([^a])uses$/' => '\1us',
				'/(s)eries$/i' => '\1\2eries',
				'/([lre])ves$/i' => '\1f',
				'/([^fo])ves$/i' => '\1fe',
				'/(^analy)ses$/i' => '\1sis',
				'/([ti])a$/i' => '\1um',
				'/(m)en$/i' => '\1an',
				'/(c)hildren$/i' => '\1\2hild',
				'/^(.*us)$/' => '\\1',
				'/s$/i' => '',
			];
			$result = $str;
			foreach ($replacements as $key => $value) {
				if (!preg_match($key, $str)) {
					continue;
				}

				$result = preg_replace($key, $value, $str) ?? $str;
				break;
			}
			$result and self::cache(__FUNCTION__, $str, $result);
		}

		return $result;
	}

	/**
	 * @param string $str
	 * @return string
	 */
	public static function urlify(string $str): string {
		return preg_replace('/[^A-Za-z0-9_]+/u', '', $str) ?: '';
	}

	/**
	 * Return $str in plural form. Simplified
	 * @param string $str Word in singular
	 * @return string Word in plural
	 */
	public static function pluralize(string $str): string {
		$result = self::cache(__FUNCTION__, $str);
		if (!$result) {
			// $replacements = array('/^(.+)y$/' => '$1ies', '/^(.+)([^s])$/' => '$1$2s');
			$replacements = [
				'/(s)tatus$/i' => '\1\2tatuses',
				'/(quiz)$/i' => '\1zes',
				'/^(ox)$/i' => '\1\2en',
				'/([m|l])ouse$/i' => '\1ice',
				'/(matr|vert|ind)(ix|ex)$/i' => '\1ices',
				'/(x|ch|ss|sh)$/i' => '\1es',
				'/([^aeiouy]|qu)y$/i' => '\1ies',
				'/(hive)$/i' => '\1s',
				'/(?:([^f])fe|([lre])f)$/i' => '\1\2ves',
				'/sis$/i' => 'ses',
				'/([ti])um$/i' => '\1a',
				'/(p)erson$/i' => '\1eople',
				'/(m)an$/i' => '\1en',
				'/(c)hild$/i' => '\1hildren',
				'/(buffal|tomat)o$/i' => '\1\2oes',
				'/(alumn|bacill|cact|foc|fung|nucle|radi|stimul|syllab|termin|vir)us$/i' => '\1i',
				'/us$/i' => 'uses',
				'/(alias)$/i' => '\1es',
				'/(ax|cris|test)is$/i' => '\1es',
				'/s$/' => 's',
				'/^$/' => '',
				'/$/' => 's',
			];
			$result = $str;
			foreach ($replacements as $key => $value) {
				if (!preg_match($key, $str)) {
					continue;
				}

				$result = preg_replace($key, $value, $str) ?? $str;
				break;
			}
			$result and self::cache(__FUNCTION__, $str, $result);
		}

		return $result;
	}

	/**
	 * Return string value of anything
	 *
	 * @param mixed $data mixed type input data
	 * @param boolean $pretty If true then output is reformatted for easier reading
	 * @return string
	 */
	public static function stringify(mixed $data, bool $pretty = false): string {
		switch (true) {
			case is_callable($data):
				// @mago-expect analysis:impossible-type-comparison
				// @mago-expect analysis:never-matching-switch-case
				switch (true) {
					case is_string($data):
						// @mago-expect analysis:invalid-operand
						$data .= '()';
						break;
					case is_array($data):
						list($c, $m) = $data;
						$data = (is_object($c) ? get_class($c) . '->' . $m : $c . '::' . $m) . '()';
						break;
					case is_object($data):
						$data = 'closure()';
						break;
					default:
						$data = strval($data);
				}
				$data = 'callable: ' . $data; //str_replace('\\', "&#92;", $data); // keep backslashes
				break;
			case is_array($data):
				$data = 'array: ' . json_encode($data, $pretty ? JSON_PRETTY_PRINT : 0);
				break;
			// @mago-expect analysis:invalid-operand
			case is_object($data):
				$data =
					'object['
					. get_class($data)
					. ']: '
					. (is_callable([$data, '__toString']) ? $data : json_encode((array) $data, $pretty ? JSON_PRETTY_PRINT : 0));
				break;
			case is_bool($data):
				$data = 'boolean: ' . ($data ? 'TRUE' : 'FALSE');
				break;
			case is_null($data):
				$data = 'NULL';
				break;
			case is_resource($data):
				$data = 'resource: ' . get_resource_type($data);
				break;
			case is_numeric($data):
				$data = strval($data);
				break;
			case is_string($data):
				// @phpstan-ignore logicalAnd.resultUnused
				strlen($data) > 500 and ($data = substr($data, 0, strrpos(substr($data, 0, 500), ' ') ?: 500) . '...');
				$data = preg_replace('/\s+/', ' ', $pretty ? html_entity_decode(str_replace('&nbsp;', ' ', $data)) : $data);
				break;
			default:
				$data = gettype($data);
				break;
		}

		// @mago-expect analysis:possibly-null-argument
		return $pretty ? (strval(preg_replace('/\\\u([0-9A-Fa-f]{4})/', '&#x$1;', $data)) ?: '<empty>') : (string) $data;
	}

	/**
	 * @param string $str
	 * @return bool
	 */
	public static function isPlural(string $str): bool {
		return $str == self::pluralize($str);
	}
}
