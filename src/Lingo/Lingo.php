<?php

namespace Hit\Lingo;

class Lingo {
	private static ?Translator $instance = null;

	public static function init(string $lang, string $path): void {
		self::$instance = new Translator($lang, $path);
	}

	/** @param string[] $langs */
	public static function devMode(array $langs): void {
		self::instance()->enableDevMode($langs);
	}

	public static function get(string $key, string ...$args): string {
		return self::instance()->get($key, ...$args);
	}

	public static function getInstance(): Translator {
		return self::instance();
	}

	public static function reset(): void {
		self::$instance = null;
	}

	private static function instance(): Translator {
		if (self::$instance === null) {
			throw new \RuntimeException('Lingo not initialized. Call Lingo::init() first.');
		}

		return self::$instance;
	}
}
