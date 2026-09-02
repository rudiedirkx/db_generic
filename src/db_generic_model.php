<?php

/**
 * @property int $id
 * @phpstan-import-type Conditions from db_generic
 */
#[AllowDynamicProperties]
abstract class db_generic_model {

	/** @var db_generic */
	static public $_db;

	/** @var string */
	static public $_table = '';

	/** @var false|array<string, array<int, self>> */
	static public $_cache = [];

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
	final static public function _modelToFromCache( $object ) {
		if ( $object && self::$_cache !== false && property_exists($object, 'id') ) {
			if ( $fromCache = static::_modelFromCache(get_class($object), $object->id) ) {
				$object = $fromCache;
			}
			else {
				static::_modelToCache($object);
			}
		}
		return $object;
	}

	/**
	 * @template TObject of self
	 * @param TObject $object
	 * @return TObject
	 */
	final static public function _modelToCache( $object ) {
		if ( self::$_cache !== false ) {
			self::$_cache[get_class($object)][$object->id] = $object;
		}
		return $object;
	}

	final static public function _modelFromCache( string $class, int $id ) : ?self {
		if ( self::$_cache !== false ) {
			if ( isset(self::$_cache[$class][$id]) ) {
				return self::$_cache[$class][$id];
			}
		}
		return null;
	}

	/**
	 * @param list<array<string, mixed>> $rows
	 * @return array<int, static>
	 */
	static protected function _fromRows( array $rows, bool $cache ) : array {
		$models = [];
		foreach ( $rows as $row ) {
			$model = new static($row);
			if ( $cache ) {
				$model = static::_modelToFromCache($model);
			}
			$models[$model->id] = $model;
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
	 * @param ?int $id
	 * @return ?static
	 */
	static public function find( $id ) {
		if ( !$id ) return null;
		if ( $object = static::_modelFromCache(get_called_class(), $id) ) {
			return $object; // @phpstan-ignore return.type
		}
		return static::first(array('id' => $id));
	}

	/**
	 * @param list<int> $ids
	 * @return static[]
	 */
	static public function finds( array $ids ) {
		if ( !count($ids) ) return [];
		return static::all(['id' => $ids]);
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
	 * @param array<string, mixed> $data
	 * @return ?static
	 */
	static public function create( array $data ) {
		static::insert($data);
		return static::find(static::$_db->insert_id());
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
	 * @return static
	 */
	public function refresh() {
		$row = static::$_db->select_assoc_first(static::$_table, ['id' => $this->id]);
		if ( $row ) {
			$this->fill($row);
		}
		return $this;
	}

	/**
	 * @param array<string, mixed> $data
	 * @return true
	 */
	public function update( array $data ) {
		static::presave($data);
		$this->fill($data);
		return static::$_db->update(static::$_table, $data, array('id' => $this->id));
	}

	public function delete() : true {
		return static::$_db->delete(static::$_table, array('id' => $this->id));
	}


	public function to_one( string $targetClass, string $foreignColumn ) : db_generic_relationship_one {
		return new db_generic_relationship_one($this, $targetClass, $foreignColumn);
	}

	public function to_first( string $targetClass, string $foreignColumn ) : db_generic_relationship_first {
		return new db_generic_relationship_first($this, $targetClass, $foreignColumn);
	}

	public function to_many( string $targetClass, string $foreignColumn ) : db_generic_relationship_many {
		return new db_generic_relationship_many($this, $targetClass, $foreignColumn);
	}

	public function to_aggregate( string $targetTable, string $aggregate, string $foreignColumn ) : db_generic_relationship_aggregate {
		return new db_generic_relationship_aggregate($this, $targetTable, $aggregate, $foreignColumn);
	}

	public function to_count( string $targetTable, string $foreignColumn ) : db_generic_relationship_aggregate {
		return $this->to_aggregate($targetTable, 'COUNT(1)', $foreignColumn);
	}

	public function to_many_through( string $targetClass, string $throughRelationship ) : db_generic_relationship_many_through {
		return new db_generic_relationship_many_through($this, $targetClass, $throughRelationship);
	}

	public function to_many_scalar( string $targetColumn, string $throughTable, string $foreignColumn ) : db_generic_relationship_many_scalar {
		return new db_generic_relationship_many_scalar($this, $targetColumn, $throughTable, $foreignColumn);
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
