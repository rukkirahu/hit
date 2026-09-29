<?php

declare(strict_types=1);

namespace Hit\Container;

use Hit\Cache\Store\StoreInterface;
use Hit\Exception\ContainerException;
use Hit\Exception\NotFoundException;
use Psr\Container\ContainerInterface;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionUnionType;

// @mago-expect lint:kan-defect
class Container implements ContainerInterface {
	public const CACHE_KEY = 'container';

	/** @var array<string, callable> */
	private array $definitions = [];

	/** @var array<string, mixed> */
	private array $instances = [];

	/** @var array<string, array{class: class-string, dependencies: array<string, string|null>, defaults: array<string, mixed>}> */
	private array $resolvedMeta = [];

	private bool $cacheLoaded = false;
	private bool $cacheDirty = false;

	/** @var array<string, true> Classes currently being resolved (for circular dependency detection) */
	private array $resolving = [];

	public function __construct(
		private ?StoreInterface $cache = null,
	) {}

	/**
	 * Register a service factory or a pre-built instance.
	 *
	 * If $factory is callable (including Closures and invokable classes), it is
	 * stored as a factory and invoked lazily on first `get()`: fn(Container $c) => new Service(...).
	 * If $factory is a non-callable object, it is stored directly as the resolved instance.
	 *
	 * Note: invokable classes (with __invoke) are treated as factories. To register an
	 * invokable instance directly, wrap it in a closure: fn() => $instance.
	 *
	 * @param string $id The class name or identifier
	 * @param callable|object $factory A closure that returns the instance, or a pre-built object instance
	 */
	public function set(string $id, callable|object $factory): void {
		if (is_callable($factory)) {
			$this->definitions[$id] = $factory;
		}
		else {
			$this->instances[$id] = $factory;
		}
	}

	/**
	 * Check if an entry exists in the container.
	 *
	 * @param string $id Identifier of the entry to check.
	 *
	 * @return bool True if the entry exists, false otherwise.
	 */
	public function isset(string $id): bool {
		return isset($this->instances[$id]);
	}

	/**
	 * Find an entry of the container by its identifier and returns it.
	 *
	 * @param string $id Identifier of the entry to look for.
	 *
	 * @throws NotFoundException No entry was found for this identifier.
	 * @throws ContainerException Error while retrieving the entry (circular dependency, build failure, etc).
	 *
	 * @return mixed Entry.
	 */
	public function get(string $id): mixed {
		// Singleton: Return existing instance
		if (array_key_exists($id, $this->instances)) {
			return $this->instances[$id];
		}

		// Manual Definition: Execute factory
		if (isset($this->definitions[$id])) {
			$this->instances[$id] = $this->definitions[$id]($this);

			return $this->instances[$id];
		}

		// Try to resolve class (with cache support)
		return $this->resolve($id);
	}

	/**
	 * Returns true if the container can return an entry for the given identifier.
	 */
	public function has(string $id): bool {
		return isset($this->instances[$id]) || isset($this->definitions[$id]) || class_exists($id);
	}

	/**
	 * Save cache to disk (call at end of bootstrap/request).
	 *
	 * Only writes if new classes were resolved during this request.
	 */
	public function saveCache(): void {
		if ($this->cacheDirty) {
			$this->cache?->set(self::CACHE_KEY, $this->resolvedMeta);
			$this->cacheDirty = false;
		}
	}

	/**
	 * Clear the cache.
	 *
	 * @return bool True if cleared
	 */
	public function clearCache(): bool {
		$this->resolvedMeta = [];
		$this->cacheLoaded = false;
		$this->cacheDirty = false;

		return $this->cache?->delete(self::CACHE_KEY) ?? false;
	}

	/**
	 * Load cache from disk.
	 */
	private function loadCache(): void {
		$this->cacheLoaded = true;
		$this->cacheDirty = false;
		/** @var array<string, array{'class': class-string, 'defaults': array<string, mixed>, 'dependencies': array<string, null|string>}>|null $cache */
		$cache = $this->cache?->get(self::CACHE_KEY);
		if (is_array($cache) && $cache !== []) {
			// Merge: in-memory meta (already resolved this request) wins over cached
			$this->resolvedMeta = array_merge($cache, $this->resolvedMeta);
		}
	}

	/**
	 * Resolve a class using Reflection or cache.
	 */
	private function resolve(string $id): object {
		if (!class_exists($id)) {
			if (interface_exists($id)) {
				throw new NotFoundException("Interface '{$id}' is not bound to an implementation. Use set() to register a concrete instance.");
			}
			throw new NotFoundException("Class or service '{$id}' not found.");
		}

		/** @var class-string $id */

		// Circular dependency detection
		if (isset($this->resolving[$id])) {
			$chain = implode(' -> ', array_keys($this->resolving)) . ' -> ' . $id;
			throw new ContainerException("Circular dependency detected: {$chain}");
		}

		// Try cache first (only load once)
		if (!$this->cacheLoaded) {
			$this->loadCache();
		}

		if (isset($this->resolvedMeta[$id])) {
			return $this->buildFromCache($id);
		}

		// Full Reflection resolve (with circular detection)
		$this->resolving[$id] = true;
		try {
			return $this->resolveWithReflection($id);
		}
		finally {
			unset($this->resolving[$id]);
		}
	}

