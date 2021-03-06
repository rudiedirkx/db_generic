<?php

class db_mysql extends db_generic {

	protected $database = '';

	public $quoteColumn = '';
	public $quoteTable = '';

	protected function __construct( $params ) {
		$this->params = $params;
	}

	public function connect() {
		if ( $this->params === false ) return;

		$this->db = mysqli_init();

		isset($this->params['timeout']) and $this->db->options(MYSQLI_OPT_CONNECT_TIMEOUT, $this->params['timeout']);

		$this->database = self::option($this->params, 'db', self::option($this->params, 'database', ''));

		$args = array(
			self::option($this->params, 'host', ini_get('mysqli.default_host')),
			self::option($this->params, 'user', ini_get('mysqli.default_user')),
			self::option($this->params, 'pass', ini_get('mysqli.default_pw')),
			$this->database,
		);
		if ( isset($this->params['port']) || isset($this->params['socket']) || isset($this->params['flags']) ) {
			$args[] = self::option($this->params, 'port', ini_get('mysqli.default_port'));

			if ( isset($this->params['socket']) || isset($this->params['flags']) ) {
				$args[] = self::option($this->params, 'socket', '');

				isset($this->params['flags']) and $args[] = $this->params['flags'];
			}
		}

		@call_user_func_array(array($this->db, 'real_connect'), $args);

		if ( $this->db->connect_errno ) {
			return $this->except('', $this->db->connect_error, $this->db->connect_errno);
		}

		$params = $this->params;
		$this->params = false;
		$this->postConnect($params);
	}

	protected function postConnect( $params ) {
		if ( !isset($params['charset']) || !empty($params['charset']) ) {
			// set encoding
			$names = "SET NAMES 'utf8'";

			$collate = '';
			if ( !empty($params['collate']) ) {
				$charset = is_string($params['collate']) ? $params['collate'] : 'utf8_general_ci';
				$collate = " COLLATE '" . $charset . "'";
			}

			$this->execute($names . $collate);
		}
	}

	public function connected() {
		try {
			return $this->db && is_object(@$this->query('SELECT USER()'));
		}
		catch ( Exception $ex ) {}

		return false;
	}


	public function quoteColumn( $column ) {
		return "$this->quoteColumn$column$this->quoteColumn";
	}

	public function quoteTable( $table ) {
		return "$this->quoteTable$table$this->quoteTable";
	}


	public function enableForeignKeys() {
	}


	public function begin() {
		return $this->execute('BEGIN');
	}

	public function commit() {
		return $this->execute('COMMIT');
	}

	public function rollback() {
		return $this->execute('ROLLBACK');
	}


	public function query( $query, $params = array() ) {
		$this->connect();

		$query = $this->replaceholders($query, $params);
		$_time = microtime(1);

		try {
			$q = @$this->db->query($query);
			if ( !$q ) {
				$this->logQuery($query, $_time, $this->error());
				return $this->except($query, $this->error());
			}
			else {
				$this->logQuery($query, $_time);
			}
		}
		catch ( Exception $ex ) {
			$this->logQuery($query, $_time, $ex->getMessage());
			return $this->except($query, $ex->getMessage());
		}

		return $q;
	}

	public function execute( $query, $params = array() ) {
		$ok = $this->query($query, $params);
		return $this->returnAffectedRows ? $this->affected_rows() : $ok;
	}

	public function error() {
		$this->connect();
		return $this->db->error;
	}

	public function errno() {
		$this->connect();
		return $this->db->errno;
	}

	public function affected_rows() {
		$this->connect();
		return $this->db->affected_rows;
	}

	public function insert_id() {
		$this->connect();
		return $this->db->insert_id;
	}

	public function escapeValue( $value ) {
		$this->connect();
		return $this->db->real_escape_string((string)$value);
	}

	public function escapeTable( $value ) {
		return $value;
	}

	public function escapeColumn( $value ) {
		return $value;
	}

	public function tables() {
		$cache = &$this->metaCache[__FUNCTION__];

		if ( empty($cache) ) {
			$this->connect();
			$cache = $this->fetch_by_field('show full tables from ' . $this->database . ' where Table_type = ?', 'Tables_in_' . $this->database, array('BASE TABLE'))->all();
		}

		return $cache;
	}

	public function columns( $tableName ) {
		$cache = &$this->metaCache[__FUNCTION__];

		if ( !isset($cache[$tableName]) ) {
			$this->connect();
			$cache[$tableName] = $this->fetch_by_field('EXPLAIN ' . $this->escapeAndQuoteTable($tableName), 'Field')->all();
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

				isset($details['options']) && $type .= '(' . implode(', ', array_map(array($this, 'escapeAndQuote'), $details['options'])) . ')';

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

			// execute
			$sql = 'ALTER TABLE ' . $this->escapeAndQuoteTable($tableName) . ' ADD COLUMN ' . $sql;
			return $this->execute($sql);
		}

		return $column;
	}

	public function indexes( $tableName ) {
		$cache = &$this->metaCache[__FUNCTION__];

		if ( !isset($cache[$tableName]) ) {
			$table = $this->escapeAndQuoteTable($tableName);
			$cache[$tableName] = $this->fetch_by_field("show index from $table", 'Key_name')->all();
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



class db_mysql_result extends db_generic_result {

	static public function make( $db, $result, $options ) {
		return false !== $result ? new self($db, $result, $options) : false;
	}


	public function singleValue() {
		$row = $this->result->fetch_row();
		return $row ? $row[0] : false;
	}


	public function nextObject( $args = array() ) {
		return $this->result->fetch_object($this->class);
	}


	public function nextAssocArray() {
		return $this->result->fetch_assoc();
	}


	public function nextNumericArray() {
		return $this->result->fetch_row();
	}

}


