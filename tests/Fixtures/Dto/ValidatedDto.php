<?php

declare(strict_types=1);

namespace Tests\Fixtures\Dto;

use Hit\Db\Dto;
use Hit\Validator\Rule;

final readonly class ValidatedDto extends Dto {
	public const PREFIX = 'Validated';

	public function __construct(
		public int $id = 0,
		#[Rule(required: true, maxLength: 50)]
		public string $email = '',
		#[Rule(required: true, maxLength: 10)]
		public string $name = '',
		#[Rule(required: true)]
		public int $group_bit = 1,
		#[Rule(maxLength: 5)]
		public string $optional = '',
		public bool $active = true,
	) {}

	public function with(...$args): self {
		return new self(
			id: (int) ($args['id'] ?? $this->id),
			email: (string) ($args['email'] ?? $this->email),
			name: (string) ($args['name'] ?? $this->name),
			group_bit: (int) ($args['group_bit'] ?? $this->group_bit),
			optional: (string) ($args['optional'] ?? $this->optional),
			active: (bool) ($args['active'] ?? $this->active),
		);
	}
}
