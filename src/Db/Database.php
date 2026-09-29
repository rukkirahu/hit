<?php

declare(strict_types=1);

namespace Hit\Db;

use PDO;
use SensitiveParameter;

class Database {
	public function __construct(
		private string $host = 'localhost',
		private string $port = '3306',
		private string $username = 'root',
		#[SensitiveParameter]
		private string $password = '',
		public readonly string $database = 'test',
		private ?PDO $pdo = null,
	) {
		if ($pdo === null) {
			$this->connect();
		}
	}

	public function connect(): void {
		$this->pdo = new PDO("mysql:host={$this->host}:{$this->port};dbname={$this->database};charset=utf8mb4", $this->username, $this->password, [
			PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
			PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
			PDO::ATTR_PERSISTENT => true,
		]);
	}

	private function getPDO(): PDO {
		if ($this->pdo === null) {
			throw new \PDOException('Database not connected');
		}

		return $this->pdo;
	}

	public function prepare(string $sql, $options = []): bool|\PDOStatement {
		// @mago-expect analysis:mixed-argument
		return $this->getPDO()->prepare($sql, $options);
	}

	public function query(string $query, int $fetchMode = PDO::FETCH_ASSOC): false|\PDOStatement {
		return $this->getPDO()->query($query, $fetchMode);
	}

	public function exec(string $query, array $values = []): false|\PDOStatement {
		if ($values === []) {
			$queryResult = $this->query($query);
		}
		else {
			$queryResult = $this->prepare($query);
			if (!$queryResult instanceof \PDOStatement) {
				throw new \PDOException("Prepare failed for query [{$query}]");
			}
			if (!$queryResult->execute($values)) {
				throw new \PDOException("Execute failed for query [{$query}]");
			}
		}

		return $queryResult;
	}

	public function exceptionRollback(): void {
		if ($this->getPDO()->inTransaction()) {
			$this->getPDO()->rollBack();
		}
	}

	public function errorInfo(): array {
		return $this->getPDO()->errorInfo();
	}

	public function lastInsertId(): string|int {
		return $this->getPDO()->lastInsertId() ?: 0;
	}

	public function begin(): bool {
		return $this->getPDO()->beginTransaction();
	}

	public function commit(): bool {
		return $this->getPDO()->commit();
	}

	public function rollback(): bool {
		return $this->getPDO()->rollBack();
	}
}
