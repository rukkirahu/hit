<?php

namespace Hit\Log\Logger;

/**
 * Log writer class.
 *
 * Writes log messages using php standard error_log function
 *
 * @version 1.1 2020-03-13 13:03:20
 * @author Indrek Pärnamaa <indrek@rukkirahu.ee>
 */

class LoggerErrorLog extends Logger {
	public function log($level, string|\Stringable $message, array $context = []): void {
		error_log($this->buildLogLine((string) $level, (string) $message, $context));
	}
}
