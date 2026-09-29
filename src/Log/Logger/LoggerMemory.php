<?php

namespace Hit\Log\Logger;

use Exception;
use Hit\Util\Later;

/**
 * Log writer class.
 *
 * Writes log messages into file or standard output.
 * Buffers all messages in memory to reduce I/O.
 *
 * @version 1.2 2026-04-21
 * @author Indrek Pärnamaa <indrek@rukkirahu.ee>
 */

class LoggerMemory extends Logger {
	/** @var array<string> */
	private array $logData = [];

	/**
	 * LoggerMemory constructor.
	 *
	 * @param string $file
	 * @throws Exception
	 */
	public function __construct(string $file) {
		parent::__construct($file);
		touch($file);
		Later::call([$this, 'stop']);
	}

	public function log($level, string|\Stringable $message, array $context = []): void {
		$this->logData[] = $this->buildLogLine((string) $level, (string) $message, $context);
	}

	/**
	 * write collected log lines and stop logging
	 */
	public function stop(): void {
		$this->logData and file_put_contents($this->filename, implode(PHP_EOL, $this->logData) . PHP_EOL, FILE_APPEND);
		$this->logData = [];
	}
}
