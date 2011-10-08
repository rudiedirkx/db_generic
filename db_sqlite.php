<?php

require_once(dirname(__FILE__).DIRECTORY_SEPARATOR.'db_generic.php');

class db_sqlite extends db_generic {

	static function fn_if( $f_bool, $f_yes, $f_no ) {
		return $f_bool ? $f_yes : $f_no;
	}

	static function fn_rand() {
		return rand() / getrandmax();
	}

	static public function open( $args ) {
		$db = new self($args);
		if ( $db->connected() ) {
			return $db;
		}
	}

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

	protected function __construct( $args ) {
		try {
			$this->dbCon = new PDO('sqlite:'.$args['database']);
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
		return $r;
	}

	public function execute( $query ) {
		$affected = $this->dbCon->exec($query);

		if ( false !== $affected ) {
			$this->m_iAffectedRows = $affected;
			return true;
		}

		return false;
	}

	public function fetch( $query, $mixed = null ) {
		// default options
		$class = false;
		$justFirst = false;
		$params = array();

		// unravel options
		if ( is_array($mixed) ) {
			if ( is_int(key($mixed)) ) {
				$params = $mixed;
			}
			else {
				isset($mixed['class']) && $class = $mixed['class'];
				isset($mixed['first']) && $justFirst = $mixed['first'];
				isset($mixed['params']) && $params = (array)$mixed['params'];
			}
		}
		else if ( is_bool($mixed) ) {
			$justFirst = $mixed;
		}
		else if ( is_string($mixed) ) {
			$class = $mixed;
		}

		// apply params
		if ( $params ) {
			$query = $this->replaceholders($query, $params);
		}

		$result = $this->query($query);

		if ( $justFirst ) {
			if ( $class ) {
				return $result->fetchObject($class, array(true));
			}
			return $result->fetchObject();
		}

		if ( $class ) {
			return $result->fetchAll(PDO::FETCH_CLASS, $class, array(true));
		}

		return $result->fetchAll(PDO::FETCH_OBJ);
	}

	/*public function fetch($f_szSqlQuery) {
		$r = $this->query($f_szSqlQuery);
		if ( !$r ) {
			return false;
		}
		return $r->fetchAll(PDO::FETCH_ASSOC);
	}*/

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

	public function fetch_one( $query ) {
		$r = $this->query($query);
		if ( !$r ) {
			return false;
		}
		return $r->fetchColumn(0);
	}

	public function count_rows($f_szSqlQuery) {
		$r = $this->query($f_szSqlQuery);
		if ( !$r ) {
			return false;
		}
		return count($r->fetchAll());
	}

	public function fetch_by_field( $query, $field ) {
		$result = $this->fetch($query);

		$a = array();
		foreach ( $result AS $obj ) {
			$a[$obj->{$field}] = $obj;
		}

		return $a;
	}

	public function table( $tableName, $definition = array() ) {
		// existing table
		$table = $this->select('sqlite_master', 'tbl_name = '.$this->escapeAndQuote($tableName));

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
			$sql = 'CREATE TABLE "'.$tableName.'" (' . "\n";
			$first = true;
			foreach ( $definition['columns'] AS $columnName => $details ) {
				// the very simple columns: array( 'a', 'b', 'c' )
				if ( is_int($columnName) ) {
					$columnName = $details;
					$details = array();
				}

				// if PK, forget the rest
				if ( !empty($details['pk']) ) {
					$type = 'INTEGER PRIMARY KEY AUTOINCREMENT';
					$notnull = '';
					$constraint = '';
					$default = '';
				}
				else {
					// check special stuff
					isset($details['unsigned']) && $details['type'] = 'INT';
					$type = isset($details['type']) ? strtoupper(trim($details['type'])) : 'TEXT';
					$notnull = isset($details['null']) ? ( $details['null'] ? '' : ' NOT' ) . ' NULL' : '';
					$constraint = !empty($details['unsigned']) ? ' CHECK ("'.$columnName.'" >= 0)' : '';
					$default = isset($details['default']) ? ' DEFAULT '.$this->escapeAndQuote($details['default']) : '';
				}

				$comma = $first ? ' ' : ',';
				$sql .= '  ' . $comma . '"'.$columnName.'" '.$type.$notnull.$default.$constraint . "\n";

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

} // END Class db_sqlite3


