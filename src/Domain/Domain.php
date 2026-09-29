<?php

declare(strict_types=1);

namespace Hit\Domain;

use Hit\Db\Database;
use Hit\Db\Dto;
use Hit\Db\Query;

abstract class Domain {
	public const TABLE = '';

	public function __construct(
		public readonly Database $db,
	) {}

	public function query(Dto $dto): Query {
		return new Query($this, dto: $dto);
	}

	public function get(Dto $dto): Query\Get {
		return new Query\Get($this, dto: $dto);
	}

	public function select(Dto $dto): Query\Select {
		return new Query\Select($this, dto: $dto);
	}

	public function listing(): Query\Listing {
		return new Query\Listing($this);
	}

	public function distinct(): Query\Distinct {
		return new Query\Distinct($this);
	}

	public function index(?Dto $dto = null): Query\Index {
		return new Query\Index($this, dto: $dto);
	}

	public function scalar(?Dto $dto = null): Query\Scalar {
		return new Query\Scalar($this, dto: $dto);
	}

	public function insert(Dto $dto): Query\Insert {
		return new Query\Insert($this, dto: $dto);
	}

	/**
	 * @param iterable<Dto> $dtos
	 */
	public function insertChunks(iterable $dtos, int $chunkSize = 500): Query\InsertChunks {
		return new Query\InsertChunks($this, $dtos, $chunkSize);
	}

	public function update(Dto $dto): Query\Update {
		return new Query\Update($this, dto: $dto);
	}

	public function delete(Dto $dto): Query\Delete {
		return new Query\Delete($this, dto: $dto);
	}

	public function beforeInsert(Dto $dto): Dto|false {
		return $dto;
	}

	public function afterInsert(Dto $dto): void {}

	public function afterNoInsert(Dto $dto, ?ErrorCode $error = null): bool {
		return false;
	}

	public function beforeUpdate(Dto $dto): Dto|false {
		return $dto;
	}

	public function afterUpdate(Dto $dto): void {}

	public function afterNoUpdate(Dto $dto, ?ErrorCode $error = null): bool {
		return false;
	}

	public function afterDelete(Dto $dto, int $rows): void {}

	public function afterNoDelete(Dto $dto, ?ErrorCode $error = null): bool {
		return false;
	}

	public function parsePdoError(string $message): ErrorCode {
		return new ErrorCode('error', $message);
	}
}
