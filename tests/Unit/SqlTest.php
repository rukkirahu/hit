<?php

declare(strict_types=1);

namespace Tests\Unit;

use Exception;
use Hit\Db\Database;
use Hit\Db\Query;
use Hit\Db\Sql;
use Hit\Db\Sql\Expression;
use Hit\Db\Sql\Value;
use PDO;
use Tests\Fixtures\Db\SqlBuilderDomain;
use Tests\Fixtures\Db\SqlRoleDomain;

/**
 * Build a real Query backed by a mock PDO (no DB connection), so the static
 * Sql builders can be exercised against genuine Query objects.
 */
function sql_builder_query(PDO $pdo): Query {
	return new Query(new SqlBuilderDomain(new Database(database: 'test', pdo: $pdo)));
}

// --- name ----------------------------------------------------------------

test('name quotes table and field', function () {
	expect(Sql::name('users', 'name'))->toBe('`users`.`name`');
});

test('name quotes field only when table is empty', function () {
	expect(Sql::name('', 'name'))->toBe('`name`');
});

test('name splits dotted field into table and column', function () {
	expect(Sql::name('users', 'acl.role'))->toBe('`acl`.`role`');
});

test('name returns numeric fields as floats', function () {
	expect(Sql::name('users', 123))->toBe('123');
	expect(Sql::name('users', '42'))->toBe('42');
});

test('name returns empty string for empty field', function () {
	expect(Sql::name('users', ''))->toBe('');
});

test('name throws on invalid field', function () {
	Sql::name('users', 'bad name!');
})->throws(Exception::class);

// --- parseCondition ------------------------------------------------------

test('parseCondition defaults to equality', function () {
	expect(Sql::parseCondition('id', 5))->toBe('`id` = 5');
});

test('parseCondition honours an explicit operator', function () {
	expect(Sql::parseCondition('count >', 5))->toBe('`count` > 5');
});

test('parseCondition qualifies the key with a table', function () {
	expect(Sql::parseCondition('id', 5, 'User'))->toBe('`User`.`id` = 5');
});

test('parseCondition maps arrays to IN', function () {
	expect(Sql::parseCondition('id', [1, 2, 3]))->toBe('`id` IN (1,2,3)');
});

test('parseCondition maps not-equal arrays to NOT IN', function () {
	expect(Sql::parseCondition('id !=', [1, 2]))->toBe('`id` NOT IN (1,2)');
});

test('parseCondition builds BETWEEN ranges', function () {
	expect(Sql::parseCondition('age BETWEEN', [1, 10]))->toBe('`age` BETWEEN (1 AND 10)');
});

test('parseCondition maps null to IS NULL', function () {
	expect(Sql::parseCondition('name', null))->toBe('`name` IS NULL');
	expect(Sql::parseCondition('name', 'NULL'))->toBe('`name` IS NULL');
});

test('parseCondition maps not-equal null to IS NOT NULL', function () {
	expect(Sql::parseCondition('name !=', null))->toBe('`name` IS NOT NULL');
});

test('parseCondition wraps an Expression as a subquery', function () {
	$expr = new Expression('SELECT id FROM roles');

	expect(Sql::parseCondition('id', $expr))->toBe('`id` IN (SELECT id FROM roles)');
});

test('parseCondition resolves a dotted string value to a column reference', function () {
	expect(Sql::parseCondition('id', 'acl.user_id'))->toBe('`id` = `acl`.`user_id`');
});

// --- getNextAttributeName ------------------------------------------------

// test('getNextAttributeName returns sequential named placeholders', function () {
// 	$first = Sql::getNextAttributeName();
// 	$second = Sql::getNextAttributeName();

// 	expect($first)->toMatch('/^:[a-z]{2}$/');
// 	expect($second)->toMatch('/^:[a-z]{2}$/');
// 	expect($first)->not->toBe($second);
// });

// --- fields --------------------------------------------------------------

test('fields quotes a simple field list', function () {
	expect(Sql::fields(['id' => null, 'name' => null]))->toBe([
		'id' => '`id`',
		'name' => '`name`',
	]);
});

