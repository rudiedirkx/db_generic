<?php

# - execute
# - query
# + select
# - fetch
# + select_one
# - fetch_one
# + select_fields
# - fetch_fields
# + select_by_field
# - fetch_by_field
# + count
# - count_rows
# + update
# + insert
# + replace
# + delete
# - table

abstract class db_generic {

	protected $dbCon;
	public $error = '';
	public $errno = 0;
	public $num_queries = 0;

	abstract protected function __construct( $args );
	abstract public function saveError();
	public function connected() {
		return false;
	}
	abstract public function insert_id();
	abstract public function affected_rows();
	abstract public function execute( $query );
	abstract public function query( $query );
	abstract public function fetch( $query );
	abstract public function fetch_fields( $query );
	abstract public function fetch_by_field( $query, $field );
	abstract public function count_rows( $query );

	abstract public function escape( $value );
	public function quote( $value ) {
		return "'" . $value . "'";
	}
	public function escapeAndQuote( $value ) {
		if ( null === $value ) {
			return 'NULL';
		}
		if ( is_bool($value) ) {
			return (int)$value;
		}
		return $this->quote($this->escape($value));
	}

	static public $paramPlaceholder = '?';

	public function replaceholders( $conditions, $params ) {
		$conditions = $this->stringifyConditions($conditions);

		if ( array() === $params || null === $params ) {
			return $conditions;
		}

		$ph = static::$paramPlaceholder;
		$offset = 0;
		foreach ( (array)$params AS $param ) {
			$pos = strpos($conditions, $ph, $offset);
			if ( false === $pos ) {
				break;
			}
			$param = is_array($param) ? implode(', ', array_map(array($this, 'escapeAndQuote'), $param)) : $this->escapeAndQuote((string)$param);
			$conditions = substr_replace($conditions, $param, $pos, strlen($ph));
			$offset = $pos + strlen($param);
		}

		return $conditions;
	}

	public function stringifyColumns( $columns ) {
		if ( !is_string($columns) ) {
			$columns = implode(', ', (array)$columns);
		}
		return $columns;
	}

	public function stringifyUpdates( $updates ) {
		if ( !is_string($updates) ) {
			$u = '';
			foreach ( (array)$updates AS $k => $v ) {
				if ( is_int($k) ) {
					$u .= ', ' . $v;
				}
				else {
					$u .= ', ' . $k . ' = ' . $this->escapeAndQuote($v);
				}
			}
			$updates = substr($u, 1);
		}
		return $updates;
	}

	public function stringifyConditions( $conditions, $delim = 'AND', $table = null ) {
		if ( !is_string($conditions) ) {
			$sql = array();
			foreach ( (array)$conditions AS $column => $value ) {
				if ( is_int($column) ) {
					$sql[] = $value;
				}
				else {
					$sql[] = $column . ( null === $value ? ' IS NULL' : ' = ' . $this->escapeAndQuote($value) );
				}
			}
			$conditions = implode(' '.$delim.' ', $sql);
		}
		return $conditions;
	}

	public function select( $table, $conditions, $params = array(), $justFirst = false ) {
		$conditions = $this->replaceholders($conditions, $params);
		$query = 'SELECT * FROM '.$table.' WHERE '.$conditions;
		return $this->fetch($query, (bool)$justFirst);
	}

	public function select_one( $table, $field, $conditions, $params = array() ) {
		$conditions = $this->replaceholders($conditions, $params);
		return $this->fetch_one('SELECT '.$field.' FROM '.$table.' WHERE '.$conditions);
	}

	public function select_fields( $table, $fields, $conditions, $params = array() ) {
		if ( !is_string($fields) ) {
			$fields = implode(', ', array_map(array($this, 'escapeAndQuoteColumn'), (array)$fields));
		}
		$query = 'SELECT '.$fields.' FROM '.$this->escapeAndQuoteTable($table).' WHERE '.$conditions;
		return $this->fetch_fields($query);
	}

	public function select_by_field( $table, $field, $conditions, $params = array() ) {
		$conditions = $this->replaceholders($conditions, $params);
		$query = 'SELECT * FROM '.$table.' WHERE '.$conditions;
		return $this->fetch_by_field($query, $field);
	}

	public function max($tbl, $field, $where = '') {
		return $this->select_one($tbl, 'MAX('.$field.')', $where);
	}

	public function min($tbl, $field, $where = '') {
		return $this->select_one($tbl, 'MIN('.$field.')', $where);
	}

	public function count($tbl, $where = '') {
		return $this->select_one($tbl, 'COUNT(1)', $where);
	}

	public function replace_into($tbl, $values) {
		foreach ( $values AS $k => $v ) {
			$values[$k] = $this->escapeAndQuote($v);
		}
		return $this->query('REPLACE INTO '.$tbl.' ('.implode(',', array_keys($values)).') VALUES ('.implode(",", $values).');');
	}

	# done
	public function insert( $table, $values ) {
		$values = array_map(array($this, 'escapeAndQuote'), $values);
		$sql = 'INSERT INTO '.$table.' ('.implode(',', array_keys($values)).') VALUES ('.implode(',', $values).');';
		return $this->execute($sql);
	}

	# done
	public function update( $table, $updates, $conditions, $params = array() ) {
		$updates = $this->stringifyUpdates($updates);
		$conditions = $this->replaceholders($conditions, $params);
		$sql = 'UPDATE '.$table.' SET '.$updates.' WHERE '.$conditions.'';
		return $this->execute($sql);
	}

	# done
	public function delete( $table, $conditions, $params = array() ) {
		$conditions = $this->replaceholders($conditions, $params);
		$sql = 'DELETE FROM '.$table.' WHERE '.$conditions.';';
		return $this->execute($sql);
	}


} // END Class db_generic


