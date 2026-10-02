<?php

declare(strict_types=1);

namespace Hit\Router\Routes;

use InvalidArgumentException;
use RuntimeException;

final class RouteTable {
	/** @var array<string, array<string, CompiledRoute>> */
	private array $routes = [];

	public function __construct(
		public readonly string $base,
		CompiledRoute ...$routes,
	) {
		if ($this->base === '' || $this->base[0] !== '/' || $this->base[strlen($this->base) - 1] !== '/') {
			throw new InvalidArgumentException('Base path must start and end with slash');
		}

		foreach ($routes as $route) {
			$definition = $route->definition;
			$methodRoutes = $this->routes[$definition->method] ?? [];
			if (array_key_exists($definition->uri, $methodRoutes)) {
				throw new RuntimeException("Duplicate route: {$definition->method} {$definition->uri}");
			}
			$methodRoutes[$definition->uri] = $route;
			ksort($methodRoutes);
			$this->routes[$definition->method] = $methodRoutes;
		}
	}

	public static function fromArray(mixed $data): self {
		if (!is_array($data) || !is_string($data['base'] ?? null) || !is_array($data['routes'] ?? null) || !array_is_list($data['routes'])) {
			throw new RuntimeException('Invalid route table in cache');
		}

		return new self($data['base'], ...array_map(CompiledRoute::fromArray(...), $data['routes']));
	}

	/** @return iterable<string, RouteDefinition> */
	public function forMethod(string $method): iterable {
		foreach ($this->routes[$method] ?? [] as $uri => $route) {
			yield $uri => $route->definition;
		}
	}

	/** @return iterable<string, CompiledRoute> */
	public function compiledForMethod(string $method): iterable {
		yield from $this->routes[$method] ?? [];
	}

	public function toArray(): array {
		$routes = [];
		foreach ($this->routes as $methodRoutes) {
			foreach ($methodRoutes as $route) {
				$routes[] = $route->toArray();
			}
		}

		return [
			'base' => $this->base,
			'routes' => $routes,
		];
	}
}
