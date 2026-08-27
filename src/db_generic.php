<?php

/**
 * @phpstan-type Conditions string|array<int|string, mixed>
 */
abstract class db_generic {

	static public function fn_regexp( string $pattern, string $subject ) : int|false {
		$pattern = '/' . $pattern . '/i';
		return preg_match($pattern, $subject);
	}

	static public function fn_regexp_replace( string $pattern, string $replacement, string $subject ) : ?string {
		$pattern = '/' . $pattern . '/i';
		return preg_replace($pattern, $replacement, $subject);
	}

	static public function fn_if( mixed $f_bool, mixed $f_yes = 1, mixed $f_no = 0 ) : mixed {
		return $f_bool ? $f_yes : $f_no;
	}

	static public function fn_rand() : int|float {
		return rand() / getrandmax();
	}

	static public function fn_concat() : string {
		return implode(func_get_args());
	}

	static public function fn_from_unixtime( int $utc ) : string {
		return date('Y-m-d H:i:s', $utc);
	}

	static public string $replaceholder = '?';

	static protected string $aliasDelim = '.'; // [table] "." [column]

	/** @var (Closure(string, float, ?string): void) */
	public ?Closure $queryLogger = null;

	/** @var false|list<string> */
	public false|array $queries = array();

	/** @var ?array<int|string, ?scalar> */
	protected $versions = null;

	/** @var array<string, mixed> */
	public array $metaCache = array();

	abstract public function connect() : void;

	protected function postConnect() : void {
	}

	/**
	 * @param ?scalar $value
	 */
	abstract protected function escapeValue( $value ) : string;

	/**
	 * @param ?scalar $value
	 */
	protected function quoteValue( $value ) : string {
		return "'" . $value . "'";
	}

	/**
	 * @param ?scalar $value
	 */
	public function escapeAndQuote( $value ) : string {
		return $this->escapeAndQuoteValue($value);
	}

	/**
	 * @param ?scalar $value
	 */
	public function escapeAndQuoteValue( $value ) : string {
		if ( null === $value ) {
			return 'NULL';
		}

		if ( is_bool($value) ) {
			$value = (int)$value;
		}

		return $this->quoteValue($this->escapeValue($value));
	}

	protected function escapeTable( string $table ) : string {
		return $table;
	}

	protected function quoteTable( string $table ) : string {
		return $table;
	}

	public function escapeAndQuoteTable( string $table ) : string {
		return is_int(stripos($table, ' as ')) ? $table : $this->quoteTable($this->escapeTable($table));
	}

	protected function escapeColumn( string $column ) : string {
		return $column;
	}

	protected function quoteColumn( string $column ) : string {
		return $column;
	}

	public function escapeAndQuoteColumn( string $column ) : string {
		return $this->quoteColumn($this->escapeColumn($column));
	}

	protected function logQuery( string $query, float $startTime, ?string $error = null ) : void {
		$query = trim(preg_replace('#\s+#', ' ', $query));
		$ms = (microtime(true) - $startTime) * 1000;

		if ( $this->queryLogger ) {
			call_user_func($this->queryLogger, $query, $ms, $error);
		}
		elseif ( is_array($this->queries) ) {
			$error = $error ? " -- ERROR: $error" : '';
			$this->queries[] = '[' . number_format($ms, 1) . 'ms] ' . $query . $error;
		}
	}

	public function except( string $query, string $error, int $errno = -1, ?Throwable $previous = null ) : never {
		throw new db_exception($error, $errno, array('query' => $query), $previous);
	}

	/**
	 * @return void
	 */
	abstract public function enableForeignKeys();

