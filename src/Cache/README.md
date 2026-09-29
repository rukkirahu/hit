# Cache

Static facade over a swappable `StoreInterface` adapter.

## Setup

```php
use Hit\Cache\Cache;
use Hit\Cache\Store\FileStore;

Cache::proxy(new FileStore('/tmp/cache'));
```

## API

| Method | Description |
| --- | --- |
| `proxy(StoreInterface $instance)` | Register the backing adapter. |
| `get($key)` | Get a value (throws if not initialized). |
| `getStr($key)` | Get as `string` (`''` on miss). |
| `getInt($key)` | Get as `int` (`0` on miss). |
| `getBool($key)` | Get as `bool` (`false` on miss). |
| `getArray($key)` | Get as `array` (`[]` on miss). |
| `set($key, $value, $ttl = 3600)` | Store a value. |
| `delete($key)` | Remove a key. |
| `exists($key)` | Check whether a key is present. |

## Adapters

| Class | Backend |
| --- | --- |
| `FileStore` | Filesystem |
| `RedisStore` | Redis |
| `NoneStore` | No-op (disabled) |

All implement `Hit\Cache\Store\StoreInterface`.
