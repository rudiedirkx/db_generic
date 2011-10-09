<?php

require_once(dirname(__FILE__).DIRECTORY_SEPARATOR.'db_generic.php');

class db_sqlite extends db_generic {

	public $affected = 0;

	static public function open( $args ) {
		$db = new self($args);
		if ( $db->connected() ) {
			return $db;
		}
	}

	protected function __construct( $args ) {
		if ( isset($args['exceptions']) ) {
			$this->throwExceptions = (bool)$args['exceptions'];
		}

		try {
			$this->db = new PDO('sqlite:'.$args['database']);
			$this->db->sqliteCreateFunction('IF', array('db_generic', 'fn_if'));
			$this->db->sqliteCreateFunction('RAND', array('db_generic', 'fn_rand'));
			$this->db->sqliteCreateFunction('CONCAT', array('db_generic', 'fn_concat'));
		}
		catch ( PDOException $ex ) {
			//$this->saveError($ex->getMessage(), $ex->getCode());
		}
	}

	public function connected() {
		return is_object(@$this->query('SELECT COUNT(1) FROM sqlite_master'));
	}


	public function query( $query ) {
		$this->queries[] = $query;

		try {
			$q = @$this->db->query($query);
			if ( !$q ) {
				return $this->except($query.' -> '.$this->error());
			}
		} catch ( PDOException $ex ) {
			return $this->except($query.' -> '.$ex->getMessage());
		}

		return $q;
	}

	public function execute( $query ) {
		$this->queries[] = $query;

		try {
			$q = @$this->db->exec($query);
			if ( !$q ) {
				return $this->except($query.' -> '.$this->error());
			}
		} catch ( PDOException $ex ) {
			return $this->except($query.' -> '.$ex->getMessage());
		}

		$this->affected = $q;

		return true;
	}

	public function result( $query, $targetClass = '' ) {
		$resultClass = __CLASS__.'_result';
		return $resultClass::make($this->query($query), $targetClass, $this);
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
		return addslashes((string)$value);
	}

	public function table( $tableName, $definition = array() ) {
		// existing table
		$table = $this->select('sqlite_master', 'tbl_name = '.$this->escapeAndQuoteValue($tableName));

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

}



class db_sqlite_result extends db_generic_result {

	static public function make( $result, $class = '', $db = null ) {
		return false !== $result ? new self($result, $class, $db) : false;
	}

	public $rows = array();
	public $index = 0;

	public function singleResult() {
		return $this->result->fetchColumn(0);
	}

	public function nextObject( $class = '', $args = array() ) {
		$class or $class = 'stdClass';

		if ( !$this->rows ) {
			$this->rows = $this->result->fetchAll(PDO::FETCH_CLASS, $class, $args);
		}

		if ( isset($this->rows[$this->index]) ) {
			return $this->rows[$this->index++];
		}
	}

	public function nextAssocArray() {
		return $this->result->fetch(PDO::FETCH_ASSOC);
	}

	public function nextNumericArray() {
		return $this->result->fetch(PDO::FETCH_NUM);
	}

}


