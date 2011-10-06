<?php

require_once(dirname(__FILE__).DIRECTORY_SEPARATOR.'db_generic.php');

class db_mysql extends db_generic {

	protected $dbCon = null;
	protected $db = false;
	public $error = '';
	public $errno = 0;
	public $num_queries = 0;

	public function __construct( $f_szHost, $f_szUser = '', $f_szPass = '', $f_szDb = '' ) {
		if ( $this->dbCon = @mysql_connect($f_szHost, $f_szUser, $f_szPass) ) {
			if ( !@mysql_select_db($f_szDb, $this->dbCon) ) {
				$this->saveError();
			}
			else {
				$this->db = true;
			}
		}
		else {
			$this->saveError();
		}
	}

	public function saveError() {
		if ( $this->dbCon ) {
			$this->error = mysql_error($this->dbCon);
			$this->errno = mysql_errno($this->dbCon);
		}
		else {
			$this->error = mysql_error();
			$this->errno = mysql_errno();
		}
	}

	public function connected() {
		return true === $this->db && is_resource($this->dbCon);
	}

	public function escape($v) {
		return mysql_real_escape_string((string)$v, $this->dbCon);
	}

	public function insert_id() {
		return mysql_insert_id($this->dbCon);
	}

	public function affected_rows() {
		return mysql_affected_rows($this->dbCon);
	}

	public function query( $f_szSqlQuery ) {
		$r = mysql_query($f_szSqlQuery, $this->dbCon);
		$this->saveError();
		$this->num_queries++;
		return $r;
	}

	public function fetch($f_szSqlQuery) {
		$r = $this->query($f_szSqlQuery);
		if ( !$r ) {
			return false;
		}
		$a = array();
		while ( $l = mysql_fetch_assoc($r) ) {
			$a[] = $l;
		}
		return $a;
	}

	public function fetch_fields($f_szSqlQuery) {
		$r = $this->query($f_szSqlQuery);
		if ( !$r ) {
			return false;
		}
		$a = array();
		while ( $l = mysql_fetch_row($r) ) {
			$a[$l[0]] = $l[1];
		}
		return $a;
	}

	public function select_one($tbl, $field, $where = '') {
		$r = $this->query('SELECT '.$field.' FROM '.$tbl.( $where ? ' WHERE '.$where : '' ).' LIMIT 1;');
		if ( !$r ) {
			return false;
		}
		return 0 < mysql_num_rows($r) ? mysql_result($r, 0) : false;
	}

	public function count_rows($f_szSqlQuery) {
		$r = $this->query($f_szSqlQuery);
		if ( !$r ) {
			return false;
		}
		return mysql_num_rows($r);
	}

	public function select_by_field($tbl, $field, $where = '') {
		$r = $this->query('SELECT * FROM '.$tbl.( $where ? ' WHERE '.$where : '' ).';');
		if ( !$r ) {
			return false;
		}
		$a = array();
		while ( $l = mysql_fetch_assoc($r) ) {
			$a[$l[$field]] = $l;
		}
		return $a;
	}

} // END Class db_mysql

?>