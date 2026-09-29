<?php

declare(strict_types=1);

namespace Tests\Unit;

use Hit\Db\Database;
use Hit\Db\Query\InsertChunks;
use Hit\Domain\ErrorCode;
use InvalidArgumentException;
use PDO;
use PDOException;
use PDOStatement;
use Tests\Fixtures\Db\ChunkDomain;
use Tests\Fixtures\Db\ChunkDto;
use Tests\Fixtures\Db\OptionalChunkDto;

function ic_domain(PDO $pdo): ChunkDomain {
	return new ChunkDomain(new Database(database: 'test', pdo: $pdo));
}

test('insertChunks returns a chunked insert query', function () {
	$query = ic_domain(test()->createMock(PDO::class))->insertChunks([], 10);

	expect($query)->toBeInstanceOf(InsertChunks::class);
});

test('insertChunks rejects invalid chunk sizes', function () {
	ic_domain(test()->createMock(PDO::class))->insertChunks([], 0);
})->throws(InvalidArgumentException::class, 'Chunk size must be greater than zero');

test('insertChunks inserts each chunk with one statement and runs hooks per dto', function () {
	$bindings = [];
	$statements = [];
	foreach ([2, 1] as $statementIndex => $rows) {
		$statement = test()->createMock(PDOStatement::class);
		$statement
			->expects(test()->once())
			->method('execute')
			->willReturnCallback(function (array $values) use (&$bindings, $statementIndex): bool {
				$bindings[$statementIndex] = array_values($values);

				return true;
			});
		$statement->method('rowCount')->willReturn($rows);
		$statements[] = $statement;
	}

	$sql = [];
	$index = 0;
	$pdo = test()->createMock(PDO::class);
	$pdo
		->expects(test()->exactly(2))
		->method('prepare')
		->willReturnCallback(function (string $query) use (&$sql, &$index, $statements): PDOStatement {
			$sql[] = preg_replace('/:[a-z]+/', ':v', $query);

			return $statements[$index++];
		});

	$dtos = [new ChunkDto(name: 'Ada', age: 31), new ChunkDto(name: 'Bob', age: 42), new ChunkDto(name: 'Cid', age: 53)];
	$domain = ic_domain($pdo);

	expect($domain->insertChunks($dtos, 2)->exec())
		->toBe(3)
		->and($sql)
		->toBe([
			'INSERT INTO `users` (`name`, `age`) VALUES (:v, 31), (:v, 42)',
			'INSERT INTO `users` (`name`, `age`) VALUES (:v, 53)',
		])
		->and($bindings)
		->toBe([['Ada', 'Bob'], ['Cid']])
		->and($domain->before)
		->toBe($dtos)
		->and($domain->after)
		->toBe($dtos);
});

test('insertChunks returns zero for an empty iterable', function () {
	$pdo = test()->createMock(PDO::class);
	$pdo->expects(test()->never())->method('prepare');

	expect(ic_domain($pdo)->insertChunks([])->exec())->toBe(0);
});

test('insertChunks aborts when beforeInsert rejects a dto', function () {
	$statement = test()->createMock(PDOStatement::class);
	$statement->method('execute')->willReturn(true);
	$statement->method('rowCount')->willReturn(2);

	$pdo = test()->createMock(PDO::class);
	$pdo->expects(test()->once())->method('prepare')->willReturn($statement);

	$domain = ic_domain($pdo);
	$domain->reject = 'Cid';
	$dtos = [new ChunkDto(name: 'Ada'), new ChunkDto(name: 'Bob'), new ChunkDto(name: 'Cid')];

	expect($domain->insertChunks($dtos, 2)->exec())
		->toBeFalse()
		->and($domain->before)
		->toBe($dtos)
		->and($domain->after)
		->toBe([$dtos[0], $dtos[1]]);
});

test('insertChunks rejects rows with different columns', function () {
	$pdo = test()->createMock(PDO::class);
	$pdo->expects(test()->never())->method('prepare');

	ic_domain($pdo)->insertChunks([new OptionalChunkDto(name: 'Ada', email: 'ada@example.com'), new OptionalChunkDto(name: 'Bob')])->exec();
})->throws(InvalidArgumentException::class, 'Insert DTOs must have identical columns');

test('insertChunks reports a failed chunk for every dto', function () {
	$statement = test()->createMock(PDOStatement::class);
	$statement->method('execute')->willThrowException(new PDOException('duplicate'));

	$pdo = test()->createMock(PDO::class);
	$pdo->method('prepare')->willReturn($statement);

	$dtos = [new ChunkDto(name: 'Ada'), new ChunkDto(name: 'Bob')];
	$domain = ic_domain($pdo);
	$domain->handleFailure = true;

	expect($domain->insertChunks($dtos)->exec())
		->toBeFalse()
		->and(array_column($domain->failed, 0))
		->toBe($dtos)
		->and($domain->failed[0][1])
		->toBeInstanceOf(ErrorCode::class);
});

test('insertChunks throws an unhandled database failure', function () {
	$statement = test()->createMock(PDOStatement::class);
	$statement->method('execute')->willThrowException(new PDOException('duplicate'));

	$pdo = test()->createMock(PDO::class);
	$pdo->method('prepare')->willReturn($statement);

	ic_domain($pdo)->insertChunks([new ChunkDto(name: 'Ada')])->exec();
})->throws(\Exception::class);