test('fields uses the value as the column when provided', function () {
	expect(Sql::fields(['alias' => 'real_column']))->toBe(['alias' => '`real_column`']);
});

test('fields adds aliases when an alias name is given', function () {
	expect(Sql::fields(['id' => null], 'U', 'U'))->toBe([
		'U.id' => '`U`.`id` AS `U.id`',
	]);
});

test('fields returns empty array for the no-fields marker', function () {
	expect(Sql::fields([false]))->toBe([]);
});

test('fields with sql expression', function () {
	expect(Sql::fields(['count' => new Expression('COUNT(*)')]))->toBe(['count' => 'COUNT(*)']);
});

// --- groups --------------------------------------------------------------

test('groups quotes a list of field names', function () {
	expect(Sql::groups(['name', 'email'], 'U'))->toBe(['`U`.`name`', '`U`.`email`']);
});

test('groups uses the key when keyed by string', function () {
	expect(Sql::groups(['name' => 'ignored']))->toBe(['`name`']);
});

// --- orders --------------------------------------------------------------

test('orders quotes plain fields with direction', function () {
	expect(Sql::orders(['name' => 'ASC']))->toBe(['`name` ASC']);
});

test('orders preserves already-qualified fields', function () {
	expect(Sql::orders(['`U`.`id`' => 'DESC']))->toBe(['`U`.`id` DESC']);
});

// --- join ----------------------------------------------------------------

test('join builds a LEFT JOIN with an ON condition', function () {
	$sql = Sql::join('users', 'U', ['id' => 'P.user_id']);

	expect($sql)->toBe(' LEFT JOIN `users` AS `U` ON `U`.`id` = `P`.`user_id`');
});

test('join honours the join type', function () {
	$sql = Sql::join('users', 'U', ['id' => 'P.user_id'], 'INNER');

	expect($sql)->toBe(' INNER JOIN `users` AS `U` ON `U`.`id` = `P`.`user_id`');
});

test('join groups AND conditions in parentheses', function () {
	$sql = Sql::join('t', 'T', ['AND' => ['a' => 1, 'b' => 2]]);

	expect($sql)->toBe(' LEFT JOIN `t` AS `T` ON ( `T`.`a` = 1 AND `T`.`b` = 2 )');
});

test('join groups OR conditions in parentheses', function () {
	$sql = Sql::join('t', 'T', ['OR' => ['a' => 1, 'b' => 2]]);

	expect($sql)->toBe(' LEFT JOIN `t` AS `T` ON ( `T`.`a` = 1 OR `T`.`b` = 2 )');
});

// --- insert --------------------------------------------------------------

test('insert builds an INSERT statement', function () {
	/** @var Tests\TestCase $this */
	$query = sql_builder_query($this->createMock(PDO::class));

	$sql = Sql::insert(['name' => "'foo'", 'age' => 5], $query);

	expect($sql)->toBe("INSERT INTO `users` (`name`, `age`) VALUES ('foo', 5)");
});

test('insertRows builds a multi-row INSERT statement', function () {
	/** @var Tests\TestCase $this */
	$query = sql_builder_query($this->createMock(PDO::class));

	$sql = Sql::insertRows([['name' => ':v0', 'age' => '5'], ['name' => ':v1', 'age' => '6']], $query);

	expect($sql)->toBe('INSERT INTO `users` (`name`, `age`) VALUES (:v0, 5), (:v1, 6)');
});

test('insertRows rejects different columns', function () {
	/** @var Tests\TestCase $this */
	$query = sql_builder_query($this->createMock(PDO::class));

	Sql::insertRows([['name' => ':v0'], ['email' => ':v1']], $query);
})->throws(Exception::class, 'Insert rows must have identical columns');

test('value placeholders remain unique beyond 676 values', function () {
	$names = [];
	for ($i = 0; $i < 700; $i++) {
		$names[] = Value::getNextAttributeName();
	}

	expect(array_unique($names))->toHaveCount(700);
});

// --- update --------------------------------------------------------------

test('update builds an UPDATE statement with a WHERE clause', function () {
	/** @var Tests\TestCase $this */
	$query = sql_builder_query($this->createMock(PDO::class))->where(['id' => 1]);

	$sql = Sql::update(['name' => "'foo'"], $query);

	expect($sql)->toBe("UPDATE `users` SET `name` = 'foo' WHERE `id` = 1");
});

