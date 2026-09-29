<?php

declare(strict_types=1);

namespace Hit\Db\Sql;

class Expression {
	public function __construct(
		public string $sql,
	) {}

	public function __toString(): string {
		return $this->sql;
	}
}
