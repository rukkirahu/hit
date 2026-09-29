<?php

namespace Hit\Db\Query;

use Hit\Db\Dto;
use Hit\Db\Query;
use Hit\Db\Sql;

/**
 * @extends Query<Dto>
 */
class Delete extends Query {
	/**
	 * @return true|false
	 */
	public function exec(): bool {
		$dto = $this->dto;
		if (!$dto instanceof Dto) {
			throw new \Exception('Dto is not set');
		}

		// build sql
		$sql = Sql::delete($this);
		$err = null;

		try {
			$queryResult = $sql ? $this->db->exec($sql, $this->Value->all()) : false;
		}
		catch (\PDOException $e) {
			$queryResult = false;
			$err = $this->domain->parsePdoError($e->getMessage());
		}

		if ($queryResult === false) {
			if (!$this->domain->afterNoDelete($dto, $err)) {
				throw new \Exception(json_encode($err ?: $this->db->errorInfo()) ?: 'Unknown error');
			}

			return false;
		}

		$this->domain->afterDelete($dto, $queryResult->rowCount());

		return true;
	}
}
