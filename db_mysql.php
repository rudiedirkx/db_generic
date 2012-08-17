<?php

require_once(dirname(__FILE__).DIRECTORY_SEPARATOR.'db_generic.php');

class db_mysql extends db_generic {

	static public function open( $args ) {
		return new self($args);
	}

	protected $database = '';

	protected function __construct( $args ) {
		$host = self::option($args, 'host', ini_get('mysqli.default_host'));
		$user = self::option($args, 'user', ini_get('mysqli.default_user'));
		$pass = self::option($args, 'pass', ini_get('mysqli.default_pw'));
		$this->database = self::option($args, 'db', self::option($args, 'database', ''));
		$port = self::option($args, 'port', ini_get('mysqli.default_port'));

		$this->db = @new mysqli($host, $user, $pass, $this->database, $port);
		if ( $this->db->connect_errno ) {
			return $this->except('', $this->db->connect_error, $this->db->connect_errno);
		}

		$this->postConnect($args);
	}

	protected function postConnect($args) {
		if ( !isset($args['charset']) || !empty($args['charset']) ) {
			// set encoding
			$names = "SET NAMES 'utf8'";

			$collate = '';
			if ( !empty($args['collate']) ) {
				$charset = is_string($args['collate']) ? $args['collate'] : 'utf8_general_ci';
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


	public function begin() {
		return $this->execute('BEGIN');
	}

	public function commit() {
		return $this->execute('COMMIT');
	}

	public function rollback() {
		return $this->execute('ROLLBACK');
	}


	public function query( $query ) {
		$this->queries[] = $query;

		try {
			$q = @$this->db->query($query);
			if ( !$q ) {
				return $this->except($query, $this->error());
			}
		} catch ( Exception $ex ) {
			return $this->except($query, $ex->getMessage());
		}

		return $q;
	}

	public function execute( $query ) {
		return $this->query($query);
	}

	public function error() {
		return $this->db->error;
	}

	public function errno() {
		return $this->db->errno;
	}

	public function affected_rows() {
		return $this->db->affected_rows;
	}

	public function insert_id() {
		return $this->db->insert_id;
	}

	public function escapeValue( $value ) {
		return $this->db->real_escape_string((string)$value);
	}

	public function escapeTable( $value ) {
		return '`' . $value . '`';
	}

	public function escapeColumn( $value ) {
		return '`' . $value . '`';
	}

	public function tables() {
		$cache = &$this->metaCache[__FUNCTION__];

		if ( empty($cache) ) {
			$cache = $this->fetch_by_field('show full tables from ' . $this->database . ' where Table_type = ?', 'Tables_in_' . $this->database, array('BASE TABLE'))->all();
		}

		return $cache;
	}

	public function columns( $tableName ) {
		$cache = &$this->metaCache[__FUNCTION__];

		if ( !isset($cache[$tableName]) ) {
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
					else if ( 'INT' == $type )  {
						$details['size'] = 10;
					}
				}
				else {
					if ( 1 == $details['size'] ) {
						$type = 'TINYINT';
					}
				}

				isset($details['size']) && $type .= '(' . (int)$details['size'] . ')';

				$properties[] = $type;

				// constraints
				if ( !empty($details['unsigned']) ) {
					$properties[] = 'unsigned';
				}

				// not null
				if ( isset($details['null']) ) {
					$properties[] = $details['null'] ? 'NULL' : 'NOT NULL';
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

}



class db_mysql_result extends db_generic_result {

	static public function make( $result, $class = '', $db = null ) {
		return false !== $result ? new self($result, $class, $db) : false;
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


