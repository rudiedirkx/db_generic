<?php

class db_exception extends Exception {
	public $query = '';
	public function __construct( $error = '', $errno = -1, $previous = null, $options = array() ) {
		parent::__construct($error, $errno, $previous);
		if ( isset($options['query']) ) {
			$this->query = $options['query'];
		}
	}
}

abstract class db_generic {

	static function option( $options, $name, $alternative = null ) {
		$options = (array)$options;
		return array_key_exists($name, $options) ? $options[$name] : $alternative;
	}

	static function fn_regexp($pattern, $subject) {
		$pattern = '/' . $pattern . '/i';
		return preg_match($pattern, $subject);
	}

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
	abstract public function __construct( $args );
	abstract public function connected();

	abstract public function escapeValue( $value );

	public function quoteValue( $value ) {
		return "'".$value."'";
	}

	public function escapeAndQuote( $value ) {
		return $this->escapeAndQuoteValue($value);
	}

	public function escapeAndQuoteValue( $value ) {
		if ( null === $value ) {
			return 'NULL';
		}

		if ( is_bool($value) ) {
			$value = (int)$value;
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

		$ph = self::$replaceholder;
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

	static public function fetch_options( $options ) {
		// default options
		$class = false;
		$first = false;
		$params = array();

		// unravel options
		// Array -> Options or Params
		if ( is_array($options) ) {
			// Params
			if ( is_int(key($options)) ) {
				$params = $options;
			}
			// Options
			else {
				isset($options['class']) && $class = $options['class'];
				isset($options['first']) && $first = $options['first'];
				isset($options['params']) && $params = (array)$options['params'];
			}
		}
		// Bool -> first
		else if ( is_bool($options) ) {
			$first = $options;
		}
		// String -> Class
		else if ( is_string($options) ) {
			$class = $options;
		}

		return compact('class', 'first', 'params');
	}

	public function fetch( $query, $options = null ) {
		// unravel options
		$options = self::fetch_options($options);

		// add query
		$options['query'] = $query;

		// apply params
		if ( $options['params'] ) {
			$query = $this->replaceholders($query, $options['params']);
		}

		$result = $this->result($query, $options);

		// no results
		if ( false === $result ) {
			return false;
		}

		// one result or null
		if ( $options['first'] ) {
			return $result->nextMatchingObject();
		}

		// iterator
		return $result;
	}

	public function result( $query, $options = array() ) {
		$resultClass = get_class($this).'_result';
		return call_user_func(array($resultClass, 'make'), $this, $this->query($query), $options);
	}

	abstract public function query( $query );
	abstract public function execute( $query );
	abstract public function error();
	abstract public function errno();
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
		while ( $l = $r->nextNumericArray() ) {
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
		while ( $l = $r->nextNumericArray() ) {
			$a[] = $l[0];
		}
		return $a;
	}

	public function fetch_by_field( $query, $field, $options = null ) {
		$result = $this->fetch($query, $options);

		if ( !$result ) {
			return false;
		}

		$a = array();
		foreach ( $result AS $record ) {
			if ( !property_exists($record, $field) ) {
				return $this->except('Undefined index: "'.$field.'"');
			}
			$a[$record->$field] = $record;
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

	static protected $aliasDelim = '.'; // [table] "." [column]

	public function select( $table, $conditions, $params = array(), $options = null ) {
		$conditions = $this->replaceholders($conditions, $params);
		$query = 'SELECT * FROM '.$this->escapeAndQuoteTable($table).' WHERE '.$conditions;
		return $this->fetch($query, $options);
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
		$conditions = $this->replaceholders($conditions, $params);
		$query = 'SELECT '.$fields.' FROM '.$this->escapeAndQuoteTable($table).' WHERE '.$conditions;
		return $this->fetch_fields_assoc($query);
	}

	public function select_fields_numeric( $table, $field, $conditions, $params = array() ) {
		$conditions = $this->replaceholders($conditions, $params);
		$query = 'SELECT '.$field.' FROM '.$this->escapeAndQuoteTable($table).' WHERE '.$conditions;
		return $this->fetch_fields_numeric($query);
	}

	public function count( $table, $conditions = '', $params = array() ) {
		$conditions = $this->replaceholders($conditions, $params);
		$conditions or $conditions = '1';
		$r = (int)$this->select_one($table, 'count(1)', $conditions);
		return $r;
	}

	public function max( $table, $field, $conditions = '', $params = array() ) {
		$conditions = $this->replaceholders($conditions, $params);
		$conditions or $conditions = '1';
		$r = (int)$this->select_one($table, 'max(' . $field . ')', $conditions);
		return $r;
	}

	public function min( $table, $field, $conditions = '', $params = array() ) {
		$conditions = $this->replaceholders($conditions, $params);
		$conditions or $conditions = '1';
		$r = (int)$this->select_one($table, 'min(' . $field . ')', $conditions);
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
		return $this->escapeAndQuoteTable($alias) . self::$aliasDelim . $this->escapeAndQuoteColumn($column);
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



abstract class db_generic_result implements Iterator {

	static public $return_object_class = 'stdClass';

	abstract static public function make( $db, $result, $options = array() );


	public $db; // typeof db_generic
	public $result; // unknown type
	public $options = array();
	public $class = '';

	public $index = 0;
	public $record; // unknown type

	public $mappers = array(); // typeof Array<callback>
	public $filters = array(); // typeof Array<callback>

	public $filtered = array(); // unknown type


	public function __construct( $db, $result, $options = array() ) {
		$this->db = $db;
		$this->result = $result;
		$this->options = $options + array('class' => '', 'args' => array());

		$this->class = $this->options['class'] ? $this->options['class'] : self::$return_object_class;
	}


	// Abstract methods
	abstract public function singleValue();

	abstract public function nextObject();

	abstract public function nextAssocArray();

	abstract public function nextNumericArray();


	// Iterator methods
	public function current() {
		return $this->record;
	}

	public function key() {
		return $this->index;
	}

	public function next() {
		$this->index++;
	}

	public function rewind() {
		$this->index = 0;
	}

	public function valid() {
		return (bool)($this->record = $this->nextMatchingObject());
	}

	public function all() {
		return iterator_to_array($this->result);
	}


	// Toys!
	public function map($callback) {
		$this->mappers[] = $callback;

		return $this;
	}

	public function filter($callback) {
		$this->filters[] = $callback;

		return $this;
	}


	// Helper methods
	public function nextMatchingObject() {
		if ( !$this->mappers && !$this->filters ) {
			return $this->nextObject($this->options['args']);
		}

		while ( $object = $this->nextObject($this->options['args']) ) {
			// filters
			if ( $this->filters ) {
				foreach ( $this->filters AS $callback ) {
					// bow out at the first sign of false
					if ( !call_user_func_array($callback, array(&$object, 1)) ) {
						$this->filtered[] = $object;
						unset($object);
						continue 2;
					}
				}
			}

			// mappers
			if ( $this->mappers ) {
				foreach ( $this->mappers AS $callback ) {
					// overwrite object
					$object = call_user_func($callback, $object, 1);
				}
			}

			return $object;
		}
	}


	public function allObjects() {
		return iterator_to_array($this);
	}


	public function singleResult() {
		return $this->singleValue($this->options['args']);
	}


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


