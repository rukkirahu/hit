<?php

declare(strict_types=1);

namespace Tests\Unit;

use Hit\View\Pager;

describe('validation', function (): void {
	it('throws when the page is below 1', function (): void {
		new Pager(0, 5);
	})->throws(\InvalidArgumentException::class);

	it('throws when the count is below 1', function (): void {
		new Pager(1, 0);
	})->throws(\InvalidArgumentException::class);

	it('throws when the page exceeds the count', function (): void {
		new Pager(6, 5);
	})->throws(\InvalidArgumentException::class);
});

describe('single page', function (): void {
	it('has no pages and no prev/next', function (): void {
		$p = new Pager(1, 1);

		expect($p->pages)->toBe([])->and($p->prev)->toBe(0)->and($p->next)->toBe(0);
	});
});

describe('prev / next', function (): void {
	it('has no prev on the first page', function (): void {
		$p = new Pager(1, 5);

		expect($p->prev)->toBe(0)->and($p->next)->toBe(2);
	});

	it('has no next on the last page', function (): void {
		$p = new Pager(5, 5);

		expect($p->prev)->toBe(4)->and($p->next)->toBe(0);
	});

	it('points to the neighbours in the middle', function (): void {
		$p = new Pager(3, 5);

		expect($p->prev)->toBe(2)->and($p->next)->toBe(4);
	});
});

describe('window (no collapse)', function (): void {
	it('lists every page when count is 7 or fewer', function (): void {
		$p = new Pager(3, 7);

		expect($p->pages)->toBe([1, 2, 3, 4, 5, 6, 7]);
	});
});

describe('window (collapse)', function (): void {
	it('collapses the tail near the start with a 0 sentinel', function (): void {
		$p = new Pager(1, 20);

		expect($p->pages)->toBe([1, 2, 3, 4, 5, 0, 20]);
	});

	it('collapses the head near the end with a 0 sentinel', function (): void {
		$p = new Pager(20, 20);

		expect($p->pages)->toBe([1, 0, 16, 17, 18, 19, 20]);
	});

	it('collapses both ends around a central page', function (): void {
		$p = new Pager(10, 20);

		expect($p->pages)->toBe([1, 0, 9, 10, 11, 0, 20]);
	});
});

describe('invariants', function (): void {
	it('always keeps the window valid across many sizes', function (): void {
		for ($count = 2; $count <= 60; $count++) {
			for ($page = 1; $page <= $count; $page++) {
				$pages = new Pager($page, $count)->pages;

				expect($pages)->toHaveCount(min($count, 7));
				expect(in_array($page, $pages, true))->toBeTrue();
				expect(in_array(1, $pages, true))->toBeTrue();
				expect(in_array($count, $pages, true))->toBeTrue();
			}
		}
	});
});
