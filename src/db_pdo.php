<?php

abstract class db_pdo extends db_generic {

	protected ?PDO $db = null;

	protected int $affected = 0;

	public function __construct(
		protected string $pdoUri,
	) {}

	/**
	 * @phpstan-assert PDO $this->db
	 */
	public function connect() : void {
		if ( isset($this->db) ) {
			return;
		}

		try {
			$this->db = new PDO($this->pdoUri, null, null, [
				PDO::ATTR_CASE => PDO::CASE_NATURAL,
				PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
				PDO::ATTR_ORACLE_NULLS => PDO::NULL_NATURAL,
				PDO::ATTR_STRINGIFY_FETCHES => false,
				PDO::ATTR_EMULATE_PREPARES => false,
			]);
		}
		catch ( PDOException $ex ) {
			$this->except('', $ex->getMessage(), $ex->getCode(), $ex);
		}

		$this->postConnect();
	}


	public function enableForeignKeys() {}


	public function begin() : true {
		$this->connect();
		$this->db->beginTransaction();
		return true;
	}

	public function commit() : true {
		$this->connect();
		$this->db->commit();
		return true;
	}

	public function rollback() : true {
		$this->connect();
		$this->db->rollBack();
		return true;
	}


	/**
	 * @param string $query
	 * @param list<mixed> $params
	 * @return PDOStatement
	 */
	protected function query( $query, $params = array() ) {
		$this->connect();

		$query = $this->replaceholders($query, $params);
		$_time = microtime(true);

		try {
			$q = @$this->db->query($query);
			if ( !$q ) {
				$err = $this->db->errorInfo();
				$this->except($query, $err[2] ?? '');
			}
			else {
				$this->logQuery($query, $_time);
			}
		}
		catch ( PDOException $ex ) {
			$this->logQuery($query, $_time, $ex->getMessage());
			$this->except($query, $ex->getMessage(), previous: $ex);
		}

		return $q;
	}

	/**
	 * @param string $query
	 * @param list<mixed> $params
	 */
	public function execute( $query, $params = array() ) : true {
		$this->connect();

		$query = $this->replaceholders($query, $params);
		$_time = microtime(true);

		try {
			$r = @$this->db->exec($query);
			if ( false === $r ) {
				$err = $this->db->errorInfo();
				$this->except($query, $err[2] ?? '');
			}
			else {
				$this->logQuery($query, $_time);
			}
		}
		catch ( PDOException $ex ) {
			$this->logQuery($query, $_time, $ex->getMessage());
			$this->except($query, $ex->getMessage(), previous: $ex);
		}

		$this->affected = $r;

		return true;
	}

	protected function result( string $query ) : db_pdo_result {
		return new db_pdo_result($this->query($query));
	}

	public function affected_rows() : int {
		return $this->affected;
	}

	public function insert_id() : int {
		$this->connect();
		return (int) $this->db->lastInsertId();
	}

}
