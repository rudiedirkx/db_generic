<?php

class db_generic_query_conditions implements Countable {

	public $delim;
	public $conditions = [];

	public function __construct( $delim ) {
		$this->delim = $delim;
	}

	public function where( $sql, array $params = [] ) {
		$this->conditions[] = $sql instanceof self ? $sql : [$sql, $params];
		return $this;
	}

	public function count() : int {
		return count($this->conditions);
	}

}
