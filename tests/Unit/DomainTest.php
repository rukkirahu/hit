<?php

declare(strict_types=1);

namespace Tests\Unit;

use Exception;
use Hit\Db\Database;
use Hit\Db\Dto;
use Hit\Db\Query;
use Hit\Db\Sql\Expression;
use Hit\Domain\Domain;
use PDO;
use PDOStatement;

// --- Test fixtures -------------------------------------------------------

class DtDomain extends Domain {
	public const TABLE = 'users';
}

class DtNoTableDomain extends Domain {
	public const TABLE = '';
}

readonly class DtDto extends Dto {
	public const PREFIX = 'U';

	public function __construct(
		public int $id = 0,
		public string $name = '',
	) {}
}

function dtDb(PDO $pdo): Database {
	return new Database(database: 'test', pdo: $pdo);
}

// --- factory shorthands --------------------------------------------------

test('query returns a base Query wired to the domain', function () {
	$db = dtDb(test()->createMock(PDO::class));
	$query = new DtDomain($db)->query(new DtDto());

	expect($query)->toBeInstanceOf(Query::class);
	expect($query->db)->toBe($db);
	expect($query->table)->toBe('users');
});

test('get returns a Query\\Get seeded from the dto', function () {
	$query = new DtDomain(dtDb(test()->createMock(PDO::class)))->get(new DtDto());

	expect($query)->toBeInstanceOf(Query\Get::class);
	expect($query->name)->toBe('U');
	expect($query->field)->toBe(['id' => null, 'name' => null]);
});

test('select returns a Query\\Select seeded from the dto', function () {
	$query = new DtDomain(dtDb(test()->createMock(PDO::class)))->select(new DtDto());

	expect($query)->toBeInstanceOf(Query\Select::class);
	expect($query->name)->toBe('U');
});

test('listing returns a Query\\Listing with no dto', function () {
	$query = new DtDomain(dtDb(test()->createMock(PDO::class)))->listing();

	expect($query)->toBeInstanceOf(Query\Listing::class);
	expect($query->name)->toBe('');
	expect($query->dto)->toBeNull();
});

test('distinct returns a Query\\Distinct with no dto and the distinct flag set', function () {
	$query = new DtDomain(dtDb(test()->createMock(PDO::class)))->distinct();

	expect($query)->toBeInstanceOf(Query\Distinct::class);
	expect($query->name)->toBe('');
	expect($query->dto)->toBeNull();
	expect($query->distinct)->toBeTrue();
});

test('index returns a Query\\Index with no dto by default', function () {
	$query = new DtDomain(dtDb(test()->createMock(PDO::class)))->index();

	expect($query)->toBeInstanceOf(Query\Index::class);
	expect($query->name)->toBe('');
	expect($query->dto)->toBeNull();
});

test('index accepts an optional dto and seeds name and fields', function () {
	$query = new DtDomain(dtDb(test()->createMock(PDO::class)))->index(new DtDto());

	expect($query)->toBeInstanceOf(Query\Index::class);
	expect($query->name)->toBe('U');
	expect($query->field)->toBe(['id' => null, 'name' => null]);
});

test('scalar returns a Query\\Scalar and accepts a dto', function () {
	$query = new DtDomain(dtDb(test()->createMock(PDO::class)))->scalar(new DtDto());

	expect($query)->toBeInstanceOf(Query\Scalar::class);
	expect($query->name)->toBe('U');
	expect($query->field)->toBe(['id' => null, 'name' => null]);
});

test('scalar with no dto leaves the alias empty', function () {
	$query = new DtDomain(dtDb(test()->createMock(PDO::class)))->scalar();

	expect($query)->toBeInstanceOf(Query\Scalar::class);
	expect($query->name)->toBe('');
	expect($query->dto)->toBeNull();
});

test('insert / update / delete return their Query subclasses seeded from the dto', function () {
	$domain = new DtDomain(dtDb(test()->createMock(PDO::class)));
	$dto = new DtDto();

	expect($domain->insert($dto))->toBeInstanceOf(Query\Insert::class);
	expect($domain->update($dto))->toBeInstanceOf(Query\Update::class);
	expect($domain->delete($dto))->toBeInstanceOf(Query\Delete::class);
});

