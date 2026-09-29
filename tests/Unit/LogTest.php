<?php

declare(strict_types=1);

namespace Tests\Unit;

use Hit\Log\Log;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;

// Reset facade between tests so static state does not leak.
beforeEach(fn() => Log::reset());

// ---------------------------------------------------------------------------
// Uninitialized state
// ---------------------------------------------------------------------------

test('throws when not initialized', function () {
	Log::debug('msg');
})->throws(\RuntimeException::class, 'Log not initialized');

test('emergency throws when not initialized', function () {
	Log::emergency('msg');
})->throws(\RuntimeException::class, 'Log not initialized');

// ---------------------------------------------------------------------------
// proxy / delegation
// ---------------------------------------------------------------------------

test('proxy registers the instance', function () {
	$logger = test()->createMock(LoggerInterface::class);
	$logger->expects(test()->once())->method('debug')->with('hello', []);
	Log::proxy($logger);

	Log::debug('hello');
});

test('each level delegates to the matching method', function () {
	$logger = test()->createMock(LoggerInterface::class);
	$logger->expects(test()->once())->method('debug')->with('d', []);
	$logger->expects(test()->once())->method('info')->with('i', []);
	$logger->expects(test()->once())->method('notice')->with('n', []);
	$logger->expects(test()->once())->method('warning')->with('w', []);
	$logger->expects(test()->once())->method('error')->with('e', []);
	$logger->expects(test()->once())->method('critical')->with('c', []);
	$logger->expects(test()->once())->method('alert')->with('a', []);
	$logger->expects(test()->once())->method('emergency')->with('em', []);
	Log::proxy($logger);

	Log::debug('d');
	Log::info('i');
	Log::notice('n');
	Log::warning('w');
	Log::error('e');
	Log::critical('c');
	Log::alert('a');
	Log::emergency('em');
});

test('context is forwarded to the underlying logger', function () {
	$logger = test()->createMock(LoggerInterface::class);
	$logger->expects(test()->once())->method('error')->with('boom', ['pid' => '1', 'auth' => 'user', 'remote_ip' => '127.0.0.1']);
	Log::proxy($logger);

	Log::setPid('1');
	Log::setAuth('user');
	Log::setRemoteIp('127.0.0.1');
	Log::error('boom');
});

// ---------------------------------------------------------------------------
// Message handling
// ---------------------------------------------------------------------------

test('empty message is not forwarded', function () {
	$logger = test()->createMock(LoggerInterface::class);
	$logger->expects(test()->never())->method('debug');
	Log::proxy($logger);

	Log::debug('   ');
});

test('whitespace-only message is not forwarded', function () {
	$logger = test()->createMock(LoggerInterface::class);
	$logger->expects(test()->never())->method('info');
	Log::proxy($logger);

	Log::info("\n\t ");
});

test('callable message is evaluated lazily', function () {
	$evaluated = false;
	$logger = test()->createMock(LoggerInterface::class);
	$logger->expects(test()->once())->method('debug')->with('computed', []);
	Log::proxy($logger);

	Log::debug(function () use (&$evaluated) {
		$evaluated = true;

		return 'computed';
	});

	expect($evaluated)->toBeTrue();
});

test('callable message is not evaluated when level is filtered out', function () {
	$evaluated = false;
	$logger = test()->createMock(LoggerInterface::class);
	$logger->expects(test()->never())->method('debug');
	Log::proxy($logger);
	Log::setLevel(LogLevel::ERROR);

	Log::debug(function () use (&$evaluated) {
		$evaluated = true;

		return 'should-not-run';
	});

	expect($evaluated)->toBeFalse();
});

test('message is trimmed before forwarding', function () {
	$logger = test()->createMock(LoggerInterface::class);
	$logger->expects(test()->once())->method('info')->with('trimmed', []);
	Log::proxy($logger);

	Log::info('  trimmed  ');
});

// ---------------------------------------------------------------------------
// setLevel threshold
// ---------------------------------------------------------------------------

test('setLevel DEBUG allows all levels', function () {
	$logger = test()->createMock(LoggerInterface::class);
	$logger->expects(test()->once())->method('debug');
	$logger->expects(test()->once())->method('warning');
	$logger->expects(test()->once())->method('error');
	Log::proxy($logger);
	Log::setLevel(LogLevel::DEBUG);

	Log::debug('d');
	Log::warning('w');
	Log::error('e');
});

test('setLevel ERROR suppresses debug info notice warning', function () {
	$logger = test()->createMock(LoggerInterface::class);
	$logger->expects(test()->never())->method('debug');
	$logger->expects(test()->never())->method('info');
	$logger->expects(test()->never())->method('notice');
	$logger->expects(test()->never())->method('warning');
	$logger->expects(test()->once())->method('error');
	$logger->expects(test()->once())->method('critical');
	$logger->expects(test()->once())->method('alert');
	Log::proxy($logger);
	Log::setLevel(LogLevel::ERROR);

	Log::debug('d');
	Log::info('i');
	Log::notice('n');
	Log::warning('w');
	Log::error('e');
	Log::critical('c');
	Log::alert('a');
});

test('setLevel ALERT allows only alert and above', function () {
	$logger = test()->createMock(LoggerInterface::class);
	$logger->expects(test()->never())->method('debug');
	$logger->expects(test()->never())->method('error');
	$logger->expects(test()->never())->method('critical');
	$logger->expects(test()->once())->method('alert');
	Log::proxy($logger);
	Log::setLevel(LogLevel::ALERT);

	Log::debug('d');
	Log::error('e');
	Log::critical('c');
	Log::alert('a');
});

test('emergency always logs regardless of level', function () {
	$logger = test()->createMock(LoggerInterface::class);
	$logger->expects(test()->once())->method('emergency')->with('boom', []);
	Log::proxy($logger);
	Log::setLevel(LogLevel::ALERT);

	Log::emergency('boom');
});

test('emergency logs even at the most restrictive level', function () {
	$logger = test()->createMock(LoggerInterface::class);
	$logger->expects(test()->never())->method('critical');
	$logger->expects(test()->once())->method('alert');
	$logger->expects(test()->once())->method('emergency');
	Log::proxy($logger);
	Log::setLevel(LogLevel::ALERT);

	Log::critical('c'); // suppressed — below ALERT threshold
	Log::alert('a'); // allowed — at ALERT threshold
	Log::emergency('em'); // always logs
});

// ---------------------------------------------------------------------------
// reset
// ---------------------------------------------------------------------------

test('reset clears the proxy instance', function () {
	$logger = test()->createMock(LoggerInterface::class);
	Log::proxy($logger);
	Log::reset();

	Log::debug('msg');
})->throws(\RuntimeException::class, 'Log not initialized');

test('reset clears context', function () {
	$logger = test()->createMock(LoggerInterface::class);
	$logger->expects(test()->once())->method('error')->with('msg', []);
	Log::proxy($logger);

	Log::setPid('1');
	Log::setAuth('user');
	Log::setRemoteIp('1.2.3.4');
	Log::reset();
	Log::proxy($logger);

	Log::error('msg'); // context should be empty after reset
});

test('reset restores default level', function () {
	$logger = test()->createMock(LoggerInterface::class);
	$logger->expects(test()->once())->method('debug');
	Log::proxy($logger);
	Log::setLevel(LogLevel::ERROR); // suppresses debug
	Log::reset();
	Log::proxy($logger);

	Log::debug('d'); // should work again — default level allows all
});
