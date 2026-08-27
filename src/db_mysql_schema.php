<?php

trait db_mysql_schema {

	public string $quoteColumn = '';
	public string $quoteTable = '';

	public function enableForeignKeys() {
	}

	protected function quoteColumn( string $column ) : string {
		return "$this->quoteColumn$column$this->quoteColumn";
	}

	protected function quoteTable( string $table ) : string {
		return "$this->quoteTable$table$this->quoteTable";
	}

	public function tables() {
		$cache = &$this->metaCache[__FUNCTION__];

		if ( empty($cache) ) {
			$this->connect();
			$cache = $this->fetch_by_field('show full tables from ' . $this->database . ' where Table_type = ?', 'Tables_in_' . $this->database, array('BASE TABLE'));
		}

		return $cache;
	}

	public function afterCreateTable( string $tableName, mixed $tableDefinition ) : bool {
		$alters = [];
		foreach ( $tableDefinition['columns'] as $columnName => $columnDefinition ) {
			if ( isset($columnDefinition['references']) ) {
				$alters[] = $this->foreignKeyClause($columnName, $columnDefinition['references']);
			}
		}

		if ( count($alters) ) {
			$sql = 'ALTER TABLE ' . $this->escapeAndQuoteTable($tableName) . ' ' . implode(', ', $alters);
			return $this->execute($sql);
		}

		return true;
	}

	public function columns( $tableName ) {
		$cache = &$this->metaCache[__FUNCTION__];

		if ( !isset($cache[$tableName]) ) {
			$this->connect();
			$cache[$tableName] = $this->fetch_by_field('EXPLAIN ' . $this->escapeAndQuoteTable($tableName), 'Field');
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
				$properties[] = 'INTEGER unsigned PRIMARY KEY auto_increment';
			}
			// check special stuff
			else {
				// type
				$type = isset($details['type']) ? strtoupper($details['type']) : 'VARCHAR';
				isset($details['unsigned']) && $type = 'INT';

				if ( !isset($details['size']) ) {
					if ( 'VARCHAR' == $type )  {
						$details['size'] = 255;
					}
				}
				else {
					if ( 1 == $details['size'] ) {
						$type = 'TINYINT';
					}
				}

				if ( !empty($details['options']) ) {
					$type .= '(' . implode(', ', array_map(array($this, 'escapeAndQuote'), (array) $details['options'])) . ')';
				}
				elseif ( !empty($details['size']) ) {
					$type .= '(' . (int) $details['size'] . ')';
				}

				$properties[] = $type;

				// constraints
				if ( !empty($details['unsigned']) ) {
					$properties[] = 'unsigned';
				}

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

			$foreign = '';
			if ( isset($columnDefinition['references']) ) {
				$foreign = ', ' . $this->foreignKeyClause($columnName, $columnDefinition['references']);
			}

			// execute
			$sql = 'ALTER TABLE ' . $this->escapeAndQuoteTable($tableName) . ' ADD COLUMN ' . $sql . $foreign;
			return $this->execute($sql);
		}

		return $column;
	}

	/**
	 * @param string $fromColumn
	 * @param list<string> $references
	 */
	protected function foreignKeyClause( $fromColumn, array $references ) : string {
		list($toTable, $toColumn, $onDelete) = array_merge($references, ['RESTRICT']);
		return 'ADD FOREIGN KEY (' . $this->escapeAndQuoteColumn($fromColumn) . ') REFERENCES ' . $this->escapeAndQuoteTable($toTable) . ' (' . $this->escapeAndQuoteColumn($toColumn) . ') ON DELETE ' . $onDelete;
	}

	public function indexes( $tableName ) {
		$cache = &$this->metaCache[__FUNCTION__];

		if ( !isset($cache[$tableName]) ) {
			$table = $this->escapeAndQuoteTable($tableName);
			$cache[$tableName] = $this->fetch_by_field("show index from $table", 'Key_name');
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

			// full SQL
			$sql = "CREATE $unique INDEX " . $this->escapeAndQuoteTable($indexName) . ' ON ' . $this->escapeAndQuoteTable($tableName) . ' (' . implode(', ', $columns) . ')';

			if ( $returnSQL ) {
				return $sql;
			}

			return $this->execute($sql);
		}

		return $index;
	}

}
