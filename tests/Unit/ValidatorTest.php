<?php

declare(strict_types=1);

namespace Tests\Unit;

use Hit\Validator\Validator;
use Tests\Fixtures\Dto\ValidatedDto;

test('returns empty array for a valid DTO', function () {
	$dto = new ValidatedDto(email: 'ada@example.com', name: 'Ada', group_bit: 1);

	expect(Validator::check($dto))->toBe([]);
});

test('reports required violations for empty required properties', function () {
	$dto = new ValidatedDto(group_bit: 0);

	expect(Validator::check($dto))->toBe([
		'email' => 'Validated.email.required',
		'name' => 'Validated.name.required',
		'group_bit' => 'Validated.group_bit.required',
	]);
});

test('reports first failing rule per property and continues to other properties', function () {
	$dto = new ValidatedDto(email: str_repeat('a', 100), name: str_repeat('b', 20), group_bit: 1);

	expect(Validator::check($dto))->toBe([
		'email' => 'Validated.email.maxLength',
		'name' => 'Validated.name.maxLength',
	]);
});

test('skips non-required empty properties', function () {
	$dto = new ValidatedDto(email: 'ada@example.com', name: 'Ada', group_bit: 1, optional: '');

	expect(Validator::check($dto))->toBe([]);
});

test('validates optional property when it has a value', function () {
	$dto = new ValidatedDto(email: 'ada@example.com', name: 'Ada', group_bit: 1, optional: str_repeat('x', 10));

	expect(Validator::check($dto))->toBe([
		'optional' => 'Validated.optional.maxLength',
	]);
});

test('uses required check to skip rules on empty non-required values', function () {
	$dto = new ValidatedDto(email: 'ada@example.com', name: 'Ada', group_bit: 1, optional: '     ');

	expect(Validator::check($dto))->toBe([]);
});

test('validates integer required fields', function () {
	$dto = new ValidatedDto(email: 'ada@example.com', name: 'Ada', group_bit: 0);

	expect(Validator::check($dto))->toBe([
		'group_bit' => 'Validated.group_bit.required',
	]);
});
