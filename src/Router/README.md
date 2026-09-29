# Router

Regex-based HTTP router. Matches a request method + path against a route table and returns a `Routing` result with the matched action, extracted parameters, and metadata.

## Components

- **`Route`** — matches a method/path against a route table. Instantiate with a route table and base path, then call `map()`.
- **`Routing`** — immutable result object returned by `map()`. Carries the action name, captured params, default flag, and accept/acl metadata.
- **`Discovery`** — builds a route table automatically by scanning a directory of Action classes decorated with route attributes (`#[Get]`, `#[Post]`, `#[Put]`, `#[Delete]`, `#[Acl]`).
- **`RoutesInterface`** — legacy interface for manually declaring a route table as class constants. Superseded by `Discovery` for most use cases.

## Route table format

Regardless of how it's built (manually or via `Discovery`), a route table has this shape:

```php
[
    'GET' => [
        'users' => ['action' => 'users.list', 'accept' => 1, 'acl' => 0],
        'users/(?<id>\d+)' => ['action' => 'users.show', 'accept' => 1, 'acl' => 0],
    ],
    'POST' => [
        'users' => ['action' => 'users.create', 'accept' => 1, 'acl' => 1],
    ],
]
```

- **Key** — URL pattern (regex), **relative to the base path** (no leading slash). The full match target is `base . key`. Use named capture groups for parameters: `(?<id>\d+)`.
- **Value** — array with `action` (string), `accept` (int bitmask), `acl` (int bitmask).

> The base path must start **and** end with `/` (default `'/'`), so keys are relative. A key `users` under base `/api/` matches `/api/users`; a leading-slash key like `/users` will never match.

## Usage

### Option 1: Attribute-based discovery (recommended)

Decorate Action classes with route attributes:

```php
namespace App\Action;

use Hit\Attribute\Get;
use Hit\Attribute\Acl;

#[Get('users/(?<id>\d+)')]
#[Acl(Acl::USER)]
class ShowUser {
    // ...
}
```

Build the route table by scanning the `Action` directory:

```php
use Hit\Router\Discovery;
use Hit\Router\Route;

$discovery = new Discovery(
    rootPath: __DIR__ . '/src',      // parent of the "Action" directory
    namespace: 'App',                // base namespace matching the directory
    cache: $cacher,                  // Hit\Cache\Store\StoreInterface, or null to skip caching
    development: false,              // true disables the mtime-based skip cache
);

$route = new Route($discovery->getRoutes(), '/api/');
$routing = $route->map('GET', '/api/users/42');
```

`Discovery` caches its output (`'routes'` and `'actions'` cache keys, 30-day TTL) so the filesystem/reflection scan only runs once per deploy. Unchanged files (by mtime) are skipped on subsequent scans, but their previously-discovered routes are still included in the result.

#### Available attributes

| Attribute | Target | Params |
|---|---|---|
| `Hit\Attribute\Get` | class | `uri`, `accept`, `acl` |
| `Hit\Attribute\Post` | class | `uri`, `accept`, `acl` |
| `Hit\Attribute\Put` | class | `uri`, `accept`, `acl` |
| `Hit\Attribute\Delete` | class | `uri`, `accept`, `acl` |
| `Hit\Attribute\Acl` | class (repeatable) | `acl` bitmask, e.g. `Acl::USER` |

Route `uri`s must be **relative** (no leading slash) since they are matched relative to the base path; `Discovery` throws a `RuntimeException` if a URI starts with `/`.

`Get`/`Post`/`Put`/`Delete` are `IS_REPEATABLE` — a single Action class can declare multiple routes (e.g. both `#[Get(...)]` and `#[Post(...)]`). `Acl` is read independently of route attribute order and defaults to `Acl::NONE` (public) if omitted.

Filter a route table by an ACL bitmask (e.g. based on the current user's roles):

```php
$discovery->getAclFilterRoutes(Acl::USER | Acl::ADMIN);
```

Routes with `acl === Acl::NONE` are always included (public); others are included only if `$acl & $route['acl']` is non-zero.

### Option 2: Manual route table

Implement `RoutesInterface` (or just build the array yourself):

```php
use Hit\Router\Routes\RoutesInterface;

class AppRoutes implements RoutesInterface {
    const BASE = '/';
    const GET = [
        'users' => ['action' => 'users.list', 'accept' => 1, 'acl' => 0],
    ];
    const POST = [];
    const PUT = [];
    const DELETE = [];
}

$route = new Route(['GET' => AppRoutes::GET, 'POST' => AppRoutes::POST], AppRoutes::BASE);
```

### Matching a request

```php
$routing = $route->map('GET', '/users/42');
// $routing->action    → 'users.show'
// $routing->params    → ['id' => '42']
// $routing->isDefault → false
// $routing->accept    → 1
// $routing->acl       → 0
```

### Default route

When a `GET` request path equals the base path (nothing left after stripping the base), the first route in the `GET` table is returned with `isDefault: true` and empty params. This provides a fallback for the site root.

### Base path

The base path must start and end with `/` (the constructor throws otherwise). It is stripped from the request path before matching and `preg_quote`-d when building the match regex, so metacharacters like `.` are treated literally. Route keys are relative to it.

```php
$route = new Route($routes, '/api/v2.0/');
$route->map('GET', '/api/v2.0/users'); // matches the 'users' route
```

## API

### `new Route(array $routes = [], string $base = '/')`

Construct a router with a route table (keyed by HTTP method) and base path. `$base` must start and end with `/` (throws `Exception` otherwise), and route keys are relative to it.

### `Route::map(string $method, string $path): ?Routing`

Match a request. Returns a `Routing` on match, `null` on no match. Throws `Exception` if a route pattern produces an invalid regex.

### `Routing` properties

| Property | Type | Description |
|---|---|---|
| `action` | `string` | Action identifier for the matched route |
| `params` | `array` | Named parameters extracted from regex capture groups |
| `isDefault` | `bool` | True if matched via the default-route fallback (empty GET path) |
| `accept` | `int` | Accept bitmask from the route config |
| `acl` | `int` | ACL bitmask from the route config |

### `new Discovery(string $rootPath, string $namespace, ?StoreInterface $cache = null, bool $development = false)`

Scans `$rootPath . '/Action'` recursively for PHP classes (directories/files must start with an uppercase letter) and reflects their route/ACL attributes.

- **`getRoutes(): array`** — the discovered route table, same shape as above.
- **`getAclFilterRoutes(int $acl): array`** — route table filtered to routes accessible with the given ACL bitmask (public routes always included).
- **`getActions(): array`** — per-class cache metadata (file mtime + contributed routes), primarily for cache inspection/debugging.

## Requirements

PHP 8.4+ (the `Hit\Attribute\RouteAttribute` interface uses property hooks).

## Testing

```bash
vendor/bin/pest tests/Unit/RouteTest.php tests/Unit/DiscoveryTest.php
```

`DiscoveryTest.php` uses fixture Action classes under `tests/Fixtures/Action/` and an in-memory `StoreInterface` fake to exercise directory scanning, attribute reflection, ACL filtering, and cache-skip behavior without touching a real cache backend.
