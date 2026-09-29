<?php

declare(strict_types=1);

namespace Tests\Unit;

use Tests\Fixtures\Dto\NestedDto;

test('match returns null for null and unsupported values', function () {
	expect(NestedDto::match(null))->toBeNull();
	expect(NestedDto::match('invalid'))->toBeNull();
});

test('match preserves an existing dto instance', function () {
	$dto = new NestedDto(id: 1);

	expect(NestedDto::match($dto))->toBe($dto);
});

test('match creates a dto from an array', function () {
	$dto = NestedDto::match(['id' => 1]);

	expect($dto)->toBeInstanceOf(NestedDto::class);
	expect($dto?->id)->toBe(1);
});

test('from creates nested dto objects from arrays', function () {
	$dto = NestedDto::from(['id' => 1, 'child' => ['id' => 2]]);

	expect($dto->id)->toBe(1);
	expect($dto->child)->toBeInstanceOf(NestedDto::class);
	expect($dto->child?->id)->toBe(2);
});
