<?php

declare(strict_types=1);

namespace Hit\Db\Query;

use Hit\Db\Dto;
use Hit\Db\Query;
use Hit\Db\Sql;

/**
 * @extends Query<Dto>
 */
class Insert extends Query {
	/**
	 * @return int|false Inserted ID, or false on error
	 */
	public function exec(): int|false {
		if (!$this->dto instanceof Dto) {
			throw new \Exception('DTO is not set');
		}

		$dto = $this->domain->beforeInsert($this->dto);
		if ($dto === false) {
			return false;
		}

		$inserts = [];
		/**
		 * @var mixed $value
		 */
		foreach ($dto->toArray() as $field => $value) {
			if ($field === self::PRIMARY) {
				continue;
			}

			$inserts[$field] = $this->Value->parse($value);
		}

		$sql = Sql::insert($inserts, $this);

		$err = null;
		$queryResult = false;

		try {
			$queryResult = $sql ? $this->db->exec($sql, $this->Value->all()) : false;
		}
		catch (\PDOException $e) {
			$queryResult = false;
			$err = $this->domain->parsePdoError($e->getMessage());
		}

		if ($queryResult === false) {
			if (!$this->domain->afterNoInsert($dto, $err)) {
				throw new \Exception(json_encode($err ?: $this->db->errorInfo()) ?: 'Unknown error');
			}

			return false;
		}

		$this->domain->afterInsert($dto);

		return intval($this->db->lastInsertId());
	}
}
