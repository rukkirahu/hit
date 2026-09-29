<?php

declare(strict_types=1);

namespace Hit\Db\Query;

use Hit\Db\Dto;
use Hit\Db\Query;
use Hit\Db\Sql;
use Hit\Db\Sql\Value;
use Hit\Domain\Domain;
use Hit\Domain\ErrorCode;

/**
 * @extends Query<Dto>
 */
class InsertChunks extends Query {
	/**
	 * @param iterable<Dto> $dtos
	 */
	public function __construct(
		Domain $domain,
		private iterable $dtos,
		private int $chunkSize = 500,
	) {
		if ($chunkSize < 1) {
			throw new \InvalidArgumentException('Chunk size must be greater than zero');
		}

		parent::__construct($domain);
	}

	/**
	 * @return int|false Number of inserted rows, or false on error
	 */
	public function exec(): int|false {
		$inserted = 0;
		$chunk = [];

		foreach ($this->dtos as $dto) {
			$dto = $this->domain->beforeInsert($dto);
			if ($dto === false) {
				return false;
			}

			$chunk[] = $dto;
			if (count($chunk) < $this->chunkSize) {
				continue;
			}

			$result = $this->insert($chunk);
			if ($result === false) {
				return false;
			}

			$inserted += $result;
			$chunk = [];
		}

		if ($chunk !== []) {
			$result = $this->insert($chunk);
			if ($result === false) {
				return false;
			}

			$inserted += $result;
		}

		return $inserted;
	}

	/**
	 * @param non-empty-list<Dto> $dtos
	 * @return int|false
	 */
	private function insert(array $dtos): int|false {
		$this->Value = new Value();
		$columns = null;
		$rows = [];

		foreach ($dtos as $dto) {
			$data = $dto->toArray();
			unset($data[self::PRIMARY]);
			$fields = array_keys($data);
			$columns ??= $fields;

			if (array_diff($fields, $columns) !== [] || array_diff($columns, $fields) !== []) {
				throw new \InvalidArgumentException('Insert DTOs must have identical columns');
			}

			$row = [];
			foreach ($columns as $field) {
				$row[$field] = $this->Value->parse($data[$field]);
			}
			$rows[] = $row;
		}

		$sql = Sql::insertRows($rows, $this);
		$err = null;

		try {
			$queryResult = $this->db->exec($sql, $this->Value->all());
		}
		catch (\PDOException $e) {
			$queryResult = false;
			$err = $this->domain->parsePdoError($e->getMessage());
		}

		if ($queryResult === false) {
			return $this->afterNoInsert($dtos, $err);
		}

		array_walk($dtos, function (Dto $dto): void {
			$this->domain->afterInsert($dto);
		});

		return $queryResult->rowCount();
	}

	/**
	 * @param non-empty-list<Dto> $dtos
	 * @return false
	 */
	private function afterNoInsert(array $dtos, ?ErrorCode $err): false {
		$handled = array_map(fn(Dto $dto): bool => $this->domain->afterNoInsert($dto, $err), $dtos);

		if (in_array(false, $handled, true)) {
			$message = json_encode($err ?? $this->db->errorInfo());
			throw new \Exception(is_string($message) && $message !== '' ? $message : 'Unknown error');
		}

		return false;
	}
}
