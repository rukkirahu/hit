<?php

declare(strict_types=1);

namespace Hit\View\Component;

use Hit\Lingo\Lingo;

final class Html {
	private static string $baseurl = '';
	/**
	 * @var callable|null
	 */
	private static $isRouteAllowed = null;

	public static function init(string $baseurl, callable $isRouteAllowed): void {
		if (self::$baseurl !== '') {
			return;
		}

		self::$isRouteAllowed = $isRouteAllowed;
		self::$baseurl = $baseurl;
	}

	public static function url(string $href = '', string $method = 'GET', int $id = 0): string {
		if (!$href) {
			return '';
		}
		static $cache = [];

		$args = '';
		$pos = strpos($href, '?');
		if ($pos !== false) {
			$args = substr($href, $pos);
			$href = substr($href, 0, $pos);
		}

		if ($id) {
			$href = rtrim($href, '/') . '/0';
		}
		if ($href === '') {
			return '';
		}

		if ($href[0] === '/') {
			return $href;
		}

		$href = self::$baseurl . $href;
		$cacheKey = $method . ' ' . $href;
		if (!array_key_exists($cacheKey, $cache)) {
			// access routing only if not cached
			$isRouteAllowed = self::$isRouteAllowed;
			if (!is_callable($isRouteAllowed)) {
				throw new \Exception('Route access check not initialized');
			}
			$cache[$cacheKey] = $isRouteAllowed($method, $href);
		}

		if (!$cache[$cacheKey]) {
			return '';
		}
		if ($id) {
			return substr($href, 0, -1) . $id . $args;
		}

		return $href . $args;
	}

	public static function quotedUrl(string $href = '', string $method = 'GET', int $id = 0): string {
		return '"' . self::url($href, $method, $id) . '"';
	}

	public static function highlight(string $text, string $needle): string {
		if ($needle === '') {
			return $text;
		}
		$pos = stripos($text, $needle);
		if ($pos === false) {
			$translated = iconv('UTF-8', 'ASCII//TRANSLIT', $needle);
			if ($translated === false) {
				throw new \Exception('Encoding error');
			}
			if ($translated === $needle) {
				return $text;
			}

			return self::highlight($text, $translated);
		}

		return substr($text, 0, $pos) . '<u>' . substr($text, $pos, strlen($needle)) . '</u>' . substr($text, $pos + strlen($needle));
	}

	/**
	 * Html link builder
	 * [
	 * 	'get' => 'users',
	 *  'hx-get' -> 'users',
	 * 	'hx-post' -> 'users',
	 * ]
	 * @param array<string,mixed> $hx
	 */
	public static function link(
		string $name,
		string $href = '',
		string $class = '',
		string $wrapper = '',
		string $type = '',
		string $role = '',
		int $tabindex = 0,
		array $hx = [],
	): string {
		$href = self::url($href, (string) ($hx['method'] ?? 'GET'));

		// if no access
		if (!$href) {
			return '';
		}

		$result = "href='{$href}'";
		$class and ($result .= " class='{$class}'");
		$type and ($result .= " type='{$type}'");
		$role and ($result .= " role='{$role}'");
		$tabindex and ($result .= " tabindex='{$tabindex}'");
		foreach ($hx as $k => $v) {
			$v = (string) $v;
			if ($k === 'method') {
				$result .= ' hx-' . strtolower($v) . "='" . htmlspecialchars($href) . "'";
			}
			else {
				$result .= " hx-{$k}='" . htmlspecialchars($v) . "'";
			}
		}
		if (str_starts_with($name, '%')) {
			$name = Lingo::get(substr($name, 1));
		}

		return $wrapper ? "<{$wrapper}><a {$result}>{$name}</a></{$wrapper}>\n" : "<a {$result}>{$name}</a>\n";
	}

	public static function h(?string $str = ''): string {
		return htmlspecialchars($str ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
	}

	// function __(string $str = '', string $x = ''): string {
	// 	return htmlspecialchars(sprintf($str, $x));
	// }
}
