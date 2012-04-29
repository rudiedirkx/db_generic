<?php

class db_exception extends Exception {
	public $query = '';
	public function __construct( $error = '', $errno = -1, $options = array() ) {
		parent::__construct($error, $errno);
		if ( isset($options['query']) ) {
			$this->query = $options['query'];
		}
	}
}

abstract class db_generic {

	static function option( $options, $name, $alternative = null ) {
		$options = (array)$options;
		return isset($options[$name]) ? $options[$name] : $alternative;
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
	public $metaCache = array();

#	public $error = '';
#	public $errno = 0;

	abstract static public function open( $args );
	abstract protected function __construct( $args );

	protected function postConnect( $args ) {
	}

	public function connected() {
		return true;
	}

	abstract public function escapeValue( $value );

	public function quoteValue( $value ) {
		return "'" . $value . "'";
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
		throw new db_exception($error, $errno, array('query' => $query));
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
		$exotics = array();

		// unravel options
		// Array -> Options or Params
		if ( is_array($options) ) {
			// Params
			if ( is_int(key($options)) ) {
				$params = $options;
			}
			// Options
			else {
				$exotics = $options;
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

		return array_merge($exotics, compact('class', 'first', 'params'));
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
			isset($l[1]) or array_push($l, $l[0]);
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
		$options = self::fetch_options($options);
		$options['by_field'] = $field;
		$result = $this->fetch($query, $options);

		if ( !$result ) {
			return false;
		}

		return $result;
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

	public function count_rows( $query, $options = null ) {
		$result = $this->fetch($query, $options);
		if ( !$result ) {
			return false;
		}

		$rows = 0;
		foreach ( $result AS $r ) {
			$rows++;
		}

		unset($r, $result);

		return $rows;
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

		$r = $this->select_one($table, 'max(' . $field . ')', $conditions);

		if ( null !== $r ) {
			return (int)$r;
		}
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
		return $this->execute($sql);
	}

	public function update( $table, $updates, $conditions, $params = array() ) {
		$updates = $this->stringifyUpdates($updates);
		$conditions = $this->replaceholders($conditions, $params);
		$sql = 'UPDATE '.$this->escapeAndQuoteTable($table).' SET '.$updates.' WHERE '.$conditions.'';
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

	public function schema($schema) {
		// format
		if ( !isset($schema['tables']) ) {
			$schema = array('tables' => $schema);
		}

		$updates = array();

		// sync tables
		foreach ( $schema['tables'] AS $tableName => $tableDefinition ) {
			// format
			if ( !isset($tableDefinition['columns']) ) {
				$tableDefinition = array('columns' => $tableDefinition);
			}

			// ensure table
			$created = $this->table($tableName, $tableDefinition);

			if ( null !== $created ) {
				// feedback
				$updates['tables'][$tableName] = $created;
			}
			else {
				// table exists
				// sync columns
				foreach ( $tableDefinition['columns'] AS $columnName => $columnDefinition ) {
					if ( is_int($columnName) ) {
						$columnName = $columnDefinition;
						$columnDefinition = array();
					}

					// ensure column
					$created = $this->column($tableName, $columnName, $columnDefinition);

					// save result for feedback
					if ( null !== $created ) {
						$updates['columns'][$tableName][$columnName] = $created;
					}
				}
			}

			// tables & columns synced
			// sync indexes
			if ( isset($tableDefinition['indexes']) ) {
				foreach ( $tableDefinition['indexes'] AS $indexName => $indexDetails ) {
					$created = $this->index($tableName, $indexName, (array)$indexDetails);

					// save result for feedback
					if ( null !== $created ) {
						$updates['indexes'][$tableName][$indexName] = $created;
					}
				}
			}
		}

		// add data
		foreach ( $schema['tables'] AS $tableName => $tableDefinition ) {
			if ( isset($updates['tables'][$tableName]) && true === $updates['tables'][$tableName] ) {
				// new table
				// add data
				if ( isset($schema['data'][$tableName]) ) {
					// all or nothing
					$this->begin();

					try {
						$inserts = 0;
						foreach ( $schema['data'][$tableName] AS $data ) {
							if ( $this->insert($tableName, $data) ) {
								$inserts++;
							}
						}

						// no exceptions => all
						$this->commit();

						// save result for feedback
						$updates['data'][$tableName] = $inserts;
					}
					catch ( db_exception $ex ) {
						// exception => nothing
						// rollback to cancel transaction
						$this->rollback();

						// save result for feedback
						$updates['data'][$tableName] = false;
					}
				}
			}
		}

		return $updates;
	}

}



abstract class db_generic_result implements Iterator {

	static public $return_object_class = 'db_generic_record';

	abstract static public function make( $db, $result, $options = array() );


	public $db; // typeof db_generic
	public $result; // unknown type
	public $options = array();
	public $class = '';
	public $collection = '';

	public $index = 0;
	public $record; // unknown type

	public $mappers = array(); // typeof Array<callback>
	public $filters = array(); // typeof Array<callback>

	public $notEmpty; // bool
	public $firstRecord; // unknown type
	public $filtered = array(); // Array<unknown type>


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
		if ( isset($this->options['by_field']) ) {
			return $this->record->{$this->options['by_field']};
		}
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


	public function notEmpty() {
		if ( null === $this->notEmpty ) {
			$this->firstRecord = $this->nextMatchingObject();
			$this->notEmpty = (bool)$this->firstRecord;
		}

		return $this->notEmpty;
	}


	public function all( $options = null ) {
		isset($options['class']) && $this->class = $options['class'];
		isset($options['collection']) && $this->collection = $options['collection'];

		$items = iterator_to_array($this);

		if ( $this->collection ) {
			$items = new $this->collection($items);
		}

		return $items;
	}

	public function fetchAll( $options = null ) {
		return $this->all($options);
	}

	public function allObjects( $options = null ) {
		return $this->all($options);
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
		if ( $this->firstRecord ) {
			$object = $this->firstRecord;
			$this->firstRecord = null;
			return $object;
		}

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



class db_generic_record implements ArrayAccess {

	public function offsetExists( $offset ) {
		return property_exists($this, $offset);
	}

	public function offsetGet( $offset ) {
		return $this->$offset;
	}

	public function offsetSet( $offset, $value ) {
		$offset = (string)$offset;
		$this->$offset = $value;
	}

	public function offsetUnset( $offset ) {
		unset($this->$offset);
	}


}



class db_generic_collection implements ArrayAccess, IteratorAggregate, Countable {

	protected $items = array();
	protected $index = 0;

	public function __construct( $items = null ) {
		if ( is_array($items) ) {
			$this->items = $items;
		}
	}

	// Array
	public function first() {
		return reset($this->items);
	}

	public function last() {
		return end($this->items);
	}

	public function shift() {
		return array_shift($this->items);
	}

	public function unshift( $item ) {
		array_unshift($this->items, $item);
		return $this;
	}

	public function push( $item ) {
		array_push($this->items, $item);
		return $this;
	}

	public function pop() {
		return array_pop($this->items);
	}

	public function slice( $offset ) {
		$args = func_get_args();
		array_unshift($args, $this->items);
		return call_user_func_array('array_slice', $args);
	}

	public function splice() {
		$args = func_get_args();
		array_unshift($args, $this->items);
		return call_user_func_array('array_splice', $args);
	}

	public function map( $callback ) {
		$items = array();
		foreach ( $this->items AS $i => $item ) {
			$items[$i] = call_user_func($callback, $item, $i, $this->items);
		}

		return $items;
	}

	public function filter( $callback = null, $negate = false ) {
		$items = array();
		foreach ( $this->items AS $i => $item ) {
			if ( !$negate == (bool)call_user_func($callback, $item, $i, $this->items) ) {
				$items[$i] = $item;
			}
		}

		return $items;
	}


	// ArrayAccess
	public function offsetExists( $offset ) {
		return isset($this->items[$offset]);
	}

	public function offsetGet( $offset ) {
		return $this->items[$offset];
	}

	public function offsetSet( $offset, $value ) {
		if ( null === $offset ) {
			$this->items[] = $value;
		}
		else if ( is_int($offset) ) {
			$this->items[$offset] = $value;
		}
		else {
			throw new InvalidArgumentException(__CLASS__ . ' offset must be an integer.');
		}
	}

	public function offsetUnset( $offset ) {
		unset($this->items[$offset]);
	}


	// IteratorAggregate
	public function getIterator() {
		return new ArrayIterator($this->items);
	}


	// Countable
	public function count() {
		return count($this->items);
	}


}