	/**
	 * @param Conditions $conditions
	 * @param mixed $params
	 */
	public function replaceholders( $conditions, $params ) : string {
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

	abstract public function begin() : true;

	abstract public function commit() : true;

	abstract public function rollback() : true;

	/**
	 * @template T
	 * @param callable(static): T $callable
	 * @return T
	 */
	public function transaction( callable $callable ) : mixed {
		$this->begin();

		try {
			$return = call_user_func($callable, $this);
			$this->commit();

			return $return;
		}
		catch ( Exception $ex ) {
			$this->rollback();

			throw $ex;
		}
	}

	/**
	 * @param list<mixed> $params
	 * @return list<stdClass>
	 */
	public function fetch( string $query, array $params = [] ) : array {
		$query = $this->replaceholders($query, $params);
		return $this->result($query)->all();
	}

	/**
	 * @param list<mixed> $params
	 */
	public function fetch_first( string $query, array $params = [] ) : ?stdClass {
		$query = $this->replaceholders($query, $params);
		return $this->result($query)->first();
	}

	/**
	 * @param list<mixed> $params
	 * @return list<array<string, ?scalar>>
	 */
	public function fetch_assoc( string $query, array $params = [] ) : array {
		$query = $this->replaceholders($query, $params);
		return $this->result($query)->allAssocArrays();
	}

	abstract protected function result( string $query ) : db_generic_result;

	/**
	 * @param string $query
	 */
	abstract public function execute( $query ) : true;

	/** @return int */
	abstract public function affected_rows();

	/** @return int */
	abstract public function insert_id();


	protected function bad_query_template( string $query ) : ?string {
		$template = trim($query);
		$template = preg_replace("#\s+#", ' ', $template);
		$template = preg_replace("#'[0-9-a-z_-]+'#i", '%', $template);
		$template = preg_replace("#%(, %)+#", '%', $template);
		return $template;
	}

	/**
	 * @return array<string, list<string>>
	 */
	public function bad_queries() : array {
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


	/**
	 * @param list<mixed> $params
	 * @return array<int|string, ?scalar>
	 */
	public function fetch_fields( string $query, array $params = array() ) : array {
		return $this->fetch_fields_assoc($query, $params);
	}

	/**
	 * @param list<mixed> $params
	 * @return array<int|string, ?scalar>
	 */
	public function fetch_fields_assoc( string $query, array $params = array() ) : array {
		$query = $this->replaceholders($query, $params);
		$r = $this->result($query);

		$a = array();
		while ( $l = $r->nextNumericArray() ) {
			if (!isset($l[1])) array_push($l, $l[0]);
			$a[$l[0]] = $l[1];
		}
		return $a;
	}

	/**
	 * @param list<mixed> $params
	 * @return list<?scalar>
	 */
	public function fetch_fields_numeric( string $query, array $params = array() ) : array {
		$query = $this->replaceholders($query, $params);
		$r = $this->result($query);

		$a = array();
		while ( $l = $r->nextNumericArray() ) {
			$a[] = $l[0];
		}
		return $a;
	}

	/**
	 * @param list<mixed> $params
	 * @return array<int|string, stdClass>
	 */
	public function fetch_by_field( string $query, string $field, array $params = array() ) : array {
		$query = $this->replaceholders($query, $params);
		return $this->result($query)->by($field);
	}

	/**
	 * @param list<mixed> $params
	 * @return ?scalar
	 */
	public function fetch_one( string $query, string $field, array $params = array() ) {
		$query = $this->replaceholders($query, $params);
		$row = $this->result($query)->nextAssocArray();
		return $row ? ($row[$field] ?? null) : null;
	}

	/**
	 * @param Conditions $conditions
	 * @param list<mixed> $params
	 * @return ?scalar
	 */
	public function select_one( string $table, string $field, $conditions, array $params = array() ) {
		$conditions = $this->replaceholders($conditions, $params);
		$query = 'SELECT ' . $field . ' FROM ' . $this->escapeAndQuoteTable($table) . ' WHERE ' . $conditions;
		return $this->fetch_one($query, $field);
	}

	/**
	 * @param list<mixed> $params
	 */
	public function count_rows( string $query, array $params = array() ) : int {
		$query = $this->replaceholders($query, $params);
		return (int) $this->fetch_one('SELECT COUNT(1) AS num FROM (' . $query . ') x', 'num');
	}

	/**
	 * @param string $table
	 * @param Conditions $conditions
	 * @param list<mixed> $params
	 * @return list<stdClass>
	 */
	public function select( string $table, $conditions, array $params = array() ) : array {
		$conditions = $this->replaceholders($conditions, $params);
		$query = 'SELECT * FROM '.$this->escapeAndQuoteTable($table).' WHERE '.$conditions;
		return $this->fetch($query);
	}

	/**
	 * @param string $table
	 * @param Conditions $conditions
	 * @param list<mixed> $params
	 */
	public function select_first( string $table, $conditions, array $params = array() ) : ?stdClass {
		$conditions = $this->replaceholders($conditions, $params);
		$query = 'SELECT * FROM '.$this->escapeAndQuoteTable($table).' WHERE '.$conditions;
		return $this->fetch_first($query);
	}

	/**
	 * @param string $table
	 * @param Conditions $conditions
	 * @param list<mixed> $params
	 * @return list<array<string, ?scalar>>
	 */
	public function select_assoc( string $table, $conditions, array $params = array() ) : array {
		$conditions = $this->replaceholders($conditions, $params);
		$query = 'SELECT * FROM '.$this->escapeAndQuoteTable($table).' WHERE '.$conditions;
		return $this->fetch_assoc($query);
	}

	/**
	 * @param string $table
	 * @param Conditions $conditions
	 * @param list<mixed> $params
	 * @return ?array<string, ?scalar>
	 */
	public function select_assoc_first( string $table, $conditions, array $params = array() ) : ?array {
		$conditions = $this->replaceholders($conditions, $params);
		$query = 'SELECT * FROM '.$this->escapeAndQuoteTable($table).' WHERE '.$conditions;
		return $this->result($query)->nextAssocArray();
	}

	/**
	 * @param Conditions $conditions
	 * @param list<mixed> $params
	 * @return array<int|string, stdClass>
	 */
	public function select_by_field( string $table, string $field, $conditions, array $params = array() ) : array {
		$conditions = $this->replaceholders($conditions, $params);
		$query = 'SELECT * FROM '.$this->escapeAndQuoteTable($table).' WHERE '.$conditions;
		return $this->fetch_by_field($query, $field);
	}

	/**
	 * @param string|list<string> $fields
	 * @param Conditions $conditions
	 * @param list<mixed> $params
	 * @return array<int|string, ?scalar>
	 */
	public function select_fields( string $table, string|array $fields, $conditions, array $params = array() ) : array {
		return $this->select_fields_assoc($table, $fields, $conditions, $params);
	}

	/**
	 * @param string|list<string> $fields
	 * @param Conditions $conditions
	 * @param list<mixed> $params
	 * @return array<int|string, ?scalar>
	 */
	public function select_fields_assoc( string $table, string|array $fields, $conditions, array $params = array() ) : array {
		if ( !is_string($fields) ) {
			$fields = implode(', ', array_map(array($this, 'escapeAndQuoteColumn'), (array)$fields));
		}
		$conditions = $this->replaceholders($conditions, $params);
		$query = 'SELECT ' . $fields . ' FROM ' . $this->escapeAndQuoteTable($table) . ' WHERE ' . $conditions;
		return $this->fetch_fields_assoc($query);
	}

	/**
	 * @param Conditions $conditions
	 * @param list<mixed> $params
	 * @return list<?scalar>
	 */
	public function select_fields_numeric( string $table, string $field, $conditions, array $params = array() ) : array {
		$conditions = $this->replaceholders($conditions, $params);
		$query = 'SELECT ' . $this->quoteColumn($field) . ' FROM ' . $this->escapeAndQuoteTable($table) . ' WHERE ' . $conditions;
		return $this->fetch_fields_numeric($query);
	}

	/**
	 * @param string $table
	 * @param Conditions $conditions
	 * @param list<mixed> $params
	 */
	public function count( string $table, $conditions = '', array $params = array() ) : int {
		$conditions = $this->replaceholders($conditions, $params);
		if (!$conditions) $conditions = '1=1';
		$r = (int)$this->select_one($table, 'count(1)', $conditions);
		return $r;
	}

	/**
	 * @param string $table
	 * @param string $field
	 * @param Conditions $conditions
	 * @param list<mixed> $params
	 */
	public function max( string $table, string $field, $conditions = '', array $params = array() ) : ?int {
		$conditions = $this->replaceholders($conditions, $params);
		if (!$conditions) $conditions = '1=1';

		$r = $this->select_one($table, 'max(' . $field . ')', $conditions);
		if ( null === $r ) return null;
		return (int) $r;
	}

	/**
	 * @param string $table
	 * @param string $field
	 * @param Conditions $conditions
	 * @param list<mixed> $params
	 */
	public function min( string $table, string $field, $conditions = '', array $params = array() ) : ?int {
		$conditions = $this->replaceholders($conditions, $params);
		if (!$conditions) $conditions = '1=1';

		$r = $this->select_one($table, 'min(' . $field . ')', $conditions);
		if ( null === $r ) return null;
		return (int) $r;
	}

	/**
	 * @param array<string, ?scalar> $values
	 */
	public function replace( string $table, array $values ) : true {
		$values = array_map(array($this, 'escapeAndQuoteValue'), $values);
		$columns = array_map(array($this, 'escapeAndQuoteColumn'), array_keys($values));

		$sql = 'REPLACE INTO ' . $this->escapeAndQuoteTable($table) . ' (' . implode(', ', $columns) . ') VALUES (' . implode(', ', $values) . ')';
		return $this->execute($sql);
	}

	/**
	 * @param array<string, ?scalar> $values
	 */
	public function insert( string $table, array $values ) : true {
		return $this->inserts($table, array($values));
	}

	/**
	 * @param string $table
	 * @param list<array<string, ?scalar>> $valueses
	 * @param array<string, ?scalar> $defaults
	 */
	public function inserts( string $table, array $valueses, array $defaults = array() ) : true {
		if ( !$valueses ) return true;

		$columns = array_map(array($this, 'escapeAndQuoteColumn'), array_keys($valueses[0]));

		foreach ( $valueses as $i => $values ) {
			$values = array_map(array($this, 'escapeAndQuoteValue'), $values);
			$valueses[$i] = '(' . implode(', ', $values) . ')';
		}

		$sql = 'INSERT INTO ' . $this->escapeAndQuoteTable($table) . ' (' . implode(', ', $columns) . ') VALUES ' . implode(', ', $valueses);
		return $this->execute($sql);
	}

	/**
	 * @param string $table
	 * @param Conditions $conditions
	 * @param list<mixed> $params
	 */
	public function delete( string $table, $conditions, array $params = array() ) : true {
		$conditions = $this->replaceholders($conditions, $params);
		$sql = 'DELETE FROM '.$this->escapeAndQuoteTable($table).' WHERE '.$conditions.';';
		return $this->execute($sql);
	}

	/**
	 * @param string $table
	 * @param Conditions $updates
	 * @param Conditions $conditions
	 * @param list<mixed> $params
	 */
	public function update( string $table, $updates, $conditions, array $params = array() ) : true {
		$updates = $this->stringifyUpdates($updates);
		$conditions = $this->replaceholders($conditions, $params);
		$sql = 'UPDATE '.$this->escapeAndQuoteTable($table).' SET '.$updates.' WHERE '.$conditions.'';
		return $this->execute($sql);
	}

	protected function aliasPrefix( string $alias, string $column ) : string {
		return $this->escapeAndQuoteTable($alias) . self::$aliasDelim . $this->escapeAndQuoteColumn($column);
	}

	/**
	 * @param Conditions $updates
	 */
	protected function stringifyUpdates( $updates ) : string {
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

	/**
	 * @param Conditions $conditions
	 */
	public function stringifyConditions( $conditions, string $delim = 'AND', ?string $table = null ) : string {
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

	/**
	 * @param array<string, mixed> $schema
	 * @return array<string, mixed>
	 */
	public function schema( array $schema, bool $returnSQL = false ) : array {
		// format
		if ( !isset($schema['tables']) ) {
			$schema = array('tables' => $schema);
		}

		$updates = array();
		$afterCreateTables = [];

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
				$afterCreateTables[$tableName] = $tableDefinition;
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

		foreach ( $afterCreateTables as $tableName => $tableDefinition ) {
			$this->afterCreateTable($tableName, $tableDefinition);
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
							$this->insert($tableName, $data);
							$inserts++;
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

	/**
	 * @return array<string, array<string, mixed>>
	 */
	abstract public function tables();

	public function table( string $tableName, mixed $tableDefinition = null, bool $returnSQL = false ) : mixed {
		// if we care only about SQL, don't fetch tables
		$tables = $table = false;
		if ( !$returnSQL ) {
			$tables = $this->tables();
			if ( isset($tables[$tableName]) ) {
				$table = $tables[$tableName];
			}
		}

		// create table
		if ( $tableDefinition ) {
			// table exists -> fail
			if ( $table && !$returnSQL ) { // @phpstan-ignore booleanNot.alwaysTrue
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

		return null;
	}

	public function afterCreateTable( string $tableName, mixed $tableDefinition ) : bool {
		return true;
	}

	/**
	 * @param string $tableName
	 * @return array<string, array<string, mixed>>
	 */
	abstract public function columns( $tableName );

	/**
	 * @param string $tableName
	 * @param string $columnName
	 * @param mixed $columnDefinition
	 * @param bool $returnSQL
	 * @return mixed
	 */
	abstract public function column( $tableName, $columnName, $columnDefinition = null, $returnSQL = false );

	/**
	 * @param string $tableName
	 * @return array<string, array<string, mixed>>
	 */
	abstract public function indexes( $tableName );

	/**
	 * @param string $tableName
	 * @param string $indexName
	 * @param mixed $indexDefinition
	 * @param bool $returnSQL
	 * @return mixed
	 */
	abstract public function index( $tableName, $indexName, $indexDefinition = null, $returnSQL = false );

	/**
	 * @return array<int|string, ?scalar>
	 */
	protected function getSchemaVersions() : array {
		return $this->versions ??= $this->select_fields('_version', '_version', '1=1');
	}

	/**
	 * @param array<string, mixed> $schema
	 */
	protected function needsSchemaUpdate( array $schema ) : bool {
		if ( !isset($schema['version']) ) {
			return false;
		}

		return !$this->hasSchemaVersion($schema['version']) || count($this->getNewUpdates($schema));
	}

	/**
	 * @param array<string, mixed> $schema
	 * @return array<string, mixed>
	 */
	public function ensureSchema( array $schema, bool $exitOnError = true ) : array {
		$this->enableForeignKeys();

		$changes = [];
		if ($this->needsSchemaUpdate($schema)) {
			try {
				$changes = $this->schema($schema);
				if (!$this->hasSchemaVersion($schema['version'])) {
					$this->setSchemaVersion($schema['version']);
				}

				foreach ($this->getNewUpdates($schema) as $index => $callback) {
					$changes['updates'][$index] = $callback($this);
					$this->setSchemaVersion($index);
				}

				$this->versions = null;
			}
			catch (db_exception $ex) {
				if (!$exitOnError) {
					throw $ex;
				}

				echo '<pre>';
				echo "ERROR: " . $ex->getMessage() . "\n\n";
				echo "QUERY: " . $ex->query . "\n\n";
				exit((string) $ex);
			}
		}

		return $changes;
	}

	protected function setSchemaVersion( string $version ) : void {
		try {
			$this->insert('_version', array('_version' => $version));
		}
		catch (db_exception $ex) {
			// throw $ex;
		}
	}

	protected function hasSchemaVersion( string $version ) : bool {
		try {
			$versions = $this->getSchemaVersions();
			return in_array($version, $versions);
		}
		catch (db_exception $ex) {
			$this->table('_version', ['_version' => ['unique' => true]]);
		}

		return false;
	}

	/**
	 * @param array<string, mixed> $schema
	 * @return array<string, \Closure>
	 */
	protected function getNewUpdates( array $schema ) : array {
		if (!isset($schema['updates'])) {
			return [];
		}

		$ran = $this->getSchemaVersions();

		$new = [];
		foreach ($schema['updates'] as $index => $callback) {
			$index = "update--$index";
			if (!in_array($index, $ran)) {
				$new[$index] = $callback;
			}
		}

		return $new;
	}

	/**
	 * @param array<string, mixed> $query
	 */
	public function newQuery( array $query = [] ) : db_generic_query {
		return new db_generic_query($this, $query);
	}

}
