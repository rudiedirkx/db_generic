<?php

abstract class db_generic_relationship {

	protected string $name;
	/** @var list<string> */
	protected array $eager = [];
	protected ?db_generic_model $source;
	protected string $target;
	protected ?string $foreign;
	protected ?string $where = null;
	protected ?string $order = null;
	protected ?string $key = null;

	public function __construct( ?db_generic_model $source, string $targetClass, ?string $foreignColumn ) {
		$this->source = $source;
		$this->target = $targetClass;
		$this->foreign = $foreignColumn;
	}

	public function load() : mixed {
		return $this->fetch();
	}

	/**
	 * @param array<db_generic_model> $objects
	 * @return array<mixed>
	 */
	public function loadAll( array $objects ) : array {
		return count($objects) ? $this->fetchAll($objects) : [];
	}

	/**
	 * @param array<db_generic_model> $targets
	 */
	protected function loadEagers( array $targets ) : void {
		$target = reset($targets);
		foreach ( $this->eager as $name ) {
			$target::eager($name, $targets);
		}
	}

	abstract protected function fetch() : mixed;

	/**
	 * @param array<db_generic_model> $objects
	 * @return array<mixed>
	 */
	abstract protected function fetchAll( array $objects ) : array;

	abstract public function getReturnType() : string;

	public function name( ?string $name ) : static {
		$this->name = $name;
		return $this;
	}

	/**
	 * @param list<string> $names
	 */
	public function eager( array $names ) : static {
		$this->eager = $names;
		return $this;
	}

	public function where( ?string $where ) : static {
		$this->where = $where;
		return $this;
	}

	public function order( ?string $order ) : static {
		$this->order = $order;
		return $this;
	}

	public function key( ?string $key ) : static {
		$this->key = $key;
		return $this;
	}

	protected function db() : db_generic {
		$source = $this->source;
		return $source::$_db;
	}

	/**
	 * @param array<int|string, mixed> $conditions
	 */
	protected function getWhereOrder( array $conditions ) : string {
		$db = $this->db();
		$conditions = $db->stringifyConditions($conditions);
		if ($this->where) $conditions .= ' AND ' . $this->where;
		$order = $this->order ? " ORDER BY {$this->order}" : '';
		return $conditions . $order;
	}

	/**
	 * @param array<db_generic_model> $objects
	 * @return list<int|string>
	 */
	protected function getForeignIds( array $objects, string $column ) : array {
		return array_filter(array_map(function($object) use ($column) {
			return $object->$column;
		}, $objects));
	}

}
