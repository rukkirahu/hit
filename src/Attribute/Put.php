<?php

declare(strict_types=1);

namespace Hit\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
final class Put implements RouteAttribute {
	public string $method;

	public function __construct(
		public string $uri,
		public int $accept = Accept::HTML,
		public int $acl = Acl::NONE,
	) {
		$this->method = 'PUT';
	}
}
