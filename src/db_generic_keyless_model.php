<?php

/**
 * @phpstan-import-type Conditions from db_generic
 */
#[AllowDynamicProperties]
abstract class db_generic_keyless_model {

	/** @var db_generic */
	static public $_db;

	/** @var string */
	static public $_table = '';

	/** @var list<string> */
	public array $_got = [];

	/**
	 * @param array<string, mixed> $data
	 */
	final public function __construct( array $data = [] ) {
		$this->fill($data);
	}

	/**
	 * @return void
	 */
	public function clear() {
		foreach ( array_unique($this->_got) as $name ) {
			unset($this->$name);
		}
		$this->_got = [];
	}

	/**
	 * @return void
	 */
	public function init() {
	}

	/**
	 * @param array<string, mixed> $props
	 * @return void
	 */
	public function fill( array $props ) {
		$this->clear();

		foreach ( $props as $name => $value ) {
			if ( is_string($name) ) { // @phpstan-ignore function.alreadyNarrowedType
				$this->$name = $value;
			}
		}

		$this->init();
	}

	public function _set( string $name, mixed $value ) : mixed {
		$this->_got[] = $name;
		$this->$name = $value;
		return $value;
	}

	public function _gettable( string $name ) : bool {
		return is_callable(array($this, 'get_' . $name)) || is_callable(array($this, 'relate_' . $name));
	}

	public function __isset( string $name ) : bool {
		return $this->_gettable($name);
	}

	public function &__get( string $name ) : mixed {
		if ( is_callable($method = array($this, 'relate_' . $name)) ) {
			$this->_set($name, call_user_func($method)->name($name)->load());
		}
		elseif ( is_callable($method = array($this, 'get_' . $name)) ) {
			$this->_set($name, call_user_func($method));
		}
		else {
			$this->_set($name, null);
		}

		return $this->$name;
	}

	/**
	 * @template TObject of ?self
	 * @param TObject $object
	 * @return TObject
	 */
	static public function _modelToFromCache( $object ) {
		return $object;
	}

	/**
	 * @param list<array<string, mixed>> $rows
	 * @return array<int, static>
	 */
	static protected function _fromRows( array $rows, bool $cache ) : array {
		$models = [];
		foreach ( $rows as $row ) {
			$models[] = new static($row);
		}
		return $models;
	}

	/**
	 * @param list<mixed> $params
	 * @return array<int, static>
	 */
	static public function query( string $query, array $params = array() ) {
		$query = static::$_db->replaceholders($query, $params);
		return static::_fromRows(static::$_db->fetch_assoc($query), false);
	}

	/**
	 * @param Conditions $conditions
	 * @param list<mixed> $params
	 * @return array<int, static>
	 */
	static public function all( $conditions, array $params = array() ) {
		return static::_fromRows(static::$_db->select_assoc(static::$_table, $conditions, $params), true);
	}

	/**
	 * @param Conditions $conditions
	 * @param list<mixed> $params
	 * @return ?static
	 */
	static public function first( $conditions, array $params = array() ) {
		$row = static::$_db->select_assoc_first(static::$_table, $conditions, $params);
		if ( !$row ) return null;
		return static::_modelToFromCache(new static($row));
	}

	/**
	 * @param Conditions $conditions
	 * @param list<mixed> $params
	 */
	static public function count( $conditions, array $params = array() ) : int {
		return static::$_db->count(static::$_table, $conditions, $params);
	}

	/**
	 * @param string|array{string}|array{string, string} $fields
	 * @param Conditions $conditions
	 * @param list<mixed> $params
	 * @return array<int|string, scalar>
	 */
	static public function fields( $fields, $conditions, array $params = array() ) : array {
		return static::$_db->select_fields(static::$_table, $fields, $conditions, $params);
	}

	/**
	 * @param array<string, mixed> $data
	 * @return true
	 */
	static public function insert( array $data ) {
		static::presave($data);

		return static::$_db->insert(static::$_table, $data);
	}

	/**
	 * @param list<array<string, mixed>> $datas
	 */
	static public function insertAll( array $datas ) : true {
		foreach ( $datas as &$data ) {
			static::presave($data);
			unset($data);
		}

		return static::$_db->inserts(static::$_table, $datas);
	}

	/**
	 * @param Conditions $conditions
	 * @param list<mixed> $params
	 */
	static public function deleteAll( $conditions, array $params = array() ) : int {
		static::$_db->delete(static::$_table, $conditions, $params);
		return static::$_db->affected_rows();
	}

	/**
	 * @param array<int|string, mixed> $updates
	 * @param Conditions $conditions
	 * @param list<mixed> $params
	 */
	static public function updateAll( array $updates, $conditions, array $params = array() ) : int {
		static::$_db->update(static::$_table, $updates, $conditions, $params);
		return static::$_db->affected_rows();
	}

	/**
	 * @param array<string, mixed> $data
	 * @param-out array<string, mixed> $data
	 */
	static public function presave( array &$data ) : void {
		static::presaveId($data);
		static::presaveTrim($data);
	}

	/**
	 * @param array<string, mixed> $data
	 * @param-out array<string, mixed> $data
	 */
	static public function presaveId( array &$data ) : void {
		unset($data['id']);
	}

	/**
	 * @param array<string, mixed> $data
	 * @param-out array<string, mixed> $data
	 */
	static public function presaveTrim( array &$data ) : void {
		$data = array_map(function($datum) {
			return is_null($datum) || is_bool($datum) ? $datum : (is_scalar($datum) ? rtrim($datum) : array_filter($datum));
		}, $data);
	}

	/**
	 * @param array<string, mixed> $data
	 * @param-out array<string, mixed> $data
	 * @param list<string> $cols
	 */
	static public function presaveNull( array &$data, array $cols ) : void {
		foreach ( $cols as $name ) {
			if ( isset($data[$name]) && $data[$name] === '' ) {
				$data[$name] = null;
			}
		}
	}


	/**
	 * @param class-string<db_generic_model> $targetClass
	 */
	public function to_one( string $targetClass, string $foreignColumn ) : db_generic_relationship_one {
		return new db_generic_relationship_one($this, $targetClass, $foreignColumn);
	}

	/**
	 * @param list<static> $objects
	 * @return list<static>
	 */
	static public function eager( string $name, array $objects ) : array {
		if ( count($objects) == 0 ) {
			return [];
		}

		$relationship = call_user_func([new static(), "relate_$name"]);
		return $relationship->name($name)->loadAll($objects);
	}

	/**
	 * @param list<static> $objects
	 * @param list<string> $names
	 */
	static public function eagers( array $objects, array $names ) : void {
		if ( count($objects) == 0 ) return;

		$return = [];
		foreach ( $names as $name ) {
			$parts = explode('.', $name);
			$sources = count($parts) == 1 ? $objects : $return[ implode('.', array_slice($parts, 0, -1)) ];
			if ( count($sources) == 0 ) {
				$return[$name] = [];
				continue;
			}

			$class = get_class(reset($sources));
			$return[$name] = call_user_func([$class, 'eager'], end($parts), $sources);
		}
	}

}
