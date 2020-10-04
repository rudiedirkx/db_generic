<?php

abstract class db_pdo extends db_generic {

	public $affected = 0;

	protected function __construct( $uri ) {
		$this->params = ['uri' => $uri];
	}

	public function connect() {
		if ( $this->params === false ) return;

		try {
			$this->db = new PDO($this->params['uri']);
			$params = $this->params;
			$this->params = false;
			$this->postConnect($params);
		}
		catch ( PDOException $ex ) {
			return $this->except('', $ex->getMessage(), $ex->getCode());
		}
	}


	public function enableForeignKeys() {}


	public function begin() {
		$this->connect();
		return $this->db->beginTransaction();
	}

	public function commit() {
		$this->connect();
		return $this->db->commit();
	}

	public function rollback() {
		$this->connect();
		return $this->db->rollBack();
	}


	public function query( $query, $params = array() ) {
		$this->connect();

		$query = $this->replaceholders($query, $params);
		$_time = microtime(1);

		try {
			$q = @$this->db->query($query);
			if ( !$q ) {
				$this->logQuery($query, $_time, $this->error());
				return $this->except($query, $this->error());
			}
			else {
				$this->logQuery($query, $_time);
			}
		}
		catch ( PDOException $ex ) {
			$this->logQuery($query, $_time, $ex->getMessage());
			return $this->except($query, $ex->getMessage());
		}

		return $q;
	}

	public function execute( $query, $params = array() ) {
		$this->connect();

		$query = $this->replaceholders($query, $params);
		$_time = microtime(1);

		try {
			$r = @$this->db->exec($query);
			if ( false === $r ) {
				$this->logQuery($query, $_time, $this->error());
				return $this->except($query, $this->error());
			}
			else {
				$this->logQuery($query, $_time);
			}
		}
		catch ( PDOException $ex ) {
			$this->logQuery($query, $_time, $ex->getMessage());
			return $this->except($query, $ex->getMessage());
		}

		$this->affected = $r;

		return true;
	}

	public function result( $query, $options = array() ) {
		return call_user_func(array('db_pdo_result', 'make'), $this, $this->query($query), $options);
	}

	public function error( $error = null ) {
		$this->connect();
		$err = $this->db->errorInfo();
		return $err[2];
	}

	public function errno( $errno = null ) {
		$this->connect();
		return $this->db->errorCode();
	}

	public function affected_rows() {
		return $this->affected;
	}

	public function insert_id() {
		$this->connect();
		return $this->db->lastInsertId();
	}

}



class db_pdo_result extends db_generic_result {

	static public function make( $db, $result, $options ) {
		return false !== $result ? new self($db, $result, $options) : false;
	}


	public function singleValue( $args = array() ) {
		return $this->result->fetchColumn(0);
	}


	public function nextObject( $args = array() ) {
		return $this->result->fetchObject($this->class);
	}


	public function nextAssocArray() {
		return $this->result->fetch(PDO::FETCH_ASSOC);
	}


	public function nextNumericArray() {
		return $this->result->fetch(PDO::FETCH_NUM);
	}

}
