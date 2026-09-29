<?php

declare(strict_types=1);

namespace Hit\View;

class View {
	public const ContentType = ContentType::HTML;
	public const Hx = false;

	public function render(): void {
		echo static::class;
	}

	public function toString(): string {
		return $this->__toString();
	}

	public function __toString(): string {
		ob_start();
		try {
			$this->render();

			return $this->minify(ob_get_contents() ?: '');
		}
		finally {
			ob_end_clean();
		}
	}

	protected function minify(string $content): string {
		// Tidy-only minification, and only for HTML output: collapse template
		// indentation without touching attribute values, inline text, or the
		// contents of whitespace-sensitive elements like <pre>/<textarea>.
		if (static::ContentType !== ContentType::HTML) {
			return $content;
		}

		return preg_replace(
			[
				'~<!--.*?-->~s', // HTML comments (multiline)
				'~ class=(["\'])\1~', // empty class="" or class=''
				'~>\s*\n\s*<~',
				'~>[ \t]+<~', // ws between block tags
				'~\n{2,}~', // collapse blank lines
			],
			['', '', ">\n<", '><', "\n"],
			$content,
		) ?? $content;
	}
}
