<?php

declare(strict_types=1);

namespace Tests\Unit;

use Hit\Router\Route;
use Hit\Router\Routes\Routing;

// ---------------------------------------------------------------------------
// Empty / default state
// ---------------------------------------------------------------------------

test('map returns null when no routes are configured', function () {
	$route = new Route();

	expect($route->map('GET', '/'))->toBeNull();
});

test('map returns null for unknown HTTP method', function () {
	$route = new Route(['GET' => ['users' => ['action' => 'index', 'accept' => 1, 'acl' => 0]]], '/');

	expect($route->map('PATCH', '/users'))->toBeNull();
});

// ---------------------------------------------------------------------------
// Basic routing
// ---------------------------------------------------------------------------

test('map matches a simple GET route', function () {
	$route = new Route([
		'GET' => [
			'users' => ['action' => 'users.list', 'accept' => 1, 'acl' => 0],
		],
	], '/');

	$result = $route->map('GET', '/users');

	expect($result)->toBeInstanceOf(Routing::class);
	expect($result->action)->toBe('users.list');
	expect($result->isDefault)->toBeFalse();
	expect($result->params)->toBe([]);
	expect($result->accept)->toBe(1);
	expect($result->acl)->toBe(0);
});

test('map matches a POST route', function () {
	$route = new Route([
		'POST' => [
			'users' => ['action' => 'users.create', 'accept' => 1, 'acl' => 1],
		],
	], '/');

	$result = $route->map('POST', '/users');

	expect($result)->not->toBeNull();
	expect($result->action)->toBe('users.create');
});

test('map returns null when no route matches the path', function () {
	$route = new Route([
		'GET' => [
			'users' => ['action' => 'users.list', 'accept' => 1, 'acl' => 0],
		],
	], '/');

	expect($route->map('GET', '/orders'))->toBeNull();
});

// ---------------------------------------------------------------------------
// Default route (empty path on GET)
// ---------------------------------------------------------------------------

test('empty GET path returns the first route as default', function () {
	$route = new Route([
		'GET' => [
			'home' => ['action' => 'home', 'accept' => 1, 'acl' => 0],
		],
	], '/');

	$result = $route->map('GET', '/');

	expect($result)->toBeInstanceOf(Routing::class);
	expect($result->isDefault)->toBeTrue();
	expect($result->action)->toBe('home');
	expect($result->params)->toBe([]);
});

test('empty path on non-GET method does not use default route', function () {
	$route = new Route([
		'POST' => [
			'submit' => ['action' => 'submit', 'accept' => 1, 'acl' => 0],
		],
	], '/');

	expect($route->map('POST', '/'))->toBeNull();
});

// ---------------------------------------------------------------------------
// Route params (regex capture groups)
// ---------------------------------------------------------------------------

test('map extracts named params from regex routes', function () {
	$route = new Route([
		'GET' => [
			'users/(?<id>\d+)' => ['action' => 'users.show', 'accept' => 1, 'acl' => 0],
		],
	], '/');

	$result = $route->map('GET', '/users/42');

	expect($result)->not->toBeNull();
	expect($result->action)->toBe('users.show');
	expect($result->params)->toBe(['id' => '42']);
});

test('map returns null when regex does not match', function () {
	$route = new Route([
		'GET' => [
			'users/(?<id>\d+)' => ['action' => 'users.show', 'accept' => 1, 'acl' => 0],
		],
	], '/');

	expect($route->map('GET', '/users/abc'))->toBeNull();
});

// ---------------------------------------------------------------------------
// Base path
// ---------------------------------------------------------------------------

test('base path is stripped before matching', function () {
	$route = new Route([
		'GET' => [
			'users' => ['action' => 'users.list', 'accept' => 1, 'acl' => 0],
		],
	], '/api/');

	$result = $route->map('GET', '/api/users');

	expect($result)->not->toBeNull();
	expect($result->action)->toBe('users.list');
});

test('base path with dot is escaped in regex', function () {
	$route = new Route([
		'GET' => [
			'items' => ['action' => 'items.list', 'accept' => 1, 'acl' => 0],
		],
	], '/api/v2.0/');

	// '/api/v2X0/items' should NOT match because the dot is escaped.
	expect($route->map('GET', '/api/v2X0/items'))->toBeNull();

	// '/api/v2.0/items' should match.
	$result = $route->map('GET', '/api/v2.0/items');
	expect($result)->not->toBeNull();
	expect($result->action)->toBe('items.list');
});

// ---------------------------------------------------------------------------
// Base path validation
// ---------------------------------------------------------------------------

test('constructor throws when base does not start with slash', function () {
	expect(fn() => new Route([], 'api/'))->toThrow(\Exception::class);
});

test('constructor throws when base does not end with slash', function () {
	expect(fn() => new Route([], '/api'))->toThrow(\Exception::class);
});

test('constructor accepts base that starts and ends with slash', function () {
	expect(fn() => new Route([], '/api/'))->not->toThrow(\Exception::class);
});

// ---------------------------------------------------------------------------
// Trailing slash handling
// ---------------------------------------------------------------------------

test('trailing slash is not trimmed before matching', function () {
	$route = new Route([
		'GET' => [
			'users' => ['action' => 'users.list', 'accept' => 1, 'acl' => 0],
		],
	], '/');

	expect($route->map('GET', '/users/'))->toBeNull();
});

// ---------------------------------------------------------------------------
// Default constructor args
// ---------------------------------------------------------------------------

test('default base is a single slash', function () {
	// With the default base '/', route URL keys should not repeat the
	// leading slash (matching becomes base + url), so a bare path segment
	// is used here instead of '/users'.
	$route = new Route([
		'GET' => [
			'users' => ['action' => 'users.list', 'accept' => 1, 'acl' => 0],
		],
	]);

	$result = $route->map('GET', '/users');

	expect($result)->not->toBeNull();
	expect($result->action)->toBe('users.list');
});

test('empty routes array and default base return null', function () {
	$route = new Route();

	expect($route->map('GET', '/anything'))->toBeNull();
});
