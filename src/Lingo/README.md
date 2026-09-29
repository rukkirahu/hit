# Lingo

Lightweight file-based i18n library for PHP.
Static translation facade with dev-mode auto-save.

## Setup

```php
use Hit\Lingo\Lingo;

Lingo::init('en', __DIR__ . '/lang');
```

Translation files are PHP arrays named `lingo.{lang}.php` under the given path:

```php
return [
    'hello' => 'Hello, %1',
    'items' => '%1 items',
];
```

## Usage

```php
Lingo::get('hello', 'Ada');  // 'Hello, Ada'
Lingo::get('items', '10');   // '10 items'
```

## API

| Method | Description |
| --- | --- |
| `init($lang, $path)` | Load a language file. |
| `devMode(array $langs)` | Load extra languages and auto-save missing keys on shutdown. |
| `get($key, ...$args)` | Translate a key, replacing `%1`..`%9` with arguments. |
| `getInstance()` | Get the underlying `Translator`. |
| `reset()` | Clear the instance (testing). |

## Dev mode

In dev mode, missing keys are written back to every loaded language file as the key itself, so new copy is surfaced immediately.
