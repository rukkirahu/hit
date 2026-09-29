<?php

declare(strict_types=1);

namespace Tests\Unit;

use Hit\Cache\Store\RedisStore;

use function expect;
use function test;

// ---------------------------------------------------------------------------
// get
// ---------------------------------------------------------------------------

test('get returns decoded value on hit', function () {
	$redis = test()->createMock(\Redis::class);
	$redis->method('get')->with('key')->willReturn(json_encode('hello'));

	expect(new RedisStore($redis)->get('key'))->toBe('hello');
});

test('get returns decoded array on hit', function () {
	$redis = test()->createMock(\Redis::class);
	$redis
		->method('get')
		->with('key')
		->willReturn(json_encode(['a' => 1]));

	expect(new RedisStore($redis)->get('key'))->toBe(['a' => 1]);
});

test('get returns null on miss', function () {
	$redis = test()->createMock(\Redis::class);
	$redis->method('get')->with('missing')->willReturn(false);

	expect(new RedisStore($redis)->get('missing'))->toBeNull();
});

test('get throws on invalid json', function () {
	$redis = test()->createMock(\Redis::class);
	$redis->method('get')->with('key')->willReturn('not-valid-json{{{');

	new RedisStore($redis)->get('key');
})->throws(\RuntimeException::class, 'Cache decode error');

// ---------------------------------------------------------------------------
// set
// ---------------------------------------------------------------------------

test('set encodes value and calls setex with ttl', function () {
	$redis = test()->createMock(\Redis::class);
	$redis->method('setex')->with('key', 3600, json_encode('value'))->willReturn(true);

	expect(new RedisStore($redis)->set('key', 'value', 3600))->toBeTrue();
});

test('set uses constructor default ttl when expires is 0', function () {
	$redis = test()->createMock(\Redis::class);
	$redis->method('setex')->with('key', 7200, json_encode('v'))->willReturn(true);

	expect(new RedisStore($redis, 7200)->set('key', 'v', 0))->toBeTrue();
});

test('set encodes array value', function () {
	$data = ['x' => 1, 'y' => 2];
	$redis = test()->createMock(\Redis::class);
	$redis->method('setex')->with('key', 3600, json_encode($data))->willReturn(true);

	expect(new RedisStore($redis)->set('key', $data, 3600))->toBeTrue();
});

test('set returns false when setex returns false', function () {
	$redis = test()->createMock(\Redis::class);
	$redis->method('setex')->willReturn(false);

	expect(new RedisStore($redis)->set('key', 'v', 60))->toBeFalse();
});

// ---------------------------------------------------------------------------
// exists
// ---------------------------------------------------------------------------

test('exists returns true when redis says key exists', function () {
	$redis = test()->createMock(\Redis::class);
	$redis->method('exists')->with('key')->willReturn(1);

	expect(new RedisStore($redis)->exists('key'))->toBeTrue();
});

test('exists returns false on miss', function () {
	$redis = test()->createMock(\Redis::class);
	$redis->method('exists')->with('key')->willReturn(0);

	expect(new RedisStore($redis)->exists('key'))->toBeFalse();
});

// ---------------------------------------------------------------------------
// delete
// ---------------------------------------------------------------------------

test('delete calls del and returns true on success', function () {
	$redis = test()->createMock(\Redis::class);
	$redis->method('del')->with('key')->willReturn(1);

	expect(new RedisStore($redis)->delete('key'))->toBeTrue();
});

test('delete returns false when key did not exist', function () {
	$redis = test()->createMock(\Redis::class);
	$redis->method('del')->with('key')->willReturn(0);

	expect(new RedisStore($redis)->delete('key'))->toBeFalse();
});
