# Domain

Abstract domain layer that wires `Database` instances to DTO lifecycle hooks.

## Usage

```php
use Hit\Domain\Domain;
use Hit\Db\Dto;

class UserDomain extends Domain {
    public const TABLE = 'users';

    public function beforeInsert(Dto $dto): Dto|false {
        // validate / mutate before insert
        return $dto;
    }

    public function afterInsert(Dto $dto): void {
        // post-insert side effects
    }
}

$domain = new UserDomain($database);
```

## Lifecycle hooks

| Hook | Called |
| --- | --- |
| `beforeInsert(Dto): Dto\|false` | Before insert. Return `false` to cancel. |
| `afterInsert(Dto): void` | After successful insert. |
| `afterNoInsert(Dto, ?ErrorCode): bool` | After a cancelled/failed insert. |
| `beforeUpdate(Dto): Dto\|false` | Before update. |
| `afterUpdate(Dto): void` | After successful update. |
| `afterNoUpdate(Dto, ?ErrorCode): bool` | After a cancelled/failed update. |
| `afterDelete(Dto, int): void` | After delete, with affected row count. |
| `afterNoDelete(Dto, ?ErrorCode): bool` | After a failed delete. |

## ErrorCode

`ErrorCode` is a simple value object carrying a `code` and `message`. Override `parsePdoError()` to map database-specific errors into domain error codes.
