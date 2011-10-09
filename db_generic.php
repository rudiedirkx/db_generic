<?php

class db_exception extends Exception {
	public $query = '';
	public function __construct( $error = '', $errno = -1, $previous = null, $options = array() ) {
		parent::__construct($error = '', $errno = -1, $previous = null);
		if ( isset($options['query']) ) {
			$this->query = $options['query'];
		}
	}
}

abstract class db_generic {

	static function fn_if( $f_bool, $f_yes = 1, $f_no = 0 ) {
		return $f_bool ? $f_yes : $f_no;
	}

	static function fn_rand() {
		return rand() / getrandmax();
	}

	static function fn_concat() {
		return implode(func_get_args());
	}

	public $queries = array();
	protected $db;
	protected $throwExceptions = true;

#	public $error = '';
#	public $errno = 0;

	abstract static public function open( $args );
	abstract protected function __construct( $args );
	abstract public function connected();

	abstract public function escapeValue( $value );

	public function quoteValue( $value ) {
		return "'".$value."'";
	}

	public function escapeAndQuoteValue( $value ) {
		if ( null === $value ) {
			return 'NULL';
		}
		if ( is_bool($value) ) {
			return (int)$value;
		}
		return $this->quoteValue($this->escapeValue($value));
	}

	public function escapeTable( $table ) {
		return $table;
	}

	public function quoteTable( $table ) {
		return $table;
	}

	public function escapeAndQuoteTable( $table ) {
		return $this->quoteTable($this->escapeTable($table));
	}

	public function escapeColumn( $column ) {
		return $column;
	}

	public function quoteColumn( $column ) {
		return $column;
	}

	public function escapeAndQuoteColumn( $column ) {
		return $this->quoteColumn($this->escapeColumn($column));
	}

	public function except( $query, $error, $errno = -1 ) {
		if ( $this->throwExceptions ) {
			throw new db_exception($error, $errno, null, array('query' => $query));
		}

#		$this->error = $error;
#		$this->errno = $errno;

		return false;
	}

	static public $replaceholder = '?';

	public function replaceholders( $conditions, $params ) {
		$conditions = $this->stringifyConditions($conditions);

		if ( array() === $params || null === $params || '' === $params ) {
			return $conditions;
		}

		$ph = static::$replaceholder;
		$offset = 0;
		foreach ( (array)$params AS $param ) {
			$pos = strpos($conditions, $ph, $offset);
			if ( false === $pos ) {
				break;
			}
			$param = is_array($param) ? implode(', ', array_map(array($this, 'escapeAndQuoteValue'), $param)) : $this->escapeAndQuoteValue((string)$param);
			$conditions = substr_replace($conditions, $param, $pos, strlen($ph));
			$offset = $pos + strlen($param);
		}

		return $conditions;
	}

	abstract public function begin();
	abstract public function commit();
	abstract public function rollback();
	public function transaction( $transaction, &$context = array() ) {
		if ( is_callable($transaction) ) {
			try {
				$this->begin();
				$context['result'] = call_user_func($transaction, $this, $context);
				$this->commit();

				return true;
			}
			catch ( Exception $ex ) {
				$this->rollback(); // I don't think an explicit rollback is necessary...

				$context['exception'] = $ex;

				return false;
			}
		}
	}

	public function fetch( $query, $mixed = null ) {
		// default options
		$class = false;
		$justFirst = false;
		$params = array();

		// unravel options
		if ( is_array($mixed) ) {
			if ( is_int(key($mixed)) ) {
				$params = $mixed;
			}
			else {
				isset($mixed['class']) && $class = $mixed['class'];
				isset($mixed['first']) && $justFirst = $mixed['first'];
				isset($mixed['params']) && $params = (array)$mixed['params'];
			}
		}
		else if ( is_bool($mixed) ) {
			$justFirst = $mixed;
		}
		else if ( is_string($mixed) ) {
			$class = $mixed;
		}

		// apply params
		if ( $params ) {
			$query = $this->replaceholders($query, $params);
		}

		$result = $this->result($query);
		if ( false === $result ) {
			return false;
		}

		if ( $justFirst ) {
			if ( $class ) {
				return $result->nextObject($class, array(true));
			}
			return $result->nextObject();
		}

		if ( $class ) {
			return $result->allObjects($class, array(true));
		}

		return $result->allObjects();
	}

	public function result( $query, $targetClass = '' ) {
		$resultClass = get_class($this).'Result';
		return $resultClass::make($this->query($query), $targetClass, $this);
	}

	abstract public function query( $query );
	abstract public function execute( $query );
	abstract public function error( $error = null );
	abstract public function errno( $errno = null );
	abstract public function affected_rows();
	abstract public function insert_id();


	public function fetch_fields( $query ) {
		return $this->fetch_fields_assoc($query);
	}

	public function fetch_fields_assoc( $query ) {
		$r = $this->result($query);
		if ( !is_object($r) ) {
			return false;
		}
		$a = array();
		while ( $l = $r->nextRow() ) {
			$a[$l[0]] = $l[1];
		}
		return $a;
	}

	public function fetch_fields_numeric( $query ) {
		$r = $this->result($query);
		if ( !is_object($r) ) {
			return false;
		}
		$a = array();
		while ( $l = $r->nextRow() ) {
			$a[] = $l[0];
		}
		return $a;
	}

	public function select_one( $table, $field, $conditions, $params = array() ) {
		$conditions = $this->replaceholders($conditions, $params);
		$query = 'SELECT '.$field.' FROM '.$this->escapeAndQuoteTable($table).' WHERE '.$conditions;
		$r = $this->result($query);
		if ( !$r ) {
			return false;
		}
		return $r->singleResult();
	}

