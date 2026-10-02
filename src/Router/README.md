# Router

Regex-based HTTP router with attribute discovery, typed route definitions, request-time ACL filtering, and an optional production cache.

## Components

- **`Discovery`** scans the application's `Action` directory, compiles base-aware route patterns, and returns a `RouteTable`.
- **`RouteTable`** contains matcher-ready routes indexed by method and URI.
- **`RouteDefinition`** describes one route: method, URI, action class, accept mask, and ACL mask.
- **`Route`** directly matches a request method, path, and caller ACL against a prepared `RouteTable`.
- **`RouteMatch`** contains the matched definition and named regex parameters. Its `action`, `accept`, and `acl` properties forward to the definition.

## Action routes

Each Action class can declare at most one route attribute:

```php
namespace App\Action;

use Hit\Attribute\Accept;
use Hit\Attribute\Acl;
use Hit\Attribute\Get;

#[Get('users/(?<id>\d+)', accept: Accept::HTML, acl: Acl::USER)]
final class GetUserAction {}
```

Available attributes are `#[Get]`, `#[Post]`, `#[Put]`, and `#[Delete]`. Their arguments are:

| Argument | Type | Default | Description |
|---|---|---|---|
| `uri` | `string` | required | Regex path relative to the router base path |
| `accept` | `int` | `Accept::HTML` | Accepted content-type bitmask |
| `acl` | `int` | `Acl::NONE` | Required caller ACL bitmask |

A URI must not start with `/`. An empty URI explicitly maps the router's base path:

```php
#[Get('')]
final class HomeAction {}
```

Declaring multiple route attributes on one Action throws `RuntimeException`. Defining the same method and URI on multiple Actions also throws.

## Discovery

```php
use Hit\Router\Discovery;

$table = new Discovery(
	rootPath: __DIR__ . '/src',
	namespace: 'App',
	base: '/api/',
	cache: $cache,
	development: false,
)->discover();
```

`rootPath` is the parent directory of `Action`, and `namespace` is the namespace corresponding to that root. Directories and PHP class files beneath `Action` must start with an uppercase letter. The base path defaults to `/` and must start and end with `/`.

### Cache behavior

Development mode always scans and reflects the source tree. It does not read or write cache entries.

Production mode reads one complete, versioned route-table cache entry containing the final regex patterns. A cache hit performs no filesystem scan, reflection, pattern construction, or regex validation. On a miss, Discovery scans all Actions, compiles and validates their patterns, and writes the matcher-ready table with a 30-day TTL. Passing `null` as the cache store disables caching.

The cache key includes the discovery root, namespace, and base path, allowing applications with different routing configurations to share a store safely.

## Matching

```php
use Hit\Attribute\Acl;
use Hit\Router\Route;

$router = new Route($table);
$match = $router->map('GET', '/api/users/42', Acl::USER);

$match?->action;       // App\Action\GetUserAction
$match?->params;       // ['id' => '42']
$match?->accept;       // forwarded from RouteDefinition
$match?->acl;          // forwarded from RouteDefinition
$match?->route->uri;   // users/(?<id>\d+)
```

`map()` returns `null` when the method or path does not match, or when the caller lacks the required ACL.

Public routes use `Acl::NONE` and match regardless of the caller ACL. Protected routes match when the caller and route masks overlap:

```php
$routeAcl === Acl::NONE || ($callerAcl & $routeAcl) !== 0
```

The base path must start and end with `/`. Route URIs are appended directly to it, so `users` under `/api/` matches `/api/users`.

## API

### `Discovery::discover(): RouteTable`

Returns the discovered or cached matcher-ready route table. Discovery validates the base path and compiles route regexes before caching.

### `new Route(RouteTable $routes)`

Creates a matcher over the prepared table without rebuilding or revalidating route patterns.

### `Route::map(string $method, string $path, int $acl = Acl::NONE): ?RouteMatch`

Matches a request using the caller's ACL.

### `RouteDefinition` properties

| Property | Type |
|---|---|
| `method` | `string` |
| `uri` | `string` |
| `action` | `string` |
| `accept` | `int` |
| `acl` | `int` |

### `RouteMatch` properties

| Property | Type | Description |
|---|---|---|
| `route` | `RouteDefinition` | Canonical matched definition |
| `params` | `array` | Named regex captures |
| `action` | `string` | Forwarded from `route` |
| `accept` | `int` | Forwarded from `route` |
| `acl` | `int` | Forwarded from `route` |

## Testing

```bash
vendor/bin/pest tests/Unit/RouteTest.php tests/Unit/DiscoveryTest.php
```
