<?php

require_once(dirname(__FILE__).DIRECTORY_SEPARATOR.'db_mysql.php');

class db_mysqli extends db_mysql {

	public function close() {
		return $this->dbCon->close();
	}

	protected $dbCon = null;
	public $error = '';
	public $errno = 0;
	public $num_queries = 0;
	public $queries = array();

	public function __construct( $f_szHost, $f_szUser = '', $f_szPass = '', $f_szDb = '' ) {
		$this->dbCon = new mysqli($f_szHost, $f_szUser, $f_szPass, $f_szDb);
	}

	public function saveError() {
		if ( $this->connected() ) {
			$this->error = $this->dbCon->error;
			$this->errno = $this->dbCon->errno;
		}
		else {
			$this->error = mysqli_connect_error();
			$this->errno = mysqli_connect_errno();
		}
	}

	public function connected() {
		return (is_object($this->dbCon) && 0 === $this->dbCon->connect_errno);
	}

	public function escape($v) {
		return $this->dbCon->real_escape_string($v);
	}

	public function insert_id() {
		return $this->dbCon->insert_id;
	}

	public function affected_rows() {
		return $this->dbCon->affected_rows;
	}

	public function query( $f_szSqlQuery ) {
		$r = $this->dbCon->query($f_szSqlQuery);
		$this->error = $r ? '' : $this->dbCon->error;
		$this->errno = $r ? 0 : $this->dbCon->errno;
		$this->num_queries++;
		return $r;
	}

	public function fetch($f_szSqlQuery) {
		$r = $this->query($f_szSqlQuery);
		if ( !is_object($r) ) {
			return false;
		}
		$a = array();
		while ( $l = $r->fetch_assoc() ) {
			$a[] = $l;
		}
		return $a;
	}

	public function fetch_fields($f_szSqlQuery) {
		$r = $this->query($f_szSqlQuery);
		if ( !is_object($r) ) {
			return false;
		}
		$a = array();
		while ( $l = $r->fetch_row() ) {
			$a[$l[0]] = $l[1];
		}
		return $a;
	}

	public function select_one($tbl, $field, $where = '') {
		$r = $this->query('SELECT '.$field.' FROM '.$tbl.( $where ? ' WHERE '.$where : '' ).' LIMIT 1;');
		if ( !is_object($r) || 0 >= $r->num_rows ) {
			return false;
		}
		return $a = current($r->fetch_row());
	}

	public function count_rows($f_szSqlQuery) {
		$r = $this->query($f_szSqlQuery);
		if ( !$r ) {
			return false;
		}
		return $r->num_rows;
	}

	public function select_by_field($tbl, $field, $where = '') {
		$r = $this->query('SELECT * FROM '.$tbl.( $where ? ' WHERE '.$where : '' ).';');
		if ( !is_object($r) ) {
			return false;
		}
		$a = array();
		while ( $l = $r->fetch_assoc() ) {
			$a[$l[$field]] = $l;
		}
		return $a;
	}

} // END Class db_mysqli

?>