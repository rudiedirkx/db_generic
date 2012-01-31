<?php

require_once(dirname(__FILE__).DIRECTORY_SEPARATOR.'db_generic.php');

class db_mysql extends db_generic {

	static public function open( $args ) {
		$db = new self($args);
		if ( $db->connected() ) {
			return $db;
		}
	}

	public function __construct( $args ) {
		if ( isset($args['exceptions']) ) {
			$this->throwExceptions = (bool)$args['exceptions'];
		}

		$host = self::option($args, 'host', ini_get('mysqli.default_host'));
		$user = self::option($args, 'user', ini_get('mysqli.default_user'));
		$pass = self::option($args, 'pass', ini_get('mysqli.default_pw'));
		$db = self::option($args, 'db', '');
		$port = self::option($args, 'port', ini_get('mysqli.default_port'));

		try {
			$this->db = new mysqli($host, $user, $pass, $db, $port);
		}
		catch ( PDOException $ex ) {
			//$this->saveError($ex->getMessage(), $ex->getCode());
		}
	}

	public function connected() {
		return $this->db && is_object(@$this->query('SELECT USER()'));
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

	public function error() {
		return $this->db->error;
	}

	public function errno() {
		return $this->db->errno;
	}

	public function affected_rows() {
		return $this->affected;
	}

	public function insert_id() {
		return $this->db->lastInsertId();
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

	public function singleResult() {
		$row = $this->result->fetch_row();
		return $row ? $row[0] : false;
	}

	public function nextObject( $class = '', $args = array() ) {
		$class or $class = self::$return_object_class;

		method_exists($class, '__construct') or $args = array();

		if ( $args ) {
			return $this->result->fetch_object($class, $args);
		}

		return $this->result->fetch_object($class);
	}

	public function nextAssocArray() {
		return $this->result->fetch_assoc();
	}

	public function nextNumericArray() {
		return $this->result->fetch_row();
	}

}


