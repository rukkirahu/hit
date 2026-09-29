<?php

declare(strict_types=1);

namespace Hit\Db\Query;

use Hit\Array\Ray;
use Hit\Db\Dto;
use Hit\Db\Query;
use Hit\Db\Sql;
use PDO;

/**
 * @template T as Dto
 * @extends Query<T>
 */
class Select extends Query {
	/**
	 * shortcut method for querying multiple objects
	 * @return array<int,T>
	 */
	public function exec(): array {
		/** @var T $dto */
		$dto = $this->dto;
		if (!$dto instanceof Dto) {
			throw new \Exception('Dto is not set');
		}
		// build sql
		$sql = Sql::select($this);

		$queryResult = $sql ? $this->db->exec($sql, $this->Value->all()) : false;
		/** @var array<int,T> $data */
		$data = [];
		if ($queryResult instanceof \PDOStatement) {
			/** @var false|array<string,mixed> $row */
			while ($row = $queryResult->fetch(PDO::FETCH_ASSOC)) {
				if (!is_array($row)) {
					continue;
				}

				$item = Ray::undot($row)[$dto::PREFIX] ?? false;
				if (is_array($item)) {
					/** @var T $object */
					$object = $dto->with(...$item);
					$data[] = $object;
				}
				else {
					throw new \Exception('Not iterable');
				}
			}
		}

		return $data;
	}
}
