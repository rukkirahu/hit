<?php

declare(strict_types=1);

namespace Hit\View\Component\FormBuilder;

use Hit\View\Component\Html;

/**
 * Shared logic for every themed builder: escaping, attribute rendering,
 * old-input repopulation, and error lookup. Themed subclasses only need to
 * override wrapField() (and a couple of markup specifics) to change how a
 * field looks — the field-building methods themselves live here once.
 */
abstract class AbstractFormBuilder implements FormBuilderInterface {
	/**
	 * @param array<string,mixed> $old Old input values, keyed by field name (e.g. $_POST after a failed submit).
	 * @param array<string,string> $errors Validation error messages, keyed by field name.
	 */
	public function __construct(
		protected array $old = [],
		protected array $errors = [],
	) {}

	// ------------------------------------------------------------------
	// Structural elements (same across themes; subclasses may still override)
	// ------------------------------------------------------------------

	public function open(string $action = '', string $method = 'POST', bool $multipart = false, array $attributes = []): string {
		$httpMethod = strtoupper($method);
		$formMethod = in_array($httpMethod, ['GET', 'POST'], true) ? $httpMethod : 'POST';

		$attributes['action'] = $action;
		$attributes['method'] = $formMethod;
		if ($multipart) {
			$attributes['enctype'] = 'multipart/form-data';
		}

		$html = '<form' . $this->attributes($attributes) . '>';

		if (!in_array($httpMethod, ['GET', 'POST'], true)) {
			$html .= $this->hidden(name: '_method', value: $httpMethod);
		}

		return $html;
	}

	public function close(): string {
		return '</form>';
	}

	public function label(string $for, string $text, array $attributes = []): string {
		$attributes['for'] = $for;

		return '<label' . $this->attributes($attributes) . '>' . Html::h($text) . '</label>';
	}

	public function csrf(#[\SensitiveParameter] string $token): string {
		return $this->hidden(name: '_token', value: $token);
	}

	public function hidden(string $name, ?string $value = null, array $attributes = []): string {
		$attributes['type'] = 'hidden';
		$attributes['name'] = $name;
		$attributes['value'] = (string) ($value ?? $this->oldValue($name, ''));

		return '<input' . $this->attributes($attributes) . '>';
	}

	// ------------------------------------------------------------------
	// Field methods — theme-agnostic assembly, delegating markup to hooks
	// ------------------------------------------------------------------

	public function text(string $name, ?string $value = null, ?string $label = null, array $attributes = []): string {
		return $this->basicInput('text', $name, $value, $label, $attributes);
	}

	public function email(string $name, ?string $value = null, ?string $label = null, array $attributes = []): string {
		return $this->basicInput('email', $name, $value, $label, $attributes);
	}

	public function password(string $name, ?string $label = null, array $attributes = []): string {
		return $this->basicInput('password', $name, '', $label, $attributes);
	}

	public function file(string $name, ?string $label = null, array $attributes = []): string {
		return $this->basicInput('file', $name, '', $label, $attributes);
	}

	public function time(string $name, ?string $value = null, ?string $label = null, array $attributes = []): string {
		return $this->basicInput('time', $name, $value, $label, $attributes);
	}

	public function date(string $name, ?string $value = null, ?string $label = null, array $attributes = []): string {
		return $this->basicInput('date', $name, $value, $label, $attributes);
	}

	public function datetimeLocal(string $name, ?string $value = null, ?string $label = null, array $attributes = []): string {
		return $this->basicInput('datetime-local', $name, $value, $label, $attributes);
	}

	// In AbstractFormBuilder.php
	public function basicInput(string $type, string $name, ?string $value, ?string $label, array $attributes): string {
		$resolvedValue = $value ?? $this->oldValue($name, '');
		$error = $this->errorFor($name);

		$attributes['type'] = $type;
		$attributes['name'] = $name;
		$attributes['id'] ??= $name;
		$attributes['value'] = (string) $resolvedValue;

		$attributes = $this->applyFieldStateClasses($attributes, $error);

		return $this->wrapField($attributes, $label, $error, fn(array $attrs) => '<input' . $this->attributes($attrs) . '>');
	}

	public function textarea(
		string $name,
		?string $value = null,
		?string $label = null,
		int $rows = 4,

		array $attributes = [],
	): string {
		$resolvedValue = $value ?? $this->oldValue($name, '');
		$error = $this->errorFor($name);

		$attributes['name'] = $name;
		$attributes['id'] ??= $name;
		$attributes['rows'] = $rows;

		$attributes = $this->applyFieldStateClasses($attributes, $error);

		return $this->wrapField(
			$attributes,
			$label,
			$error,
			fn(array $attrs) => '<textarea' . $this->attributes($attrs) . '>' . Html::h((string) $resolvedValue) . '</textarea>',
		);
	}

