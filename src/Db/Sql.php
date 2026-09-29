<?php

declare(strict_types=1);

namespace Hit\Db;

use Hit\Db\Sql\Expression;

// @mago-expect lint:kan-defect
class Sql {
	private static function quote(string $identifier): string {
		return '`' . $identifier . '`';
	}

	private static function validate(string $identifier): void {
		if (preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $identifier) !== 1) {
			throw new \Exception('Invalid identifier: ' . $identifier);
		}
	}

	/**
	 * Quote a table-qualified field name.
	 */
	public static function name(string $table, string|int $field): string {
		if ($field === '') {
			return '';
		}
		if (is_int($field) || is_numeric($field)) {
			return (string) (float) $field;
		}
		if (str_contains($field, '.')) {
			[$t, $f] = explode('.', $field, 2);
			self::validate($t);
			self::validate($f);

			return self::quote($t) . '.' . self::quote($f);
		}
		self::validate($field);

		return $table === '' ? self::quote($field) : self::quote($table) . '.' . self::quote($field);
	}

	/**
	 * Render a value for inlining into SQL. When $asColumn is true a dotted
	 * identifier string (e.g. "acl.user_id") is treated as a column reference;
	 * otherwise every string is rendered as a quoted literal.
	 */
	private static function value(mixed $value, bool $asColumn = true): string {
		if ($value === null) {
			return 'NULL';
		}
		if (is_bool($value)) {
			return $value ? '1' : '0';
		}
		if (is_int($value) || is_float($value)) {
			return (string) $value;
		}
		if ($value instanceof Expression) {
			return $value->sql;
		}
		if (is_string($value)) {
			if ($asColumn && strpos($value, '.')) {
				return self::name('', $value);
			}

			return "'" . str_replace("'", "''", $value) . "'";
		}

		throw new \Exception('Unsupported value type: ' . gettype($value));
	}

	public static function parseCondition(string $key, mixed $value, string $table = '', bool $valuesAsColumns = true): string {
		if (($key === 'AND' || $key === 'OR') && is_array($value)) {
			return '( ' . implode(' ' . $key . ' ', self::buildConditions($value, $table, $valuesAsColumns)) . ' )';
		}

		$parts = explode(' ', trim($key), 2);
		$field = $parts[0];
		$operator = isset($parts[1]) ? strtoupper(trim($parts[1])) : '=';
		$name = self::name($table, $field);

		if ($value === null || $value === 'NULL') {
			return $name . ($operator === '!=' ? ' IS NOT NULL' : ' IS NULL');
		}

		if ($value instanceof Expression) {
			$op = $operator === '!=' ? 'NOT IN' : 'IN';

			return $name . ' ' . $op . ' (' . $value->sql . ')';
		}

		if (is_array($value)) {
			$rendered = array_map(static fn(mixed $v): string => self::value($v, $valuesAsColumns), $value);

			if ($operator === 'BETWEEN') {
				return $name . ' BETWEEN (' . implode(' AND ', $rendered) . ')';
			}

			$op = $operator === '!=' ? 'NOT IN' : 'IN';

			return $name . ' ' . $op . ' (' . implode(',', $rendered) . ')';
		}

		return $name . ' ' . $operator . ' ' . self::value($value, $valuesAsColumns);
	}

	/**
	 * @param array<array-key,mixed> $conditions
	 * @return array<int,string>
	 */
	private static function buildConditions(array $conditions, string $table, bool $valuesAsColumns = true): array {
		$out = [];
		foreach ($conditions as $key => $value) {
			$out[] = self::parseCondition((string) $key, $value, $table, $valuesAsColumns);
		}

		return $out;
	}

	/**
	 * @param array<int|string,mixed> $fields
	 * @return array<string,string>
	 */
	public static function fields(array $fields, string $table = '', string $alias = ''): array {
		if ($fields === ['*' => false]) {
			return [];
		}

		$out = [];
		foreach ($fields as $key => $value) {
			if ($value === false) {
				continue;
			}

			$rendered = $value instanceof Expression ? $value->sql : self::name($table, (string) $value ?: (string) $key);

			if ($alias !== '') {
				$aliasKey = $alias . '.' . $key;
				$out[$aliasKey] = $rendered . ' AS ' . self::quote($aliasKey);
			}
			else {
				$out[(string) $key] = $rendered;
			}
		}

		return $out;
	}

	/**
	 * @param array<int|string,string> $groups
	 * @return array<int,string>
	 */
	public static function groups(array $groups, string $table = ''): array {
		$out = [];
		foreach ($groups as $key => $value) {
			$field = is_string($key) ? $key : $value;
			$out[] = self::name($table, $field);
		}

		return $out;
	}

	/**
	 * @param array<string,string> $orders
	 * @return array<int,string>
	 */
	public static function orders(array $orders, string $table = ''): array {
		$out = [];
		foreach ($orders as $field => $direction) {
			$name = str_contains($field, '`') ? $field : self::name($table, $field);
			$out[] = $name . ' ' . strtoupper($direction);
		}

		return $out;
	}

	/**
	 * @param array<string,string> $on
	 */
	public static function join(string $table, string $alias, array $on, string $type = 'LEFT'): string {
		$conditions = self::buildConditions($on, $alias);

		return ' ' . $type . ' JOIN ' . self::quote($table) . ' AS ' . self::quote($alias) . ' ON ' . implode(' AND ', $conditions);
	}

	/**
	 * @param array<string,string> $data Pre-rendered column => value pairs.
	 */
	public static function insert(array $data, Query $query): string {
		return self::insertRows([$data], $query);
	}

	/**
	 * @param non-empty-list<array<string,string>> $rows Pre-rendered column => value pairs.
	 */
	public static function insertRows(array $rows, Query $query): string {
		$fields = array_keys($rows[0]);
		$columns = array_map(self::quote(...), $fields);
		$values = [];

		foreach ($rows as $row) {
			if (array_keys($row) !== $fields) {
				throw new \Exception('Insert rows must have identical columns');
			}

			$values[] = '(' . implode(', ', array_values($row)) . ')';
		}

		return (
			'INSERT '
			. ($query->ignore ? 'IGNORE ' : '')
			. 'INTO '
			. self::quote($query->table)
			. ' ('
			. implode(', ', $columns)
			. ') VALUES '
			. implode(', ', $values)
		);
	}

	/**
	 * @param array<string,string> $data Pre-rendered column => value pairs.
	 */
	public static function update(array $data, Query $query): string {
		if ($query->Where === null) {
			throw new \Exception('Update requires a WHERE condition');
		}

		$sets = [];
		foreach ($data as $column => $value) {
			$sets[] = self::quote($column) . ' = ' . $value;
		}

		$sql =
			'UPDATE '
			. ($query->ignore ? 'IGNORE ' : '')
			. self::quote($query->table)
			. ' SET '
			. implode(', ', $sets)
			. ' WHERE '
			. implode(' AND ', $query->Where->build(''));

		if ($query->Limit !== null) {
			$sql .= ' LIMIT ' . $query->Limit->offset . ', ' . $query->Limit->limit;
		}

		return $sql;
	}

	public static function delete(Query $query): string {
		if ($query->Where === null) {
			throw new \Exception('Delete requires a WHERE condition');
		}

		return 'DELETE ' . ($query->ignore ? 'IGNORE ' : '') . 'FROM ' . self::quote($query->table) . ' WHERE ' . implode(' AND ', $query->Where->build(''));
	}

	public static function select(Query $query): string {
		$alias = $query->name;

		$fields = self::fields($query->field, $alias, $alias);
		foreach ($query->join as $join) {
			$child = $join->query;
			$childAlias = $alias . '.' . $child->name;
			$fields = array_merge($fields, self::fields($child->field, $child->name, $childAlias));
		}

		$sql = 'SELECT ' . ($query->distinct ? 'DISTINCT ' : '') . implode(', ', $fields);
		$sql .= ' FROM ' . self::quote($query->table);
		if ($alias !== '') {
			$sql .= ' AS ' . self::quote($alias);
		}

		foreach ($query->join as $join) {
			$child = $join->query;
			$sql .= self::join($child->table, $child->name, $join->on);
		}

		$wheres = [];
		if ($query->Where !== null) {
			$wheres = array_merge($wheres, $query->Where->build($alias));
		}
		foreach ($query->join as $join) {
			$child = $join->query;
			if ($child->Where !== null) {
				$wheres = array_merge($wheres, $child->Where->build($child->name));
			}
		}
		if ($wheres !== []) {
			$sql .= ' WHERE ' . implode(' AND ', $wheres);
		}

		if ($query->Group !== null) {
			$sql .= ' GROUP BY ' . implode(', ', self::groups($query->Group->groups, $alias));
		}

		if ($query->Order !== null) {
			$sql .= ' ORDER BY ' . implode(', ', self::orders($query->Order->orders, $alias));
		}

		if ($query->Limit !== null) {
			$sql .= ' LIMIT ' . $query->Limit->offset . ', ' . $query->Limit->limit;
		}

		return $sql;
	}
}
