<?php

declare(strict_types=1);

namespace Hit\Router\Routes;

use RuntimeException;

final class RouteTable {
	/** @var array<string, array<string, RouteDefinition>> */
	private array $routes = [];

	public function __construct(RouteDefinition ...$routes) {
		foreach ($routes as $route) {
			$methodRoutes = $this->routes[$route->method] ?? [];
			if (array_key_exists($route->uri, $methodRoutes)) {
				throw new RuntimeException("Duplicate route: {$route->method} {$route->uri}");
			}
			$methodRoutes[$route->uri] = $route;
			ksort($methodRoutes);
			$this->routes[$route->method] = $methodRoutes;
		}
	}

	public static function fromArray(mixed $data): self {
		if (!is_array($data) || !array_is_list($data)) {
			throw new RuntimeException('Invalid route table in cache');
		}

		return new self(...array_map(RouteDefinition::fromArray(...), $data));
	}

	/** @return iterable<string, RouteDefinition> */
	public function forMethod(string $method): iterable {
		yield from $this->routes[$method] ?? [];
	}

	public function toArray(): array {
		$data = [];
		foreach ($this->routes as $routes) {
			foreach ($routes as $route) {
				$data[] = $route->toArray();
			}
		}

		return $data;
	}
}
