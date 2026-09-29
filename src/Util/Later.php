<?php

declare(strict_types=1);

namespace Hit\Util;

use Hit\Log\Log;
use Throwable;

/**
 * Stack of callables to be executed after main process is finished
 * PHP register_shutdown_function works as FIFO, this class does FILO
 *
 * @version 2.1  2024-08-02
 * @author Indrek Pärnamaa <indrek@holdit.ee>
 */
class Later {
	/**
	 * @var array<int,array{callback:callable,arguments:mixed[]}>
	 */
	private static array $callbacks = [];

	/**
	 * Add method to stack
	 * @param callable $callback Method/Closure/Function to be called later
	 * @param mixed ...$args
	 */
	public static function call(callable $callback, ...$args): void {
		Log::debug(static fn() => 'Later call:' . Inflector::stringify($callback));
		if (self::$callbacks === []) {
			register_shutdown_function([self::class, 'process']);
		}
		self::$callbacks[] = ['callback' => $callback, 'arguments' => $args];
	}

	/**
	 * Clear all pending callbacks without executing them.
	 * Note: register_shutdown_function cannot be unregistered, so process()
	 * may still run at shutdown — it will just be a no-op with an empty stack.
	 */
	public static function reset(): void {
		self::$callbacks = [];
	}

	/**
	 * process Callables stack
	 * @return void
	 */
	public static function process(): void {
		function_exists('fastcgi_finish_request') and fastcgi_finish_request();
		$error = error_get_last();
		if ($error) {
			error_log(json_encode($error) ?: 'unknown json error');
		}

		while ($callback = array_pop(self::$callbacks)) {
			Log::debug(static fn() => 'Later calling ' . Inflector::stringify($callback['callback']));
			try {
				$callback['callback'](...$callback['arguments']);
			}
			catch (Throwable $e) {
				error_log('Later failed to call [' . Inflector::stringify($callback['callback']) . ']: ' . $e->getMessage());
			}
		}
	}
}
