# AGENTS.md

Project notes (`Hit`).

`heindrich/hit` — a small PHP 8.5 base library (no framework). PSR-4 `Hit\` → `src/`. Each `src/<Module>/` is self-contained and most ship a `README.md` with the authoritative API; read it before using or changing a module.

## Modules

| Namespace | What it does | Entry points |
| --- | --- | --- |
| `Hit\Array` | Array helpers with dot-notation (`get`/`set`/`dot`/`remap`). | `Ray` (static) |
| `Hit\Cache` | Static facade over swappable stores. `Cache::proxy($store)` first. | `Cache`, `Store\{FileStore,RedisStore,NoneStore}` |
| `Hit\Container` | PSR-11 DI container with autowiring + optional reflection cache. | `Container` |
| `Hit\Db` | Typed MySQL query builder over PDO, built around Domains + DTOs. | `Database`, `Dto`, `Query`, `Query\{Get,Select,Insert,Update,Delete,Scalar,Distinct,Listing,Index}` |
| `Hit\Domain` | Table layer wiring `Database` to DTO lifecycle hooks. | `Domain` (extend; set `const TABLE`) |
| `Hit\Router` | Regex router + attribute-based route discovery. | `Route`, `Discovery`, `Routes\Routing` |
| `Hit\Attribute` | Route/ACL attributes for discovery. | `#[Get]` `#[Post]` `#[Put]` `#[Delete]` `#[Acl]` `#[Accept]` |
| `Hit\Middleware` | PSR-15 middleware stack. | `Middleware` (extend), `Runner` |
| `Hit\Validator` | Attribute-driven DTO validation. | `#[Rule(...)]`, `Validator::check($dto)`, `Validation` |
| `Hit\View` | Base view (capture + minify) and HTML/Form/Table/Pager helpers. | `View` (extend), `Component\{Html,Form,Table}`, `Pager` |
| `Hit\Lingo` | File-based i18n static facade. `Lingo::init($lang, $path)` first. | `Lingo`, `Translator` |
| `Hit\Log` | PSR-3 static logging facade. `Log::proxy($logger)` first. | `Log`, `Logger\{Logger,LoggerMemory,LoggerErrorLog}` |
| `Hit\Session` | Static `$_SESSION` wrapper (requires active session). | `Session` |
| `Hit\Util` | Misc helpers. | `Inflector`, `Uuid`, `Later`, `Lldap` |

## Conventions

- PHP 8.5, `declare(strict_types=1)`, tabs for indentation.
- Static-facade modules (`Cache`, `Log`, `Lingo`, `Html`) require a one-time `proxy()`/`init()` before use and throw if used uninitialized.
- DTOs are `readonly`, carry a `const PREFIX`, and are built via `Dto::from([...])` / `->with(...)`.
- Prefer `match` over `if`/`switch` chains; push validation/transformation into value objects (keeps `mago` `kan-defect` low).
- When changing a module, update its `README.md` and the matching `tests/Unit/*Test.php` (Pest).

## Verification

- Tests: `./vendor/bin/pest`
- Lint: `mago lint src/` (auto-fix safe issues: `mago lint src/ --fix --format-after-fix`)
- Static analysis: `mago analyze`
- Config: `mago.toml` (formatter uses tabs, print-width 160). Only `error`-level lints fail; `warning`/`help`/`note` are advisory.
