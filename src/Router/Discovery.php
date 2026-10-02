<?php

declare(strict_types=1);

namespace Hit\Router;

use Hit\Attribute\RouteAttribute;
use Hit\Cache\Store\StoreInterface;
use Hit\Router\Routes\CompiledRoute;
use Hit\Router\Routes\RouteDefinition;
use Hit\Router\Routes\RouteTable;
use InvalidArgumentException;
use ReflectionAttribute;
use ReflectionClass;
use RuntimeException;

class Discovery {
	private const CACHE_VERSION = 2;
	private const CACHE_TTL = 2_592_000;

	public function __construct(
		private string $rootPath,
		private string $namespace,
		private string $base = '/',
		private ?StoreInterface $cache = null,
		private bool $development = false,
	) {
		if ($this->base === '' || $this->base[0] !== '/' || $this->base[strlen($this->base) - 1] !== '/') {
			throw new InvalidArgumentException('Base path must start and end with slash');
		}
	}

	public function discover(): RouteTable {
		$key = $this->cacheKey();
		if (!$this->development && $this->cache !== null) {
			/** @var array<string, mixed>|null $cached */
			$cached = $this->cache->get($key);
			if ($cached !== null) {
				return RouteTable::fromArray($cached);
			}
		}

		/** @var list<RouteDefinition> $routes */
		$routes = [];
		$this->discoverPath($this->rootPath, 'Action', $this->namespace, $routes);
		$table = new RouteTable($this->base, ...array_map(fn(RouteDefinition $route): CompiledRoute => CompiledRoute::compile($route, $this->base), $routes));

		if (!$this->development && $this->cache !== null && !$this->cache->set($key, $table->toArray(), self::CACHE_TTL)) {
			throw new RuntimeException('Failed to cache routes');
		}

		return $table;
	}

	private function cacheKey(): string {
		return 'router/routes/v' . self::CACHE_VERSION . '/' . sha1($this->rootPath . "\0" . $this->namespace . "\0" . $this->base);
	}

	/** @param list<RouteDefinition> $routes */
	private function discoverPath(string $root, string $name, string $namespace, array &$routes): void {
		$path = $root . '/' . $name;
		if (is_dir($path)) {
			$children = scandir($path, SCANDIR_SORT_ASCENDING);
			if ($children === false) {
				throw new RuntimeException("Unable to scan directory: {$path}");
			}

			$childNamespace = $namespace . '\\' . $name;
			foreach ($children as $child) {
				if (!ctype_upper($child[0])) {
					continue;
				}
				$this->discoverPath($path, $child, $childNamespace, $routes);
			}

			return;
		}

		$pathInfo = pathinfo($path);
		$fileName = $pathInfo['filename'];
		if (($pathInfo['extension'] ?? null) !== 'php' || $fileName === '' || !ctype_upper($fileName[0])) {
			return;
		}

		$className = $namespace . '\\' . $fileName;
		if (!class_exists($className)) {
			throw new RuntimeException("Class not found: {$className}");
		}

		$attributes = new ReflectionClass($className)->getAttributes(RouteAttribute::class, ReflectionAttribute::IS_INSTANCEOF);
		if (count($attributes) > 1) {
			throw new RuntimeException("Action must declare at most one route: {$className}");
		}
		if ($attributes === []) {
			return;
		}

		$route = $attributes[0]->newInstance();
		$routes[] = new RouteDefinition($route->method, $route->uri, $className, $route->accept, $route->acl);
	}
}
