<?php

declare(strict_types=1);

namespace Hit\Db\Query;

use Hit\Db\Query;
use Hit\Db\Sql;
use PDO;
use PDOStatement;

/**
 * Single-value query: SELECT of exactly one field, returning the first cell.
 *
 * Define the value with field(), typically an aggregate expression:
 *   ->field(['count' => new Expression('COUNT(*)')])
 *   ->field(['max' => new Expression('MAX(`U`.`id`)')])
 *
 * @template T as \Hit\Db\Dto
 * @extends Query<T>
 */
class Scalar extends Query {
	public function exec(): int|float {
		if (count($this->field) !== 1 || $this->field === ['*' => false]) {
			throw new \Exception('Scalar query must contain exactly one field');
		}

		$sql = Sql::select($this);
		$queryResult = $this->db->exec($sql, $this->Value->all());

		if (!$queryResult instanceof PDOStatement) {
			return 0;
		}

		$res = $queryResult->fetch(PDO::FETCH_NUM);
		if (!is_array($res)) {
			throw new \Exception('Invalid result type: ' . gettype($res));
		}

		return self::toNumber($res[0] ?? null);
	}

	private static function toNumber(mixed $value): int|float {
		if ($value === null || $value === '') {
			return 0;
		}
		if (is_int($value) || is_float($value)) {
			return $value;
		}
		if (!is_numeric($value)) {
			throw new \Exception('Non-numeric scalar result: ' . gettype($value));
		}
		$string = (string) $value;

		return preg_match('/^-?\d+$/', $string) === 1 ? (int) $string : (float) $string;
	}
}
