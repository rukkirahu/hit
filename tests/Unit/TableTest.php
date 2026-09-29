<?php

declare(strict_types=1);

namespace Tests\Unit;

use Hit\Lingo\Lingo;
use Hit\View\Component\Table;
use Hit\View\Pager;

beforeAll(function (): void {
	// pager() renders prev/next labels via Lingo::get(); a missing key falls
	// back to the key itself, so an empty temp store is enough.
	$dir = sys_get_temp_dir() . '/hit_view_table_test_' . uniqid() . '/';
	mkdir($dir);
	Lingo::init('en', $dir);
});

describe('pager', function (): void {
	it('renders an (almost) empty container for a single page', function (): void {
		$html = Table::pager(new Pager(1, 1));

		expect($html)
			->toContain('id="pager"')
			->and($html)
			->not->toContain('id="pg-page"')->and($html)
			->not->toContain('id="pg-prev"');
	});

	it('renders a numbered link for every page and marks the current one', function (): void {
		$html = Table::pager(new Pager(2, 3));

		expect($html)
			->toContain('value="2"') // the hidden page input
			->and($html)
			->toContain('<a href="#" class="current">2</a>')
			->and($html)
			->toContain('<a href="#1">1</a>')
			->and($html)
			->toContain('<a href="#3">3</a>');
	});

	it('keeps prev/next active in the middle of the range', function (): void {
		$html = Table::pager(new Pager(2, 3));

		expect($html)
			->toContain('id="pg-prev" _="on click set #pg-page.value to 1')
			->and($html)
			->toContain('id="pg-next" _="on click set #pg-page.value to 3')
			->and($html)
			->not->toContain('class="disabled" id="pg-prev"');
	});

	it('disables prev on the first page', function (): void {
		$html = Table::pager(new Pager(1, 3));

		expect($html)->toContain('class="disabled" id="pg-prev"');
	});

	it('disables next on the last page', function (): void {
		$html = Table::pager(new Pager(3, 3));

		expect($html)->toContain('class="disabled" id="pg-next"');
	});

	it('renders the ellipsis sentinel for a long range', function (): void {
		// 20 pages, page 10 -> Pager collapses to [1, 0, 9, 10, 11, 0, 20];
		// the 0 entries must become "…" spans, not links to "#0".
		$html = Table::pager(new Pager(10, 20));

		expect($html)
			->toContain('<span>…</span>')
			->and($html)
			->toContain('<a href="#" class="current">10</a>')
			->and($html)
			->not->toContain('href="#0"');
	});

	it('emits the oob swap attribute by default and omits it when disabled', function (): void {
		expect(Table::pager(new Pager(1, 1)))->toContain('hx-swap-oob="true"');
		expect(Table::pager(new Pager(1, 1), false))->not->toContain('hx-swap-oob');
	});
});

describe('order', function (): void {
	it('checks the desc radio when the value matches', function (): void {
		$html = Table::order('sort', 'name', 'name.desc');

		expect($html)
			->toContain('class="desc" name="sort" value="name.desc" checked')
			->and($html)
			->toContain('class="asc" name="sort" value="name.asc" ');
	});

	it('checks the asc radio when the value matches', function (): void {
		$html = Table::order('sort', 'name', 'name.asc');

		expect($html)
			->toContain('class="asc" name="sort" value="name.asc" checked')
			->and($html)
			->not->toContain('value="name.desc" checked');
	});

	it('checks neither radio when nothing matches', function (): void {
		$html = Table::order('sort', 'name', '');

		expect($html)->not->toContain('checked');
	});

	it('escapes the name and title to prevent attribute breakout', function (): void {
		$html = Table::order('s"x', 'a"b', 'whatever');

		expect($html)
			->toContain('&quot;')
			->and($html)
			->not->toContain('name="s"x"')->and($html)
			->not->toContain('value="a"b.desc"');
	});
});
