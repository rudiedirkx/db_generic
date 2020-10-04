<?php

class db_pgsql extends db_pdo {

	protected $database = '';

	protected function __construct( $params ) {
		if ( isset($params['uri']) ) {
			$params = parse_url("x://" . $params['uri']);
			unset($params['scheme']);

			$params['dbname'] = substr($params['path'], 1);
			unset($params['path']);
		}

		if ( isset($params['pass']) ) {
			$params['password'] = $params['pass'];
			unset($params['pass']);
		}

		$this->database = $params['dbname'];

		$uri = str_replace('&', ';', http_build_query($params));
		parent::__construct('pgsql:' . $uri);
	}


	public function escapeValue( $value ) {
		return str_replace("'", "''", (string) $value);
	}

	public function quoteColumn( $column ) {
		return $column;
	}

	public function quoteTable( $table ) {
		return $table;
	}


	public function tables() {
		$cache = &$this->metaCache[__FUNCTION__];

		if ( empty($cache) ) {
			$this->connect();
			$cache = $this->fetch_by_field("
				SELECT *
				FROM pg_catalog.pg_tables
				WHERE 1=1
			", 'tablename')->all();
// print_r($cache);
// exit;
		}

		return $cache;
	}

	public function columns( $tableName ) {
		$cache = &$this->metaCache[__FUNCTION__];

		if ( !isset($cache[$tableName]) ) {
			$this->connect();
			$cache[$tableName] = $this->fetch_by_field("
				SELECT *
				FROM information_schema.columns
				WHERE table_name = ?
			", 'column_name', [$tableName])->all();
// print_r($cache[$tableName]);
// exit;
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
				$properties[] = 'SERIAL';
			}
			// check special stuff
			else {
				// type
				$type = isset($details['type']) ? strtoupper($details['type']) : 'VARCHAR';
				isset($details['unsigned']) && $type = 'INT';

				if ( isset($details['size']) ) {
					if ( 1 == $details['size'] ) {
						$type = 'SMALLINT';
					}
				}

				isset($details['options']) && $type .= '(' . implode(', ', array_map(array($this, 'escapeAndQuote'), $details['options'])) . ')';

				if ( $type == 'VARCHAR' && ($details['ci'] ?? true) === true ) {
					$type = 'CITEXT';
				}

				$properties[] = $type;

				// not null
				if ( isset($details['null']) ) {
					$properties[] = $details['null'] ? 'NULL' : 'NOT NULL';
				}

				// unique
				if ( !empty($details['unique']) ) {
					$properties[] = 'UNIQUE';
				}

				// default -- ignore NULL
				if ( isset($details['default']) ) {
					$D = $details['default'];
					$properties[] = 'DEFAULT ' . ( is_int($D) || is_float($D) ? $D : $this->escapeAndQuote($D) );
				}
			}

			// SQL
			$sql = $this->escapeAndQuoteColumn($columnName) . ' ' . implode(' ', $properties);

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
		return [];
	}

	public function index( $tableName, $indexName, $indexDefinition = null, $returnSQL = false ) {
		$sql = '';

		if ( $returnSQL ) {
			return $sql;
		}

		return true;
	}

}