test('update builds an UPDATE statement with a WHERE clause and name', function () {
	/** @var Tests\TestCase $this */
	$query = sql_builder_query($this->createMock(PDO::class))->where(['id' => 1]);
	$query->name = 'U';

	$sql = Sql::update(['name' => "'foo'"], $query);

	expect($sql)->toBe("UPDATE `users` SET `name` = 'foo' WHERE `id` = 1");
});

test('update appends a LIMIT when present', function () {
	/** @var Tests\TestCase $this */
	$query = sql_builder_query($this->createMock(PDO::class))->where(['id' => 1])->limit([0, 5]);

	$sql = Sql::update(['name' => "'foo'"], $query);

	expect($sql)->toBe("UPDATE `users` SET `name` = 'foo' WHERE `id` = 1 LIMIT 0, 5");
});

test('update throws without a WHERE condition', function () {
	/** @var Tests\TestCase $this */
	$query = sql_builder_query($this->createMock(PDO::class));

	Sql::update(['name' => "'foo'"], $query);
})->throws(Exception::class);

// --- delete --------------------------------------------------------------

test('delete builds a DELETE statement with a WHERE clause', function () {
	/** @var Tests\TestCase $this */
	$query = sql_builder_query($this->createMock(PDO::class))->where(['id' => 1]);

	expect(Sql::delete($query))->toBe('DELETE FROM `users` WHERE `id` = 1');
});

test('delete throws without a WHERE condition', function () {
	/** @var Tests\TestCase $this */
	$query = sql_builder_query($this->createMock(PDO::class));

	Sql::delete($query);
})->throws(Exception::class);

// --- select --------------------------------------------------------------

test('select builds a basic SELECT', function () {
	/** @var Tests\TestCase $this */
	$query = sql_builder_query($this->createMock(PDO::class))->field(['id', 'name']);

	expect(Sql::select($query))->toBe('SELECT `id`, `name` FROM `users`');
});

test('select renders SELECT DISTINCT when the query is marked distinct', function () {
	/** @var Tests\TestCase $this */
	$query = sql_builder_query($this->createMock(PDO::class))->field(['name'])->distinct();

	expect(Sql::select($query))->toBe('SELECT DISTINCT `name` FROM `users`');
});

test('select omits DISTINCT again when the flag is turned off', function () {
	/** @var Tests\TestCase $this */
	$query = sql_builder_query($this->createMock(PDO::class))->field(['name'])->distinct()->distinct(false);

	expect(Sql::select($query))->toBe('SELECT `name` FROM `users`');
});

test('select builds a basic SELECT with where', function () {
	/** @var Tests\TestCase $this */
	$query = sql_builder_query($this->createMock(PDO::class))->field(['id', 'email'])->where(['email' => 'test@example.com']);

	expect(Sql::select($query))->toBe("SELECT `id`, `email` FROM `users` WHERE `email` = 'test@example.com'");
});

test('select groups OR conditions in WHERE', function () {
	/** @var Tests\TestCase $this */
	$query = sql_builder_query($this->createMock(PDO::class))
		->field(['id', 'email'])
		->where(['OR' => ['id' => 1, 'AND' => ['id' => 3, 'email' => 'test@example.com']]]);

	expect(Sql::select($query))->toBe("SELECT `id`, `email` FROM `users` WHERE ( `id` = 1 OR ( `id` = 3 AND `email` = 'test@example.com' ) )");
});

test('select builds a basic SELECT with where and dotted string value', function () {
	/** @var Tests\TestCase $this */
	$query = sql_builder_query($this->createMock(PDO::class))->field(['id', 'domain'])->where(['domain' => 'example.com']);
	$query->name = 'User';

	expect(Sql::select($query))
		->toBe("SELECT `User`.`id` AS `User.id`, `User`.`domain` AS `User.domain` FROM `users` AS `User` WHERE `User`.`domain` = 'example.com'");
});

