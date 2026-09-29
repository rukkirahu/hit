<?php

declare(strict_types=1);

namespace Hit\Router;

use Exception;
use Hit\Router\Routes\Routing;

/**
 * Class Route
 * @package Hit\Router
 */
class Route {
	public function __construct(
		/** @var array<string, array<string, array{action: string, accept: int, acl: int}>> */
		private array $routes = [],
		private string $base = '/',
	) {
		if ($this->base[0] !== '/' || $this->base[strlen($this->base) - 1] !== '/') {
			throw new Exception('Base path must start and end with slash');
		}
	}

	public function map(string $method, string $path): ?Routing {
		/** @var array<string, array{action: string, accept: int, acl: int}> $routes */
		$routes = $this->routes[$method] ?? [];

		if ($routes === []) {
			return null;
		}

		if (str_starts_with($path, $this->base)) {
			$path = substr($path, strlen($this->base));
		}

		// if url is empty, match always and use default route
		if ($path === '' && $method === 'GET') {
			$first = $routes[array_key_first($routes)];

			return new Routing(...$first, params: [], isDefault: true);
		}

		$escapedBase = preg_quote($this->base, '/');

		foreach ($routes as $url => $route) {
			$matches = [];
			$pattern = '/^' . $escapedBase . str_replace('/', '\/', $url) . '$/';
			$preg = preg_match($pattern, $this->base . $path, $matches);

			if ($preg === 1) {
				return new Routing(...$route, params: array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY), isDefault: false);
			}
			if ($preg === false) {
				throw new Exception('Incorrect routing regexp: ' . $url);
			}
		}

		return null;
	}
}
