<?php

declare(strict_types=1);

namespace Tests\Fixtures\Db;

use Hit\Db\Dto;
use Hit\Domain\Domain;
use Hit\Domain\ErrorCode;

final class ChunkDomain extends Domain {
	public const TABLE = 'users';

	public array $before = [];
	public array $after = [];
	public array $failed = [];
	public ?string $reject = null;
	public bool $handleFailure = false;

	public function beforeInsert(Dto $dto): Dto|false {
		$this->before[] = $dto;

		return property_exists($dto, 'name') && $dto->name === $this->reject ? false : $dto;
	}

	public function afterInsert(Dto $dto): void {
		$this->after[] = $dto;
	}

	public function afterNoInsert(Dto $dto, ?ErrorCode $error = null): bool {
		$this->failed[] = [$dto, $error];

		return $this->handleFailure;
	}
}
