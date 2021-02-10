<?php

class db_exception extends Exception {
	public $query = '';
	public function __construct( $error = '', $errno = -1, $options = array() ) {
		parent::__construct($error, $errno);
		if ( isset($options['query']) ) {
			$this->query = $options['query'];
		}
	}
	public function getQuery() {
		return $this->query;
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

	static function fn_regexp_replace($pattern, $replacement, $subject) {
		$pattern = '/' . $pattern . '/i';
		return preg_replace($pattern, $replacement, $subject);
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

	static function fn_from_unixtime( $utc ) {
		return date('Y-m-d H:i:s', $utc);
	}

	protected $params = array();
	protected $db;

	public $returnAffectedRows = false;
	public $queryLogger;
	public $queries = array();
	public $metaCache = array();

	/** @return db_generic */
	static public function open( $params ) {
		return new static($params);
	}

	abstract protected function __construct( $params );

	abstract public function connect();

	protected function postConnect( $params ) {}

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

	protected function logQuery( $query, $startTime, $error = null ) {
		$query = trim(preg_replace('#\s+#', ' ', $query));
		$ms = (microtime(1) - $startTime) * 1000;

		if ( $this->queryLogger ) {
			call_user_func($this->queryLogger, $query, $ms, $error);
		}
		elseif ( is_array($this->queries) ) {
			$error = $error ? " -- ERROR: $error" : '';
			$this->queries[] = '[' . number_format($ms, 1) . 'ms] ' . $query . $error;
		}
	}

	public function except( $query, $error, $errno = -1 ) {
		throw new db_exception($error, $errno, array('query' => $query));
	}

	abstract public function enableForeignKeys();

	static public $replaceholder = '?';

	public function replaceholders( $conditions, $params ) {
		$this->connect();

		$conditions = $this->stringifyConditions($conditions);

		if ( array() === $params || null === $params || '' === $params ) {
			return $conditions;
		}

		$ph = self::$replaceholder;
		$offset = 0;
		foreach ( (array)$params AS $param ) {
			$pos = strpos($conditions, $ph, $offset);
			if ( false === $pos ) {
				throw new InvalidArgumentException("Too many params in replaceholders()");
			}
			$param = is_array($param) ? implode(', ', array_map(array($this, 'escapeAndQuoteValue'), $param)) : $this->escapeAndQuoteValue((string)$param);
			$conditions = substr_replace($conditions, $param, $pos, strlen($ph));
			$offset = $pos + strlen($param);
		}

		if (strpos($conditions, $ph, $offset) !== false) {
			throw new InvalidArgumentException("Too few params in replaceholders()");
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
		elseif ( is_bool($options) ) {
			$first = $options;
		}
		// String -> Class
		elseif ( is_string($options) ) {
			$class = $options;
		}

		return array_merge($exotics, compact('class', 'first', 'params'));
	}

	/** @return db_generic_result */
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


	public function bad_query_template( $query ) {
		$template = trim($query);
		$template = preg_replace("#\s+#", ' ', $template);
		$template = preg_replace("#'[0-9-a-z_-]+'#i", '%', $template);
		$template = preg_replace("#%(, %)+#", '%', $template);
		return $template;
	}

	public function bad_queries() {
		$templates = array();
		foreach ( $this->queries as $query ) {
			$template = $this->bad_query_template($query);
			$templates[$template][] = $query;
		}

		foreach ( $templates as $template => $queries ) {
			if ( count($queries) < 2 ) {
				unset($templates[$template]);
			}
		}

		return $templates;
	}


	public function fetch_fields( $query, $params = array() ) {
		return $this->fetch_fields_assoc($query, $params);
	}

	public function fetch_fields_assoc( $query, $params = array() ) {
		$query = $this->replaceholders($query, $params);
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

	public function fetch_fields_numeric( $query, $params = array() ) {
		$query = $this->replaceholders($query, $params);
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

	public function fetch_one( $query, $field, $params = array() ) {
		$query = $this->replaceholders($query, $params);
		$record = $this->fetch($query)->first();
		if ( $record && isset($record->$field) ) {
			return $record->$field;
		}
	}

	public function select_one( $table, $field, $conditions, $params = array() ) {
		$conditions = $this->replaceholders($conditions, $params);
		$query = 'SELECT ' . $field . ' FROM ' . $this->escapeAndQuoteTable($table) . ' WHERE ' . $conditions;
		return $this->fetch_one($query, $field);
	}

	public function count_rows( $query, $params = array() ) {
		$query = $this->replaceholders($query, $params);
		return $this->fetch_one('SELECT COUNT(1) AS num FROM (' . $query . ') x', 'num');
	}

	static protected $aliasDelim = '.'; // [table] "." [column]

	public function select( $table, $conditions, $params = array(), $options = null ) {
		$conditions = $this->replaceholders($conditions, $params);
		$query = 'SELECT * FROM '.$this->escapeAndQuoteTable($table).' WHERE '.$conditions;
		return $this->fetch($query, $options);
	}

	public function select_by_field( $table, $field, $conditions, $params = array(), $options = null ) {
		$conditions = $this->replaceholders($conditions, $params);
		$query = 'SELECT * FROM '.$this->escapeAndQuoteTable($table).' WHERE '.$conditions;
		return $this->fetch_by_field($query, $field, $options);
	}

	public function select_fields( $table, $fields, $conditions, $params = array() ) {
		return $this->select_fields_assoc($table, $fields, $conditions, $params);
	}

	public function select_fields_assoc( $table, $fields, $conditions, $params = array() ) {
		if ( !is_string($fields) ) {
			$fields = implode(', ', array_map(array($this, 'escapeAndQuoteColumn'), (array)$fields));
		}
		$conditions = $this->replaceholders($conditions, $params);
		$query = 'SELECT ' . $fields . ' FROM ' . $this->escapeAndQuoteTable($table) . ' WHERE ' . $conditions;
		return $this->fetch_fields_assoc($query);
	}

	public function select_fields_numeric( $table, $field, $conditions, $params = array() ) {
		$conditions = $this->replaceholders($conditions, $params);
		$query = 'SELECT ' . $this->quoteColumn($field) . ' FROM ' . $this->escapeAndQuoteTable($table) . ' WHERE ' . $conditions;
		return $this->fetch_fields_numeric($query);
	}

	public function count( $table, $conditions = '', $params = array() ) {
		$conditions = $this->replaceholders($conditions, $params);
		$conditions or $conditions = '1=1';
		$r = (int)$this->select_one($table, 'count(1)', $conditions);
		return $r;
	}

	public function max( $table, $field, $conditions = '', $params = array() ) {
		$conditions = $this->replaceholders($conditions, $params);
		$conditions or $conditions = '1=1';

		$r = $this->select_one($table, 'max(' . $field . ')', $conditions);

		if ( null !== $r ) {
			return (int)$r;
		}
	}

	public function min( $table, $field, $conditions = '', $params = array() ) {
		$conditions = $this->replaceholders($conditions, $params);
		$conditions or $conditions = '1=1';
		$r = (int)$this->select_one($table, 'min(' . $field . ')', $conditions);
		return $r;
	}

	public function replace( $table, $values ) {
		$values = array_map(array($this, 'escapeAndQuoteValue'), $values);
		$columns = array_map(array($this, 'escapeAndQuoteColumn'), array_keys($values));

		$sql = 'REPLACE INTO ' . $this->escapeAndQuoteTable($table) . ' (' . implode(', ', $columns) . ') VALUES (' . implode(', ', $values) . ')';
		return $this->execute($sql);
	}

	public function insert( $table, $values ) {
		return $this->inserts($table, array($values));
	}

	public function inserts( $table, $valueses, $defaults = array() ) {
		if ( !$valueses ) return;

		$columns = array_map(array($this, 'escapeAndQuoteColumn'), array_keys($valueses[0]));

		foreach ( $valueses as $i => $values ) {
			$values = array_map(array($this, 'escapeAndQuoteValue'), $values);
			$valueses[$i] = '(' . implode(', ', $values) . ')';
		}

		$sql = 'INSERT INTO ' . $this->escapeAndQuoteTable($table) . ' (' . implode(', ', $columns) . ') VALUES ' . implode(', ', $valueses);
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
					$u .= ', ' . $this->escapeAndQuoteColumn($k) . ' = ' . $this->escapeAndQuoteValue($v);
				}
			}
			$updates = substr($u, 1);
		}
		return $updates;
	}

	public function stringifyConditions( $conditions, $delim = 'AND', $table = null ) {
		$this->connect();

		if ( !is_string($conditions) ) {
			$sql = array();
			foreach ( (array)$conditions AS $column => $value ) {
				if ( is_int($column) ) {
					$sql[] = $value;
				}
				else {
					$column = $table ? $this->aliasPrefix($table, $column) : $this->escapeAndQuoteColumn($column);
					if ( is_array($value) ) {
						$values = array_map(array($this, 'escapeAndQuoteValue'), $value);
						$sql[] = $column . ' IN (' . implode(', ', $values) . ')';
					}
					else {
						$sql[] = $column . ( null === $value ? ' IS NULL' : ' = ' . $this->escapeAndQuoteValue($value) );
					}
				}
			}
			$conditions = implode(' '.$delim.' ', $sql);
		}
		return $conditions;
	}

	public function schema( $schema, $returnSQL = false ) {
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
			$created = $this->table($tableName, $tableDefinition, $returnSQL);

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
					$created = $this->column($tableName, $columnName, $columnDefinition, $returnSQL);

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

	abstract public function tables();

	public function table( $tableName, $tableDefinition = null, $returnSQL = false ) {
		// if we care only about SQL, don't fetch tables
		$tables = $table = false;
		if ( !$returnSQL ) {
			$tables = $this->tables();
			isset($tables[$tableName]) && $table = $tables[$tableName];
		}

		// create table
		if ( $tableDefinition ) {
			// table exists -> fail
			if ( $table && !$returnSQL ) {
				return null;
			}

			// table definition
			if ( !isset($tableDefinition['columns']) ) {
				$tableDefinition = array('columns' => $tableDefinition);
			}

			// create table sql
			$sql = 'CREATE TABLE ' . $this->escapeAndQuoteTable($tableName) . ' (' . "\n";
			$first = true;
			foreach ( $tableDefinition['columns'] AS $columnName => $details ) {
				// the very simple columns: array( 'a', 'b', 'c' )
				if ( is_int($columnName) ) {
					$columnName = $details;
					$details = array();
				}

				$columnSQL = $this->column($tableName, $columnName, $details, true);

				$comma = $first ? ' ' : ',';
				$sql .= '  ' . $comma . $columnSQL . "\n";

				$first = false;
			}
			$sql .= ');';

			// return SQL
			if ( $returnSQL ) {
				return $sql;
			}

			// execute
			return $this->execute($sql);
		}

		// table exists -> success
		if ( $table ) {
			return $table;
		}
	}

	abstract public function columns( $tableName );

	abstract public function column( $tableName, $columnName, $columnDefinition = null, $returnSQL = false );

	abstract public function indexes( $tableName );

	abstract public function index( $tableName, $indexName, $indexDefinition = null, $returnSQL = false );

	protected function needsSchemaUpdate(array $schema) {
		if ( !isset($schema['version']) ) {
			return false;
		}

		return !$this->hasSchemaVersion($schema['version']) || count($this->getNewUpdates($schema));
	}

	public function ensureSchema(array $schema, callable $callback = null) {
		$this->enableForeignKeys();

		$changes = [];
		if ( $this->needsSchemaUpdate($schema) ) {
			try {
				$changes = $this->schema($schema);
				$this->setSchemaVersion($schema['version']);

				foreach ($this->getNewUpdates($schema) as $index => $callback) {
					$changes['updates'][$index] = $callback($this);
					$this->setSchemaVersion($index);
				}

				$callback and $callback($changes);
			}
			catch (db_exception $ex) {
				echo '<pre>';
				echo "ERROR: " . $ex->getMessage() . "\n\n";
				echo "QUERY: " . $ex->query . "\n\n";
				exit((string) $ex);
			}
		}

		return $changes;
	}

	protected function setSchemaVersion($version) {
		try {
			$this->insert('_version', array('_version' => $version));
		}
		catch (db_exception $ex) {
			// throw $ex;
		}
	}

	protected function hasSchemaVersion($version) {
		try {
			$versions = $this->select_fields('_version', '_version', '1=1');
			return in_array($version, $versions);
		}
		catch (db_exception $ex) {
			$this->table('_version', ['_version' => ['unique' => true]]);
		}

		return false;
	}

	protected function getNewUpdates(array $schema) {
		if (!isset($schema['updates'])) {
			return [];
		}

		$ran = $this->select_fields('_version', '_version', '1=1');

		$new = [];
		foreach ($schema['updates'] as $index => $callback) {
			$index = "update--$index";
			if (!in_array($index, $ran)) {
				$new[$index] = $callback;
			}
		}

		return $new;
	}

	public function newQuery( array $query = [] ) {
		return new db_generic_query($this, $query);
	}

}

class db_generic_query_conditions implements Countable {
	public $delim;
	public $conditions = [];

	public function __construct( $delim ) {
		$this->delim = $delim;
	}

	public function where( $sql, array $params = [] ) {
		$this->conditions[] = $sql instanceof self ? $sql : [$sql, $params];
		return $this;
	}

	public function count() {
		return count($this->conditions);
	}
}

class db_generic_query {
	protected $db;

	protected $fields = [];
	protected $tables = [];
	protected $join = [];
	protected $conditions;
	protected $order = [];

	public function __construct( db_generic $db, array $query = [] ) {
		$this->db = $db;

		$this->conditions = $this->and();
	}

	public function and() {
		return new db_generic_query_conditions('AND');
	}

	public function or() {
		return new db_generic_query_conditions('OR');
	}

	public function field( $field, $alias = null ) {
		$this->fields[] = [$field, $alias];
		return $this;
	}

	public function table( $table, $alias = null ) {
		$this->tables[] = [$table, $alias];
		return $this;
	}

	public function condition( $condition, array $params = [] ) {
		$this->conditions->where($condition, $params);

		return $this;
	}

	public function buildSelect() {
		return implode("\n", array_filter([
			$this->buildFields(),
			$this->buildFrom(),
			$this->buildJoin(),
			$this->buildWhere(),
			$this->buildOrder(),
		]));
	}

	public function buildConditions( db_generic_query_conditions $conditions ) {
		$sqls = [];

		foreach ( $conditions->conditions as $condition ) {
			if ( $condition instanceof db_generic_query_conditions ) {
				$sqls[] = '(' . $this->buildConditions($condition) . ')';
			}
			else {
				list($sql, $params) = $condition;
				$sqls[] = '(' . $this->db->replaceholders($sql, $params) . ')';
			}
		}

		return implode(" $conditions->delim ", $sqls);
	}

	public function buildFields() {
		if ( !$this->fields ) {
			return 'SELECT *';
		}

		$fields = [];

		foreach ( $this->fields AS list($field, $alias) ) {
			$alias = $alias ? " AS $alias" : '';
			$fields[] = $this->buildField($field) . $alias;
		}

		return 'SELECT ' . implode(', ', $fields);
	}

	public function buildField( $field ) {
		if ( is_string($field) ) {
			return $field;
		}

		if ( $field instanceof db_generic_query_conditions ) {
			return $this->buildConditions($field);
		}
	}

	public function buildFrom() {
		$tables = [];

		foreach ( $this->tables as $table ) {
			$alias = '';
			if ( is_array($table) ) {
				$alias = ' AS ' . $table[1];
				$table = $table[0];
			}
			$tables[] = 'FROM ' . $this->db->escapeAndQuoteTable($table) . $alias;
		}

		return implode(', ', $tables);
	}

	public function buildJoin() {
		$joins = [];

		foreach ( $this->join as $info ) {
			list($type, $table, $conditions) = $info;

			$tableAlias = '';
			if ( is_array($table) ) {
				$tableAlias = ' ' . $table[1];
				$table = $table[0];
			}

			$on = '';
			if ( $conditions ) {
				$on = ' ON ' . implode(' AND ', array_map(function($condition) {
					return is_array($condition) ? $this->db->replaceholders(...$condition) : $condition;
				}, $conditions));
			}

			$joins[] = trim(strtoupper("$type join")) . ' ' . $this->db->escapeAndQuoteTable($table) . $tableAlias . $on;
		}

		return implode("\n", $joins);
	}

	public function buildWhere() {
		if ( count($this->conditions) == 0 ) {
			return null;
		}

		return 'WHERE ' . $this->buildConditions($this->conditions);
	}

	public function buildOrder() {
		if ( !$this->order ) {
			return null;
		}

		$order = [];

		foreach ( $this->order AS $field ) {
			$direction = 'ASC';
			$tableAlias = '';
			if ( is_array($field) ) {
				if ( isset($field[2]) ) {
					$direction = strtoupper($field[2]);
				}

				$tableAlias = $field[0] . '.';
				$field = $field[1];
			}

			$order[] = $tableAlias . $this->db->escapeAndQuoteColumn($field) . ' ' . $direction;
		}

		return 'ORDER BY ' . implode(', ', $order);
	}

	public function __toString() {
		return $this->buildSelect();
	}
}



abstract class db_generic_result implements Iterator {

	static public $return_object_class = 'db_generic_record';

	// abstract static public function make( $db, $result, $options = array() );


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
		if ( isset($this->options['by_field']) && property_exists($this->record, $this->options['by_field']) ) {
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

	public function fields( $valueField, $keyField = null ) {
		$fields = array();

		foreach ( $this AS $object ) {
			$value = $object->$valueField;
			if ( null !== $keyField ) {
				$fields[ $object->$keyField ] = $object->$valueField;
			}
			else {
				$fields[] = $object->$valueField;
			}
		}

		return $fields;
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
			return $this->init($object);
		}

		if ( !$this->mappers && !$this->filters ) {
			return $this->init($this->nextObject($this->options['args']));
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

			return $this->init($object);
		}
	}

	public function init( $object ) {
		if ( $object instanceof db_generic_model ) {
			$object->init();
		}

		return $object;
	}

	public function first() {
		return $this->nextMatchingObject();
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

	public function __construct( $data = array() ) {
		foreach ( $data as $key => $value ) {
			$this->$key = $value;
		}
	}

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


	public function _gettable( $name ) {
		return is_callable(array($this, 'get_' . $name));
	}

	public function __isset( $name ) {
		return $this->_gettable($name);
	}

	public function &__get( $name ) {
		$this->$name = NULL;

		if ( is_callable($method = array($this, 'get_' . $name)) ) {
			$this->$name = call_user_func($method);
		}

		return $this->$name;
	}


}



abstract class db_generic_model extends db_generic_record {

	/** @var db_generic */
	static public $_db;

	static public $_table = '';

	static public $_cache = [];

	static function _modelToFromCache( $object = null ) {
		if ( $object && self::$_cache !== false && property_exists($object, 'id') ) {
			if ( $fromCache = self::_modelFromCache(get_class($object), $object->id) ) {
// echo "discard " . get_class($object) . " $object->id\n";
				$object = $fromCache;
			}
			else {
				self::_modelToCache($object);
			}
		}
		return $object;
	}

	static function _modelToCache( $object ) {
		if ( self::$_cache !== false ) {
			self::$_cache[get_class($object)][$object->id] = $object;
		}
		return $object;
	}

	static function _modelFromCache( $class, $id ) {
		if ( self::$_cache !== false ) {
			if ( isset(self::$_cache[$class][$id]) ) {
				return self::$_cache[$class][$id];
			}
		}
	}

	/** @return static[] */
	static function query( $query, array $params = array() ) {
		return static::$_db->fetch_by_field($query, 'id', array('params' => $params, 'class' => get_called_class()))->all();
	}

	/** @return static[] */
	static function all( $conditions, array $params = array(), array $options = [] ) {
		$options += ['id' => 'id', 'class' => get_called_class()];
		return array_map([__CLASS__, '_modelToFromCache'], static::$_db->select_by_field(static::$_table, $options['id'], $conditions, $params, $options)->all());
	}

	/** @return static */
	static function first( $conditions, array $params = array() ) {
		return self::_modelToFromCache(static::$_db->select(static::$_table, $conditions, $params, array('class' => get_called_class()))->first());
	}

	/** @return static */
	static function find( $id ) {
		if ( $id ) {
			if ( $object = self::_modelFromCache(get_called_class(), $id) ) {
				return $object;
			}
			return static::first(array('id' => $id));
		}
	}

	/** @return int */
	static function count( $conditions, array $params = array() ) {
		return static::$_db->count(static::$_table, $conditions, $params);
	}

	/** @return int|bool */
	static function insert( array $data ) {
		static::presave($data);

		if ( static::$_db->insert(static::$_table, $data) ) {
			$id = static::$_db->insert_id();
			return $id ?: true;
		}

		return false;
	}

	/** @return bool */
	static function insertAll( array $datas ) {
		foreach ( $datas as &$data ) {
			static::presave($data);
			unset($data);
		}

		return static::$_db->inserts(static::$_table, $datas);
	}

	/** @return int|bool */
	static function deleteAll( $conditions, array $params = array() ) {
		$result = static::$_db->delete(static::$_table, $conditions, $params);
		if ( $result === false ) {
			return false;
		}

		if ( static::$_db->returnAffectedRows ) {
			return $result;
		}

		return static::$_db->affected_rows();
	}

	/** @return int|bool */
	static function updateAll( array $updates, $conditions, array $params = array() ) {
		$result = static::$_db->update(static::$_table, $updates, $conditions, $params);
		if ( $result === false ) {
			return false;
		}

		if ( static::$_db->returnAffectedRows ) {
			return $result;
		}

		return static::$_db->affected_rows();
	}

	/** @return void */
	static function presave( array &$data ) {
	}

	/** @return void */
	static function presaveTrim( array &$data ) {
		$data = array_map(function($datum) {
			return is_null($datum) || is_bool($datum) ? $datum : (is_scalar($datum) ? trim($datum) : array_filter($datum));
		}, $data);
	}

	/** @return void */
	function init() {
	}

	/** @return void */
	function fill( array $props ) {
		foreach ( $props as $name => $value ) {
			$this->$name = $value;
		}

		$this->init();
	}

	/** @return static */
	function refresh() {
		$data = static::$_db->select(static::$_table, ['id' => $this->id]);
		$this->fill($data->nextAssocArray());
		return $this;
	}

	/** @return bool */
	function update( $data ) {
		if ( is_array($data) ) {
			static::presave($data);
			$this->fill($data);
		}
		return static::$_db->update(static::$_table, $data, array('id' => $this->id));
	}

	/** @return bool */
	function delete() {
		return static::$_db->delete(static::$_table, array('id' => $this->id));
	}


	public function to_one( $targetClass, $foreignColumn ) {
		return new db_generic_relationship_one($this, $targetClass, $foreignColumn);
	}

	public function to_first( $targetClass, $foreignColumn ) {
		return new db_generic_relationship_first($this, $targetClass, $foreignColumn);
	}

	public function to_many( $targetClass, $foreignColumn ) {
		return new db_generic_relationship_many($this, $targetClass, $foreignColumn);
	}

	public function to_count( $targetTable, $foreignColumn ) {
		return new db_generic_relationship_count($this, $targetTable, $foreignColumn);
	}

	public function to_many_through( $targetClass, $throughRelationship ) {
		return new db_generic_relationship_many_through($this, $targetClass, $throughRelationship);
	}

	public function to_many_scalar( $targetColumn, $throughTable, $foreignColumn ) {
		return new db_generic_relationship_many_scalar($this, $targetColumn, $throughTable, $foreignColumn);
	}

	public function _gettable( $name ) {
		return parent::_gettable($name) || is_callable(array($this, 'relate_' . $name));
	}

	public function &__get( $name ) {
		if ( is_callable($method = array($this, 'relate_' . $name)) ) {
			$this->$name = call_user_func($method)->name($name)->load();
			return $this->$name;
		}

		return parent::__get($name);
	}

	static public function eager( $name, array $objects ) {
		if ( count($objects) == 0 ) {
			return [];
		}

		$relationship = call_user_func([new static(), "relate_$name"]);
		return $relationship->name($name)->loadAll($objects);
	}


}

abstract class db_generic_relationship {
	protected $name;
	protected $eager = [];
	protected $source;
	protected $target;
	protected $foreign;
	protected $where;
	protected $order;
	protected $key;

	public function __construct( db_generic_model $source = null, $targetClass, $foreignColumn ) {
		$this->source = $source;
		$this->target = $targetClass;
		$this->foreign = $foreignColumn;
	}

	public function load() {
		return $this->fetch();
	}

	public function loadAll( array $objects ) {
		return count($objects) ? $this->fetchAll($objects) : [];
	}

	protected function loadEagers( array $targets ) {
		$target = reset($targets);
		foreach ( $this->eager as $name ) {
			$target::eager($name, $targets);
		}
	}

	abstract protected function fetch();

	abstract protected function fetchAll( array $objects );

	public function name( $name ) {
		$this->name = $name;
		return $this;
	}

	public function eager( array $names ) {
		$this->eager = $names;
		return $this;
	}

	public function where( $where ) {
		$this->where = $where;
		return $this;
	}

	public function order( $order ) {
		$this->order = $order;
		return $this;
	}

	public function key( $key ) {
		$this->key = $key;
		return $this;
	}

	protected function db() {
		$source = $this->source;
		return $source::$_db;
	}

	protected function getWhereOrder( array $conditions ) {
		$db = $this->db();
		$conditions = $db->stringifyConditions($conditions);
		$this->where and $conditions .= ' AND ' . $this->where;
		$order = $this->order ? " ORDER BY {$this->order}" : '';
		return $conditions . $order;
	}

	protected function getForeignIds( array $objects, $column ) {
		return array_filter(array_map(function($object) use ($column) {
			return $object->$column;
		}, $objects));
	}
}

class db_generic_relationship_one extends db_generic_relationship {
	protected function fetch() {
		$object = call_user_func([$this->target, 'find'], $this->source->{$this->foreign});
		$object and $this->loadEagers([$object]);
		return $object;
	}

	protected function fetchAll( array $objects ) {
		$name = $this->name;
		$foreignColumn = $this->foreign;

		$foreignIds = $this->getForeignIds($objects, $foreignColumn);
		$targets = call_user_func([$this->target, 'all'], ['id' => array_unique($foreignIds)]);

		foreach ( $objects as $object ) {
			$object->$name = $targets[$object->$foreignColumn] ?? null;
		}

		count($targets) and $this->loadEagers($targets);

		return $targets;
	}
}

class db_generic_relationship_first extends db_generic_relationship {
	protected function fetch() {
		$where = $this->getWhereOrder([$this->foreign => $this->source->id]);
		$object = call_user_func([$this->target, 'first'], $where);
		$object and $this->loadEagers([$object]);
		return $object;
	}

	protected function fetchAll( array $objects ) {
		$name = $this->name;
		$foreignColumn = $this->foreign;

		$foreignIds = $this->getForeignIds($objects, 'id');
		$where = $this->getWhereOrder([$foreignColumn => array_unique($foreignIds)]);
		$targets = call_user_func([$this->target, 'all'], $where);

		$indexed = [];
		foreach ( $targets as $target ) {
			$indexed[$target->$foreignColumn] = $target;
		}

		foreach ( $objects as $object ) {
			$object->$name = $indexed[$object->id] ?? null;
		}

		count($targets) and $this->loadEagers($targets);

		return $targets;
	}
}

class db_generic_relationship_many extends db_generic_relationship {
	protected function fetch() {
		$where = $this->getWhereOrder([$this->foreign => $this->source->id]);
		$options = $this->key ? ['id' => $this->key] : [];
		$targets = call_user_func([$this->target, 'all'], $where, [], $options);
		count($targets) and $this->loadEagers($targets);
		return $targets;
	}

	protected function fetchAll( array $objects ) {
		$name = $this->name;
		$ids = array_flip($this->getForeignIds($objects, 'id'));
		$foreignColumn = $this->foreign;
		$where = $this->getWhereOrder([$foreignColumn => array_keys($ids)]);

		$targets = call_user_func([$this->target, 'all'], $where);

		foreach ( $objects as $object ) {
			$object->$name = [];
		}

		foreach ( $targets as $target ) {
			$object = $objects[ $ids[ $target->$foreignColumn ] ];
			$key = $this->key ?: 'id';
			$object->$name[ $target->$key ] = $target;
		}

		count($targets) and $this->loadEagers($targets);

		return $targets;
	}
}

class db_generic_relationship_count extends db_generic_relationship {
	protected function fetch() {
		$db = $this->db();
		$where = $this->getWhereOrder([$this->foreign => $this->source->id]);
		return $db->count($this->target, $where);
	}

	protected function fetchAll( array $objects ) {
		$name = $this->name;
		$db = $this->db();

		$ids = array_flip($this->getForeignIds($objects, 'id'));
		$foreignColumn = $this->foreign;
		$qForeignColumn = $db->quoteColumn($foreignColumn);
		$where = $this->getWhereOrder([$foreignColumn => array_keys($ids)]);
		$where .= ' GROUP BY ' . $qForeignColumn;

		$targets = $db->select_fields($this->target, $qForeignColumn . ', COUNT(1)', $where);

		foreach ( $ids as $id => $index ) {
			$objects[$index]->$name = (int) ($targets[$id] ?? 0);
		}

		return $targets;
	}
}

class db_generic_relationship_many_through extends db_generic_relationship {
	protected $throughRelationship;

	public function __construct( db_generic_model $source = null, $targetClass, $throughRelationship ) {
		parent::__construct($source, $targetClass, null);

		$this->throughRelationship = $throughRelationship;
	}

	protected function fetch() {
		$db = $this->db();
		$targetIds = $this->source->{$this->throughRelationship};

		if ( count($targetIds) == 0 ) {
			return [];
		}

		$where = $this->getWhereOrder(['id' => $targetIds]);
		$targets = call_user_func([$this->target, 'all'], $where);

		count($targets) and $this->loadEagers($targets);
		return $targets;
	}

	protected function fetchAll( array $objects ) {
		$name = $this->name;
		$db = $this->db();

		$class = get_class(reset($objects));
		$targetIds = call_user_func([$class, 'eager'], $this->throughRelationship, $objects);

		$where = $this->getWhereOrder(['id' => array_unique($targetIds)]);
		$targets = call_user_func([$this->target, 'all'], $where);

		$grouped = [];
		foreach ( $objects as $object ) {
			foreach ( $object->{$this->throughRelationship} as $id ) {
				if ( $target = $targets[$id] ?? null ) {
					$grouped[$object->id][$target->id] = $target;
				}
			}
		}

		foreach ( $objects as $object ) {
			$object->$name = $grouped[$object->id] ?? [];
		}

		count($targets) and $this->loadEagers($targets);

		return $targets;
	}
}

class db_generic_relationship_many_scalar extends db_generic_relationship {
	protected $throughTable;

	public function __construct( db_generic_model $source = null, $targetColumn, $throughTable, $foreignColumn ) {
		parent::__construct($source, $targetColumn, $foreignColumn);

		$this->throughTable = $throughTable;
	}

	protected function fetch() {
		$db = $this->db();
		$where = $this->getWhereOrder([$this->foreign => $this->source->id]);
		return $db->select_fields_numeric($this->throughTable, $this->target, $where);
	}

	protected function fetchAll( array $objects ) {
		$name = $this->name;
		$db = $this->db();

		$ids = array_flip($this->getForeignIds($objects, 'id'));
		$where = $this->getWhereOrder([$this->foreign => array_keys($ids)]);
		$links = $db->fetch("select $this->foreign, $this->target from $this->throughTable where $where")->all();

		foreach ( $objects as $object ) {
			$object->$name = [];
		}

		foreach ( $links as $link ) {
			$objects[ $ids[$link->{$this->foreign}] ]->$name[] = $link->{$this->target};
		}

		return array_column($links, $this->target);
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
		elseif ( is_int($offset) ) {
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


