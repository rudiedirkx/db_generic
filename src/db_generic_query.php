<?php

class db_generic_query {

	protected $db;

	protected $fields = [];
	protected $tables = [];
	protected $join = [];
	protected $conditions;
	protected $order = [];

	public function __construct( db_generic $db, array $query = [] ) {
		$this->db = $db;

		foreach ( $query as $prop => $value ) {
			$this->$prop = $value;
		}

		$this->conditions = $this->and();
	}

	public function and() {
		return new db_generic_query_conditions('AND');
	}

	public function or() {
		return new db_generic_query_conditions('OR');
	}

	public function field( $field, $alias = null ) {
		$this->fields[] = [$field, $alias];
		return $this;
	}

	public function table( $table, $alias = null ) {
		$this->tables[] = [$table, $alias];
		return $this;
	}

	public function condition( $condition, array $params = [] ) {
		$this->conditions->where($condition, $params);

		return $this;
	}

	public function buildSelect() {
		return implode("\n", array_filter([
			$this->buildFields(),
			$this->buildFrom(),
			$this->buildJoin(),
			$this->buildWhere(),
			$this->buildOrder(),
		]));
	}

	public function buildConditions( db_generic_query_conditions $conditions ) {
		$sqls = [];

		foreach ( $conditions->conditions as $condition ) {
			if ( $condition instanceof db_generic_query_conditions ) {
				$sqls[] = '(' . $this->buildConditions($condition) . ')';
			}
			else {
				list($sql, $params) = $condition;
				$sqls[] = '(' . $this->db->replaceholders($sql, $params) . ')';
			}
		}

		return implode(" $conditions->delim ", $sqls);
	}

	public function buildFields() {
		if ( !$this->fields ) {
			return 'SELECT *';
		}

		$fields = [];

		foreach ( $this->fields AS list($field, $alias) ) {
			$alias = $alias ? " AS $alias" : '';
			$fields[] = $this->buildField($field) . $alias;
		}

		return 'SELECT ' . implode(', ', $fields);
	}

	public function buildField( $field ) {
		if ( is_string($field) ) {
			return $field;
		}

		if ( $field instanceof db_generic_query_conditions ) {
			return $this->buildConditions($field);
		}
	}

	public function buildFrom() {
		$tables = [];

		foreach ( $this->tables as $table ) {
			$alias = '';
			if ( is_array($table) ) {
				$alias = ' AS ' . $table[1];
				$table = $table[0];
			}
			$tables[] = 'FROM ' . $this->db->escapeAndQuoteTable($table) . $alias;
		}

		return implode(', ', $tables);
	}

	public function buildJoin() {
		$joins = [];

		foreach ( $this->join as $info ) {
			list($type, $table, $conditions) = $info;

			$tableAlias = '';
			if ( is_array($table) ) {
				$tableAlias = ' ' . $table[1];
				$table = $table[0];
			}

			$on = '';
			if ( $conditions ) {
				$on = ' ON ' . implode(' AND ', array_map(function($condition) {
					return is_array($condition) ? $this->db->replaceholders(...$condition) : $condition;
				}, $conditions));
			}

			$joins[] = trim(strtoupper("$type join")) . ' ' . $this->db->escapeAndQuoteTable($table) . $tableAlias . $on;
		}

		return implode("\n", $joins);
	}

	public function buildWhere() {
		if ( count($this->conditions) == 0 ) {
			return null;
		}

		return 'WHERE ' . $this->buildConditions($this->conditions);
	}

	public function buildOrder() {
		if ( !$this->order ) {
			return null;
		}

		$order = [];

		foreach ( $this->order AS $field ) {
			$direction = 'ASC';
			$tableAlias = '';
			if ( is_array($field) ) {
				if ( isset($field[2]) ) {
					$direction = strtoupper($field[2]);
				}

				$tableAlias = $field[0] . '.';
				$field = $field[1];
			}

			$order[] = $tableAlias . $this->db->escapeAndQuoteColumn($field) . ' ' . $direction;
		}

		return 'ORDER BY ' . implode(', ', $order);
	}

	public function __toString() {
		return $this->buildSelect();
	}

}
