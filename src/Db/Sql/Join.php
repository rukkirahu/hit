<?php

declare(strict_types=1);

namespace Hit\Db\Sql;

use Hit\Db\Query;

class Join {
	/**
	 * @param array<string,string> $on
	 */
	public function __construct(
		public Query $query,
		public array $on = [],
	) {}

	/**
	 * Build the ON map, qualifying parent columns with $parentName and child
	 * columns with the joined query's name.
	 * @param array<string,string> $on
	 */
	public function setOn(string $parentName, array $on): void {
		$result = [];
		foreach ($on as $key => $value) {
			$result[$parentName . '.' . $key] = $this->query->name . '.' . $value;
		}
		$this->on = $result;
	}
}
