<?php

declare(strict_types=1);

namespace Hit\Domain;

final class ErrorCode {
	public function __construct(
		public string $code,
		public string $message,
	) {}
}
