<?php

declare(strict_types=1);

namespace Hit\Log\Logger;

use DateTime;
use Exception;
use Psr\Log\AbstractLogger;

/**
 * Log writer class.
 *
 * Writes log messages into file or standard output.
 *
 * @version 1.2 2026-04-21
 * @author Indrek Pärnamaa <indrek@rukkirahu.ee>
 */

abstract class Logger extends AbstractLogger {
	// protected string $minLevel;
	protected string $filename;

	/**
	 * LoggerBase constructor.
	 */
	public function __construct(string $file) {
		$this->filename = $file;
	}

	protected function buildLogLine(string $level, string $message, array $context): string {
		try {
			$time = new DateTime();
			$now = $time->format('[Y-m-d H:i:s.u e]');
		}
		catch (Exception) {
			$now = 'now';
		}
		$data = [
			$now,
			$level,
			(string) $context['pid'],
			(string) $context['auth'],
			(string) $context['remote_ip'],
			$message,
		];

		return implode(' ', $data);
	}

	public function stop(): void {}
}
