<?php

declare(strict_types=1);

namespace Hit\Log;

use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use RuntimeException;

/**
 * Class Log
 * @package Hit\Log
 */
class Log {
	/**
	 * The instance of the log object
	 */
	private static ?LoggerInterface $logInstance = null;
	/** @var array<string,mixed> */
	private static array $context = [];
	private static int $level = 0b111_1111;

	/**
	 * Registers the proxy instance
	 * @param LoggerInterface $instance
	 */
	public static function proxy(LoggerInterface $instance): void {
		self::$logInstance = $instance;
	}

	public static function instance(): ?LoggerInterface {
		return self::$logInstance;
	}

	public static function setLevel(string $level): void {
		self::$level = match ($level) {
			LogLevel::DEBUG => 0b111_1111,
			LogLevel::INFO => 0b111_1110,
			LogLevel::NOTICE => 0b111_1100,
			LogLevel::WARNING => 0b111_1000,
			LogLevel::ERROR => 0b111_0000,
			LogLevel::CRITICAL => 0b110_0000,
			LogLevel::ALERT => 0b100_0000,
			default => 0b111_1111,
		};
	}

	public static function debug(string|callable $message): void {
		if (self::$level & 0b1) {
			$message = trim(is_callable($message) ? (string) $message() : $message);
			$message and self::_instance()->debug($message, self::$context);
		}
	}

	public static function info(string|callable $message): void {
		if (self::$level & 0b10) {
			$message = trim(is_callable($message) ? (string) $message() : $message);
			$message and self::_instance()->info($message, self::$context);
		}
	}

	public static function notice(string|callable $message): void {
		if (self::$level & 0b100) {
			$message = trim(is_callable($message) ? (string) $message() : $message);
			$message and self::_instance()->notice($message, self::$context);
		}
	}

	public static function warning(string|callable $message): void {
		if (self::$level & 0b1000) {
			$message = trim(is_callable($message) ? (string) $message() : $message);
			$message and self::_instance()->warning($message, self::$context);
		}
	}

	public static function error(string|callable $message): void {
		if (self::$level & 0b1_0000) {
			$message = trim(is_callable($message) ? (string) $message() : $message);
			$message and self::_instance()->error($message, self::$context);
		}
	}

	public static function critical(string|callable $message): void {
		if (self::$level & 0b10_0000) {
			$message = trim(is_callable($message) ? (string) $message() : $message);
			$message and self::_instance()->critical($message, self::$context);
		}
	}

	public static function alert(string|callable $message): void {
		if (self::$level & 0b100_0000) {
			$message = trim(is_callable($message) ? (string) $message() : $message);
			$message and self::_instance()->alert($message, self::$context);
		}
	}

	// Emergency always logs, regardless of the configured level.
	public static function emergency(string|callable $message): void {
		$message = trim(is_callable($message) ? (string) $message() : $message);
		$message and self::_instance()->emergency($message, self::$context);
	}

	private static function _instance(): LoggerInterface {
		if (self::$logInstance === null) {
			throw new RuntimeException('Log not initialized. Call Log::proxy() first.');
		}

		return self::$logInstance;
	}

	public static function reset(): void {
		self::$logInstance = null;
		self::$context = [];
		self::$level = 0b111_1111;
	}

	public static function setRemoteIp(string $ip): void {
		self::$context['remote_ip'] = $ip;
	}

	public static function setAuth(string $userId): void {
		self::$context['auth'] = $userId;
	}

	public static function setPid(string $pid): void {
		self::$context['pid'] = $pid;
	}
}
