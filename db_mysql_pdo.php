<?php

require __DIR__ . '/db_mysql.php';

class db_mysql_pdo extends db_mysql {

	public function connect() {
		if ( $this->params === false ) return;

		$host = self::option($this->params, 'host', ini_get('mysqli.default_host'));
		$this->database = self::option($this->params, 'db', self::option($this->params, 'database', ''));
		$user = self::option($this->params, 'user', ini_get('mysqli.default_user'));
		$pass = self::option($this->params, 'pass', ini_get('mysqli.default_pw'));

		try {
			$this->db = new PDO('mysql:host=' . $host . ';dbname=' . $this->database, $user, $pass);
		}
		catch ( PDOException $ex ) {
			return $this->except('', $ex->getMessage(), $ex->getCode());
		}

		$this->params = false;
		$this->postConnect($this->params);
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
		return $this->result->fetchColumn();
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