test('every shorthand throws when the domain has no table', function () {
	$domain = new DtNoTableDomain(dtDb(test()->createMock(PDO::class)));

	$domain->get(new DtDto());
})->throws(Exception::class);

// --- scalar exec ---------------------------------------------------------

/** PDO mock that expects exactly one query with the given SQL and answers with a single row. */
function dtScalarPdo(string $sql, mixed $cell): PDO {
	$stmt = test()->createMock(PDOStatement::class);
	$stmt->method('fetch')->with(PDO::FETCH_NUM)->willReturn([$cell]);

	$pdo = test()->createMock(PDO::class);
	$pdo->expects(test()->once())->method('query')->with($sql, PDO::FETCH_ASSOC)->willReturn($stmt);

	return $pdo;
}

test('scalar exec renders a single expression field without a dto', function () {
	$pdo = dtScalarPdo('SELECT COUNT(*) FROM `users`', '7');

	$query = new DtDomain(dtDb($pdo))
		->scalar()
		->field(['count' => new Expression('COUNT(*)')]);

	expect($query->exec())->toBe(7);
});

test('scalar exec with a dto aliases the expression under the dto prefix', function () {
	$pdo = dtScalarPdo('SELECT MAX(`U`.`id`) AS `U.max` FROM `users` AS `U`', '42');

	$query = new DtDomain(dtDb($pdo))
		->scalar(new DtDto())
		->field(['max' => new Expression('MAX(`U`.`id`)')]);

	expect($query->exec())->toBe(42);
});

test('scalar exec accepts a plain column as the single field', function () {
	$pdo = dtScalarPdo('SELECT `U`.`id` AS `U.id` FROM `users` AS `U` LIMIT 0, 1', '9');

	$query = new DtDomain(dtDb($pdo))
		->scalar(new DtDto())
		->field(['id'])
		->limit([0, 1]);

	expect($query->exec())->toBe(9);
});

test('scalar exec returns a float for non-integral results', function () {
	$pdo = dtScalarPdo('SELECT AVG(`id`) FROM `users`', '2.5000');

	$query = new DtDomain(dtDb($pdo))
		->scalar()
		->field(['avg' => new Expression('AVG(`id`)')]);

	expect($query->exec())->toBe(2.5);
});

test('scalar exec returns an int for integral decimal strings', function () {
	$pdo = dtScalarPdo('SELECT SUM(`id`) FROM `users`', '10');

	$query = new DtDomain(dtDb($pdo))
		->scalar()
		->field(['sum' => new Expression('SUM(`id`)')]);

	expect($query->exec())->toBe(10);
});

test('scalar exec returns 0 when the aggregate yields NULL', function () {
	$pdo = dtScalarPdo('SELECT MAX(`id`) FROM `users`', null);

	$query = new DtDomain(dtDb($pdo))
		->scalar()
		->field(['max' => new Expression('MAX(`id`)')]);

	expect($query->exec())->toBe(0);
});

test('scalar exec applies where conditions', function () {
	$pdo = dtScalarPdo('SELECT MIN(`U`.`id`) AS `U.min` FROM `users` AS `U` WHERE `U`.`name` = \'Alice\'', '1');

	$query = new DtDomain(dtDb($pdo))
		->scalar(new DtDto())
		->field(['min' => new Expression('MIN(`U`.`id`)')])
		->where(['name' => 'Alice']);

	expect($query->exec())->toBe(1);
});

test('scalar exec throws when no field was defined', function () {
	new DtDomain(dtDb(test()->createMock(PDO::class)))->scalar()->exec();
})->throws(Exception::class, 'exactly one field');

test('scalar exec throws when the dto seeded more than one field', function () {
	new DtDomain(dtDb(test()->createMock(PDO::class)))
		->scalar(new DtDto())
		->exec();
})->throws(Exception::class, 'exactly one field');

