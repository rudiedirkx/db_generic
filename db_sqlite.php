<?php

require_once __DIR__ . '/db_pdo.php';

class db_sqlite extends db_pdo {

	protected function __construct( $params ) {
		parent::__construct('sqlite:' . $params['database']);
	}

	protected function postConnect($params) {
		// Add custom functions
		$this->db->sqliteCreateFunction('REGEXP', array(__CLASS__, 'fn_regexp'));
		$this->db->sqliteCreateFunction('REGEXP_REPLACE', array(__CLASS__, 'fn_regexp_replace'));
		$this->db->sqliteCreateFunction('IF', array(__CLASS__, 'fn_if'));
		$this->db->sqliteCreateFunction('RAND', array(__CLASS__, 'fn_rand'));
		$this->db->sqliteCreateFunction('CONCAT', array(__CLASS__, 'fn_concat'));
		$this->db->sqliteCreateFunction('FROM_UNIXTIME', array(__CLASS__, 'fn_from_unixtime'));

		// Add simple functions
		$this->db->sqliteCreateFunction('CEIL', 'ceil');
		$this->db->sqliteCreateFunction('FLOOR', 'floor');
		$this->db->sqliteCreateFunction('INTVAL', 'intval');
		$this->db->sqliteCreateFunction('FLOATVAL', 'floatval');
		$this->db->sqliteCreateFunction('LOWER', 'mb_strtolower');
		$this->db->sqliteCreateFunction('UPPER', 'mb_strtoupper');
		$this->db->sqliteCreateFunction('SHA1', 'sha1');
		$this->db->sqliteCreateFunction('MD5', 'md5');

		// set encoding
		$this->execute('PRAGMA encoding="UTF-8"');

		// screw ACID, go SPEED!
		$this->execute('PRAGMA synchronous=OFF');
		$this->execute('PRAGMA journal_mode=OFF');
	}

	public function addFunction($name, $callable) {
		$this->db->sqliteCreateFunction($name, $callable);
	}

	public function enableForeignKeys() {
		return $this->execute('PRAGMA foreign_keys = ON');
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
			$this->connect();
			$cache = $this->select_by_field('sqlite_master', 'tbl_name', array(
				'type' => 'table',
			))->all();
		}

		return $cache;
	}

	public function columns( $tableName ) {
		$cache = &$this->metaCache[__FUNCTION__];

		if ( !isset($cache[$tableName]) ) {
			$this->connect();
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
				if (  in_array($type, array('DATE', 'TIME', 'DATETIME')) ) {
					$type = 'TEXT';
				}
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
					list($tbl, $col, $onDelete) = array_merge(array_filter($details['references']), array('restrict'));
					$properties[] = 'REFERENCES ' . $tbl . '(' . $col . ') ON DELETE ' . strtoupper($onDelete);
				}

				// Case-insensitive (not the default in SQLite)
				if ( 'TEXT' == $type && ($details['ci'] ?? true) === true ) {
					$properties[] = 'COLLATE NOCASE';
				}
			}

			// SQL
			$sql = $this->quoteColumn($columnName) . ' ' . implode(' ', $properties);

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
