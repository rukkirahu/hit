<?php

declare(strict_types=1);

namespace Hit\Attribute;

final class Acl {
	public const ADMIN = 256;
	public const MANAGER = 8;
	public const OP = 4;
	public const USER = 2;
	public const GUEST = 1;
	public const NONE = 0;
	public const ALL = self::GUEST | self::ADMIN | self::MANAGER | self::OP | self::USER;
	public const GROUPS = [
		self::USER => 'User',
		self::OP => 'Operator',
		self::MANAGER => 'Manager',
		self::ADMIN => 'Admin',
	];

	private function __construct() {}
}
