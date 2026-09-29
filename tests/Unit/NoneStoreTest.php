<?php

declare(strict_types=1);

namespace Tests;

use Hit\Cache\Store\NoneStore;

use function expect;
use function test;

test('get always returns null', function () {
	expect(new NoneStore()->get('any'))->toBeNull();
});

test('exists always returns false', function () {
	expect(new NoneStore()->exists('any'))->toBeFalse();
});

test('set always returns false', function () {
	expect(new NoneStore()->set('k', 'v', 60))->toBeFalse();
});

test('delete always returns false', function () {
	expect(new NoneStore()->delete('any'))->toBeFalse();
});
