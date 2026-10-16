# Hit

`rukkirahu/hit` — a small PHP 8.5 base library (no framework).

Hit is a collection of self-contained modules covering the common needs of a web
application: routing, DI, a typed MySQL query builder, validation, caching,
i18n, logging, views, and utilities. Each module under `src/<Module>/` is
independent, and most ship a `README.md` with the authoritative API reference.

## Requirements

- PHP 8.5
- Extensions: `curl`, `json`, `openssl`, `pdo`, `ldap`
- [symfony/ldap](https://symfony.com/doc/current/components/ldap.html) (only used by `Hit\Util\Lldap`)

## Installation

```bash
composer require rukkirahu/hit
```

PSR-4 autoload: `Hit\` → `src/`.

## Modules

| Namespace | What it does | Entry points | Docs |
| --- | --- | --- | --- |
| `Hit\Array` | Array helpers with dot-notation (`get`/`set`/`dot`/`remap`). | `Ray` | [README](src/Array/README.md) |
| `Hit\Attribute` | Route attributes and ACL/accept bitmasks. | `#[Get]` `#[Post]` `#[Put]` `#[Delete]`, `Acl`, `Accept` | [Router](src/Router/README.md) |
| `Hit\Cache` | Static facade over swappable stores. | `Cache`, `Store\{FileStore,RedisStore,NoneStore}` | [README](src/Cache/README.md) |
| `Hit\Container` | PSR-11 DI container with autowiring + optional reflection cache. | `Container` | [README](src/Container/README.md) |
| `Hit\Db` | Typed MySQL query builder over PDO, built around Domains + DTOs. | `Database`, `Dto`, `Query`, `Query\{Get,Select,Insert,Update,Delete,Scalar,Distinct,Listing,Index}` | [README](src/Db/README.md) |
| `Hit\Domain` | Table layer wiring `Database` to DTO lifecycle hooks. | `Domain` | [README](src/Domain/README.md) |
| `Hit\Exception` | Shared exceptions. | `ContainerException`, `NotFoundException`, `MiddlewareExhaustedException` | — |
| `Hit\Lingo` | File-based i18n static facade. | `Lingo`, `Translator` | [README](src/Lingo/README.md) |
| `Hit\Log` | PSR-3 static logging facade. | `Log`, `Logger\{Logger,LoggerMemory,LoggerErrorLog}` | — |
| `Hit\Middleware` | PSR-15 middleware stack. | `Middleware`, `Runner` | [README](src/Middleware/README.md) |
| `Hit\Router` | Regex router + attribute-based route discovery with production cache. | `Route`, `Discovery`, `Routes\{RouteDefinition,RouteTable,RouteMatch}` | [README](src/Router/README.md) |
| `Hit\Session` | Static `$_SESSION` wrapper. | `Session` | — |
| `Hit\Util` | Misc helpers. | `Inflector`, `Uuid`, `Later`, `Lldap` | — |
| `Hit\Validator` | Attribute-driven DTO validation. | `#[Rule(...)]`, `Validator`, `Validation` | [README](src/Validator/README.md) |
| `Hit\View` | Base view (capture + minify) and HTML/Form/Table/Pager helpers. | `View`, `Component\{Html,Form,Table}`, `Pager` | [README](src/View/README.md) |

## Conventions

- `declare(strict_types=1)` everywhere; tabs for indentation.
- **Static facades must be initialized once before use** and throw otherwise:
  - `Cache::proxy(new FileStore($dir))`
  - `Log::proxy($psr3Logger)`
  - `Lingo::init($lang, $path)`
  - `Html::init($baseurl, $isRouteAllowed)`
  - `Session::init($name)` (starts the session)
- **DTOs** are `readonly`, carry a `const PREFIX`, and are built via
  `Dto::from([...])` / `->with(...)`.
- **Routes** live on Action classes via attributes:

  ```php
  #[Get('users/(?<id>\d+)', accept: Accept::HTML, acl: Acl::USER)]
  final class GetUserAction {}
  ```

- ACL and Accept are bitmasks: `Acl::NONE/GUEST/USER/OP/MANAGER/ADMIN`,
  `Accept::HTML/HTMX/FORMDATA`.

## Quick taste

```php
use Hit\Db\Database;
use Hit\Db\Dto;
use Hit\Db\Query\Select;
use Hit\Domain\Domain;
use Hit\Validator\{Rule, Validator};

class UserDomain extends Domain {
    public const TABLE = 'users';
}

final readonly class User extends Dto {
    public const PREFIX = 'U';

    public function __construct(
        public int $id = 0,
        #[Rule(required: true, maxLength: 50)]
        public string $name = '',
    ) {}
}

$db = new Database(host: 'localhost', username: 'app', password: '***', database: 'app');
$users = new UserDomain($db);

$rows = new Select($users, new User())
    ->where(['name' => 'Alice'])
    ->order(['name' => 'ASC'])
    ->limit([0, 20])
    ->exec();                    // User[]

$errors = Validator::check(new User());  // ['name' => 'U.name.required']
```

See the per-module READMEs for the full APIs.

## Development

```bash
composer install

./vendor/bin/pest        # tests
mago lint src/           # lint (safe fixes: --fix --format-after-fix)
mago analyze             # static analysis
```

Tooling config lives in `mago.toml` (tabs, print-width 160; only `error`-level
lints fail) and `phpunit.xml`. Tests use [Pest](https://pestphp.com) under
`tests/Unit`.

## License

MIT — see [LICENSE](LICENSE).
