<?php

declare(strict_types=1);

namespace Hit\Router\Routes;

final class Routing {
	public function __construct(
		public readonly string $action,
		public readonly array $params = [],
		public readonly bool $isDefault = false,
		public readonly int $accept = 0,
		public readonly int $acl = 0,
	) {}
}
