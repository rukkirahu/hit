<?php

declare(strict_types=1);

namespace Tests\Unit;

use Hit\Attribute\Acl;
use Hit\Router\Route;
use Hit\Router\Routes\RouteDefinition;
use Hit\Router\Routes\RouteMatch;
use Hit\Router\Routes\RouteTable;
use InvalidArgumentException;
use RuntimeException;

function route_table(RouteDefinition ...$routes): RouteTable {
	return new RouteTable(...$routes);
}

// ---------------------------------------------------------------------------
// Empty / default state
// ---------------------------------------------------------------------------

test('map returns null when no routes are configured', function () {
	$route = new Route(route_table());

	expect($route->map('GET', '/'))->toBeNull();
});

test('map returns null for unknown HTTP method', function () {
	$route = new Route(route_table(new RouteDefinition('GET', 'users', 'index', 1, Acl::NONE)));

	expect($route->map('PATCH', '/users'))->toBeNull();
});

// ---------------------------------------------------------------------------
// Basic routing
// ---------------------------------------------------------------------------

test('map returns a typed match with forwarded route metadata', function () {
	$definition = new RouteDefinition('GET', 'users', 'users.list', 1, Acl::NONE);
	$route = new Route(route_table($definition));

	$result = $route->map('GET', '/users');

	expect($result)
		->toBeInstanceOf(RouteMatch::class)
		->and($result->route)
		->toBe($definition)
		->and($result->action)
		->toBe('users.list')
		->and($result->accept)
		->toBe(1)
		->and($result->acl)
		->toBe(Acl::NONE)
		->and($result->params)
		->toBe([]);
});

test('map matches a POST route', function () {
	$route = new Route(route_table(new RouteDefinition('POST', 'users', 'users.create', 1, Acl::NONE)));

	expect($route->map('POST', '/users')?->action)->toBe('users.create');
});

test('map returns null when no route matches the path', function () {
	$route = new Route(route_table(new RouteDefinition('GET', 'users', 'users.list', 1, Acl::NONE)));

	expect($route->map('GET', '/orders'))->toBeNull();
});

// ---------------------------------------------------------------------------
// Explicit base route
// ---------------------------------------------------------------------------

test('empty URI explicitly matches the base path', function () {
	$route = new Route(route_table(new RouteDefinition('GET', '', 'home', 1, Acl::NONE)));

	expect($route->map('GET', '/')?->action)->toBe('home');
});

test('a non-empty route is not used as an implicit default', function () {
	$route = new Route(route_table(new RouteDefinition('GET', 'home', 'home', 1, Acl::NONE)));

	expect($route->map('GET', '/'))->toBeNull();
});

// ---------------------------------------------------------------------------
// Route params (regex capture groups)
// ---------------------------------------------------------------------------

test('map extracts named params from regex routes', function () {
	$route = new Route(route_table(new RouteDefinition('GET', 'users/(?<id>\d+)', 'users.show', 1, Acl::NONE)));

	$result = $route->map('GET', '/users/42');

	expect($result?->action)->toBe('users.show')->and($result?->params)->toBe(['id' => '42']);
});

test('invalid route regex is rejected during router construction', function () {
	$table = route_table(new RouteDefinition('GET', '[', 'invalid', 1, Acl::NONE));

	expect(fn() => new Route($table))->toThrow(InvalidArgumentException::class);
});

// ---------------------------------------------------------------------------
// ACL filtering
// ---------------------------------------------------------------------------

test('public routes match without a caller ACL', function () {
	$route = new Route(route_table(new RouteDefinition('GET', 'public', 'public', 1, Acl::NONE)));

	expect($route->map('GET', '/public')?->action)->toBe('public');
});

test('protected routes do not match an insufficient caller ACL', function () {
	$route = new Route(route_table(new RouteDefinition('GET', 'users', 'users', 1, Acl::USER)));

	expect($route->map('GET', '/users', Acl::GUEST))->toBeNull();
});

test('protected routes match a sufficient caller ACL', function () {
	$route = new Route(route_table(new RouteDefinition('GET', 'users', 'users', 1, Acl::USER)));

	expect($route->map('GET', '/users', Acl::USER | Acl::ADMIN)?->action)->toBe('users');
});

// ---------------------------------------------------------------------------
// Base path
// ---------------------------------------------------------------------------

test('base path is included in matching', function () {
	$route = new Route(route_table(new RouteDefinition('GET', 'users', 'users.list', 1, Acl::NONE)), '/api/');

	expect($route->map('GET', '/api/users')?->action)->toBe('users.list')->and($route->map('GET', '/users'))->toBeNull();
});

test('base path with dot is escaped in regex', function () {
	$route = new Route(route_table(new RouteDefinition('GET', 'items', 'items.list', 1, Acl::NONE)), '/api/v2.0/');

	expect($route->map('GET', '/api/v2X0/items'))->toBeNull()->and($route->map('GET', '/api/v2.0/items')?->action)->toBe('items.list');
});

// ---------------------------------------------------------------------------
// Base path validation
// ---------------------------------------------------------------------------

test('constructor rejects invalid base paths', function (string $base) {
	expect(fn() => new Route(route_table(), $base))->toThrow(InvalidArgumentException::class);
})->with(['', 'api/', '/api']);

// ---------------------------------------------------------------------------
// Route table validation
// ---------------------------------------------------------------------------

test('duplicate method and URI definitions are rejected', function () {
	expect(fn() => route_table(new RouteDefinition('GET', 'users', 'first', 1, Acl::NONE), new RouteDefinition('GET', 'users', 'second', 1, Acl::NONE)))
		->toThrow(RuntimeException::class);
});

test('route definitions reject leading slash URIs', function () {
	expect(fn() => new RouteDefinition('GET', '/users', 'users', 1, Acl::NONE))->toThrow(InvalidArgumentException::class);
});

test('route tables validate serialized cache data', function () {
	expect(fn() => RouteTable::fromArray([['method' => 'GET']]))->toThrow(RuntimeException::class);
});