	public function select(
		string $name,
		array $options,
		string|array|null $selected = null,
		?string $label = null,
		?string $placeholder = null,
		array $attributes = [],
	): string {
		$resolvedSelected = $selected ?? $this->oldValue($name);
		$selectedValues = array_map('strval', (array) $resolvedSelected);
		$error = $this->errorFor($name);

		$attributes['name'] = $name . ($attributes['multiple'] ?? false ? '[]' : '');
		$attributes['id'] ??= $name;

		$attributes = $this->applyFieldStateClasses($attributes, $error, isSelect: true);
		// $labelHtml = $label !== null ? $this->label(for: $attributes['id'], text: $label) : '';

		$optionsHtml = $placeholder !== null ? '<option value="">' . Html::h($placeholder) . '</option>' : '';
		$optionsHtml .= $this->renderOptions($options, $selectedValues);

		return $this->wrapField($attributes, $label, $error, fn(array $attrs) => '<select' . $this->attributes($attrs) . '>' . $optionsHtml . '</select>');
	}

	private function renderOptions(array $options, array $selectedValues): string {
		$html = '';
		foreach ($options as $value => $optionLabel) {
			if (is_array($optionLabel)) {
				$html .= '<optgroup label="' . Html::h((string) $value) . '">' . $this->renderOptions($optionLabel, $selectedValues) . '</optgroup>';
				continue;
			}
			$isSelected = in_array((string) $value, $selectedValues, true);
			$html .= '<option value="' . Html::h((string) $value) . '"' . ($isSelected ? ' selected' : '') . '>' . Html::h((string) $optionLabel) . '</option>';
		}

		return $html;
	}

	public function checkbox(string $name, string $value = '1', ?bool $checked = null, ?string $label = null, array $attributes = []): string {
		return $this->checkable('checkbox', $name, $value, $checked, $label, $attributes);
	}

	public function radio(string $name, string $value, ?bool $checked = null, ?string $label = null, array $attributes = []): string {
		return $this->checkable('radio', $name, $value, $checked, $label, $attributes);
	}

	protected function checkable(string $type, string $name, string $value, ?bool $checked, ?string $label, array $attributes): string {
		$resolvedChecked = $checked ?? (string) $this->oldValue($name) === $value;

		$attributes['type'] = $type;
		$attributes['name'] = $name;
		$attributes['id'] ??= $name . ($type === 'radio' ? '_' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $value) : '');
		$attributes['value'] = $value;
		if ($resolvedChecked) {
			$attributes['checked'] = true;
		}

		return $this->wrapCheckable($attributes, $label);
	}

	public function submit(string $text = 'Submit', array $attributes = []): string {
		$attributes['type'] = 'submit';

		return '<button' . $this->attributes($attributes) . '>' . Html::h($text) . '</button>';
	}

	public function button(string $text, string $type = 'button', array $attributes = []): string {
		$attributes['type'] = $type;

		return '<button' . $this->attributes($attributes) . '>' . Html::h($text) . '</button>';
	}

	// ------------------------------------------------------------------
	// Theme hooks — override these in a subclass to change how fields look
	// ------------------------------------------------------------------

	/** Wraps a label + input + error into the theme's field markup (e.g. a Bootstrap `.mb-3` div). */
	abstract protected function wrapField(array $attributes, ?string $label, ?string $error, ?callable $renderControl = null): string;

	/** Wraps a checkbox/radio input + its label into the theme's markup (e.g. Bootstrap `.form-check`). */
	abstract protected function wrapCheckable(array $attributes, ?string $label): string;

	/** Adds theme-specific state classes (e.g. `form-control`, `is-invalid`) to a field's attributes. */
	abstract protected function applyFieldStateClasses(array $attributes, ?string $error, bool $isSelect = false): array;

	// ------------------------------------------------------------------
	// Internals shared by every theme
	// ------------------------------------------------------------------

	protected function oldValue(string $name, mixed $default = null): mixed {
		return $this->old[$name] ?? $default;
	}

	protected function errorFor(string $name): ?string {
		return $this->errors[$name] ?? null;
	}

	protected function attributes(array $attributes): string {
		$html = '';
		foreach ($attributes as $key => $value) {
			if ($value === false || $value === null) {
				continue;
			}
			if ($value === true) {
				$html .= ' ' . Html::h((string) $key);
				continue;
			}
			$html .= ' ' . Html::h((string) $key) . '="' . Html::h((string) $value) . '"';
		}

		return $html;
	}
}
