<?php

declare(strict_types=1);

namespace Tests\Unit;

use Hit\Log\Log;
use Hit\Util\Later;
use Psr\Log\LoggerInterface;

// Later::call() and process() both invoke Log::debug(), which throws if Log
// was never proxied. Set up a no-op logger before each test and reset both
// static facades afterwards.
beforeEach(function () {
	Log::reset();
	Later::reset();
	Log::proxy(test()->createMock(LoggerInterface::class));
});

afterEach(fn() => Later::reset());

// ---------------------------------------------------------------------------
// FILO ordering
// ---------------------------------------------------------------------------

test('callbacks run in reverse registration order (FILO)', function () {
	$sequence = [];

	Later::call(function () use (&$sequence) {
		$sequence[] = 'first';
	});
	Later::call(function () use (&$sequence) {
		$sequence[] = 'second';
	});
	Later::call(function () use (&$sequence) {
		$sequence[] = 'third';
	});

	Later::process();

	expect($sequence)->toBe(['third', 'second', 'first']);
});

test('single callback runs', function () {
	$called = false;

	Later::call(function () use (&$called) {
		$called = true;
	});
	Later::process();

	expect($called)->toBeTrue();
});

// ---------------------------------------------------------------------------
// Arguments
// ---------------------------------------------------------------------------

test('arguments are passed through to the callback', function () {
	$received = null;

	Later::call(
		function (...$args) use (&$received) {
			$received = $args;
		},
		'a',
		42,
		['key' => 'val'],
	);
	Later::process();

	expect($received)->toBe(['a', 42, ['key' => 'val']]);
});

test('callback with no arguments works', function () {
	$called = false;

	Later::call(function () use (&$called) {
		$called = true;
	});
	Later::process();

	expect($called)->toBeTrue();
});

// ---------------------------------------------------------------------------
// Exception handling
// ---------------------------------------------------------------------------

test('exception in one callback does not stop the stack', function () {
	$sequence = [];

	Later::call(function () use (&$sequence) {
		$sequence[] = 'first';
	});
	Later::call(function () {
		throw new \RuntimeException('boom');
	});
	Later::call(function () use (&$sequence) {
		$sequence[] = 'third';
	});

	Later::process();

	// FILO: third runs first (succeeds), second throws (caught), first runs last.
	expect($sequence)->toBe(['third', 'first']);
});

test('exception message is sent to error_log', function () {
	$file = sys_get_temp_dir() . '/hit_later_test_' . uniqid() . '.log';
	$previous = ini_set('error_log', $file);

	try {
		Later::call(function () {
			throw new \RuntimeException('deliberate failure');
		});
		Later::process();

		$contents = file_get_contents($file);
		expect($contents)->toContain('deliberate failure');
		expect($contents)->toContain('Later failed to call');
	}
	finally {
		ini_set('error_log', $previous);
		@unlink($file);
	}
});

// ---------------------------------------------------------------------------
// reset
// ---------------------------------------------------------------------------

test('reset clears pending callbacks without executing them', function () {
	$called = false;

	Later::call(function () use (&$called) {
		$called = true;
	});
	Later::reset();
	Later::process();

	expect($called)->toBeFalse();
});

test('process on empty stack is a no-op', function () {
	// Should not throw or produce any side effects.
	Later::process();

	expect(true)->toBeTrue();
});

// ---------------------------------------------------------------------------
// Re-usage after process
// ---------------------------------------------------------------------------

test('callbacks can be added and processed again after a full cycle', function () {
	$sequence = [];

	Later::call(function () use (&$sequence) {
		$sequence[] = 'batch1';
	});
	Later::process();

	Later::call(function () use (&$sequence) {
		$sequence[] = 'batch2';
	});
	Later::process();

	expect($sequence)->toBe(['batch1', 'batch2']);
});
