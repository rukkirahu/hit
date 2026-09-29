<?php

declare(strict_types=1);

namespace Hit\Db\Query;

use Hit\Db\Dto;
use Hit\Db\Query;
use Hit\Db\Sql;
use Hit\Domain\ErrorCode;

/**
 * @extends Query<Dto>
 */
class Update extends Query {
	/**
	 * @param array<string, mixed> $data
	 * @return false|int Number of affected rows, or false on error
	 */
	public function exec(array $data = []): false|int {
		if (!$this->dto instanceof Dto) {
			throw new \Exception('DTO is not set');
		}
		$dto = $this->dto;

		if ($data === []) {
			if (!$this->Where) {
				if (!property_exists($this->dto, self::PRIMARY)) {
					throw new \Exception('Primary id property does not exist');
				}
				$id = $this->dto->{self::PRIMARY};
				if (!$id) {
					throw new \Exception('Primary id value is missing');
				}
				$this->where([self::PRIMARY => $id]);
			}

			$dto = $this->domain->beforeUpdate($dto);
			if ($dto === false) {
				return false;
			}
			$data = $dto->toArray();
		}
		else {
			if (!$this->Where) {
				throw new \Exception('Missing update conditions');
			}
		}

		$updates = [];
		/**
		 * @var array<string, mixed> $updates
		 * @var string $field
		 * @var mixed $value */
		foreach ($data as $field => $value) {
			if ($field === self::PRIMARY) {
				continue;
			}

			$updates[$field] = $this->Value->parse($value);
		}
		$sql = Sql::update($updates, $this);

		$err = null;
		$queryResult = false;

		try {
			$queryResult = $sql ? $this->db->exec($sql, $this->Value->all()) : false;
		}
		catch (\PDOException $e) {
			$queryResult = false;
			$err = $this->domain->parsePdoError($e->getMessage());
		}

		if (!$queryResult) {
			if (!$this->domain->afterNoUpdate($dto, $err)) {
				throw new \Exception(json_encode($err ?: $this->db->errorInfo()) ?: 'Unknown error');
			}

			return false;
		}

		$result = $queryResult->rowCount();

		if (0 === $result) {
			$this->domain->afterNoUpdate($dto, new ErrorCode('ok', 'No changes'));
		}
		else {
			$this->domain->afterUpdate($dto);
		}

		return $result;
	}
}
