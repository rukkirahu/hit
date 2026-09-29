<?php

declare(strict_types=1);

namespace Tests\Unit;

use Hit\Lingo\Lingo;
use Hit\View\Component\Form;
use Hit\View\Component\FormBuilder\PicoFormBuilder;
use Hit\View\Component\FormBuilder\PlainFormBuilder;

beforeAll(function (): void {
	$dir = sys_get_temp_dir() . '/hit_view_form_test_' . uniqid() . '/';
	mkdir($dir);
	Lingo::init('en', $dir);
});

beforeEach(function (): void {
	Form::reset();
});

describe('Form facade', function (): void {
	it('defaults to PlainFormBuilder instance', function (): void {
		expect(Form::instance())->toBeInstanceOf(PlainFormBuilder::class);
	});

	it('allows swapping the builder with Form::use()', function (): void {
		$pico = new PicoFormBuilder();
		Form::use($pico);

		expect(Form::instance())->toBe($pico);
	});

	it('resets the builder instance with Form::reset()', function (): void {
		Form::use(new PicoFormBuilder());
		Form::reset();

		expect(Form::instance())->toBeInstanceOf(PlainFormBuilder::class);
	});

	it('forwards static method calls to the active builder', function (): void {
		$html = Form::text('username', 'indrek');

		expect($html)
			->toContain('type="text"')
			->and($html)
			->toContain('name="username"')
			->and($html)
			->toContain('value="indrek"');
	});

	it('throws BadMethodCallException when calling non-existent methods', function (): void {
		Form::__callStatic('nonExistentMethod', []);
	})->throws(\BadMethodCallException::class);
});

describe('PlainFormBuilder - form & structural elements', function (): void {
	it('renders open form tag with default POST method', function (): void {
		$builder = new PlainFormBuilder();
		$html = $builder->open('/submit');

		expect($html)->toBe('<form action="/submit" method="POST">');
	});

	it('renders open form tag with GET method', function (): void {
		$builder = new PlainFormBuilder();
		$html = $builder->open('/search', 'GET');

		expect($html)->toBe('<form action="/search" method="GET">');
	});

	it('handles method spoofing for PUT, PATCH, DELETE', function (): void {
		$builder = new PlainFormBuilder();
		$html = $builder->open('/users/1', 'PUT');

		expect($html)
			->toContain('method="POST"')
			->and($html)
			->toContain('<input type="hidden" name="_method" value="PUT">');
	});

	it('adds multipart enctype when requested', function (): void {
		$builder = new PlainFormBuilder();
		$html = $builder->open('/upload', 'POST', multipart: true);

		expect($html)->toBe('<form action="/upload" method="POST" enctype="multipart/form-data">');
	});

	it('renders closing form tag', function (): void {
		$builder = new PlainFormBuilder();

		expect($builder->close())->toBe('</form>');
	});

	it('renders CSRF hidden input', function (): void {
		$builder = new PlainFormBuilder();
		$html = $builder->csrf('token123');

		expect($html)->toBe('<input type="hidden" name="_token" value="token123">');
	});

	it('renders hidden input and escapes value', function (): void {
		$builder = new PlainFormBuilder();
		$html = $builder->hidden('id', '<123>');

		expect($html)->toBe('<input type="hidden" name="id" value="&lt;123&gt;">');
	});

	it('renders label with escaped text', function (): void {
		$builder = new PlainFormBuilder();
		$html = $builder->label('email_id', 'Email <address>');

		expect($html)->toBe('<label for="email_id">Email &lt;address&gt;</label>');
	});
});

