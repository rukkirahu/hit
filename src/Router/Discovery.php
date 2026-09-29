<?php

declare(strict_types=1);

namespace Hit\Router;

use Hit\Attribute\Acl;
use Hit\Attribute\RouteAttribute;
use Hit\Cache\Store\StoreInterface;
use ReflectionClass;
use RuntimeException;

/**
 * Class Discovery
 *
 * Scans a directory tree for Action classes decorated with route attributes
 * (Get/Post/Put/Delete + Acl) and builds a route table compatible with
 * Hit\Router\Route::init(). Results are cached by mtime to avoid re-reflecting
 * unchanged classes on every run.
 *
 * @package Hit\Router
 */
// @mago-expect lint:kan-defect
class Discovery {
	/** @var array<string, array<string, array{action: string, accept: int, acl: int}>> */
	private array $routes = [];

	/**
	 * Per-class cache: file mtime plus the routes that class contributed.
	 * Reusing the stored routes (rather than just the timestamp) lets us
	 * skip re-reflection of unchanged files without losing their route data.
	 * @var array<string, array{ts: int, routes: list<array{method: string, url: string, route: array{action: string, accept: int, acl: int}}>}>
	 */
	private array $actions = [];

	public function __construct(
		private string $rootPath,
		private string $namespace,
		private ?StoreInterface $cache = null,
		private bool $development = false,
	) {
		$routes = [];
		if (!$this->development && $this->cache !== null) {
			// Load routes from cache
			$routes = $this->cache->get('routes');
			if (is_array($routes) && $routes !== []) {
				foreach (['GET', 'POST', 'PUT', 'DELETE'] as $method) {
					if (!is_array($routes[$method] ?? null)) {
						throw new RuntimeException('Invalid routes format in cache');
					}
					// @mago-expect analysis:property-type-coercion
					$this->routes[$method] = $routes[$method];
				}
			}
		}
		if ($this->routes === []) {
			$cachedActions = $this->cache?->get('actions') ?? [];
			/** @var array<string, array{ts: int, routes: list<array{method: string, url: string, route: array{action: string, accept: int, acl: int}}>}> $cachedActions */
			$this->actions = is_array($cachedActions) ? $cachedActions : [];
			$this->discovery($this->rootPath, 'Action', $this->namespace);

			foreach ($this->routes as $method => $routes) {
				ksort($routes);
				$this->routes[$method] = $routes;
			}
			if (!$this->cache?->set('routes', $this->routes, 2_592_000 /* 30 days */)) {
				throw new RuntimeException('Failed to cache routes');
			}
			if (!$this->cache?->set('actions', $this->actions, 2_592_000 /* 30 days */)) {
				throw new RuntimeException('Failed to cache actions');
			}
		}
	}

	public function getRoutes(): array {
		return $this->routes;
	}

	public function getAclFilterRoutes(int $acl): array {
		return array_map(static fn($routes) => array_filter($routes, static fn($r) => $r['acl'] === Acl::NONE || ($acl & $r['acl']) !== 0), $this->routes);
	}

	public function getActions(): array {
		return $this->actions;
	}

	private function discovery(string $root, string $name, string $nameSpace): void {
		$fullpath = $root . '/' . $name;

		// Directories are scanned recursively
		if (is_dir($fullpath)) {
			$subPaths = scandir($fullpath, SCANDIR_SORT_NONE);
			if ($subPaths === false) {
				return;
			}

			$ns = $nameSpace . '\\' . $name;
			foreach ($subPaths as $subPath) {
				// Only process directories that start with an uppercase letter
				$ord = ord($subPath[0]);
				if ($ord >= 65 && $ord <= 90) {
					$this->discovery($fullpath, $subPath, $ns);
				}

				// Scan all files and folders within this directory
			}

			return;
		}

		// discover action attributes
		$pathInfo = pathinfo($fullpath);
		$extension = $pathInfo['extension'] ?? null;
		$fileName = $pathInfo['filename'] ?: null;

		// If this is a PHP file starting with an uppercase letter, we assume it's a class.
		if ($extension === 'php' && $fileName && ucfirst($fileName) === $fileName) {
			$className = $nameSpace . '\\' . $fileName;
			$ts = filemtime($fullpath) ?: 0;

			// Same file, same timestamp: reuse the previously discovered routes
			// instead of re-reflecting, but still register them for this run.
			$cached = $this->actions[$className] ?? null;
			if (!$this->development && $cached !== null && $cached['ts'] === $ts) {
				foreach ($cached['routes'] as $entry) {
					$this->routes[$entry['method']][$entry['url']] = $entry['route'];
				}

				return;
			}

			if (!class_exists($className)) {
				throw new RuntimeException("Class not found: {$className}");
			}

			$ref = new ReflectionClass($className);
			$attributes = $ref->getAttributes();

			// Two passes: Acl can be declared in any order relative to the
			// route attributes, and a class may declare multiple routes
			// (Get/Post/Put/Delete are IS_REPEATABLE).
			$acl = Acl::NONE;
			foreach ($attributes as $attribute) {
				$target = $attribute->newInstance();
				if ($target instanceof Acl) {
					$acl = $target->acl;
				}
			}

			$discovered = [];
			foreach ($attributes as $attribute) {
				$target = $attribute->newInstance();
				if ($target instanceof RouteAttribute) {
					// Route keys are relative to the base path (Route matches
					// against `base . key` and base ends with '/'), so a
					// leading slash in the attribute URI is a mistake.
					if (str_starts_with($target->uri, '/')) {
						throw new RuntimeException("Route URI must be relative (no leading slash): '{$target->uri}' in {$className}");
					}
					$route = ['action' => $className, 'accept' => $target->accept, 'acl' => $acl];
					$this->routes[$target->method][$target->uri] = $route;
					$discovered[] = ['method' => $target->method, 'url' => $target->uri, 'route' => $route];
				}
			}

			$this->actions[$className] = ['ts' => $ts, 'routes' => $discovered];
		}
	}
}