	/**
	 * Build instance from cached metadata (no Reflection needed).
	 *
	 * @param class-string $id
	 */
	private function buildFromCache(string $id): object {
		$meta = $this->resolvedMeta[$id];
		$dependencies = [];

		foreach ($meta['dependencies'] as $index => $depId) {
			if ($depId === null) {
				// Use cached default value
				$dependencies[$index] = $meta['defaults'][$index];
			}
			else {
				$dependencies[$index] = $this->get($depId);
			}
		}

		// @mago-expect analysis:unknown-class-instantiation
		try {
			$instance = new $id(...$dependencies);
		}
		catch (\Throwable $e) {
			throw new ContainerException("Error while creating service '{$id}': " . $e->getMessage(), 0, $e);
		}

		$this->instances[$id] = $instance;

		return $instance;
	}

	/**
	 * Resolve a class using Reflection and cache the result.
	 *
	 * @param class-string $id
	 */
	private function resolveWithReflection(string $id): object {
		$reflector = new \ReflectionClass($id);

		if (!$reflector->isInstantiable()) {
			throw new ContainerException("Class '{$id}' is not instantiable (abstract or interface).");
		}

		$constructor = $reflector->getConstructor();

		// No constructor? Simple instantiation.
		if ($constructor === null) {
			$this->resolvedMeta[$id] = [
				'class' => $id,
				'dependencies' => [],
				'defaults' => [],
			];
			$this->cacheDirty = true;

			// @mago-expect analysis:unknown-class-instantiation
			$instance = new $id();
			$this->instances[$id] = $instance;

			return $instance;
		}

		// Resolve dependencies and build metadata
		$dependencies = [];
		$depIds = [];
		$defaults = [];

		foreach ($constructor->getParameters() as $index => $param) {
			$resolved = $this->resolveParameter($param, $id);
			$dependencies[$param->name] = $resolved['value'];
			$depIds[$param->name] = $resolved['depId'];
			if ($resolved['depId'] === null) {
				$defaults[$param->name] = $resolved['value'];
			}
		}

		// Cache the resolution metadata
		$this->resolvedMeta[$id] = [
			'class' => $id,
			'dependencies' => $depIds,
			'defaults' => $defaults,
		];
		$this->cacheDirty = true;

		try {
			$instance = $reflector->newInstanceArgs($dependencies);
		}
		catch (\Throwable $e) {
			throw new ContainerException("Error while creating service '{$id}': " . $e->getMessage(), 0, $e);
		}

		// newInstanceArgs throws on failure; this guard is for the type checker.
		if ($instance === null) {
			throw new ContainerException("Failed to instantiate '{$id}'");
		}

		$this->instances[$id] = $instance;

		return $instance;
	}

	/**
	 * Resolve a single constructor parameter.
	 *
	 * @return array{value: mixed, depId: string|null}
	 */
	private function resolveParameter(ReflectionParameter $param, string $classId): array {
		$type = $param->getType();

		// No type hint or Union Types (not supported for simplicity)
		if (!$type || $type instanceof ReflectionUnionType) {
			if ($param->isDefaultValueAvailable()) {
				return ['value' => $param->getDefaultValue(), 'depId' => null];
			}
			throw new ContainerException("Cannot resolve parameter '{$param->getName()}' in class '{$classId}'. No type hint or union type.");
		}

		/** @var ReflectionNamedType $type */

		// Primitives (int, string, bool) cannot be autowired unless default value exists
		if ($type->isBuiltin()) {
			if ($param->isDefaultValueAvailable()) {
				return ['value' => $param->getDefaultValue(), 'depId' => null];
			}
			throw new ContainerException(
				"Cannot resolve primitive parameter '{$param->getName()}' (type: {$type->getName()}) in class '{$classId}'. Use set() to define this service manually.",
			);
		}

		// It's a class/interface dependency -> Recursion!
		$depClassName = $type->getName();
		try {
			return ['value' => $this->get($depClassName), 'depId' => $depClassName];
		}
		catch (NotFoundException $e) {
			// Optional dependency?
			if ($param->isOptional()) {
				return ['value' => $param->getDefaultValue(), 'depId' => null];
			}
			throw new ContainerException("Cannot resolve dependency '{$depClassName}' for parameter '{$param->getName()}' in class '{$classId}'.", 0, $e);
		}
	}
}
