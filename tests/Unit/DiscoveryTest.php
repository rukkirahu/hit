<?php

declare(strict_types=1);

namespace Tests\Unit;

use Hit\Attribute\Accept;
use Hit\Attribute\Acl;
use Hit\Router\Discovery;
use Hit\Router\Routes\RouteTable;
use InvalidArgumentException;
use RuntimeException;
use Tests\Fixtures\Action\Admin\NestedAction;
use Tests\Fixtures\Action\MultiRouteAction;
use Tests\Fixtures\Action\PublicAction;
use Tests\Fixtures\Action\SampleGetAction;
use Tests\Support\FakeStore;

function fixtures_root(): string {
	return dirname(__DIR__) . '/Fixtures';
}

function discovered_routes(RouteTable $table, string $method): array {
	return iterator_to_array($table->forMethod($method));
}

// ---------------------------------------------------------------------------
// Basic discovery
// ---------------------------------------------------------------------------

test('discovers a typed route definition from an action attribute', function () {
	$table = new Discovery(fixtures_root(), 'Tests\\Fixtures', development: true)->discover();
	$route = discovered_routes($table, 'GET')['sample'];

	expect($route->action)
		->toBe(SampleGetAction::class)
		->and($route->accept)
		->toBe(Accept::HTML)
		->and($route->acl)
		->toBe(Acl::USER);
});

test('discovery rejects a route URI with a leading slash', function () {
	$root = dirname(__DIR__) . '/FixturesBadRoute';

	expect(fn() => new Discovery($root, 'Tests\\FixturesBadRoute', development: true)->discover())
		->toThrow(InvalidArgumentException::class);
});

test('discovers nested action classes in subdirectories', function () {
	$table = new Discovery(fixtures_root(), 'Tests\\Fixtures', development: true)->discover();

	expect(discovered_routes($table, 'GET')['admin/nested']->action)->toBe(NestedAction::class);
});

test('route ACL defaults to Acl NONE', function () {
	$table = new Discovery(fixtures_root(), 'Tests\\Fixtures', development: true)->discover();
	$route = discovered_routes($table, 'GET')['public'];

	expect($route->action)->toBe(PublicAction::class)->and($route->acl)->toBe(Acl::NONE);
});

test('discovers a non-GET action route', function () {
	$table = new Discovery(fixtures_root(), 'Tests\\Fixtures', development: true)->discover();

	expect(discovered_routes($table, 'POST')['multi-post']->action)->toBe(MultiRouteAction::class);
});

test('an action cannot declare multiple route attributes', function () {
	$root = dirname(__DIR__) . '/FixturesMultipleRoutes';

	expect(fn() => new Discovery($root, 'Tests\\FixturesMultipleRoutes', development: true)->discover())
		->toThrow(RuntimeException::class);
});

// ---------------------------------------------------------------------------
// Cache behavior
// ---------------------------------------------------------------------------

test('production discovery uses one cached route table after the first scan', function () {
	$cache = new FakeStore();
	$discovery = new Discovery(fixtures_root(), 'Tests\\Fixtures', $cache);

	$first = $discovery->discover();
	$second = $discovery->discover();

	expect($second->toArray())
		->toBe($first->toArray())
		->and($cache->gets)
		->toBe(2)
		->and($cache->sets)
		->toBe(1);
});

test('development discovery bypasses cache reads and writes', function () {
	$cache = new FakeStore();
	$discovery = new Discovery(fixtures_root(), 'Tests\\Fixtures', $cache, development: true);

	$discovery->discover();
	$discovery->discover();

	expect($cache->gets)->toBe(0)->and($cache->sets)->toBe(0);
});

test('discovery works without a cache store', function () {
	$table = new Discovery(fixtures_root(), 'Tests\\Fixtures')->discover();

	expect(discovered_routes($table, 'GET'))->toHaveKey('sample');
});
