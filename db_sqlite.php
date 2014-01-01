<?php

require_once(dirname(__FILE__).DIRECTORY_SEPARATOR.'db_generic.php');

class db_sqlite extends db_generic {

	static public function open( $args ) {
		return new self($args);
	}

	public $affected = 0;

	protected function __construct( $args ) {
		try {
			$this->db = new PDO('sqlite:' . $args['database']);

			// add custom functions
			$refl = new ReflectionClass(get_class($this));
			$methods = $refl->getMethods(ReflectionMethod::IS_STATIC);
			foreach ( $methods AS $method ) {
				if ( 0 === strpos($method->name, 'fn_') ) {
					$functionName = strtoupper(substr($method->name, 3));
					$this->db->sqliteCreateFunction($functionName, array('db_sqlite', $method->name));
				}
			}

			// add simple functions
			$this->db->sqliteCreateFunction('CEIL', 'ceil');
			$this->db->sqliteCreateFunction('FLOOR', 'floor');
			$this->db->sqliteCreateFunction('INTVAL', 'intval');
			$this->db->sqliteCreateFunction('FLOATVAL', 'floatval');
			$this->db->sqliteCreateFunction('LOWER', 'mb_strtolower');
			$this->db->sqliteCreateFunction('UPPER', 'mb_strtoupper');
			$this->db->sqliteCreateFunction('SHA1', 'sha1');
			$this->db->sqliteCreateFunction('MD5', 'md5');

			$this->postConnect($args);
		}
		catch ( PDOException $ex ) {
			return $this->except('', $ex->getMessage(), $ex->getCode());
		}
	}

	protected function postConnect($args) {
		// set encoding
		$this->execute('PRAGMA encoding = "UTF-8"');
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


	public function query( $query, $params = array() ) {
		$query = $this->replaceholders($query, $params);
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

	public function execute( $query, $params = array() ) {
		$query = $this->replaceholders($query, $params);
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

	public function quoteColumn( $column ) {
		return '"' . $column . '"';
	}

	public function quoteTable( $table ) {
		return '"' . $table . '"';
	}

	public function tables() {
		$cache = &$this->metaCache[__FUNCTION__];

		if ( empty($cache) ) {
			$cache = $this->select_by_field('sqlite_master', 'tbl_name', array(
				'type' => 'table',
			))->all();
		}

		return $cache;
	}

	public function columns( $tableName ) {
		$cache = &$this->metaCache[__FUNCTION__];

		if ( !isset($cache[$tableName]) ) {
			$cache[$tableName] = $this->fetch_by_field('PRAGMA table_info(?);', 'name', array($tableName))->all();
		}

		return $cache[$tableName];
	}

	public function column( $tableName, $columnName, $columnDefinition = null, $returnSQL = false ) {
		// if we care only about SQL, don't fetch columns
		$columns = $column = false;
		if ( !$returnSQL ) {
			$columns = $this->columns($tableName);
			isset($columns[$columnName]) && $column = $columns[$columnName];
		}

		// create it?
		// can be empty: array()
		if ( null !== $columnDefinition ) {
			// column exists -> fail
			if ( $column && !$returnSQL ) {
				return null;
			}

			// add column
			$details = $columnDefinition;
			$properties = array();

			// if PK, forget the rest
			if ( !empty($details['pk']) ) {
				$properties[] = 'INTEGER PRIMARY KEY AUTOINCREMENT';
			}
			// check special stuff
			else {
				// type
				$type = isset($details['type']) ? strtoupper($details['type']) : 'TEXT';
				isset($details['unsigned']) && $type = 'INT';
				$properties[] = $type;

				// not null
				if ( isset($details['null']) ) {
					$properties[] = $details['null'] ? 'NULL' : 'NOT NULL';
				}

				// unique
				if ( !empty($details['unique']) ) {
					$properties[] = 'UNIQUE';
				}

				// constraints
				if ( !empty($details['unsigned']) ) {
					$properties[] = 'CHECK ("'.$columnName.'" >= 0)';
				}
				if ( isset($details['min']) ) {
					$properties[] = 'CHECK ("'.$columnName.'" >= ' . (float)$details['min'] . ')';
				}
				if ( isset($details['max']) ) {
					$properties[] = 'CHECK ("'.$columnName.'" <= ' . (float)$details['max'] . ')';
				}

				// default -- ignore NULL
				if ( isset($details['default']) ) {
					$D = $details['default'];
					$properties[] = 'DEFAULT ' . ( is_int($D) || is_float($D) ? $D : $this->escapeAndQuote($D) );
				}

				// foreign key relationship
				if ( isset($details['references']) ) {
					list($tbl, $col) = $details['references'];
					$properties[] = 'REFERENCES ' . $tbl . '(' . $col . ')';
				}

				// Case-insensitive (not the default in SQLite)
				if ( 'TEXT' == $type ) {
					$properties[] = 'COLLATE NOCASE';
				}
			}

			// SQL
			$sql = '"' . $columnName . '" ' . implode(' ', $properties);

			// return SQL
			if ( $returnSQL ) {
				return $sql;
			}

			// execute
			$sql = 'ALTER TABLE ' . $this->escapeAndQuoteTable($tableName) . ' ADD COLUMN ' . $sql;
			return $this->execute($sql);
		}

		return $column;
	}

	public function indexes( $tableName ) {
		$cache = &$this->metaCache[__FUNCTION__];

		if ( !isset($cache[$tableName]) ) {
			$cache[$tableName] = $this->select_by_field('sqlite_master', 'name', array(
				'type' => 'index',
				'tbl_name' => $tableName,
			))->all();
		}

		return $cache[$tableName];
	}

	public function index( $tableName, $indexName, $indexDefinition = null, $returnSQL = false ) {
		// existing index
		$indexes = $this->indexes($tableName);
		$index = @$indexes[$indexName];

		// create index
		if ( $indexDefinition ) {
			// column exists -> fail
			if ( $index && !$returnSQL ) {
				return null;
			}

			// format
			if ( !isset($indexDefinition['columns']) ) {
				$indexDefinition = array('columns' => $indexDefinition);
			}

			// unique
			$unique = !empty($indexDefinition['unique']);
			$unique = $unique ? 'UNIQUE' : '';

			// subject columns
			$columns = array_map(array($this, 'escapeAndQuoteTable'), $indexDefinition['columns']);
			$columns = implode(', ', $columns);

			// full SQL
			$sql = 'CREATE '.$unique.' INDEX "'.$indexName.'" ON "'.$tableName.'" ('.$columns.')';

			if ( $returnSQL ) {
				return $sql;
			}

			return $this->execute($sql);
		}

		return $index;
	}

}



class db_sqlite_result extends db_generic_result {

	static public function make( $db, $result, $options ) {
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


