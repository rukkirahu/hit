<?php

declare(strict_types=1);

namespace Tests\Unit;

use Hit\Log\Logger\LoggerErrorLog;
use Hit\Log\Logger\LoggerMemory;

// Helper: full context array with all keys that buildLogLine expects.
function logContext(): array {
	return ['pid' => '123', 'auth' => 'user42', 'remote_ip' => '10.0.0.1'];
}

// Helper: temp file path that is cleaned up after the test.
function tempLogFile(): string {
	$file = sys_get_temp_dir() . '/hit_log_test_' . uniqid() . '.log';
	register_shutdown_function(fn() => @unlink($file));

	return $file;
}

// ---------------------------------------------------------------------------
// LoggerMemory — buffering
// ---------------------------------------------------------------------------

test('LoggerMemory buffers lines and writes them on stop()', function () {
	$file = tempLogFile();
	$logger = new LoggerMemory($file);

	$logger->log('debug', 'first message', logContext());
	$logger->log('error', 'second message', logContext());

	// Nothing written yet.
	expect(file_get_contents($file))->toBe('');

	$logger->stop();

	$contents = file_get_contents($file);
	expect($contents)->toContain('first message');
	expect($contents)->toContain('second message');
	expect($contents)->toContain('debug');
	expect($contents)->toContain('error');
});

test('LoggerMemory appends to an existing file', function () {
	$file = tempLogFile();
	file_put_contents($file, "pre-existing line\n");

	$logger = new LoggerMemory($file);
	$logger->log('info', 'appended', logContext());
	$logger->stop();

	$contents = file_get_contents($file);
	expect($contents)->toContain('pre-existing line');
	expect($contents)->toContain('appended');
});

test('LoggerMemory stop with no buffered lines does not write', function () {
	$file = tempLogFile();
	$logger = new LoggerMemory($file);

	// Constructor calls touch(), so file exists but is empty.
	$before = file_get_contents($file);
	$logger->stop();

	expect(file_get_contents($file))->toBe($before);
});

test('LoggerMemory stop clears the buffer', function () {
	$file = tempLogFile();
	$logger = new LoggerMemory($file);

	$logger->log('info', 'msg', logContext());
	$logger->stop();

	// After stop, buffer is cleared — calling stop again should not write.
	$contentsAfterFirstFlush = file_get_contents($file);
	$logger->stop();

	expect(file_get_contents($file))->toBe($contentsAfterFirstFlush);
});

// ---------------------------------------------------------------------------
// LoggerMemory — buildLogLine format
// ---------------------------------------------------------------------------

test('buildLogLine includes level, context fields, and message', function () {
	$file = tempLogFile();
	$logger = new LoggerMemory($file);

	$logger->log('warning', 'something broke', ['pid' => '99', 'auth' => 'admin', 'remote_ip' => '192.168.1.1']);
	$logger->stop();

	$line = trim(file_get_contents($file));

	// Format: [timestamp] level pid auth remote_ip message
	expect($line)->toMatch('/^\[.+\] warning 99 admin 192\.168\.1\.1 something broke$/');
});

test('buildLogLine handles Stringable message', function () {
	$file = tempLogFile();
	$logger = new LoggerMemory($file);

	$stringable = new class implements \Stringable {
		public function __toString(): string {
			return 'stringable-msg';
		}
	};

	$logger->log('info', $stringable, logContext());
	$logger->stop();

	expect(file_get_contents($file))->toContain('stringable-msg');
});

// ---------------------------------------------------------------------------
// LoggerErrorLog
// ---------------------------------------------------------------------------

test('LoggerErrorLog writes to the configured error log', function () {
	$file = tempLogFile();
	$previous = ini_set('error_log', $file);

	try {
		$logger = new LoggerErrorLog('/dev/null'); // filename unused by this logger
		$logger->log('error', 'error_log test message', logContext());

		$contents = file_get_contents($file);
		expect($contents)->toContain('error_log test message');
		expect($contents)->toContain('error');
	}
	finally {
		ini_set('error_log', $previous);
	}
});

test('LoggerErrorLog does not require a real file path', function () {
	$logger = new LoggerErrorLog('/dev/null');

	// Just verify it doesn't throw — the filename is unused.
	$logger->log('debug', 'msg', logContext());

	expect(true)->toBeTrue();
});

// ---------------------------------------------------------------------------
// Logger — stop() is a no-op on the base class
// ---------------------------------------------------------------------------

test('Logger stop() is a no-op on the base implementation', function () {
	// LoggerMemory inherits stop() with real behavior; the base Logger::stop()
	// is an empty method. We verify via LoggerErrorLog which does not override
	// stop() — calling it should have no side effects.
	$logger = new LoggerErrorLog('/dev/null');
	$logger->stop(); // should not throw

	expect(true)->toBeTrue();
});
