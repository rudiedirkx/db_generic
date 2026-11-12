<?php

class db_sqlite extends db_pdo {

	public function __construct( string $filepath ) {
		parent::__construct('sqlite:' . $filepath);
	}

	protected function postConnect() : void {
		// Add custom functions
		$this->addFunction('REGEXP', array(__CLASS__, 'fn_regexp'));
		$this->addFunction('REGEXP_REPLACE', array(__CLASS__, 'fn_regexp_replace'));
		$this->addFunction('IF', array(__CLASS__, 'fn_if'));
		$this->addFunction('RAND', array(__CLASS__, 'fn_rand'));
		$this->addFunction('CONCAT', array(__CLASS__, 'fn_concat'));
		$this->addFunction('FROM_UNIXTIME', array(__CLASS__, 'fn_from_unixtime'));

		// Add simple functions
		$this->addFunction('CEIL', 'ceil');
		$this->addFunction('FLOOR', 'floor');
		$this->addFunction('INTVAL', 'intval');
		$this->addFunction('FLOATVAL', 'floatval');
		$this->addFunction('LOWER', 'mb_strtolower');
		$this->addFunction('UPPER', 'mb_strtoupper');
		$this->addFunction('SHA1', 'sha1');
		$this->addFunction('MD5', 'md5');

		// set encoding
		$this->execute('PRAGMA encoding="UTF-8"');

		// screw ACID, go SPEED!
		$this->execute('PRAGMA synchronous=OFF');
		$this->execute('PRAGMA journal_mode=MEMORY');
	}

	public function addFunction( string $name, callable $callable ) : void {
		// Ugly PHP 8.3 compat. Bump to PHP 8.4 someday and always use Pdo\Sqlite::createFunction().
		if ( $this->db instanceof Pdo\Sqlite ) { // @phpstan-ignore class.notFound
			$this->db->createFunction($name, $callable); // @phpstan-ignore class.notFound
		}
		else {
			$this->db->sqliteCreateFunction($name, $callable);
		}
	}

	public function enableForeignKeys() {
		$this->execute('PRAGMA foreign_keys = ON');
	}


	protected function escapeValue( $value ) : string {
		return str_replace("'", "''", (string)$value);
	}

	protected function quoteColumn( string $column ) : string {
		return '"' . $column . '"';
	}

	protected function quoteTable( string $table ) : string {
		return '"' . $table . '"';
	}

	public function tables() {
		$cache = &$this->metaCache[__FUNCTION__];

		if ( empty($cache) ) {
			$this->connect();
			$cache = $this->select_by_field('sqlite_master', 'tbl_name', array(
				'type' => 'table',
			));
		}

		return $cache;
	}

	public function columns( $tableName ) {
		$cache = &$this->metaCache[__FUNCTION__];

		if ( !isset($cache[$tableName]) ) {
			$this->connect();
			$cache[$tableName] = $this->fetch_by_field('PRAGMA table_info(?);', 'name', array($tableName));
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
			if ( $column && !$returnSQL ) { // @phpstan-ignore booleanNot.alwaysTrue
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
			));
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
