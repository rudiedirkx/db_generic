<?php

require_once(dirname(__FILE__).DIRECTORY_SEPARATOR.'db_generic.php');

class db_sqlite extends db_generic {

	static public function open( $args ) {
		$db = new self($args);
		if ( $db->connected() ) {
			return $db;
		}
	}

	public $affected = 0;

	public function __construct( $args ) {
		if ( isset($args['exceptions']) ) {
			$this->throwExceptions = (bool)$args['exceptions'];
		}

		try {
			$this->db = new PDO('sqlite:'.$args['database']);
			$this->db->sqliteCreateFunction('IF', array('db_generic', 'fn_if'));
			$this->db->sqliteCreateFunction('RAND', array('db_generic', 'fn_rand'));
			$this->db->sqliteCreateFunction('CONCAT', array('db_generic', 'fn_concat'));
			$this->db->sqliteCreateFunction('FLOOR', 'floor');
			$this->db->sqliteCreateFunction('CEIL', 'ceil');
		}
		catch ( PDOException $ex ) {
			//$this->saveError($ex->getMessage(), $ex->getCode());
		}
	}

	public function connected() {
		return $this->db && is_object(@$this->query('SELECT COUNT(1) FROM sqlite_master'));
	}


	public function begin() {
		return $this->db->beginTransaction();
	}

	public function commit() {
		return $this->db->commit();
	}

	public function rollback() {
		return $this->db->rollBack();
	}


	public function query( $query ) {
		$this->queries[] = $query;

		try {
			$q = @$this->db->query($query);
			if ( !$q ) {
				return $this->except($query, $this->error());
			}
		} catch ( PDOException $ex ) {
			return $this->except($query, $ex->getMessage());
		}

		return $q;
	}

	public function execute( $query ) {
		$this->queries[] = $query;

		try {
			$r = @$this->db->exec($query);
			if ( false === $r ) {
				return $this->except($query, $this->error());
			}
		} catch ( PDOException $ex ) {
			return $this->except($query, $ex->getMessage());
		}

		$this->affected = $r;

		return true;
	}

	public function error( $error = null ) {
		$err = $this->db->errorInfo();
		return $err[2];
	}

	public function errno( $errno = null ) {
		return $this->db->errorCode();
	}

	public function affected_rows() {
		return $this->affected;
	}

	public function insert_id() {
		return $this->db->lastInsertId();
	}

	public function escapeValue( $value ) {
		return str_replace("'", "''", (string)$value);
	}

	public function tables() {
		$query = $this->select('sqlite_master', array('type' => 'table'));

		$tables = array();
		foreach ( $query AS $table ) {
			$tables[$table->name] = $table;
		}

		return $tables;
	}

	public function table( $tableName, $definition = array() ) {
		// existing table
		$table = $this->select('sqlite_master', array('tbl_name' => $tableName), null, true);

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
			return $this->execute($sql);
		}

		// table exists -> success
		if ( $table ) {
			return $table;
		}
	}

}



class db_sqlite_result extends db_generic_result {

	static public function make( $db, $result, $options = array() ) {
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


