<?php

declare(strict_types=1);

namespace Hit\Db\Sql;

class Group {
	/**
	 * @param array<int,string> $groups
	 */
	public function __construct(
		public array $groups = [],
	) {}

	/**
	 * @param string|array<int,string> $fields
	 */
	public function add(string|array $fields): void {
		foreach ((array) $fields as $field) {
			$this->groups[] = $field;
		}
	}
}
