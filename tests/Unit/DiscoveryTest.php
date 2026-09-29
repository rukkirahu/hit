<?php

declare(strict_types=1);

namespace Tests\Unit;

use Hit\Attribute\Accept;
use Hit\Attribute\Acl;
use Hit\Router\Discovery;
use RuntimeException;
use Tests\Fixtures\Action\Admin\NestedAction;
use Tests\Fixtures\Action\MultiRouteAction;
use Tests\Fixtures\Action\PublicAction;
use Tests\Fixtures\Action\SampleGetAction;
use Tests\Support\FakeStore;

function fixturesRoot(): string {
	return dirname(__DIR__) . '/Fixtures';
}

// ---------------------------------------------------------------------------
// Basic discovery
// ---------------------------------------------------------------------------

test('discovers a route from an attribute-decorated action class', function () {
	$discovery = new Discovery(fixturesRoot(), 'Tests\\Fixtures', new FakeStore(), development: true);
	$routes = $discovery->getRoutes();

	expect($routes['GET']['sample']['action'])->toBe(SampleGetAction::class);
	expect($routes['GET']['sample']['accept'])->toBe(Accept::HTML);
	expect($routes['GET']['sample']['acl'])->toBe(Acl::USER);
});

test('discovery throws when a route URI has a leading slash', function () {
	$root = dirname(__DIR__) . '/FixturesBadRoute';

	expect(fn() => new Discovery($root, 'Tests\\FixturesBadRoute', new FakeStore(), development: true))
		->toThrow(RuntimeException::class);
});

test('discovers nested action classes in subdirectories', function () {
	$discovery = new Discovery(fixturesRoot(), 'Tests\\Fixtures', new FakeStore(), development: true);
	$routes = $discovery->getRoutes();

	expect($routes['GET']['admin/nested']['action'])->toBe(NestedAction::class);
});

test('action without an Acl attribute defaults to Acl::NONE', function () {
	$discovery = new Discovery(fixturesRoot(), 'Tests\\Fixtures', new FakeStore(), development: true);
	$routes = $discovery->getRoutes();

	expect($routes['GET']['public']['action'])->toBe(PublicAction::class);
	expect($routes['GET']['public']['acl'])->toBe(Acl::NONE);
});

// ---------------------------------------------------------------------------
// Repeatable route attributes (regression: previously only the last one
// survived because routes were only written once, after the attribute loop)
// ---------------------------------------------------------------------------

test('a class with multiple route attributes registers all of them', function () {
	$discovery = new Discovery(fixturesRoot(), 'Tests\\Fixtures', new FakeStore(), development: true);
	$routes = $discovery->getRoutes();

	expect($routes['GET']['multi-get']['action'])->toBe(MultiRouteAction::class);
	expect($routes['POST']['multi-post']['action'])->toBe(MultiRouteAction::class);
});

// ---------------------------------------------------------------------------
// getAclFilterRoutes (regression: public/acl=NONE routes used to be
// excluded entirely because "0 & anything" is always falsy)
// ---------------------------------------------------------------------------

test('getAclFilterRoutes keeps public routes regardless of caller acl', function () {
	$discovery = new Discovery(fixturesRoot(), 'Tests\\Fixtures', new FakeStore(), development: true);

	$filtered = $discovery->getAclFilterRoutes(Acl::GUEST);

	expect($filtered['GET'])->toHaveKey('public');
});

test('getAclFilterRoutes excludes routes the caller acl does not satisfy', function () {
	$discovery = new Discovery(fixturesRoot(), 'Tests\\Fixtures', new FakeStore(), development: true);

	$filtered = $discovery->getAclFilterRoutes(Acl::GUEST);

	expect($filtered['GET'])->not->toHaveKey('sample');
});

test('getAclFilterRoutes includes routes the caller acl satisfies', function () {
	$discovery = new Discovery(fixturesRoot(), 'Tests\\Fixtures', new FakeStore(), development: true);

	$filtered = $discovery->getAclFilterRoutes(Acl::USER);

	expect($filtered['GET'])->toHaveKey('sample');
	expect($filtered['GET'])->toHaveKey('public');
});

// ---------------------------------------------------------------------------
// Cache skip regression (bug #1): routes must survive a rebuild even when
// the per-file "unchanged" skip path is taken.
// ---------------------------------------------------------------------------

test('routes survive rediscovery when the routes cache is cleared but actions cache is not', function () {
	$cache = new FakeStore();

	$first = new Discovery(fixturesRoot(), 'Tests\\Fixtures', $cache, development: false);
	$firstRoutes = $first->getRoutes();

	expect($firstRoutes['GET']['sample']['action'])->toBe(SampleGetAction::class);

	// Simulate the "routes" cache entry expiring/being cleared independently
	// of the "actions" (per-class mtime) cache entry.
	$cache->delete('routes');

	$second = new Discovery(fixturesRoot(), 'Tests\\Fixtures', $cache, development: false);

	expect($second->getRoutes())->toBe($firstRoutes);
});
