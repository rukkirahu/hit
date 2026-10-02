<?php

declare(strict_types=1);

namespace Hit\Router;

use Hit\Attribute\Acl;
use Hit\Router\Routes\CompiledRoute;
use Hit\Router\Routes\RouteMatch;
use Hit\Router\Routes\RouteTable;
use InvalidArgumentException;
use RuntimeException;

class Route {
	/** @var array<string, list<CompiledRoute>> */
	private array $routes = [];

	public function __construct(
		RouteTable $routes,
		private string $base = '/',
	) {
		if ($this->base === '' || $this->base[0] !== '/' || $this->base[strlen($this->base) - 1] !== '/') {
			throw new InvalidArgumentException('Base path must start and end with slash');
		}

		foreach (['GET', 'POST', 'PUT', 'DELETE'] as $method) {
			foreach ($routes->forMethod($method) as $definition) {
				$pattern = '~^' . preg_quote($this->base, '~') . str_replace('~', '\\~', $definition->uri) . '$~';
				if (!$this->isValidPattern($pattern)) {
					throw new InvalidArgumentException("Invalid route pattern: {$definition->uri}");
				}
				$this->routes[$method][] = new CompiledRoute($definition, $pattern);
			}
		}
	}

	private function isValidPattern(string $pattern): bool {
		set_error_handler(static fn(): bool => true);
		try {
			return preg_match($pattern, '') !== false;
		}
		finally {
			restore_error_handler();
		}
	}

	public function map(string $method, string $path, int $acl = Acl::NONE): ?RouteMatch {
		if (!str_starts_with($path, $this->base)) {
			return null;
		}

		foreach ($this->routes[$method] ?? [] as $route) {
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