test('scalar exec throws on the select-all marker', function () {
	new DtDomain(dtDb(test()->createMock(PDO::class)))
		->scalar()
		->field([])
		->exec();
})->throws(Exception::class, 'exactly one field');

// --- distinct exec -------------------------------------------------------

/** PDO mock that expects exactly one query with the given SQL and answers with the given rows. */
function dtRowsPdo(string $sql, array $rows): PDO {
	$stmt = test()->createMock(PDOStatement::class);
	$stmt->method('fetch')->with(PDO::FETCH_NUM)->willReturnOnConsecutiveCalls(...[...$rows, false]);

	$pdo = test()->createMock(PDO::class);
	$pdo->expects(test()->once())->method('query')->with($sql, PDO::FETCH_ASSOC)->willReturn($stmt);

	return $pdo;
}

test('listing exec with two fields returns scalar values and preserves string keys', function () {
	$pdo = dtRowsPdo('SELECT `code`, `name` FROM `users`', [['admin', 'Alice'], ['editor', 'Bob']]);

	expect(
		new DtDomain(dtDb($pdo))
			->listing()
			->field(['code', 'name'])
			->exec(),
	)
		->toBe(['admin' => 'Alice', 'editor' => 'Bob']);
});

test('listing exec with several fields returns the remaining values as a tuple', function () {
	$pdo = dtRowsPdo('SELECT `id`, `name`, `email` FROM `users`', [
		[1, 'Alice', 'alice@example.com'],
		[2, 'Bob',   'bob@example.com'],
	]);

	expect(
		new DtDomain(dtDb($pdo))
			->listing()
			->field(['id', 'name', 'email'])
			->exec(),
	)
		->toBe([1 => ['Alice', 'alice@example.com'], 2 => ['Bob', 'bob@example.com']]);
});

test('listing exec throws when fewer than two fields were defined', function () {
	new DtDomain(dtDb(test()->createMock(PDO::class)))
		->listing()
		->field(['id'])
		->exec();
})->throws(Exception::class, 'at least two fields');

test('distinct exec with one field returns a flat list of strings', function () {
	$pdo = dtRowsPdo('SELECT DISTINCT `name` FROM `users`', [['Alice'], ['Bob']]);

	expect(
		new DtDomain(dtDb($pdo))
			->distinct()
			->field(['name'])
			->exec(),
	)
		->toBe(['Alice', 'Bob']);
});

test('distinct exec with several fields returns a list of string rows', function () {
	$pdo = dtRowsPdo('SELECT DISTINCT `name`, `id` FROM `users`', [['Alice', 1], ['Bob', null]]);

	expect(
		new DtDomain(dtDb($pdo))
			->distinct()
			->field(['name', 'id'])
			->exec(),
	)
		->toBe([['Alice', '1'], ['Bob', '']]);
});

test('distinct exec applies where, order and limit', function () {
	$pdo = dtRowsPdo('SELECT DISTINCT `name` FROM `users` WHERE `id` > 5 ORDER BY `name` ASC LIMIT 0, 10', [['Zed']]);

	expect(
		new DtDomain(dtDb($pdo))
			->distinct()
			->field(['name'])
			->where(['id >' => 5])
			->order(['name' => 'asc'])
			->limit([0, 10])
			->exec(),
	)
		->toBe(['Zed']);
});

test('distinct exec returns an empty list when nothing matches', function () {
	$pdo = dtRowsPdo('SELECT DISTINCT `name` FROM `users`', []);

	expect(
		new DtDomain(dtDb($pdo))
			->distinct()
			->field(['name'])
			->exec(),
	)
		->toBe([]);
});

test('distinct exec throws when no field was defined', function () {
	new DtDomain(dtDb(test()->createMock(PDO::class)))->distinct()->exec();
})->throws(Exception::class, 'at least one field');

test('distinct exec throws on the select-all marker', function () {
	new DtDomain(dtDb(test()->createMock(PDO::class)))
		->distinct()
		->field([])
		->exec();
})->throws(Exception::class, 'at least one field');
