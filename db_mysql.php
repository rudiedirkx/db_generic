<?php

require_once(dirname(__FILE__).DIRECTORY_SEPARATOR.'db_generic.php');

class db_mysql extends db_generic {

	static public function open( $args ) {
		return new self($args);
	}

	protected function __construct( $args ) {
		$host = self::option($args, 'host', ini_get('mysqli.default_host'));
		$user = self::option($args, 'user', ini_get('mysqli.default_user'));
		$pass = self::option($args, 'pass', ini_get('mysqli.default_pw'));
		$db = self::option($args, 'db', self::option($args, 'database', ''));
		$port = self::option($args, 'port', ini_get('mysqli.default_port'));

		$this->db = @new mysqli($host, $user, $pass, $db, $port);
		if ( $this->db->connect_errno ) {
			return $this->except('', $this->db->connect_error, $this->db->connect_errno);
		}

		$this->postConnect($args);
	}

	protected function postConnect($args) {
		// set encoding
		$names = "SET NAMES 'utf8'";

		$collate = '';
		if ( !empty($args['collate']) ) {
			$collate = " COLLATE '" . ( is_string($args['collate']) ? $args['collate'] : 'utf7_general_ci' ) . "'";
		}

		$this->execute($names . $collate);
	}

	public function connected() {
		try {
			return $this->db && is_object(@$this->query('SELECT USER()'));
		}
		catch ( Exception $ex ) {}

		return false;
	}


	public function begin() {
		return $this->db->execute('BEGIN');
	}

	public function commit() {
		return $this->db->execute('COMMIT');
	}

	public function rollback() {
		return $this->db->execute('ROLLBACK');
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

	public function table( $tableName, $definition = array() ) {
		// existing table
		try {
			$table = $this->fetch('EXPLAIN '.$this->escapeAndQuoteTable($tableName));
		}
		catch ( Exception $ex ) {
			$table = false;
		}

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
			$sql = 'CREATE TABLE `'.$tableName.'` (' . "\n";
			$first = true;
			foreach ( $definition['columns'] AS $columnName => $details ) {
				// do stuff here

				$comma = $first ? ' ' : ',';
//				$sql .= '  ' . $comma . '"'.$columnName.'" '.$type.$notnull.$default.$constraint . "\n";

				$first = false;
			}
			$sql .= ');';

			// execute
			return false && $this->execute($sql);
		}

		// table exists -> success
		if ( $table ) {
			return $table;
		}
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


