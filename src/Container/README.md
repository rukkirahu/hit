# Container

Lightweight PSR-11 dependency injection container with autowiring and optional cache metadata.

## Usage

```php
use Hit\Container\Container;

$container = new Container();

// Autowiring — resolves constructor dependencies via reflection
$service = $container->get(MyService::class);

// Register a factory
$container->set('my.service', fn(Container $c) => new MyService($c->get(Dep::class)));

// Register a pre-built instance
$container->set('my.instance', new MyService());

// Check availability
$container->has('my.service');   // true if registered or autowirable
$container->isset('my.instance'); // true if an instance is already stored
```

## Caching

Pass a `StoreInterface` to cache reflection metadata so the constructor graph is resolved once and then rebuilt from cache:

```php
use Hit\Container\Container;
use Hit\Cache\Store\FileStore;

$container = new Container(new FileStore('/var/cache/container'));
$container->get(MyService::class);
$container->saveCache(); // call at end of bootstrap
```

Clear it on deploy via `$container->clearCache()` or bust the cache externally.

## API

| Method | Description |
| --- | --- |
| `get(string $id): mixed` | Resolve and return an entry (singleton). |
| `has(string $id): bool` | True if registered or autowirable. |
| `set(string $id, callable\|object $factory): void` | Register a factory or pre-built instance. |
| `isset(string $id): bool` | True if an instance is already stored. |
| `saveCache(): void` | Persist resolved metadata to the store. |
| `clearCache(): bool` | Clear cached metadata. |
