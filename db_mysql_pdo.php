<?php

class db_mysql_pdo extends db_mysql {

	public function connect() {
		if ( $this->params === false ) return;

		$host = self::option($this->params, 'host') ?: ini_get('mysqli.default_host') ?: 'localhost';
		$this->database = self::option($this->params, 'db', self::option($this->params, 'database', ''));
		$user = self::option($this->params, 'user', ini_get('mysqli.default_user'));
		$pass = self::option($this->params, 'pass', ini_get('mysqli.default_pw'));

		try {
			$this->db = new PDO('mysql:host=' . $host . ';dbname=' . $this->database, $user, $pass, [
				PDO::ATTR_CASE => PDO::CASE_NATURAL,
				PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
				PDO::ATTR_ORACLE_NULLS => PDO::NULL_NATURAL,
				PDO::ATTR_STRINGIFY_FETCHES => false,
				PDO::ATTR_EMULATE_PREPARES => false,
			]);
		}
		catch ( PDOException $ex ) {
			return $this->except('', $ex->getMessage(), $ex->getCode());
		}

		$params = $this->params;
		$this->params = false;
		$this->postConnect($params);
	}

	protected function postConnect( $params ) {
		$this->execute("SET NAMES 'utf8' COLLATE 'utf8_general_ci'");
	}


	public function query( $query, $params = array() ) {
		$this->connect();

		$query = $this->replaceholders($query, $params);

		if ( is_array($this->queries) ) {
			$this->queries[] = $query;
		}

		try {
			$q = @$this->db->query($query);
			if ( !$q ) {
				return $this->except($query, $this->error());
			}
		}
		catch ( Exception $ex ) {
			return $this->except($query, $ex->getMessage());
		}

		return $q;
	}

	public function execute( $query, $params = array() ) {
		$query = $this->replaceholders($query, $params);

		try {
			$result = $this->db->exec($query);
			if ( $result === false ) {
				return $this->except($query, $this->error());
			}
		}
		catch ( Exception $ex ) {
			return $this->except($query, $ex->getMessage());
		}

		return $this->returnAffectedRows ? $result : true;
	}

	public function error() {
		$this->connect();
		list($errno, , $error) = $this->db->errorInfo();
		return $error;
	}

	public function errno() {
		$this->connect();
		list($errno, , $error) = $this->db->errorInfo();
		return $errno;
	}

	public function affected_rows() {
		$this->connect();
		return $this->db->rowCount();
	}

	public function insert_id() {
		$this->connect();
		return $this->db->lastInsertId();
	}

	public function escapeValue( $value ) {
		$this->connect();
		return addslashes($value);
	}

	public function escapeTable( $value ) {
		return $value;
	}

	public function escapeColumn( $value ) {
		return $value;
	}

}



class db_mysql_pdo_result extends db_generic_result {

	static public function make( $db, $result, $options ) {
		return false !== $result ? new self($db, $result, $options) : false;
	}


	public function singleValue() {
		return $this->result->fetchColumn(0);
	}


	public function nextAssocArray() {
		return $this->result->fetch(PDO::FETCH_ASSOC);
	}


	public function nextNumericArray() {
		return $this->result->fetch(PDO::FETCH_NUM);
	}

}