describe('PlainFormBuilder - inputs & state repopulation', function (): void {
	it('renders basic text input with default id', function (): void {
		$builder = new PlainFormBuilder();
		$html = $builder->text('username', 'john');

		expect($html)->toBe('<input type="text" name="username" id="username" value="john">');
	});

	it('repopulates value from old input if value is null', function (): void {
		$builder = new PlainFormBuilder(old: ['title' => 'Old Title']);
		$html = $builder->text('title');

		expect($html)->toContain('value="Old Title"');
	});

	it('prefers explicit value over old input', function (): void {
		$builder = new PlainFormBuilder(old: ['title' => 'Old Title']);
		$html = $builder->text('title', 'Explicit Title');

		expect($html)->toContain('value="Explicit Title"');
	});

	it('wraps input in label if label is provided', function (): void {
		$builder = new PlainFormBuilder();
		$html = $builder->text('name', label: 'Your Name');

		expect($html)->toBe('<label>Your Name</label><input type="text" name="name" id="name" value="">');
	});

	it('renders error message and adds is-invalid class', function (): void {
		$builder = new PlainFormBuilder(errors: ['email' => 'Invalid email address']);
		$html = $builder->email('email', 'bad-email');

		expect($html)
			->toContain('class="is-invalid"')
			->and($html)
			->toContain('<div class="form-error">Invalid email address</div>');
	});

	it('renders specific input types', function (): void {
		$builder = new PlainFormBuilder();

		expect($builder->email('em'))
			->toContain('type="email"')
			->and($builder->password('pw'))
			->toContain('type="password"')
			->and($builder->file('doc'))
			->toContain('type="file"')
			->and($builder->date('d'))
			->toContain('type="date"')
			->and($builder->time('t'))
			->toContain('type="time"')
			->and($builder->datetimeLocal('dt'))
			->toContain('type="datetime-local"');
	});

	it('renders boolean attributes properly and omits false or null', function (): void {
		$builder = new PlainFormBuilder();
		$html = $builder->text('q', attributes: ['required' => true, 'disabled' => false, 'title' => null]);

		expect($html)
			->toContain(' required')
			->and($html)
			->not->toContain('disabled')->and($html)
			->not->toContain('title');
	});
});

describe('PlainFormBuilder - textarea & select', function (): void {
	it('renders textarea with escaped value', function (): void {
		$builder = new PlainFormBuilder();
		$html = $builder->textarea('bio', '<script>alert(1)</script>', rows: 5);

		expect($html)->toBe('<textarea name="bio" id="bio" rows="5">&lt;script&gt;alert(1)&lt;/script&gt;</textarea>');
	});

	it('repopulates textarea value from old input', function (): void {
		$builder = new PlainFormBuilder(old: ['comment' => 'Saved text']);
		$html = $builder->textarea('comment');

		expect($html)->toContain('>Saved text</textarea>');
	});

	it('renders select with options and selected value', function (): void {
		$builder = new PlainFormBuilder();
		$html = $builder->select('role', ['admin' => 'Admin', 'user' => 'User'], selected: 'user');

		expect($html)
			->toContain('<select name="role" id="role">')
			->and($html)
			->toContain('<option value="admin">Admin</option>')
			->and($html)
			->toContain('<option value="user" selected>User</option>');
	});

	it('renders select placeholder', function (): void {
		$builder = new PlainFormBuilder();
		$html = $builder->select('item', ['1' => 'First'], placeholder: 'Choose an item...');

		expect($html)->toContain('<option value="">Choose an item...</option>');
	});

	it('renders select optgroups for nested option arrays', function (): void {
		$builder = new PlainFormBuilder();
		$options = [
			'Europe' => ['ee' => 'Estonia', 'lv' => 'Latvia'],
			'America' => ['us' => 'United States'],
		];
		$html = $builder->select('country', $options, selected: 'ee');

		expect($html)
			->toContain('<optgroup label="Europe">')
			->and($html)
			->toContain('<option value="ee" selected>Estonia</option>')
			->and($html)
			->toContain('</optgroup>')
			->and($html)
			->toContain('<optgroup label="America">');
	});

	it('appends [] to select name when multiple is set', function (): void {
		$builder = new PlainFormBuilder();
		$html = $builder->select('tags', ['a' => 'A', 'b' => 'B'], selected: ['a', 'b'], attributes: ['multiple' => true]);

		expect($html)
			->toContain('name="tags[]"')
			->and($html)
			->toContain('<option value="a" selected>A</option>')
			->and($html)
			->toContain('<option value="b" selected>B</option>');
	});

	it('repopulates select from old input', function (): void {
		$builder = new PlainFormBuilder(old: ['role' => 'admin']);
		$html = $builder->select('role', ['admin' => 'Admin', 'user' => 'User']);

		expect($html)->toContain('<option value="admin" selected>Admin</option>');
	});
});

