<?php

declare(strict_types=1);

namespace Hit\Db\Query;

use Hit\Db\Query;
use Hit\Db\Sql;
use Hit\Domain\Domain;
use PDO;
use PDOStatement;

/**
 * SELECT DISTINCT over one or more plain fields, without a dto.
 *
 *   ->field(['category'])->exec()          // list<string>
 *   ->field(['category', 'brand'])->exec() // list<list<string>>
 *
 * @extends Query<\Hit\Db\Dto>
 */
class Distinct extends Query {
	public function __construct(Domain $domain) {
		parent::__construct($domain);
		$this->distinct = true;
	}

	/**
	 * @return list<string>|list<list<string>>
	 */
	public function exec(): array {
		if ($this->field === [] || $this->field === ['*' => false]) {
			throw new \Exception('Distinct query must contain at least one field');
		}

		$sql = Sql::select($this);
		$queryResult = $this->db->exec($sql, $this->Value->all());

		/** @var list<list<string>> $rows */
		$rows = [];
		if ($queryResult instanceof PDOStatement) {
			/** @var false|array<int,mixed> $row */
			while ($row = $queryResult->fetch(PDO::FETCH_NUM)) {
				$rows[] = array_values(array_map(strval(...), $row));
			}
		}

		if (count($this->field) === 1) {
			return array_values(array_column($rows, 0));
		}

		return $rows;
	}
}
