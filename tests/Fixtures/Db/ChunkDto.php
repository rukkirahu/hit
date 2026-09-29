<?php

declare(strict_types=1);

namespace Tests\Fixtures\Db;

use Hit\Db\Dto;

final readonly class ChunkDto extends Dto {
	public function __construct(
		public int $id = 0,
		public string $name = '',
		public int $age = 0,
	) {}
}
