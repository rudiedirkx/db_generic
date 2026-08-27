<?php

class db_mysql extends db_generic {

	use db_mysql_schema;

	protected ?mysqli $db = null;

	public function __construct(
		protected string $database,
		protected ?string $host = null,
		protected ?string $user = null,
		protected ?string $pass = null,
	) {}

	/**
	 * @phpstan-assert mysqli $this->db
	 */
	public function connect() : void {
		if ( isset($this->db) ) {
			return;
		}

		$pass = $this->pass;
		$this->pass = null;

		$db = mysqli_init();
		if ( !$db ) {
			$this->except('', 'Could not initialize mysqli');
		}

		$db->options(MYSQLI_OPT_CONNECT_TIMEOUT, 5);
		$db->options(MYSQLI_OPT_INT_AND_FLOAT_NATIVE, 1);
		$db->options(MYSQLI_SET_CHARSET_NAME, 'utf8');

		try {
			$db->real_connect($this->host, $this->user, $pass, $this->database);
		}
		catch ( Exception $ex ) {
			$this->except('', $ex->getMessage(), $ex->getCode(), $ex);
		}

		if ( $db->connect_errno ) {
			$this->except('', $db->connect_error, $db->connect_errno);
		}

		$this->db = $db;

		$this->postConnect();
	}

	protected function postConnect() : void {
		$this->execute("SET NAMES 'utf8' COLLATE 'utf8_general_ci'");
	}


	public function begin() : true {
		$this->execute('BEGIN');
		return true;
	}

	public function commit() : true {
		$this->execute('COMMIT');
		return true;
	}

	public function rollback() : true {
		$this->execute('ROLLBACK');
		return true;
	}


	/**
	 * @param string $query
	 * @param list<mixed> $params
	 * @return mysqli_result|bool
	 */
	protected function query( $query, $params = array() ) {
		$this->connect();

		$query = $this->replaceholders($query, $params);
		$_time = microtime(true);

		try {
			$q = @$this->db->query($query);
			if ( !$q ) {
				$this->except($query, $this->db->error);
			}
			else {
				$this->logQuery($query, $_time);
			}
		}
		catch ( Exception $ex ) {
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
		$this->query($query, $params);
		return true;
	}

	public function affected_rows() : int {
		$this->connect();
		return (int) $this->db->affected_rows;
	}

	public function insert_id() : int {
		$this->connect();
		return (int) $this->db->insert_id;
	}

	protected function result( string $query ) : db_mysql_result {
		$result = $this->query($query);
		if ( !$result instanceof mysqli_result ) {
			$this->except($query, 'Query did not return a result set');
		}
		return new db_mysql_result($result);
	}

	protected function escapeValue( $value ) : string {
		$this->connect();
		return $this->db->real_escape_string((string)$value);
	}

}
