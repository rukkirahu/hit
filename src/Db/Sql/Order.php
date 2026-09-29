<?php

declare(strict_types=1);

namespace Hit\Db\Sql;

class Order {
	/**
	 * @param array<string,string> $orders
	 */
	public function __construct(
		public array $orders = [],
	) {
		foreach ($orders as $direction) {
			if (!in_array(strtoupper($direction), ['ASC', 'DESC'], true)) {
				throw new \Exception('Invalid order direction: ' . $direction);
			}
		}
	}
}
