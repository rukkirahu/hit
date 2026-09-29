<?php

declare(strict_types=1);

namespace Hit\View\Component\FormBuilder;

use Hit\View\Component\Html;

/**
 * Bare HTML output: no CSS framework classes, minimal wrapping.
 * Useful as a default, or as a base to hand-theme with your own CSS.
 */
class PlainFormBuilder extends AbstractFormBuilder {
	protected function wrapField(array $attributes, ?string $label, ?string $error, ?callable $renderControl = null): string {
		$inputHtml = $renderControl ? $renderControl($attributes) : '';
		$html = $label !== null ? '<label>' . Html::h($label) . '</label>' . $inputHtml : $inputHtml;

		if ($error !== null) {
			$html .= '<div class="form-error">' . Html::h($error) . '</div>';
		}

		return $html;
	}

	protected function wrapCheckable(array $attributes, ?string $label): string {
		$input = '<input' . $this->attributes($attributes) . '>';

		return $label !== null ? '<label>' . $input . ' ' . Html::h($label) . '</label>' : $input;
	}

	protected function applyFieldStateClasses(array $attributes, ?string $error, bool $isSelect = false): array {
		if ($error !== null) {
			$attributes['class'] = trim(($attributes['class'] ?? '') . ' is-invalid');
		}

		return $attributes;
	}
}
