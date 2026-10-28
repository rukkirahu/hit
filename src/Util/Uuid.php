<?php

declare(strict_types=1);

namespace Hit\Util;

/**
 * Generates RFC 4122 v4 (random) UUIDs.
 */
class Uuid {
	/**
	 * Generate a random v4 UUID.
	 *
	 * @param bool $dash Whether to include hyphens in the output.
	 * @return string
	 */
	public static function v4(bool $dash = true): string {
		$data = random_bytes(16);
		$data[6] = chr((ord($data[6]) & 0x0f) | 0x40); // set version to 0100
		$data[8] = chr((ord($data[8]) & 0x3f) | 0x80); // set bits 6-7 to 10

		return $dash ? vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4)) : bin2hex($data);
	}

	public static function isValid(string $uuid): bool {
		if (strlen($uuid) !== 36 || preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $uuid) !== 1) {
			return false;
		}

		return true;
	}
}
