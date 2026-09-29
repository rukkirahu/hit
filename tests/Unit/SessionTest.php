<?php

declare(strict_types=1);

namespace Tests\Unit;

use Hit\Session\Session;
use RuntimeException;

// Ensure a session is active for every test, and clear the superglobal between
// tests. session_start() can only be called once per process, so we guard it.
beforeEach(function () {
	if (session_status() !== PHP_SESSION_ACTIVE) {
		session_start();
	}
	$_SESSION = [];
});

afterEach(function () {
	$_SESSION = [];
});

// ---------------------------------------------------------------------------
// Uninitialized state
// ---------------------------------------------------------------------------

test('set throws when session is not active', function () {
	session_destroy();
	Session::set('k', 'v');
})->throws(RuntimeException::class, 'Session not initialized');

test('get throws when session is not active', function () {
	session_destroy();
	Session::get('k');
})->throws(RuntimeException::class, 'Session not initialized');

// ---------------------------------------------------------------------------
// set / get
// ---------------------------------------------------------------------------

test('set stores a value under a key', function () {
	Session::set('foo', 'bar');
	expect($_SESSION['foo'])->toBe('bar');
});

test('get returns the stored value', function () {
	$_SESSION['foo'] = 'bar';
	expect(Session::get('foo'))->toBe('bar');
});

test('get returns null for a missing key', function () {
	expect(Session::get('missing'))->toBeNull();
});

test('get with empty key returns the entire session array', function () {
	$_SESSION = ['a' => 1, 'b' => 2];
	expect(Session::get())->toBe(['a' => 1, 'b' => 2]);
});

test('set with no value defaults to null', function () {
	Session::set('flag');
	expect($_SESSION['flag'])->toBeNull();
});

// ---------------------------------------------------------------------------
// getString
// ---------------------------------------------------------------------------

test('getString returns a string value', function () {
	$_SESSION['name'] = 'hello';
	expect(Session::getString('name'))->toBe('hello');
});

test('getString casts an int to string', function () {
	$_SESSION['count'] = 42;
	expect(Session::getString('count'))->toBe('42');
});

test('getString returns empty string for missing key', function () {
	expect(Session::getString('missing'))->toBe('');
});

test('getString returns empty string for a stored array', function () {
	$_SESSION['data'] = ['a', 'b'];
	expect(Session::getString('data'))->toBe('');
});

test('getString returns empty string for a stored null', function () {
	$_SESSION['nil'] = null;
	expect(Session::getString('nil'))->toBe('');
});

// ---------------------------------------------------------------------------
// pull
// ---------------------------------------------------------------------------

test('pull returns and removes the value', function () {
	$_SESSION['temp'] = 'flash';
	expect(Session::pull('temp'))->toBe('flash');
	expect(array_key_exists('temp', $_SESSION))->toBeFalse();
});

test('pull returns null for a missing key', function () {
	expect(Session::pull('missing'))->toBeNull();
});

test('pull retrieves falsy values correctly', function () {
	$_SESSION['zero'] = 0;
	expect(Session::pull('zero'))->toBe(0);
	expect(array_key_exists('zero', $_SESSION))->toBeFalse();

	$_SESSION['empty'] = '';
	expect(Session::pull('empty'))->toBe('');
	expect(array_key_exists('empty', $_SESSION))->toBeFalse();

	$_SESSION['false'] = false;
	expect(Session::pull('false'))->toBeFalse();
	expect(array_key_exists('false', $_SESSION))->toBeFalse();
});

// ---------------------------------------------------------------------------
// reset
// ---------------------------------------------------------------------------

test('reset clears the session array', function () {
	$_SESSION = ['a' => 1, 'b' => 2];
	Session::reset();
	expect($_SESSION)->toBe([]);
});
