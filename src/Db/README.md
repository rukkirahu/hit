# Hit\Db — MySQL Query Builder

A small, typed query builder for MySQL on top of PDO. Queries are built around
**Domains** (tables) and **DTOs** (typed rows), producing parameterized SQL that
is executed through a thin `Database` wrapper.

## Requirements

- PHP 8.4+, `ext-pdo` (MySQL)
- Installed via Composer (PSR-4 autoload under the `Hit\` namespace)

## Concepts

- **`Database`** — wraps a `PDO` connection; handles `prepare`/`exec`, transactions
  (`begin`/`commit`/`rollback`) and `lastInsertId`.
- **`Domain`** — declares a table (`const TABLE`) and lifecycle hooks
  (`beforeInsert`, `afterUpdate`, `afterNoDelete`, ...).
- **`Dto`** — an immutable (`readonly`) row object with a `PREFIX` and column
  properties; built via `Dto::from([...])` / `with(...)`.
- **`Query`** — fluent builder: `field()`, `where()`, `order()`, `group()`,
  `limit()`, `join()` / `on()`.

## Quick start

```php
use Hit\Db\Database;
use Hit\Db\Dto;
use Hit\Domain\Domain;
use Hit\Db\Query\{Get, Select, Insert, InsertChunks, Update, Delete, Scalar, Distinct, Listing, Index};

// 1. Connect
$db = new Database(host: 'localhost', username: 'root', password: '', database: 'app');

// 2. Define a domain (table) and a DTO (row)
class UserDomain extends Domain {
    public const TABLE = 'users';
}

readonly class User extends Dto {
    public const PREFIX = 'U';
    public function __construct(
        public int $id = 0,
        public string $name = '',
    ) {}
}

$domain = new UserDomain($db);
```

## Nested DTOs

Use `Dto::match()` in a constructor to accept `null`, an associative array, or an existing instance for a nested DTO property. Array input is passed to the nested DTO's `from()` method.

```php
readonly class Address extends Dto {
    public function __construct(public string $city = '') {}

    public function with(...$args): self {
        return new self(city: (string) ($args['city'] ?? $this->city));
    }
}

readonly class User extends Dto {
    public ?Address $address;

    public function __construct(
        public int $id = 0,
        mixed $address = null,
    ) {
        $this->address = Address::match($address);
    }
}

$user = new User(address: ['city' => 'Tallinn']);
```

Unsupported values resolve to `null`.

## Reading

```php
// Single row by primary id
$user = new Get($domain, new User(id: 5))->exec();          // returns User

// Multiple rows
$users = new Select($domain, new User())
    ->where(['name' => 'Alice'])
    ->order(['name' => 'ASC'])
    ->limit([0, 20])
    ->exec();                                                // returns User[]

// Single value (COUNT, MAX, MIN, SUM, AVG, ...) — exactly one field, returns int|float
$total  = $domain->scalar()->field(['count' => new Expression('COUNT(*)')])->exec();
$latest = $domain->scalar()->field(['max' => new Expression('MAX(`id`)')])->exec();
$mean   = $domain->scalar(new User())
    ->field(['avg' => new Expression('AVG(`U`.`age`)')])
    ->where(['name' => 'Alice'])
    ->exec();                                                // float

// Distinct values, no dto — one field gives list<string>, several give list<list<string>>
$names = $domain->distinct()->field(['name'])->order(['name' => 'ASC'])->exec();
$pairs = $domain->distinct()->field(['name', 'id'])->exec();

// First field => value; one remaining field returns a scalar, several return a tuple
$names = new Listing($domain, new User())->field(['id', 'name'])->exec();
$contacts = new Listing($domain, new User())->field(['id', 'name', 'email'])->exec();

// id => row/object map (two or more fields)
$index = new Index($domain, new User())->field(['id', 'name'])->exec();
```

## Writing

```php
// Insert — returns the new id (or false)
$id = new Insert($domain, new User(name: 'Bob'))->exec();

// Chunked multi-row insert — returns the total inserted row count (or false)
$rows = $domain->insertChunks($users, 500)->exec();

// Update — returns affected row count (or false)
$rows = new Update($domain, new User(id: $id, name: 'Bobby'))->exec();

// Update by explicit data + conditions
$rows = new Update($domain, new User())
    ->where(['id' => $id])
    ->exec(['name' => 'Robert']);

// Delete — returns bool
new Delete($domain, new User(id: $id))->where(['id' => $id])->exec();
```

`insertChunks()` accepts any `iterable<Dto>` and executes one multi-row statement per chunk. All DTOs in a chunk must produce the same columns after `beforeInsert()`; primary IDs are omitted. The query calls insert lifecycle hooks for each DTO but does not manage transactions, so wrap it with `Database::begin()` / `commit()` / `rollback()` when all chunks must be atomic.

## Building blocks

- `field([...])` — select columns; `['*' => false]` for all, or `['alias' => Expression]`.
- `distinct()` — render `SELECT DISTINCT` (whole-row de-duplication) on any query; `Query\Distinct` sets it by default.
- `where(['col' => $value])` — equality by default; operators via `Sql::parseCondition`; group conditions with `['AND' => [...]]` or `['OR' => [...]]`.
- `join($otherQuery)` then `on(['a.b' => 'c.d'])` — relate domains.
- `Hit\Db\Sql\Expression` — raw SQL fragments (e.g. `COUNT(...)`).
- `Scalar` — `field()` must define exactly one field (usually an `Expression`); with a dto the
  field list is seeded from the dto, so always call `field()` to narrow it. `exec()` returns the
  first cell: `int` for integral results, `float` otherwise, `0` for `NULL`.
- `Hit\Db\query($domain, $dto)` — helper returning a base `Query`.
