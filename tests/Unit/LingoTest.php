<?php

namespace Tests\Unit;

use Hit\Lingo\Lingo;
use Hit\Lingo\Translator;

// Helper: create a temp translations directory with optional language files
function translationsDir(array $files = []): string {
	$dir = sys_get_temp_dir() . '/lingo_test_' . uniqid();
	mkdir($dir, 0777, true);

	foreach ($files as $lang => $translations) {
		$exported = var_export($translations, true);
		file_put_contents($dir . '/lingo.' . $lang . '.php', "<?php\n\nreturn " . $exported . ";\n");
	}

	return $dir;
}

// Reset facade between tests
beforeEach(fn() => Lingo::reset());

// ---------------------------------------------------------------------------
// Basic get()
// ---------------------------------------------------------------------------

test('returns translated value for existing key', function () {
	$dir = translationsDir(['en' => ['greeting' => 'Hello']]);
	Lingo::init('en', $dir);

	expect(Lingo::get('greeting'))->toBe('Hello');
});

test('falls back to key when translation is missing', function () {
	$dir = translationsDir(['en' => []]);
	Lingo::init('en', $dir);

	expect(Lingo::get('greeting'))->toBe('greeting');
});

test('falls back to key when translation file does not exist', function () {
	$dir = translationsDir(); // empty dir, no files
	Lingo::init('en', $dir);

	expect(Lingo::get('greeting'))->toBe('greeting');
});

// ---------------------------------------------------------------------------
// Placeholders
// ---------------------------------------------------------------------------

test('replaces positional placeholders in translation', function () {
	$dir = translationsDir(['en' => ['msg %1 %2' => 'Hello %1, you are %2']]);
	Lingo::init('en', $dir);

	expect(Lingo::get('msg %1 %2', 'World', 'great'))->toBe('Hello World, you are great');
});

test('replaces placeholders in fallback key string', function () {
	$dir = translationsDir(['en' => []]);
	Lingo::init('en', $dir);

	// Key itself is returned as value, so placeholders in key are replaced
	expect(Lingo::get('Hello %1', 'World'))->toBe('Hello World');
});

test('handles single placeholder', function () {
	$dir = translationsDir(['en' => ['name %1' => 'My name is %1']]);
	Lingo::init('en', $dir);

	expect(Lingo::get('name %1', 'Alice'))->toBe('My name is Alice');
});

test('throws when more than 9 positional args are passed', function () {
	$dir = translationsDir(['en' => []]);
	Lingo::init('en', $dir);

	Lingo::get('key', 'a', 'b', 'c', 'd', 'e', 'f', 'g', 'h', 'i', 'j');
})->throws(\RuntimeException::class);

test('accepts exactly 9 positional args without throwing', function () {
	$dir = translationsDir(['en' => ['k' => '%1%2%3%4%5%6%7%8%9']]);
	Lingo::init('en', $dir);

	expect(Lingo::get('k', 'a', 'b', 'c', 'd', 'e', 'f', 'g', 'h', 'i'))->toBe('abcdefghi');
});

// ---------------------------------------------------------------------------
// Dirty flag
// ---------------------------------------------------------------------------

test('instance is dirty when a key is missing', function () {
	$dir = translationsDir(['en' => []]);
	Lingo::init('en', $dir);
	Lingo::get('missing');

	expect(Lingo::getInstance()->isDirty())->toBeTrue();
});

test('instance is not dirty when all keys are present', function () {
	$dir = translationsDir(['en' => ['hello' => 'Hi']]);
	Lingo::init('en', $dir);
	Lingo::get('hello');

	expect(Lingo::getInstance()->isDirty())->toBeFalse();
});

// ---------------------------------------------------------------------------
// Double init / reset
// ---------------------------------------------------------------------------

test('second init() resets and reinitializes', function () {
	$dir1 = translationsDir(['en' => ['greeting' => 'Hello']]);
	$dir2 = translationsDir(['en' => ['greeting' => 'Hi']]);

	Lingo::init('en', $dir1);
	expect(Lingo::get('greeting'))->toBe('Hello');

	Lingo::init('en', $dir2);
	expect(Lingo::get('greeting'))->toBe('Hi');
});

test('get() throws RuntimeException when called before init()', function () {
	Lingo::get('anything');
})->throws(\RuntimeException::class, 'Lingo not initialized');

// ---------------------------------------------------------------------------
// Dev mode: file save
// ---------------------------------------------------------------------------

test('dev mode writes missing keys to all language files on save', function () {
	$dir = translationsDir([
		'en' => ['existing' => 'Existing'],
		'et' => ['existing' => 'Olemasolev'],
	]);

	$instance = new Translator('en', $dir);
	$instance->enableDevMode(['et']);

	$instance->get('new_key'); // missing in both languages

	$instance->save();

	// Check active language file
	$en = require $dir . '/lingo.en.php';
	expect($en)->toHaveKey('new_key');
	expect($en['new_key'])->toBe('new_key');
	expect($en['existing'])->toBe('Existing'); // preserved

	// Check dev language file
	$et = require $dir . '/lingo.et.php';
	expect($et)->toHaveKey('new_key');
	expect($et['new_key'])->toBe('new_key');
	expect($et['existing'])->toBe('Olemasolev'); // preserved
});

test('dev mode creates translation file if it does not exist', function () {
	$dir = translationsDir(); // empty dir

	$instance = new Translator('en', $dir);
	$instance->enableDevMode(['fr']);
	$instance->get('hello');

	$instance->save();

	expect(file_exists($dir . '/lingo.en.php'))->toBeTrue();
	expect(file_exists($dir . '/lingo.fr.php'))->toBeTrue();

	$en = require $dir . '/lingo.en.php';
	$fr = require $dir . '/lingo.fr.php';

	expect($en['hello'])->toBe('hello');
	expect($fr['hello'])->toBe('hello');
});

test('no file write occurs when store is not dirty', function () {
	$dir = translationsDir(['en' => ['hello' => 'Hello']]);

	$instance = new Translator('en', $dir);
	$instance->enableDevMode([]);
	$instance->get('hello'); // key exists, not dirty

	$mtime = filemtime($dir . '/lingo.en.php');
	sleep(1);

	$instance->save();

	expect(filemtime($dir . '/lingo.en.php'))->toBe($mtime);
});

// ---------------------------------------------------------------------------
// getInstance()
// ---------------------------------------------------------------------------

test('getInstance() returns the Translator', function () {
	$dir = translationsDir(['en' => []]);
	Lingo::init('en', $dir);

	expect(Lingo::getInstance())->toBeInstanceOf(Translator::class);
});
