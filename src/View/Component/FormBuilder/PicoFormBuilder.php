<?php

declare(strict_types=1);

namespace Hit\View\Component\FormBuilder;

use Hit\Lingo\Lingo;
use Hit\View\Component\Html;

/**
 * PicoCss HTML output.
 */
class PicoFormBuilder extends AbstractFormBuilder {
	protected function wrapField(array $attributes, ?string $label, ?string $error, ?callable $renderControl = null): string {
		// 1. Extract and unset builder-specific wrapper attributes
		$addon = $attributes['addon'] ?? null;
		$icon = $attributes['icon'] ?? null;
		$readonly = $attributes['readonly'] ?? null;
		$disabled = $attributes['disabled'] ?? false ? ' disabled' : '';
		unset($attributes['addon'], $attributes['icon']);

		// 2. Render the actual input with clean attributes
		$inputHtml = $renderControl ? $renderControl($attributes) : '';

		// 3. Assemble wrapper
		$body = $label !== null ? '<label>' . Html::h($label) : '';

		if ($addon || $icon) {
			$body .= '<fieldset role="group">';
		}

		$body .= $inputHtml;

		if ($addon) {
			$body .= '<span role="button"' . ($readonly ? ' class="outline"' : '') . $disabled . '>' . Html::h($addon) . '</span></fieldset>';
		}
		if ($icon) {
			$body .=
				'<span role="button" class="icon icon-'
				. Html::h($icon)
				. ($readonly ? ' outline secondary' : '')
				. ' just-icon"'
				. $disabled
				. '></span></fieldset>';
		}

		if ($error !== null) {
			$body .= '<small>' . Lingo::get($error) . '</small>';
		}

		if ($label !== null) {
			$body .= '</label>';
		}

		return $body;
	}

	protected function wrapCheckable(array $attributes, ?string $label): string {
		$labelHtml = $label !== null ? Html::h($label) : '';
		$idAttr = Html::h($attributes['id'] ?? $attributes['name']);
		$nameAttr = Html::h($attributes['name']);

		return <<<HTML
			    <input type="hidden" name="{$nameAttr}" value="0">
			    <label for="{$idAttr}"><input{$this->attributes($attributes)}>{$labelHtml}</label>
			HTML;
	}

	protected function applyFieldStateClasses(array $attributes, ?string $error, bool $isSelect = false): array {
		if ($error !== null) {
			$attributes['aria-invalid'] = 'true';
			$attributes['onfocus'] = 'this.removeAttribute(\'aria-invalid\')';
			unset($attributes['autofocus']);
		}

		return $attributes;
	}
}
