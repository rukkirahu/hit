<?php

declare(strict_types=1);

namespace Tests\Fixtures\Dto;

use Hit\Db\Dto;

final readonly class NestedDto extends Dto {
	public ?self $child;

	public function __construct(
		public int $id = 0,
		mixed $child = null,
	) {
		$this->child = self::match($child);
	}

	public function with(...$args): self {
		return new self(
			id: (int) ($args['id'] ?? $this->id),
			child: $args['child'] ?? $this->child,
		);
	}
}
