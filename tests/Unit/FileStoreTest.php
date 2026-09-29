<?php

declare(strict_types=1);

namespace Tests\Unit;

use FilesystemIterator;
use Hit\Cache\Store\FileStore;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

use function expect;
use function test;

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

function tempCacheDir(): string {
	$dir = sys_get_temp_dir() . '/hit_cache_test_' . bin2hex(random_bytes(6));
	mkdir($dir, 0755, true);

	return $dir;
}

function removeDir(string $dir): void {
	if (!is_dir($dir)) {
		return;
	}
	$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
	foreach ($files as $file) {
		$file->isDir() ? rmdir($file->getRealPath()) : unlink($file->getRealPath());
	}
	rmdir($dir);
}

// ---------------------------------------------------------------------------
// Construction
// ---------------------------------------------------------------------------

test('creates cache directory if it does not exist', function () {
	$dir = sys_get_temp_dir() . '/hit_cache_new_' . bin2hex(random_bytes(6));
	expect(is_dir($dir))->toBeFalse();

	new FileStore($dir);

	expect(is_dir($dir))->toBeTrue();
	removeDir($dir);
});

test('accepts existing directory', function () {
	$dir = tempCacheDir();
	expect(fn() => new FileStore($dir))->not->toThrow(\Exception::class);
	removeDir($dir);
});

// ---------------------------------------------------------------------------
// set / get round-trip
// ---------------------------------------------------------------------------

test('stores and retrieves a string value', function () {
	$dir = tempCacheDir();
	$cacher = new FileStore($dir, 3600);

	$cacher->set('greeting', 'hello', 3600);

	expect($cacher->get('greeting'))->toBe('hello');
	removeDir($dir);
});

test('stores and retrieves an array value', function () {
	$dir = tempCacheDir();
	$cacher = new FileStore($dir, 3600);

	$cacher->set('data', ['foo' => 'bar', 'num' => 1], 3600);

	expect($cacher->get('data'))->toBe(['foo' => 'bar', 'num' => 1]);
	removeDir($dir);
});

test('get returns null for missing key', function () {
	$dir = tempCacheDir();
	$cacher = new FileStore($dir, 3600);

	expect($cacher->get('nonexistent'))->toBeNull();
	removeDir($dir);
});

test('get returns null for expired entry', function () {
	$dir = tempCacheDir();
	$cacher = new FileStore($dir, 3600);

	// TTL of 1 second, already in the past
	$cacher->set('stale', 'old', -1);

	expect($cacher->get('stale'))->toBeNull();
	removeDir($dir);
});

test('uses constructor default timeout when expires is 0', function () {
	$dir = tempCacheDir();
	$cacher = new FileStore($dir, 3600);

	$cacher->set('k', 'v', 0);

	expect($cacher->get('k'))->toBe('v');
	removeDir($dir);
});

// ---------------------------------------------------------------------------
// exists
// ---------------------------------------------------------------------------

test('exists returns true for a live key', function () {
	$dir = tempCacheDir();
	$cacher = new FileStore($dir, 3600);
	$cacher->set('k', 'v', 3600);

	expect($cacher->exists('k'))->toBeTrue();
	removeDir($dir);
});

test('exists returns false for a missing key', function () {
	$dir = tempCacheDir();
	$cacher = new FileStore($dir, 3600);

	expect($cacher->exists('missing'))->toBeFalse();
	removeDir($dir);
});

test('exists returns false for an expired key', function () {
	$dir = tempCacheDir();
	$cacher = new FileStore($dir, 3600);
	$cacher->set('stale', 'v', -1);

	expect($cacher->exists('stale'))->toBeFalse();
	removeDir($dir);
});

// ---------------------------------------------------------------------------
// delete
// ---------------------------------------------------------------------------

test('delete removes an existing key', function () {
	$dir = tempCacheDir();
	$cacher = new FileStore($dir, 3600);
	$cacher->set('k', 'v', 3600);

	$result = $cacher->delete('k');

	expect($result)->toBeTrue();
	expect($cacher->get('k'))->toBeNull();
	removeDir($dir);
});

test('delete returns false for a non-existent key', function () {
	$dir = tempCacheDir();
	$cacher = new FileStore($dir, 3600);

	expect($cacher->delete('ghost'))->toBeFalse();
	removeDir($dir);
});

// ---------------------------------------------------------------------------
// Key sanitization / subdirectories
// ---------------------------------------------------------------------------

test('keys with slashes create subdirectory entries', function () {
	$dir = tempCacheDir();
	$cacher = new FileStore($dir, 3600);

	$cacher->set('user/profile/42', 'data', 3600);

	expect($cacher->get('user/profile/42'))->toBe('data');
	removeDir($dir);
});

test('special characters in keys are sanitized', function () {
	$dir = tempCacheDir();
	$cacher = new FileStore($dir, 3600);

	$cacher->set('key with spaces & symbols!', 'value', 3600);

	expect($cacher->get('key with spaces & symbols!'))->toBe('value');
	removeDir($dir);
});

// ---------------------------------------------------------------------------
// Overwrite
// ---------------------------------------------------------------------------

test('set overwrites an existing entry', function () {
	$dir = tempCacheDir();
	$cacher = new FileStore($dir, 3600);

	$cacher->set('k', 'first', 3600);
	$cacher->set('k', 'second', 3600);

	expect($cacher->get('k'))->toBe('second');
	removeDir($dir);
});
