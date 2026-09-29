<?php

namespace Hit\Domain;

final class ErrorCode {
	public function __construct(
		public string $code,
		public string $message,
	) {}
}