describe('PlainFormBuilder - checkables and buttons', function (): void {
	it('renders checkbox with default value 1 and label', function (): void {
		$builder = new PlainFormBuilder();
		$html = $builder->checkbox('agree', label: 'I accept');

		expect($html)->toBe('<label><input type="checkbox" name="agree" id="agree" value="1"> I accept</label>');
	});

	it('renders checked checkbox when checked is true or old input matches', function (): void {
		$builder = new PlainFormBuilder(old: ['opt_in' => 'yes']);
		$html = $builder->checkbox('opt_in', value: 'yes', label: 'Opt In');

		expect($html)->toContain('checked');
	});

	it('renders radio button with generated id per value', function (): void {
		$builder = new PlainFormBuilder();
		$html = $builder->radio('gender', 'female', label: 'Female');

		expect($html)->toBe('<label><input type="radio" name="gender" id="gender_female" value="female"> Female</label>');
	});

	it('renders submit button and generic button', function (): void {
		$builder = new PlainFormBuilder();

		expect($builder->submit('Save'))
			->toBe('<button type="submit">Save</button>')
			->and($builder->button('Cancel', type: 'reset'))
			->toBe('<button type="reset">Cancel</button>');
	});
});

describe('PicoFormBuilder', function (): void {
	it('wraps fields with label around the input', function (): void {
		$builder = new PicoFormBuilder();
		$html = $builder->text('username', label: 'Username');

		expect($html)->toBe('<label>Username<input type="text" name="username" id="username" value=""></label>');
	});

	it('renders input groups with addons and unsets wrapper attributes from input', function (): void {
		$builder = new PicoFormBuilder();
		$html = $builder->text('domain', attributes: ['addon' => '.com']);

		expect($html)
			->toContain('<fieldset role="group">')
			->and($html)
			->toContain('<input type="text" name="domain" id="domain" value="">')
			->and($html)
			->toContain('<span role="button">.com</span></fieldset>')
			->and($html)
			->not->toContain('addon=');
	});

	it('renders input groups with icons and unsets wrapper attributes from input', function (): void {
		$builder = new PicoFormBuilder();
		$html = $builder->text('search', attributes: ['icon' => 'search']);

		expect($html)
			->toContain('<fieldset role="group">')
			->and($html)
			->toContain('<span role="button" class="icon icon-search just-icon"></span></fieldset>')
			->and($html)
			->not->toContain('icon=');
	});

	it('handles validation error with aria-invalid and small error tag', function (): void {
		$builder = new PicoFormBuilder(errors: ['email' => 'Required field']);
		$html = $builder->email('email', attributes: ['autofocus' => true]);

		expect($html)
			->toContain('aria-invalid="true"')
			->and($html)
			->toContain('onfocus="this.removeAttribute(&#039;aria-invalid&#039;)"')
			->and($html)
			->toContain('<small>Required field</small>')
			->and($html)
			->not->toContain('autofocus');
	});

	it('renders checkable with hidden 0 fallback and label', function (): void {
		$builder = new PicoFormBuilder();
		$html = $builder->checkbox('terms', label: 'Agree to terms');

		expect($html)
			->toContain('<input type="hidden" name="terms" value="0">')
			->and($html)
			->toContain('<label for="terms"><input type="checkbox" name="terms" id="terms" value="1">Agree to terms</label>');
	});
});
