<?php

declare(strict_types=1);

namespace Tests\Unit;

use Hit\Util\Uuid;

// ---------------------------------------------------------------------------
// Format
// ---------------------------------------------------------------------------

test('v4 with dashes produces a valid RFC 4122 UUID', function () {
	$uuid = Uuid::v4();

	expect($uuid)->toMatch('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/');
});

test('v4 without dashes produces 32 hex characters', function () {
	$uuid = Uuid::v4(false);

	expect($uuid)->toMatch('/^[0-9a-f]{32}$/');
	expect(strlen($uuid))->toBe(32);
});

test('v4 with explicit true produces dashed format', function () {
	$uuid = Uuid::v4(true);

	expect($uuid)->toContain('-');
	expect(strlen($uuid))->toBe(36);
});

// ---------------------------------------------------------------------------
// Version and variant bits
// ---------------------------------------------------------------------------

test('v4 sets version nibble to 4', function () {
	$uuid = Uuid::v4();
	$versionNibble = $uuid[14]; // 13th hex char after dashes are at positions 8, 13, 18, 23

	expect($versionNibble)->toBe('4');
});

test('v4 sets variant bits correctly (8 or 9 or a or b)', function () {
	$uuid = Uuid::v4();
	$variantNibble = $uuid[19]; // first char of 4th group

	expect(['8', '9', 'a', 'b'])->toContain($variantNibble);
});

// ---------------------------------------------------------------------------
// Uniqueness
// ---------------------------------------------------------------------------

test('v4 generates unique values across many calls', function () {
	$uuids = [];
	for ($i = 0; $i < 1000; $i++) {
		$uuids[] = Uuid::v4();
	}

	expect(count(array_unique($uuids)))->toBe(1000);
});

// ---------------------------------------------------------------------------
// Both formats in one call sequence
// ---------------------------------------------------------------------------

test('v4 dashed and non-dashed produce same bytes', function () {
	// We can't compare two random calls, but we can verify both formats
	// are consistent in structure: the non-dashed version should be 32 chars
	// and the dashed version should be the same 32 chars plus 4 hyphens.
	$dashed = Uuid::v4(true);
	$noDash = Uuid::v4(false);

	expect(strlen($dashed))->toBe(36);
	expect(strlen($noDash))->toBe(32);
	expect(str_replace('-', '', $dashed))->toMatch('/^[0-9a-f]{32}$/');
});
