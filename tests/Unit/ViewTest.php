<?php

declare(strict_types=1);

namespace Tests\Unit;

use Hit\View\ContentType;
use Hit\View\View;

/** Renders whatever string it is given as HTML. */
function htmlView(string $content): View {
	return new class($content) extends View {
		public function __construct(
			private string $content,
		) {}

		public function render(): void {
			echo $this->content;
		}
	};
}

/** Same, but declares a JSON content type so minify() should be skipped. */
function jsonView(string $content): View {
	return new class($content) extends View {
		public const ContentType = ContentType::JSON;

		public function __construct(
			private string $content,
		) {}

		public function render(): void {
			echo $this->content;
		}
	};
}

describe('rendering', function (): void {
	it('captures render() output through __toString()', function (): void {
		expect((string) htmlView('<p>hello</p>'))->toBe('<p>hello</p>');
	});

	it('exposes the same result via toString()', function (): void {
		$view = htmlView('<p>hi</p>');

		expect($view->toString())->toBe((string) $view);
	});

	it('lets exceptions from render() propagate (and cleans up the buffer)', function (): void {
		$before = ob_get_level();
		$view = new class extends View {
			public function render(): void {
				throw new \RuntimeException('boom');
			}
		};

		try {
			(string) $view;
			expect(false)->toBeTrue(); // unreachable
		}
		catch (\RuntimeException $e) {
			expect($e->getMessage())->toBe('boom');
		}

		expect(ob_get_level())->toBe($before); // no leaked output buffer
	});
});

describe('minify (html)', function (): void {
	it('collapses indentation between tags to a single newline', function (): void {
		$html = (string) htmlView("<ul>\n    <li>x</li>\n    <li>y</li>\n</ul>");

		expect($html)->toBe("<ul>\n<li>x</li>\n<li>y</li>\n</ul>");
	});

	it('collapses ws between tags', function (): void {
		$html = (string) htmlView('<ul>    <li>x</li>    <li>y</li></ul>');

		expect($html)->toBe('<ul><li>x</li><li>y</li></ul>');
	});

	it('strips multi-line HTML comments', function (): void {
		expect((string) htmlView("<!--\n note\n-->visible"))->toBe('visible');
	});

	it('drops empty class attributes', function (): void {
		expect((string) htmlView("<div class=''>hi</div>"))->toBe('<div>hi</div>');
	});

	it('never corrupts whitespace inside attribute values', function (): void {
		expect((string) htmlView('<input value="a    b">'))->toBe('<input value="a    b">');
	});

	it('preserves the contents of <pre>', function (): void {
		$in = "<pre>line1\n    indented</pre>";

		expect((string) htmlView($in))->toBe($in);
	});

	it('preserves the contents of <textarea>', function (): void {
		$in = '<textarea>keep    me</textarea>';

		expect((string) htmlView($in))->toBe($in);
	});
});

describe('minify (non-html)', function (): void {
	it('leaves JSON output completely untouched', function (): void {
		$json = "{\n    \"name\": \"John    Doe\"\n}";

		expect((string) jsonView($json))->toBe($json);
	});
});
