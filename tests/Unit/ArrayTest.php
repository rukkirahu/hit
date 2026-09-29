<?php

declare(strict_types=1);

namespace Tests\Unit;

use Hit\Array\Ray;

test('dot flattens nested arrays', function () {
	$input = ['a' => ['b' => ['c' => 1], 'd' => 2], 'e' => 3];

	expect(Ray::dot($input))->toBe([
		'a.b.c' => 1,
		'a.d' => 2,
		'e' => 3,
	]);
});

test('dot keeps empty arrays as values', function () {
	expect(Ray::dot(['a' => [], 'b' => 1]))->toBe(['a' => [], 'b' => 1]);
});

test('dot applies prepend prefix', function () {
	expect(Ray::dot(['a' => 1], 'pre.'))->toBe(['pre.a' => 1]);
});

test('undot expands dotted keys', function () {
	$input = ['a.b.c' => 1, 'a.d' => 2, 'e' => 3];
	expect(Ray::undot($input))->toBe(['a' => ['b' => ['c' => 1], 'd' => 2], 'e' => 3]);
});

test('dot and undot round trip', function () {
	$input = ['x' => ['y' => ['z' => 'v']], 'flat' => 5];

	expect(Ray::undot(Ray::dot($input)))->toBe($input);
});

test('remove deletes top level keys', function () {
	$array = ['a' => 1, 'b' => 2, 'c' => 3];

	Ray::remove($array, ['b']);

	expect($array)->toBe(['a' => 1, 'c' => 3]);
});

test('remove deletes nested keys via dot notation', function () {
	$array = ['a' => ['b' => 1, 'c' => 2]];

	Ray::remove($array, ['a.b']);

	expect($array)->toBe(['a' => ['c' => 2]]);
});

test('remove does nothing for empty keys', function () {
	$array = ['a' => 1];

	Ray::remove($array, []);

	expect($array)->toBe(['a' => 1]);
});

test('remove ignores missing nested path', function () {
	$array = ['a' => ['b' => 1]];

	Ray::remove($array, ['a.x.y']);

	expect($array)->toBe(['a' => ['b' => 1]]);
});

test('get returns value for top level key', function () {
	expect(Ray::get(['a' => 1], 'a'))->toBe(1);
});

test('get resolves dot notation', function () {
	expect(Ray::get(['a' => ['b' => 2]], 'a.b'))->toBe(2);
});

test('get returns default when missing', function () {
	expect(Ray::get(['a' => 1], 'x', 'fallback'))->toBe('fallback');
});

test('getStr casts result to string', function () {
	expect(Ray::getStr(['a' => 123], 'a'))->toBe('123');
});

test('getStr returns default string when missing', function () {
	expect(Ray::getStr(['a' => 1], 'x'))->toBe('');
});

test('has returns true when key present', function () {
	expect(Ray::has(['a' => ['b' => 1]], 'a.b'))->toBeTrue();
});

test('has returns false when key missing', function () {
	expect(Ray::has(['a' => 1], 'a.b'))->toBeFalse();
});

test('has supports multiple keys', function () {
	$array = ['a' => 1, 'b' => ['c' => 2]];

	expect(Ray::has($array, ['a', 'b.c']))->toBeTrue();
	expect(Ray::has($array, ['a', 'missing']))->toBeFalse();
});

test('has returns false for empty inputs', function () {
	expect(Ray::has([], 'a'))->toBeFalse();
	expect(Ray::has(['a' => 1], []))->toBeFalse();
});

test('isAssoc distinguishes associative arrays', function () {
	expect(Ray::isAssoc(['a' => 1]))->toBeTrue();
	expect(Ray::isAssoc([0 => 'x', 1 => 'y']))->toBeFalse();
});

test('isList distinguishes lists', function () {
	expect(Ray::isList(['x', 'y']))->toBeTrue();
	expect(Ray::isList(['a' => 1]))->toBeFalse();
});

test('only returns subset of keys', function () {
	$array = ['a' => 1, 'b' => 2, 'c' => 3];

	expect(Ray::only($array, ['a', 'c']))->toBe(['a' => 1, 'c' => 3]);
});

test('query builds a query string', function () {
	expect(Ray::query(['a' => 1, 'b' => 'x y']))->toBe('a=1&b=x%20y');
});

test('set assigns value with dot notation', function () {
	$data = [];

	Ray::set($data, 'a.b.c', 1);

	expect($data)->toBe(['a' => ['b' => ['c' => 1]]]);
});

test('set overwrites non array intermediate values', function () {
	$data = ['a' => 5];

	Ray::set($data, 'a.b', 2);

	expect($data)->toBe(['a' => ['b' => 2]]);
});

test('remap renames keys from source', function () {
	$array = ['id' => 1, 'name' => 'Bob'];

	$result = Ray::remap($array, ['id' => 'order_id', 'name' => 'customer_name']);

	expect($result)->toBe(['order_id' => 1, 'customer_name' => 'Bob']);
});

test('remap ignores null values by default', function () {
	$array = ['id' => 1];

	$result = Ray::remap($array, ['id' => 'order_id', 'missing' => 'foo']);

	expect($result)->toBe(['order_id' => 1]);
});

test('remap keeps null values when disabled', function () {
	$array = ['id' => 1];

	$result = Ray::remap($array, ['missing' => 'foo'], false);

	expect($result)->toBe(['foo' => null]);
});

test('remap supports callable destination', function () {
	$array = ['price' => 10];

	$result = Ray::remap($array, [
		'price' => fn($key, $src) => ['total', $src[$key] * 2],
	]);

	expect($result)->toBe(['total' => 20]);
});
