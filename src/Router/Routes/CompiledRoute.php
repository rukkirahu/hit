<?php

declare(strict_types=1);

namespace Hit\Router\Routes;

final readonly class CompiledRoute {
	public function __construct(
		public RouteDefinition $definition,
		public string $pattern,
	) {}
}
