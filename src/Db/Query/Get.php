<?php

declare(strict_types=1);

namespace Hit\Db\Query;

use Hit\Array\Ray;
use Hit\Db\Dto;
use Hit\Db\Query;
use Hit\Db\Sql;
use PDO;
use PDOStatement;

/**
 * @template T as \Hit\Db\Dto
 * @extends Query<T>
 */
class Get extends Query {
	/**
	 * shortcut method for loading one object by id value
	 * @return T
	 */
	public function exec(): Dto {
		$dto = $this->dto;
		if (!$dto instanceof Dto) {
			throw new \Exception('Dto is not set');
		}
		$this->limit([0, 1]);

		// build sql
		$sql = Sql::select($this);

		$queryResult = $sql ? $this->db->exec($sql, $this->Value->all()) : false;

		if ($queryResult instanceof PDOStatement) {
			/** @var array<string,mixed>|false $row */
			$row = $queryResult->fetch(PDO::FETCH_ASSOC);

			if ($row !== false) {
				$data = Ray::undot($row)[$dto::PREFIX] ?? false;
				if (is_array($data)) {
					/** @var T $result */
					return $dto->with(...$data);
				}
				throw new \Exception('Data is not iterable');
			}
		}

		return $dto;
	}
}
