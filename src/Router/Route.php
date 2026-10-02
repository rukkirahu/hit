<?php

declare(strict_types=1);

namespace Hit\Router;

use Hit\Attribute\Acl;
use Hit\Router\Routes\RouteMatch;
use Hit\Router\Routes\RouteTable;
use RuntimeException;

class Route {
	public function __construct(
		private RouteTable $routes,
	) {}

	public function map(string $method, string $path, int $acl = Acl::NONE): ?RouteMatch {
		if (!str_starts_with($path, $this->routes->base)) {
			return null;
		}

		foreach ($this->routes->compiledForMethod($method) as $route) {
			$definition = $route->definition;
			if ($definition->acl !== Acl::NONE && ($acl & $definition->acl) === 0) {
				continue;
			}

			$matches = [];
			$matched = preg_match($route->pattern, $path, $matches);
			if ($matched === 1) {
				return new RouteMatch($definition, array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY));
			}
			if ($matched === false) {
				throw new RuntimeException("Invalid route pattern: {$definition->uri}");
			}
		}

		return null;
	}
}
