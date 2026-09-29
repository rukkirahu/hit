<?php

declare(strict_types=1);

use Hit\Db\Database;
use Hit\Db\Dto;
use Hit\Db\Query;
use Hit\Db\Sql\Expression;
use Hit\Db\Sql\Group;
use Hit\Db\Sql\Limit;
use Hit\Db\Sql\Order;
use Hit\Db\Sql\Where;
use Hit\Domain\Domain;

use function Hit\Db\query;

// --- Test fixtures -------------------------------------------------------

class QtDomain extends Domain {
	public const TABLE = 'users';
}

class QtRoleDomain extends Domain {
	public const TABLE = 'roles';
}

class QtNoTableDomain extends Domain {
	public const TABLE = '';
}

readonly class QtDto extends Dto {
	public const PREFIX = 'U';

	public function __construct(
		public int $id = 0,
		public string $name = '',
	) {}
}

/**
 * Build a Database backed by the given mock PDO (no real connection).
 */
function qtDb(PDO $pdo): Database {
	return new Database(database: 'test', pdo: $pdo);
}

// --- constructor ---------------------------------------------------------

test('constructor wires the domain database and table', function () {
	$db = qtDb(test()->createMock(PDO::class));
	$query = new Query(new QtDomain($db));

	expect($query->table)->toBe('users');
	expect($query->db)->toBe($db);
	expect($query->name)->toBe('');
});

test('constructor throws when the domain has no table', function () {
	$db = qtDb(test()->createMock(PDO::class));

	new Query(new QtNoTableDomain($db));
})->throws(Exception::class);

test('constructor seeds name and fields from a dto', function () {
	$db = qtDb(test()->createMock(PDO::class));
	$query = new Query(new QtDomain($db), dto: new QtDto());

	expect($query->name)->toBe('U');
	expect($query->field)->toBe(['id' => null, 'name' => null]);
});

test('the query() helper returns a Query for the domain', function () {
	$db = qtDb(test()->createMock(PDO::class));

	expect(query(new QtDomain($db)))->toBeInstanceOf(Query::class);
});

// --- field ---------------------------------------------------------------

test('field with a list of names maps them to null columns', function () {
	$query = new Query(new QtDomain(qtDb(test()->createMock(PDO::class))));

	expect($query->field(['id', 'name'])->field)->toBe(['id' => null, 'name' => null]);
});

test('field with string keys keeps the aliased column', function () {
	$query = new Query(new QtDomain(qtDb(test()->createMock(PDO::class))));

	expect($query->field(['alias' => 'real_column'])->field)->toBe(['alias' => 'real_column']);
});

test('field with no arguments marks the table as field-less', function () {
	$query = new Query(new QtDomain(qtDb(test()->createMock(PDO::class))));

	expect($query->field([])->field)->toBe(['*' => false]);
});

test('field throws when a list value is not a string', function () {
	$query = new Query(new QtDomain(qtDb(test()->createMock(PDO::class))));

	$query->field([123]);
})->throws(Exception::class);

// --- order ---------------------------------------------------------------

test('order builds an Order with the given directions', function () {
	$query = new Query(new QtDomain(qtDb(test()->createMock(PDO::class))));

	$result = $query->order(['id' => 'DESC', 'name' => 'ASC']);

	expect($result)->toBe($query);
	expect($query->Order)->toBeInstanceOf(Order::class);
	expect($query->Order->orders)->toBe(['id' => 'DESC', 'name' => 'ASC']);
});

test('order rejects an invalid direction', function () {
	$query = new Query(new QtDomain(qtDb(test()->createMock(PDO::class))));

	$query->order(['id' => 'SIDEWAYS']);
})->throws(Exception::class);

// --- group ---------------------------------------------------------------

test('group accepts a single field as a string', function () {
	$query = new Query(new QtDomain(qtDb(test()->createMock(PDO::class))));

	$query->group('id');

	expect($query->Group)->toBeInstanceOf(Group::class);
	expect($query->Group->groups)->toBe(['id']);
});

test('group accumulates fields across calls', function () {
	$query = new Query(new QtDomain(qtDb(test()->createMock(PDO::class))));

	$query->group(['a', 'b'])->group('c');

	expect($query->Group->groups)->toBe(['a', 'b', 'c']);
});

// --- limit ---------------------------------------------------------------

test('limit sets offset and limit', function () {
	$query = new Query(new QtDomain(qtDb(test()->createMock(PDO::class))));

	$query->limit([5, 10]);

	expect($query->Limit)->toBeInstanceOf(Limit::class);
	expect($query->Limit->offset)->toBe(5);
	expect($query->Limit->limit)->toBe(10);
});

test('limit defaults the limit to zero when omitted', function () {
	$query = new Query(new QtDomain(qtDb(test()->createMock(PDO::class))));

	$query->limit([7]);

	expect($query->Limit->offset)->toBe(7);
	expect($query->Limit->limit)->toBe(0);
});

// --- where ---------------------------------------------------------------

test('where builds a Where that renders conditions', function () {
	$query = new Query(new QtDomain(qtDb(test()->createMock(PDO::class))));

	$result = $query->where(['id' => 1]);

	expect($result)->toBe($query);
	expect($query->Where)->toBeInstanceOf(Where::class);
	expect($query->Where->build())->toBe(['`id` = 1']);
});

// --- join ----------------------------------------------------------------

test('join registers the child query and derives the ON condition', function () {
	$db = qtDb(test()->createMock(PDO::class));
	$parent = new Query(new QtDomain($db));
	$parent->name = 'U';
	$child = new Query(new QtRoleDomain($db));
	$child->name = 'R';

	$parent->join($child);

	expect($parent->join['roles']->query)->toBe($child);
	expect($parent->join['roles']->on)->toBe(['U.r_id' => 'R.id']);
});

// --- Value::parse --------------------------------------------------------

test('Value::parse formats scalar and null values', function () {
	$value = new Hit\Db\Sql\Value();

	expect($value->parse(null))->toBe('NULL');
	expect($value->parse(5))->toBe('5');
	expect($value->parse(5.5))->toBe('5.5');
	expect($value->parse(true))->toBe('1');
	expect($value->parse(false))->toBe('0');
});

test('Value::parse stores strings as named placeholders', function () {
	$value = new Hit\Db\Sql\Value();

	$placeholder = $value->parse('hello');

	expect($placeholder)->toMatch('/^:[a-z]{2}$/');
	expect($value->all())->toBe([$placeholder => 'hello']);
});

test('Value::parse throws for an Expression', function () {
	$value = new Hit\Db\Sql\Value();

	$value->parse(new Expression('NOW()'));
})->throws(Exception::class);
