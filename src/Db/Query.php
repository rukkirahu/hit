<?php

declare(strict_types=1);

namespace Hit\Db;

use Hit\Db\Sql\Group;
use Hit\Db\Sql\Join;
use Hit\Db\Sql\Limit;
use Hit\Db\Sql\Order;
use Hit\Db\Sql\Value;
use Hit\Db\Sql\Where;
use Hit\Domain\Domain;

/**
 * @template T as Dto
 */
// @mago-expect lint:too-many-properties
class Query {
	public const PRIMARY = 'id';

	public Database $db;

	public string $table;

	public string $name = '';

	/** @var array<string,mixed> */
	public array $field = [];

	public bool $distinct = false;

	public ?Order $Order = null;

	public ?Group $Group = null;

	public ?Limit $Limit = null;

	public ?Where $Where = null;

	public Value $Value;

	/** @var array<string,Join> */
	public array $join = [];

	/**
	 * @param T $dto
	 */
	public function __construct(
		public Domain $domain,
		public ?Dto $dto = null,
	) {
		$this->db = $domain->db;
		$this->table = $domain::TABLE;

		if ($this->table === '') {
			throw new \Exception('Domain has no table');
		}

		$this->Value = new Value();

		if ($dto instanceof Dto) {
			$this->name = $dto::PREFIX;
			$this->field = array_fill_keys(array_keys($dto->toArray()), null);
		}
	}

	/**
	 * @param array<int|string,mixed> $fields
	 * @return $this
	 */
	public function field(array $fields): static {
		if ($fields === []) {
			$this->field = ['*' => false];

			return $this;
		}

		$result = [];
		foreach ($fields as $key => $value) {
			if (is_int($key)) {
				if (!is_string($value)) {
					throw new \Exception('Field name must be a string');
				}
				$result[$value] = null;
				continue;
			}

			$result[$key] = $value;
		}
		$this->field = $result;

		return $this;
	}

	/**
	 * Render as SELECT DISTINCT (de-duplicates whole result rows).
	 * @return $this
	 */
	public function distinct(bool $distinct = true): static {
		$this->distinct = $distinct;

		return $this;
	}

	/**
	 * @param array<string,string> $orders
	 * @return $this
	 */
	public function order(array $orders): static {
		$this->Order = new Order($orders);

		return $this;
	}

	/**
	 * @param string|array<int,string> $fields
	 * @return $this
	 */
	public function group(string|array $fields): static {
		$this->Group ??= new Group();
		$this->Group->add($fields);

		return $this;
	}

	/**
	 * @param array<int,int> $limit
	 * @return $this
	 */
	public function limit(array $limit): static {
		$this->Limit = new Limit((int) ($limit[0] ?? 0), (int) ($limit[1] ?? 0));

		return $this;
	}

	/**
	 * @param array<string,mixed> $conditions
	 * @return $this
	 */
	public function where(array $conditions): static {
		if (!$this->Where instanceof Where) {
			$this->Where = new Where($this->name);
		}

		$this->Where->add($conditions);

		return $this;
	}

	/**
	 * @return $this
	 */
	public function join(Query $query): static {
		$join = new Join($query, [
			$this->name . '.' . strtolower($query->name) . '_id' => $query->name . '.id',
		]);
		$this->join[$query->table] = $join;

		return $this;
	}

	/**
	 * @param array<string,string> $on
	 * @return $this
	 */
	public function on(array $on): static {
		$key = array_key_last($this->join);
		if ($key === null) {
			throw new \Exception('Cannot set ON condition before joining a query');
		}
		$this->join[$key]->setOn($this->name, $on);

		return $this;
	}
}