test('select adds an alias when the query is named', function () {
	/** @var Tests\TestCase $this */
	$query = sql_builder_query($this->createMock(PDO::class))->field(['id']);
	$query->name = 'U';

	expect(Sql::select($query))->toBe('SELECT `U`.`id` AS `U.id` FROM `users` AS `U`');
});

test('select appends WHERE, GROUP BY, ORDER BY and LIMIT', function () {
	/** @var Tests\TestCase $this */
	$query = sql_builder_query($this->createMock(PDO::class))
		->field(['id'])
		->where(['id' => 1])
		->group(['name'])
		->order(['name' => 'ASC'])
		->limit([0, 10]);

	expect(Sql::select($query))->toBe('SELECT `id` FROM `users` WHERE `id` = 1 GROUP BY `name` ORDER BY `name` ASC LIMIT 0, 10');
});

test('select builds a LEFT JOIN from a joined query', function () {
	/** @var Tests\TestCase $this */
	$db = new Database(database: 'test', pdo: $this->createMock(PDO::class));

	$parent = new Query(new SqlBuilderDomain($db));
	$parent->name = 'U';
	$parent->field(['id', 'name', 'email']);

	$child = new Query(new SqlRoleDomain($db));
	$child->name = 'R';
	$child->field(['id', 'title', 'level']);

	$parent->join($child);

	expect(Sql::select($parent))
		->toBe(
			'SELECT `U`.`id` AS `U.id`, `U`.`name` AS `U.name`, `U`.`email` AS `U.email`,'
			. ' `R`.`id` AS `U.R.id`, `R`.`title` AS `U.R.title`, `R`.`level` AS `U.R.level`'
			. ' FROM `users` AS `U` LEFT JOIN `roles` AS `R` ON `U`.`r_id` = `R`.`id`',
		);
});

test('select merges WHERE conditions from both sides of a join', function () {
	/** @var Tests\TestCase $this */
	$db = new Database(database: 'test', pdo: $this->createMock(PDO::class));

	$parent = new Query(new SqlBuilderDomain($db));
	$parent->name = 'User';
	$parent->field(['id', 'name', 'email'])->where(['active' => 1]);

	$child = new Query(new SqlRoleDomain($db));
	$child->name = 'Role';
	$child->field(['id', 'title', 'level'])->where(['level' => 5]);

	// default join, automatic ON
	$parent->join($child);

	expect(Sql::select($parent))
		->toBe(
			'SELECT `User`.`id` AS `User.id`, `User`.`name` AS `User.name`, `User`.`email` AS `User.email`,'
			. ' `Role`.`id` AS `User.Role.id`, `Role`.`title` AS `User.Role.title`, `Role`.`level` AS `User.Role.level`'
			. ' FROM `users` AS `User` LEFT JOIN `roles` AS `Role` ON `User`.`role_id` = `Role`.`id`'
			. ' WHERE `User`.`active` = 1 AND `Role`.`level` = 5',
		);
});

test('select merges WHERE conditions from both sides of a join other way around', function () {
	/** @var Tests\TestCase $this */
	$db = new Database(database: 'test', pdo: $this->createMock(PDO::class));

	$parent = new Query(new SqlBuilderDomain($db));
	$parent->name = 'User';
	$parent->field(['id', 'name', 'email', 'role_id'])->where(['active' => 1]);

	$child = new Query(new SqlRoleDomain($db));
	$child->name = 'Role';
	$child->field(['id', 'title', 'level'])->where(['level' => 5]);

	// $child->join($parent, on: ['id' => 'role_id']);
	$child->join($parent)->on(['id' => 'role_id']);

	expect(Sql::select($child))
		->toBe(
			'SELECT `Role`.`id` AS `Role.id`, `Role`.`title` AS `Role.title`, `Role`.`level` AS `Role.level`, '
			. '`User`.`id` AS `Role.User.id`, `User`.`name` AS `Role.User.name`, `User`.`email` AS `Role.User.email`, `User`.`role_id` AS `Role.User.role_id`'
			. ' FROM `roles` AS `Role` LEFT JOIN `users` AS `User` ON `Role`.`id` = `User`.`role_id`'
			. ' WHERE `Role`.`level` = 5 AND `User`.`active` = 1',
		);
});
