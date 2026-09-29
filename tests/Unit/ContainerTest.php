<?php

declare(strict_types=1);

namespace Tests\Unit;

use Hit\Cache\Store\StoreInterface;
use Hit\Container\Container;
use Hit\Exception\ContainerException;
use Hit\Exception\NotFoundException;

// --- Test fixtures -------------------------------------------------------

class CntLeaf {}

class CntDep {
	public function __construct(
		public CntLeaf $leaf,
	) {}
}

class CntDefault {
	public function __construct(
		public int $x = 5,
	) {}
}

class CntRequiredPrimitive {
	public function __construct(
		public int $x,
	) {}
}

class CntNoType {
	public function __construct(
		public $x = 'default',
	) {}
}

class CntCircA {
	public function __construct(
		public CntCircB $b,
	) {}
}

class CntCircB {
	public function __construct(
		public CntCircA $a,
	) {}
}

abstract class CntAbstract {}

// --- get(): manual entries ----------------------------------------------

test('set with an object stores it as a singleton instance', function () {
	$container = new Container();
	$service = new CntLeaf();

	$container->set('my.service', $service);

	expect($container->get('my.service'))->toBe($service);
});

test('set with a callable registers a factory', function () {
	$container = new Container();
	$container->set('built', fn(Container $c) => new CntDefault(42));

	$instance = $container->get('built');

	expect($instance)->toBeInstanceOf(CntDefault::class);
	expect($instance->x)->toBe(42);
});

test('factory result is cached as a singleton', function () {
	$container = new Container();
	$calls = 0;
	$container->set('counter', function () use (&$calls) {
		$calls++;

		return new CntLeaf();
	});

	$first = $container->get('counter');
	$second = $container->get('counter');

	expect($first)->toBe($second);
	expect($calls)->toBe(1);
});

// --- get(): autowiring ---------------------------------------------------

test('autowires a class with no constructor', function () {
	$container = new Container();

	expect($container->get(CntLeaf::class))->toBeInstanceOf(CntLeaf::class);
});

test('autowires nested class dependencies', function () {
	$container = new Container();

	$dep = $container->get(CntDep::class);

	expect($dep)->toBeInstanceOf(CntDep::class);
	expect($dep->leaf)->toBeInstanceOf(CntLeaf::class);
});

test('shares the same dependency instance across the graph', function () {
	$container = new Container();

	$leaf = $container->get(CntLeaf::class);
	$dep = $container->get(CntDep::class);

	expect($dep->leaf)->toBe($leaf);
});

test('autowired class is returned as a singleton', function () {
	$container = new Container();

	expect($container->get(CntLeaf::class))->toBe($container->get(CntLeaf::class));
});

test('uses default value for primitive parameters', function () {
	$container = new Container();

	expect($container->get(CntDefault::class)->x)->toBe(5);
});

test('uses default value for untyped parameters', function () {
	$container = new Container();

	expect($container->get(CntNoType::class)->x)->toBe('default');
});

// --- get(): error paths --------------------------------------------------

test('throws when class does not exist', function () {
	new Container()->get('Nope\\Missing');
})->throws(NotFoundException::class);

test('throws when class is not instantiable', function () {
	new Container()->get(CntAbstract::class);
})->throws(ContainerException::class);

test('throws for unresolvable primitive parameter', function () {
	new Container()->get(CntRequiredPrimitive::class);
})->throws(ContainerException::class);

test('detects circular dependencies', function () {
	new Container()->get(CntCircA::class);
})->throws(ContainerException::class, 'Circular dependency detected');

// --- isset() / has() -----------------------------------------------------

test('isset reflects stored instances only', function () {
	$container = new Container();
	$container->set('thing', new CntLeaf());

	expect($container->isset('thing'))->toBeTrue();
	expect($container->isset('missing'))->toBeFalse();
});

test('has is true for instances, definitions and existing classes', function () {
	$container = new Container();
	$container->set('instance', new CntLeaf());
	$container->set('factory', fn() => new CntLeaf());

	expect($container->has('instance'))->toBeTrue();
	expect($container->has('factory'))->toBeTrue();
	expect($container->has(CntLeaf::class))->toBeTrue();
	expect($container->has('Totally\\Missing'))->toBeFalse();
});

// --- caching -------------------------------------------------------------

test('saveCache writes resolved metadata when dirty', function () {
	$cache = $this->createMock(StoreInterface::class);
	$cache->method('get')->willReturn(null);
	$cache
		->expects($this->once())
		->method('set')
		->with(Container::CACHE_KEY, $this->isArray())
		->willReturn(true);

	$container = new Container($cache);
	$container->get(CntLeaf::class);
	$container->saveCache();
});

test('saveCache does nothing when nothing was resolved', function () {
	$cache = $this->createMock(StoreInterface::class);
	$cache->method('get')->willReturn(null);
	$cache->expects($this->never())->method('set');

	new Container($cache)->saveCache();
});

test('builds instances from cached metadata without reflection', function () {
	$meta = [
		CntDep::class => [
			'class' => CntDep::class,
			'dependencies' => ['leaf' => CntLeaf::class],
			'defaults' => [],
		],
		CntLeaf::class => [
			'class' => CntLeaf::class,
			'dependencies' => [],
			'defaults' => [],
		],
	];

	$cache = $this->createMock(StoreInterface::class);
	$cache->method('get')->with(Container::CACHE_KEY)->willReturn($meta);

	$container = new Container($cache);
	$dep = $container->get(CntDep::class);

	expect($dep)->toBeInstanceOf(CntDep::class);
	expect($dep->leaf)->toBeInstanceOf(CntLeaf::class);
});

test('clearCache delegates to the cacher and returns its result', function () {
	$cache = $this->createMock(StoreInterface::class);
	$cache
		->expects($this->once())
		->method('delete')
		->with(Container::CACHE_KEY)
		->willReturn(true);

	expect(new Container($cache)->clearCache())->toBeTrue();
});

test('clearCache returns false without a cacher', function () {
	expect(new Container()->clearCache())->toBeFalse();
});
