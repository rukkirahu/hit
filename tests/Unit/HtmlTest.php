<?php

declare(strict_types=1);

namespace Tests\Unit;

use Hit\Lingo\Lingo;
use Hit\View\Component\Html;

beforeAll(function (): void {
	// Html::init is idempotent (guards on a non-empty baseurl), so it can only
	// be configured once per process. The route checker denies anything whose
	// href contains "denied", and additionally denies POST on a "postonly"
	// path so we can prove the access cache is keyed by method.
	Html::init('https://x/', function (string $method, string $href): bool {
		if (str_contains($href, 'denied')) {
			return false;
		}
		if ($method === 'POST' && str_contains($href, 'postblock')) {
			return false;
		}

		return true;
	});

	// link() falls back to Lingo::get() for %-prefixed names; an empty store is
	// enough since a missing key returns the key itself.
	$dir = sys_get_temp_dir() . '/hit_view_html_test_' . uniqid() . '/';
	mkdir($dir);
	Lingo::init('en', $dir);
});

describe('url', function (): void {
	it('prefixes a relative href with the base url when allowed', function (): void {
		expect(Html::url('users', 'GET'))->toBe('https://x/users');
	});

	it('leaves an absolute href (leading slash) unprefixed', function (): void {
		expect(Html::url('/abs', 'GET'))->toBe('/abs');
	});

	it('returns an empty string when the route is denied', function (): void {
		expect(Html::url('denied', 'GET'))->toBe('');
	});

	it('returns an empty string for an empty href', function (): void {
		expect(Html::url('', 'GET'))->toBe('');
	});

	it('preserves the query string', function (): void {
		expect(Html::url('search?q=1&p=2', 'GET'))->toBe('https://x/search?q=1&p=2');
	});

	it('returns an empty string when only a query string is given', function (): void {
		// After stripping the query the href is empty; must not crash on $href[0].
		expect(Html::url('?q=1', 'GET'))->toBe('');
	});

	it('keys the access cache by method, not just href', function (): void {
		// Same href: GET is allowed, POST is denied. A href-only cache would let
		// the POST call reuse the GET result.
		expect(Html::url('postblock/page', 'GET'))->toBe('https://x/postblock/page');
		expect(Html::url('postblock/page', 'POST'))->toBe('');
	});

	it('substitutes the id without stripping legitimate trailing zeros', function (): void {
		// rtrim($href, '/0') used to turn "users/200" into "users/2".
		expect(Html::url('users/200', 'GET', 5))->toBe('https://x/users/200/5');
	});

	it('substitutes the id placeholder and keeps the query string', function (): void {
		expect(Html::url('items?sort=name', 'GET', 7))->toBe('https://x/items/7?sort=name');
	});
});

describe('quotedUrl', function (): void {
	it('wraps the url in double quotes', function (): void {
		expect(Html::quotedUrl('users', 'GET'))->toBe('"https://x/users"');
	});

	it('returns empty quotes when denied', function (): void {
		expect(Html::quotedUrl('denied', 'GET'))->toBe('""');
	});
});

describe('highlight', function (): void {
	it('underlines a case-insensitive match preserving the original casing', function (): void {
		expect(Html::highlight('Hello World', 'world'))->toBe('Hello <u>World</u>');
	});

	it('returns the text unchanged when the needle is empty', function (): void {
		expect(Html::highlight('Hello', ''))->toBe('Hello');
	});

	it('returns the text unchanged when there is no match', function (): void {
		expect(Html::highlight('Hello', 'zzz'))->toBe('Hello');
	});

	it('falls back to a transliterated needle when the literal is not found', function (): void {
		expect(Html::highlight('Hello', 'é'))->toBe('H<u>e</u>llo');
	});
});

describe('h', function (): void {
	it('escapes both quotes and angle brackets', function (): void {
		expect(Html::h('<a href="x">'))->toBe('&lt;a href=&quot;x&quot;&gt;');
	});

	it('treats null as an empty string', function (): void {
		expect(Html::h(null))->toBe('');
	});
});

describe('link', function (): void {
	it('builds an anchor for an allowed href', function (): void {
		$html = Html::link('Click', 'users');

		expect($html)
			->toContain("href='https://x/users'")
			->and($html)
			->toContain('>Click</a>');
	});

	it('returns an empty string when the href is denied', function (): void {
		expect(Html::link('Click', 'denied'))->toBe('');
	});

	it('adds the optional attributes when provided', function (): void {
		$html = Html::link('Go', 'users', class: 'btn', role: 'button', tabindex: 3);

		expect($html)
			->toContain("class='btn'")
			->and($html)
			->toContain("role='button'")
			->and($html)
			->toContain("tabindex='3'");
	});

	it('wraps the anchor in the given wrapper element', function (): void {
		$html = Html::link('Go', 'users', wrapper: 'li');

		expect($html)->toContain('<li><a ')->and($html)->toContain('</a></li>');
	});

	it('resolves a %-prefixed name through Lingo', function (): void {
		// Missing key falls back to the key itself, so the % is stripped.
		$html = Html::link('%dashboard', 'users');

		expect($html)->toContain('>dashboard</a>')->and($html)->not->toContain('%dashboard');
	});

	it('does not crash on an empty name', function (): void {
		$html = Html::link('', 'users');

		expect($html)->toContain('></a>');
	});
});
