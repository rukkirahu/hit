# Ray

PHP array helper methods, inspired by CakePHP, Laravel, and others.

A small, dependency-free library that provides static utilities for working
with arrays using "dot" notation.


## Usage

```php
use Hit\Array\Ray;

$data = ['user' => ['name' => 'Ada', 'roles' => ['admin']]];

Ray::get($data, 'user.name');        // 'Ada'
Ray::has($data, 'user.roles');       // true
Ray::set($data, 'user.active', true);
Ray::remove($data, ['user.roles']);

Ray::dot(['a' => ['b' => 1]]);       // ['a.b' => 1]
Ray::undot(['a.b' => 1]);            // ['a' => ['b' => 1]]
```

## Methods

| Method | Description |
| --- | --- |
| `dot($array, $prepend = '')` | Flatten a nested array into dot-notation keys. |
| `undot($array)` | Expand a dot-notation array back into a nested array. |
| `get($array, $key, $default = null)` | Get a value using dot notation. |
| `getStr($array, $key, $default = '')` | Get a value cast to `string`. |
| `set(&$data, $keys, $value = null)` | Set a value using dot notation. |
| `has($array, $keys)` | Check whether one or more keys exist. |
| `remove(&$array, $keys)` | Remove keys, supporting dot notation. |
| `only($array, $keys)` | Get a subset of the array by keys. |
| `isAssoc($array)` | Determine if an array is associative. |
| `isList($array)` | Determine if an array is a list. |
| `query($array)` | Convert an array into an RFC 3986 query string. |
| `remap($array, $map, $ignoreNullValues = true)` | Build a new array by remapping/renaming keys. |
