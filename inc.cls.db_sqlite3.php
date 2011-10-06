<?php

require_once(dirname(__FILE__).DIRECTORY_SEPARATOR.'inc.cls.db_sqlite.php');

class db_sqlite3 extends db_sqlite {

	protected $dbCon = null;
	public $error = '';
	public $errno = 0;
	public $num_queries = 0;
	public $m_iAffectedRows = 0;
	public $last_query = '';

	public function begin() {
		return $this->dbCon->beginTransaction();
	}
	public function commit() {
		return $this->dbCon->commit();
	}
	public function rollback() {
		return $this->dbCon->rollBack();
	}

	public function __construct( $f_szDatabase, $f_szUser = null, $f_szPass = null, $f_szDb = null ) {
		try {
			$this->dbCon = new PDO('sqlite:'.$f_szDatabase);
			$this->dbCon->sqliteCreateFunction('IF', array('db_sqlite', 'fn_if'));
			$this->dbCon->sqliteCreateFunction('RAND', array('db_sqlite', 'fn_rand'));
		}
		catch ( PDOException $ex ) {
			$this->saveError($ex->getMessage(), $ex->getCode());
		}
	}

	public function saveError( $error = true, $errno = 0 ) {
		if ( is_string($error) && $errno ) {
			
		}
		else if ( $error ) {
			$error = $this->dbCon->errorInfo();
			$this->errno = $error[1];
			$this->error = $error[2];
			$this->m_iAffectedRows = 0;
		}
		else {
			$this->errno = 0;
			$this->error = '';
		}
	}

	public function connected() {
		return $this->dbCon && false !== $this->dbCon->query('SELECT 1 FROM sqlite_master');
	}

	public function escape($v) {
		return str_replace("'", "''", (string)$v);
	}

	public function insert_id() {
		return $this->dbCon->lastInsertId();
	}

	public function affected_rows() {
		return $this->m_iAffectedRows;
	}

	public function query( $f_szSqlQuery ) {
		$this->num_queries++;
		$this->last_query = $f_szSqlQuery;
		if ( false === ($r = $this->dbCon->query($f_szSqlQuery)) ) {
			$this->saveError(true);
			return false;
		}
		$this->saveError(false);
		$this->m_iAffectedRows = $r->rowCount();
		return $r;
	}

	public function fetch($f_szSqlQuery) {
		$r = $this->query($f_szSqlQuery);
		if ( !$r ) {
			return false;
		}
		return $r->fetchAll(PDO::FETCH_ASSOC);
	}

	public function fetch_fields($f_szSqlQuery) {
		$r = $this->query($f_szSqlQuery);
		if ( !$r ) {
			return false;
		}
		$a = array();
		while ( $l = $r->fetch(PDO::FETCH_NUM) ) {
			$a[$l[0]] = $l[1];
		}
		return $a;
	}

	public function select_one($tbl, $field, $where = '') {
		$r = $this->query('SELECT '.$field.' FROM '.$tbl.( $where ? ' WHERE '.$where : '' ).' LIMIT 1;');
		if ( !$r ) {
			return false;
		}
		return $r->fetchColumn();
	}

	public function count_rows($f_szSqlQuery) {
		$r = $this->query($f_szSqlQuery);
		if ( !$r ) {
			return false;
		}
		return count($r->fetchAll());
	}

	public function select_by_field($tbl, $field, $where = '') {
		$r = $this->query('SELECT * FROM '.$tbl.( $where ? ' WHERE '.$where : '' ).';');
		if ( !$r ) {
			return false;
		}
		$a = array();
		while ( $l = $r->fetch(PDO::FETCH_ASSOC) ) {
			$a[$l[$field]] = $l;
		}
		return $a;
	}

} // END Class db_sqlite3


