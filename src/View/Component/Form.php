<?php

declare(strict_types=1);

namespace Hit\View\Component;

use Hit\View\Component\FormBuilder\FormBuilderInterface;
use Hit\View\Component\FormBuilder\PlainFormBuilder;

/**
 * Static facade over a swappable FormBuilderInterface instance.
 *
 * Form::use() picks the active theme/builder for the current request.
 * Every other Form::xxx() call is forwarded to that instance via __callStatic,
 * so call sites (Form::text(...), Form::select(...)) never change when you
 * switch themes — only the Form::use() line does.
 *
 * NOTE: the active builder is a single global slot. That's fine for the
 * common case of one form (and one $old/$errors set) per request. If you
 * need two independent forms with different data in the same response,
 * instantiate the builders directly instead of going through this facade:
 *
 *   $loginForm    = new BootstrapFormBuilder(old: $old1, errors: $errors1);
 *   $registerForm = new BootstrapFormBuilder(old: $old2, errors: $errors2);
 *
 * @method static string open(string $action = '', string $method = 'POST', bool $multipart = false, array $attributes = [])
 * @method static string close()
 * @method static string label(string $for, string $text, array $attributes = [])
 * @method static string csrf(string $token)
 * @method static string hidden(string $name, ?string $value = null, array $attributes = [])
 * @method static string text(string $name, ?string $value = null, ?string $label = null, array $attributes = [])
 * @method static string email(string $name, ?string $value = null, ?string $label = null, array $attributes = [])
 * @method static string password(string $name, ?string $label = null, array $attributes = [])
 * @method static string file(string $name, ?string $label = null, array $attributes = [])
 * @method static string date(string $name, ?string $value = null, ?string $label = null, array $attributes = [])
 * @method static string time(string $name, ?string $value = null, ?string $label = null, array $attributes = [])
 * @method static string datetimeLocal(string $name, ?string $value = null, ?string $label = null, array $attributes = [])
 * @method static string textarea(string $name, ?string $value = null, ?string $label = null, int $rows = 4, array $attributes = [])
 * @method static string select(string $name, array $options, string|array|null $selected = null, ?string $label = null, ?string $placeholder = null, array $attributes = [])
 * @method static string checkbox(string $name, string $value = '1', ?bool $checked = null, ?string $label = null, array $attributes = [])
 * @method static string radio(string $name, string $value, ?bool $checked = null, ?string $label = null, array $attributes = [])
 * @method static string submit(string $text = 'Submit', array $attributes = [])
 * @method static string button(string $text, string $type = 'button', array $attributes = [])
 */
final class Form {
	private static ?FormBuilderInterface $builder = null;

	private function __construct() {
		// Not instantiable — this class is a proxy only.
	}

	/** Sets the active builder for subsequent Form::xxx() calls in this request. */
	public static function use(FormBuilderInterface $builder): void {
		self::$builder = $builder;
	}

	/** Returns the active builder, defaulting to a plain, state-free one if none was set. */
	public static function instance(): FormBuilderInterface {
		return self::$builder ??= new PlainFormBuilder();
	}

	/** Clears the active builder (mainly useful between tests). */
	public static function reset(): void {
		self::$builder = null;
	}

	public static function __callStatic(string $name, array $arguments): mixed {
		$builder = self::instance();

		if (!method_exists($builder, $name)) {
			throw new \BadMethodCallException(sprintf('Form::%s() is not defined on %s', $name, $builder::class));
		}

		return $builder->{$name}(...$arguments);
	}
}
