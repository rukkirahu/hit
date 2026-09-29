# Validator

DTO validation using attributes and the static `Validation` helpers.

## Rules

Mark DTO properties with `#[Rule(...)]`:

```php
use Hit\Db\Dto;
use Hit\Validator\Rule;

final readonly class UserDto extends Dto {
    public const PREFIX = 'User';

    public function __construct(
        #[Rule(required: true, maxLength: 50)]
        public string $email = '',
        #[Rule(required: true, maxLength: 100)]
        public string $name = '',
    ) {}
}
```

Rule keys map to static methods on `Validation`. `true` enables a rule without arguments; other values are passed to the method. Arrays are passed as a single argument when the method expects an array, otherwise spread as positional arguments:

```php
#[Rule(maxLength: 50)]           // Validation::maxLength($value, 50)
#[Rule(between: [5, 10])]        // Validation::between($value, [5, 10])
#[Rule(comparison: ['>', 0])]    // Validation::comparison($value, '>', 0)
```

## Validating a DTO

```php
use Hit\Validator\Validator;

$errors = Validator::check(new UserDto(email: '', name: str_repeat('x', 200)));
// ['email' => 'User.email.required', 'name' => 'User.name.maxLength']
```

The returned array is keyed by property name. Each value is `PREFIX.property.rule` for the first failing rule on that property. Empty, non-required properties are skipped automatically.

## Built-in rules

| Method | Purpose |
| --- | --- |
| `required` | Non-null, non-empty string / non-zero scalar. |
| `minLength` / `maxLength` / `between` | String length checks. |
| `email($deep = false)` | Email format (and optionally MX record). |
| `numeric` / `range` | Number checks. |
| `comparison($operator, $operand)` | Compare numeric values. |
| `inList($list)` | Value membership. |
| `ip($type)` | IP address validation. |
| `uuid` | UUID format. |
| `extension($extensions)` | Allowed file extension. |
| `equalTo` / `notEqualTo` | Strict equality. |
