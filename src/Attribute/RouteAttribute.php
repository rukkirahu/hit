<?php

declare(strict_types=1);

namespace Hit\Attribute;

interface RouteAttribute {
	public string $method { get; set; }

	public string $uri { get; set; }

	public int $accept { get; set; }

	public int $acl { get; set; }
}