	public function count_rows( $query ) {
		$r = $this->fetch($query);
		if ( !$r ) {
			return false;
		}
		return count($r);
	}

	public function fetch_by_field( $query, $field, $class = '' ) {
		$r = $this->result($query);
		if ( !$r ) {
			return false;
		}
		$a = array();
		while ( $l = $r->nextObject($class) ) {
			if ( !property_exists($l, $field) ) {
				return $this->except('Undefined index: "'.$field.'"');
			}
			$a[$l->$field] = $l;
		}
		return $a;
	}

	static protected $aliasDelim = '.'; // [table] "." [column]

	public function select( $table, $conditions, $params = array(), $justFirst = false ) {
		$conditions = $this->replaceholders($conditions, $params);
		$query = 'SELECT * FROM '.$this->escapeAndQuoteTable($table).' WHERE '.$conditions;
//var_dump($query); exit;
		return $this->fetch($query, true === $justFirst);
	}

	public function select_by_field( $table, $field, $conditions, $params = array() ) {
		$conditions = $this->replaceholders($conditions, $params);
		$query = 'SELECT * FROM '.$this->escapeAndQuoteTable($table).' WHERE '.$conditions;
		return $this->fetch_by_field($query, $field);
	}

	public function select_fields( $table, $fields, $conditions, $params = array() ) {
		return $this->select_fields_assoc($table, $fields, $conditions);
	}

	public function select_fields_assoc( $table, $fields, $conditions, $params = array() ) {
		if ( !is_string($fields) ) {
			$fields = implode(', ', array_map(array($this, 'escapeAndQuoteColumn'), (array)$fields));
		}
		$query = 'SELECT '.$fields.' FROM '.$this->escapeAndQuoteTable($table).' WHERE '.$conditions;
		return $this->fetchFieldsAssoc($query);
	}

	public function select_fields_numeric( $table, $field, $conditions, $params = array() ) {
		$conditions = $this->replaceholders($conditions, $params);
		$query = 'SELECT '.$field.' FROM '.$this->escapeAndQuoteTable($table).' WHERE '.$conditions;
		return $this->fetch_fields_numeric($query);
	}

	public function count( $table, $conditions = '', $params = array() ) {
		$conditions = $this->replaceholders($conditions, $params) ?: '1';
		$r = (int)$this->select_one($table, 'count(1)', $conditions);
		return $r;
	}

	public function replace( $table, $values ) {
		$values = array_map(array($this, 'escapeAndQuoteValue'), $values);
		$sql = 'REPLACE INTO '.$this->escapeAndQuoteTable($table).' ('.implode(',', array_keys($values)).') VALUES ('.implode(',', $values).');';
		return $this->execute($sql);
	}

	public function insert( $table, $values ) {
		$values = array_map(array($this, 'escapeAndQuoteValue'), $values);
		$sql = 'INSERT INTO '.$this->escapeAndQuoteTable($table).' ('.implode(',', array_keys($values)).') VALUES ('.implode(',', $values).');';
		return $this->execute($sql);
	}

	public function delete( $table, $conditions, $params = array() ) {
		$conditions = $this->replaceholders($conditions, $params);
		$sql = 'DELETE FROM '.$this->escapeAndQuoteTable($table).' WHERE '.$conditions.';';
//var_dump($sql); exit;
		return $this->execute($sql);
	}

	public function update( $table, $updates, $conditions, $params = array() ) {
		$updates = $this->stringifyUpdates($updates);
		$conditions = $this->replaceholders($conditions, $params);
		$sql = 'UPDATE '.$this->escapeAndQuoteTable($table).' SET '.$updates.' WHERE '.$conditions.'';
//var_dump($sql); exit;
		return $this->execute($sql);
	}

	public function aliasPrefix( $alias, $column ) {
		return $this->escapeAndQuoteTable($alias) . $this::$aliasDelim . $this->escapeAndQuoteColumn($column);
	}

	public function stringifyColumns( $columns ) {
		if ( !is_string($columns) ) {
			$columns = implode(', ', array_map(array($this, 'escapeAndQuoteColumn'), (array)$columns));
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
					$u .= ', ' . $k . ' = ' . $this->escapeAndQuoteValue($v);
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
					$column = $table ? $this->aliasPrefix($table, $column) : $this->escapeAndQuoteColumn($column);
					$sql[] = $column . ( null === $value ? ' IS NULL' : ' = ' . $this->escapeAndQuoteValue($value) );
				}
			}
			$conditions = implode(' '.$delim.' ', $sql);
		}
		return $conditions;
	}

}



abstract class db_generic_result {

	abstract static public function make( $result, $class = '' , $db = null );


	public $result; // typeof who cares
	public $class = '';


	public function __construct( $result, $class = '', $db = null ) {
		$this->result = $result;
		$this->class = $class;
		$this->db = $db;
	}


	abstract public function singleResult();


	abstract public function nextObject( $class = '', $args = array() );

	public function allObjects( $class = '', $args = array() ) {
		$class or $class = 'stdClass';

		$a = array();
		while ( $r = $this->nextObject($class, $args) ) {
			$a[] = $r;
		}
		return $a;
	}


	abstract public function nextAssocArray();

	public function nextRecord() {
		return $this->nextAssocArray();
	}

	public function allAssocArrays() {
		$a = array();
		while ( $r = $this->nextAssocArray() ) {
			$a[] = $r;
		}
		return $a;
	}


	abstract public function nextNumericArray();

	public function nextRow() {
		return $this->nextNumericArray();
	}

	public function allNumericArrays() {
		$a = array();
		while ( $r = $this->nextNumericArray() ) {
			$a[] = $r;
		}
		return $a;
	}


}


