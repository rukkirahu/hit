<?php

namespace Hit\Validator;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
final class Rule {
	/** @var array<array-key, mixed> */
	public array $validations = [];

	public function __construct(...$args) {
		$this->validations = $args;
	}
}
