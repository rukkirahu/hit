<?php

declare(strict_types=1);

namespace Hit\Session;

use RuntimeException;

class Session {
	public static function init(string $name): void {
		session_name($name);
		session_start();
	}

	public static function getId(): string {
		return session_id() ?: 'session';
	}

	public static function set(string $key, mixed $value = null): void {
		self::guard();
		$_SESSION[$key] = $value;
	}

	public static function get(string $key = ''): mixed {
		self::guard();

		return $key !== '' ? $_SESSION[$key] ?? null : $_SESSION;
	}

	public static function getString(string $key): string {
		$value = self::get($key);

		return is_string($value) ? $value : (is_scalar($value) ? (string) $value : '');
	}

	public static function pull(string $key): mixed {
		self::guard();
		if (!array_key_exists($key, $_SESSION)) {
			return null;
		}
		$result = $_SESSION[$key];
		unset($_SESSION[$key]);

		return $result;
	}

	public static function reset(): void {
		$_SESSION = [];
	}

	private static function guard(): void {
		if (session_status() !== PHP_SESSION_ACTIVE) {
			throw new RuntimeException('Session not initialized. Call Session::init() first.');
		}
	}
}
