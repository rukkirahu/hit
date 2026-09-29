<?php

declare(strict_types=1);

namespace Hit\Db\Sql;

use Hit\Db\Sql;

class Where {
	/**
	 * @var array<string,mixed>
	 */
	public array $conditions = [];

	public function __construct(
		private string $table,
	) {}

	/**
	 * @param array<string,mixed> $conditions
	 */
	public function add(array $conditions): void {
		$this->conditions = array_merge($this->conditions, $conditions);
	}

	/**
	 * @return array<int,string>
	 */
	public function build(?string $table = null): array {
		$table ??= $this->table;
		$out = [];
		foreach ($this->conditions as $key => $value) {
			$out[] = Sql::parseCondition($key, $value, $table, false);
		}

		return $out;
	}
}
