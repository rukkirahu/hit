<?php

declare(strict_types=1);

namespace Tests\Unit;

use Hit\Db\Database;
use PDO;
use PDOException;
use PDOStatement;

function makeDatabase(PDO $pdo, string $database = 'testdb'): Database {
	return new Database(database: $database, pdo: $pdo);
}

test('exposes the database name', function () {
	$db = makeDatabase(test()->createMock(PDO::class), 'mydb');

	expect($db->database)->toBe('mydb');
});

test('prepare delegates to the PDO instance', function () {
	$stmt = test()->createMock(PDOStatement::class);
	$pdo = test()->createMock(PDO::class);
	$pdo
		->expects(test()->once())
		->method('prepare')
		->with('SELECT 1')
		->willReturn($stmt);

	expect(makeDatabase($pdo)->prepare('SELECT 1'))->toBe($stmt);
});

test('query delegates to the PDO instance', function () {
	$stmt = test()->createMock(PDOStatement::class);
	$pdo = test()->createMock(PDO::class);
	$pdo
		->expects(test()->once())
		->method('query')
		->with('SELECT 1', PDO::FETCH_ASSOC)
		->willReturn($stmt);

	expect(makeDatabase($pdo)->query('SELECT 1'))->toBe($stmt);
});

test('exec without values runs a direct query', function () {
	$stmt = test()->createMock(PDOStatement::class);
	$pdo = test()->createMock(PDO::class);
	$pdo->expects(test()->once())->method('query')->willReturn($stmt);
	$pdo->expects(test()->never())->method('prepare');

	expect(makeDatabase($pdo)->exec('SELECT 1'))->toBe($stmt);
});

test('exec with values prepares and executes the statement', function () {
	$stmt = test()->createMock(PDOStatement::class);
	$stmt
		->expects(test()->once())
		->method('execute')
		->with([':id' => 5])
		->willReturn(true);

	$pdo = test()->createMock(PDO::class);
	$pdo
		->expects(test()->once())
		->method('prepare')
		->with('SELECT * FROM users WHERE id = :id')
		->willReturn($stmt);

	$result = makeDatabase($pdo)->exec('SELECT * FROM users WHERE id = :id', [':id' => 5]);

	expect($result)->toBe($stmt);
});

test('exec throws when prepare fails', function () {
	$pdo = test()->createMock(PDO::class);
	$pdo->method('prepare')->willReturn(false);

	makeDatabase($pdo)->exec('BAD SQL', [':id' => 5]);
})->throws(PDOException::class);

test('lastInsertId returns the generated id', function () {
	$pdo = test()->createMock(PDO::class);
	$pdo->method('lastInsertId')->willReturn('42');

	expect(makeDatabase($pdo)->lastInsertId())->toBe('42');
});

test('lastInsertId returns 0 when there is no id', function () {
	$pdo = test()->createMock(PDO::class);
	$pdo->method('lastInsertId')->willReturn(false);

	expect(makeDatabase($pdo)->lastInsertId())->toBe(0);
});

test('errorInfo delegates to the PDO instance', function () {
	$info = ['00000', null, null];
	$pdo = test()->createMock(PDO::class);
	$pdo->method('errorInfo')->willReturn($info);

	expect(makeDatabase($pdo)->errorInfo())->toBe($info);
});

test('begin, commit and rollback delegate to the PDO instance', function () {
	$pdo = test()->createMock(PDO::class);
	$pdo->expects(test()->once())->method('beginTransaction')->willReturn(true);
	$pdo->expects(test()->once())->method('commit')->willReturn(true);
	$pdo->expects(test()->once())->method('rollBack')->willReturn(true);

	$db = makeDatabase($pdo);

	expect($db->begin())->toBeTrue();
	expect($db->commit())->toBeTrue();
	expect($db->rollback())->toBeTrue();
});

test('exceptionRollback rolls back an active transaction', function () {
	$pdo = test()->createMock(PDO::class);
	$pdo->method('inTransaction')->willReturn(true);
	$pdo->expects(test()->once())->method('rollBack')->willReturn(true);

	makeDatabase($pdo)->exceptionRollback();
});

test('exceptionRollback does nothing without an active transaction', function () {
	$pdo = test()->createMock(PDO::class);
	$pdo->method('inTransaction')->willReturn(false);
	$pdo->expects(test()->never())->method('rollBack');

	makeDatabase($pdo)->exceptionRollback();
});
