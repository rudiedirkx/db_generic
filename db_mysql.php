<?php

require_once(dirname(__FILE__).DIRECTORY_SEPARATOR.'db_generic.php');

class db_mysql extends db_generic {

	static public function open( $host, $user, $pass, $db ) {
		if ( class_exists('mysqli') ) {
			require_once(dirname(__FILE__).DIRECTORY_SEPARATOR.'db_mysqli.php');

			return new db_mysqli($host, $user, $pass, $db);
		}

		return new self($host, $user, $pass, $db);
	}

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

	public function table( $tableName, $definition = array() ) {
		// existing table
		$table = $this->fetch('EXPLAIN `'.$tableName.'`');

		// create table
		if ( $definition ) {
			// table exists -> fail
			if ( $table ) {
				return false;
			}

			// table definition
			if ( !isset($definition['columns']) ) {
				$definition = array('columns' => $definition);
			}

			// create table sql
			$sql = 'CREATE TABLE `'.$tableName.'` (' . "\n";
			$first = true;
			foreach ( $definition['columns'] AS $columnName => $details ) {
				// the very simple columns: array( 'a', 'b', 'c' )
				if ( is_int($columnName) ) {
					$columnName = $details;
					$details = array();
				}

				// if PK, forget the rest
				if ( !empty($details['pk']) ) {
					$type = 'INTEGER PRIMARY KEY AUTO_INCREMENT';
					$notnull = '';
					$constraint = '';
					$default = '';
				}
				else {
					// check special stuff
					isset($details['unsigned']) && $details['type'] = 'INT';
					$type = isset($details['type']) ? strtoupper(trim($details['type'])) : 'TEXT';
					$notnull = isset($details['null']) ? ( $details['null'] ? '' : ' NOT' ) . ' NULL' : '';
					$constraint = !empty($details['unsigned']) ? ' UNSIGNED' : '';
					$default = isset($details['default']) ? ' DEFAULT '.$this->escapeAndQuote($details['default']) : '';
				}

				$comma = $first ? ' ' : ',';
				$sql .= '  ' . $comma . '`'.$columnName.'` '.$type.$notnull.$constraint . "\n";

				$first = false;
			}
			$sql .= ');';

			// execute
			return (bool)$this->query($sql);
		}

		// table exists -> success
		if ( $table ) {
			return $table[0];
		}
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