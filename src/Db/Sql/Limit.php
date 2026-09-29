<?php

declare(strict_types=1);

namespace Hit\Db\Sql;

class Limit {
	public function __construct(
		public int $offset = 0,
		public int $limit = 0,
	) {}
}
