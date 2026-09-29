<?php

declare(strict_types=1);

namespace Tests\Unit;

use Hit\Cache\Cache;
use Hit\Cache\Store\StoreInterface;
use ReflectionClass;

use function expect;
use function test;

/**
 * Reset the static Cache::$cacheInstance between tests so they are isolated.
 */
function resetCache(): void {
	$ref = new ReflectionClass(Cache::class);
	$prop = $ref->getProperty('cacheInstance');
	$prop->setValue(null, null);
}

// ---------------------------------------------------------------------------
// proxy / assertInitialized
// ---------------------------------------------------------------------------

test('throws when not initialized', function () {
	resetCache();
	Cache::get('key');
})->throws(\RuntimeException::class, 'Cache not initialized');

test('proxy registers the instance', function () {
	resetCache();
	$driver = test()->createMock(StoreInterface::class);
	$driver->method('get')->with('k')->willReturn('v');

	Cache::proxy($driver);

	expect(Cache::get('k'))->toBe('v');
});

// ---------------------------------------------------------------------------
// get / typed getters
// ---------------------------------------------------------------------------

test('get returns value from driver', function () {
	resetCache();
	$driver = test()->createMock(StoreInterface::class);
	$driver->method('get')->with('key')->willReturn('hello');
	Cache::proxy($driver);

	expect(Cache::get('key'))->toBe('hello');
});

test('get returns null on miss', function () {
	resetCache();
	$driver = test()->createMock(StoreInterface::class);
	$driver->method('get')->willReturn(null);
	Cache::proxy($driver);

	expect(Cache::get('missing'))->toBeNull();
});

test('getStr casts to string', function () {
	resetCache();
	$driver = test()->createMock(StoreInterface::class);
	$driver->method('get')->willReturn(42);
	Cache::proxy($driver);

	expect(Cache::getStr('k'))->toBe('42');
});

test('getStr returns empty string on miss', function () {
	resetCache();
	$driver = test()->createMock(StoreInterface::class);
	$driver->method('get')->willReturn(null);
	Cache::proxy($driver);

	expect(Cache::getStr('k'))->toBe('');
});

test('getInt casts to int', function () {
	resetCache();
	$driver = test()->createMock(StoreInterface::class);
	$driver->method('get')->willReturn('99');
	Cache::proxy($driver);

	expect(Cache::getInt('k'))->toBe(99);
});

test('getInt returns 0 on miss', function () {
	resetCache();
	$driver = test()->createMock(StoreInterface::class);
	$driver->method('get')->willReturn(null);
	Cache::proxy($driver);

	expect(Cache::getInt('k'))->toBe(0);
});

test('getBool casts to bool', function () {
	resetCache();
	$driver = test()->createMock(StoreInterface::class);
	$driver->method('get')->willReturn(1);
	Cache::proxy($driver);

	expect(Cache::getBool('k'))->toBeTrue();
});

test('getBool returns false on miss', function () {
	resetCache();
	$driver = test()->createMock(StoreInterface::class);
	$driver->method('get')->willReturn(null);
	Cache::proxy($driver);

	expect(Cache::getBool('k'))->toBeFalse();
});

test('getArray returns array value', function () {
	resetCache();
	$driver = test()->createMock(StoreInterface::class);
	$driver->method('get')->willReturn(['a' => 1]);
	Cache::proxy($driver);

	expect(Cache::getArray('k'))->toBe(['a' => 1]);
});

test('getArray returns empty array on miss', function () {
	resetCache();
	$driver = test()->createMock(StoreInterface::class);
	$driver->method('get')->willReturn(null);
	Cache::proxy($driver);

	expect(Cache::getArray('k'))->toBe([]);
});

test('getArray returns empty array when value is not an array', function () {
	resetCache();
	$driver = test()->createMock(StoreInterface::class);
	$driver->method('get')->willReturn('not-an-array');
	Cache::proxy($driver);

	expect(Cache::getArray('k'))->toBe([]);
});

// ---------------------------------------------------------------------------
// set
// ---------------------------------------------------------------------------

test('set delegates to driver and returns true', function () {
	resetCache();
	$driver = test()->createMock(StoreInterface::class);
	$driver->method('set')->with('k', 'v', 3600)->willReturn(true);
	Cache::proxy($driver);

	expect(Cache::set('k', 'v'))->toBeTrue();
});

test('set passes custom ttl', function () {
	resetCache();
	$driver = test()->createMock(StoreInterface::class);
	$driver->method('set')->with('k', 'v', 60)->willReturn(true);
	Cache::proxy($driver);

	expect(Cache::set('k', 'v', 60))->toBeTrue();
});

// ---------------------------------------------------------------------------
// delete
// ---------------------------------------------------------------------------

test('delete delegates to driver', function () {
	resetCache();
	$driver = test()->createMock(StoreInterface::class);
	$driver->method('delete')->with('k')->willReturn(true);
	Cache::proxy($driver);

	expect(Cache::delete('k'))->toBeTrue();
});

// ---------------------------------------------------------------------------
// exists
// ---------------------------------------------------------------------------

test('exists delegates to driver', function () {
	resetCache();
	$driver = test()->createMock(StoreInterface::class);
	$driver->method('exists')->with('k')->willReturn(true);
	Cache::proxy($driver);

	expect(Cache::exists('k'))->toBeTrue();
});

test('exists returns false when driver says so', function () {
	resetCache();
	$driver = test()->createMock(StoreInterface::class);
	$driver->method('exists')->with('k')->willReturn(false);
	Cache::proxy($driver);

	expect(Cache::exists('k'))->toBeFalse();
});
