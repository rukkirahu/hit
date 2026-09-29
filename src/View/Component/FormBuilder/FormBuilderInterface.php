<?php

declare(strict_types=1);

namespace Hit\View\Component\FormBuilder;

/**
 * Contract every themed form builder must satisfy (Plain, Bootstrap, Tailwind, ...).
 * Keeping this as an interface is what makes Form::use() a real strategy swap
 * rather than just inheritance for its own sake.
 */
interface FormBuilderInterface {
	public function open(string $action = '', string $method = 'POST', bool $multipart = false, array $attributes = []): string;

	public function close(): string;

	public function label(string $for, string $text, array $attributes = []): string;

	public function csrf(#[\SensitiveParameter] string $token): string;

	public function hidden(string $name, ?string $value = null, array $attributes = []): string;

	public function text(string $name, ?string $value = null, ?string $label = null, array $attributes = []): string;

	public function email(string $name, ?string $value = null, ?string $label = null, array $attributes = []): string;

	public function password(string $name, ?string $label = null, array $attributes = []): string;

	public function file(string $name, ?string $label = null, array $attributes = []): string;

	public function date(string $name, ?string $value = null, ?string $label = null, array $attributes = []): string;

	public function time(string $name, ?string $value = null, ?string $label = null, array $attributes = []): string;

	public function datetimeLocal(string $name, ?string $value = null, ?string $label = null, array $attributes = []): string;

	public function textarea(string $name, ?string $value = null, ?string $label = null, int $rows = 4, array $attributes = []): string;

	/** @param array<int|string,string|array<int|string,string>> $options */
	public function select(
		string $name,
		array $options,
		string|array|null $selected = null,
		?string $label = null,
		?string $placeholder = null,
		array $attributes = [],
	): string;

	public function checkbox(string $name, string $value = '1', ?bool $checked = null, ?string $label = null, array $attributes = []): string;

	public function radio(string $name, string $value, ?bool $checked = null, ?string $label = null, array $attributes = []): string;

	public function submit(string $text = 'Submit', array $attributes = []): string;

	public function button(string $text, string $type = 'button', array $attributes = []): string;
}
