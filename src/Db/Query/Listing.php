<?php

declare(strict_types=1);

namespace Hit\Db\Query;

use Hit\Db\Dto;
use Hit\Db\Query;
use Hit\Db\Sql;
use PDO;
use PDOStatement;

/**
 * @extends Query<Dto>
 */
class Listing extends Query {
	/**
	 * @return array<int|string,int|string|list<int|string>>
	 */
	public function exec(): array {
		// fields must contain at least two fields
		if (count($this->field) < 2) {
			throw new \Exception('Listing query must contain at least two fields');
		}

		// build sql
		$sql = Sql::select($this);
		$queryResult = $sql ? $this->db->exec($sql, $this->Value->all()) : false;

		/** @var array<int|string,int|string|list<int|string>> $data */
		$data = [];
		if ($queryResult instanceof PDOStatement) {
			while (true) {
				/** @var false|list<string|int> $row */
				$row = $queryResult->fetch(PDO::FETCH_NUM);
				if ($row === false) {
					break;
				}
				$values = array_slice($row, 1);
				$data[$row[0]] = count($values) === 1 ? $values[0] : $values;
			}
		}

		return $data;
	}
}
