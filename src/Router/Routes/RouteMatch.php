<?php

declare(strict_types=1);

namespace Hit\Router\Routes;

final class RouteMatch {
	public string $action {
		get => $this->route->action;
	}

	public int $accept {
		get => $this->route->accept;
	}

	public int $acl {
		get => $this->route->acl;
	}

	public function __construct(
		public readonly RouteDefinition $route,
		public readonly array $params = [],
	) {}
}
