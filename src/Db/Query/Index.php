<?php

declare(strict_types=1);

namespace Hit\Db\Query;

use Hit\Array\Ray;
use Hit\Db\Query;
use Hit\Db\Sql;
use PDO;
use PDOStatement;

/**
 * @template T as \Hit\Db\Dto
 * @extends Query<T>
 */
class Index extends Query {
	/**
	 * @return array<int,mixed>
	 */
	public function exec(): array {
		// fields must contain at least two fields
		if (count($this->field) < 2) {
			throw new \Exception('Index query must contain at least two fields');
		}

		// build sql
		$sql = Sql::select($this);
		$queryResult = $sql ? $this->db->exec($sql, $this->Value->all()) : false;

		$data = [];
		if ($queryResult instanceof PDOStatement) {
			if ($this->dto) {
				/** @var false|array<string,mixed> $row */
				while ($row = $queryResult->fetch(PDO::FETCH_ASSOC)) {
					if (!is_array($row)) {
						continue;
					}
					$item = Ray::undot($row)[$this->dto::PREFIX] ?? false;
					if (is_array($item)) {
						$data[(int) $item['id']] = $this->dto->with(...$item);
					}
					else {
						throw new \Exception('Not iterable');
					}
				}
			}
			else if (count($this->field) === 2) {
				/** @var false|array<int,mixed> $row */
				while ($row = $queryResult->fetch(PDO::FETCH_NUM)) {
					$data[(int) $row[0]] = $row[1];
				}
			}
			else {
				/** @var false|array<int,mixed> $row */
				while ($row = $queryResult->fetch(PDO::FETCH_NUM)) {
					$data[(int) $row[0]] = $row;
				}
			}
		}

		return $data;
	}
}
