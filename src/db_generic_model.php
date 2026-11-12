<?php

/**
 * @property int $id
 */
abstract class db_generic_model extends db_generic_keyless_model {

	/** @var false|array<class-string<self>, array<int, self>> */
	static public $_cache = [];

	/**
	 * Overrides the keyless pass-through with the identity cache.
	 *
	 * @template TObject of ?self
	 * @param TObject $object
	 * @return TObject
	 */
	final static public function _modelToFromCache( $object ) {
		if ( $object && self::$_cache !== false && isset($object->id) ) {
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

	/**
	 * @param class-string<self> $class
	 */
	final static public function _modelFromCache( string $class, int $id ) : ?self {
		if ( self::$_cache !== false ) {
			if ( isset(self::$_cache[$class][$id]) ) {
				return self::$_cache[$class][$id];
			}
		}
		return null;
	}

	/**
	 * Overrides the keyless version to key rows by id. Rows without id are still appended.
	 */
	static protected function _fromRows( array $rows, bool $cache ) : array {
		$models = [];
		foreach ( $rows as $row ) {
			$model = new static($row);
			if ( $model->id ) {
				if ( $cache ) {
					$model = static::_modelToFromCache($model);
				}
				$models[$model->id] = $model;
			}
			else {
				$models[] = $model;
			}
		}
		return $models;
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
	 * @param array<string, mixed> $data
	 * @return ?static
	 */
	static public function create( array $data ) {
		static::insert($data);
		return static::find(static::$_db->insert_id());
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


	/**
	 * @param class-string<db_generic_keyless_model> $targetClass
	 */
	public function to_first( string $targetClass, string $foreignColumn ) : db_generic_relationship_first {
		return new db_generic_relationship_first($this, $targetClass, $foreignColumn);
	}

	/**
	 * @param class-string<db_generic_keyless_model> $targetClass
	 */
	public function to_many( string $targetClass, string $foreignColumn ) : db_generic_relationship_many {
		return new db_generic_relationship_many($this, $targetClass, $foreignColumn);
	}

	public function to_aggregate( string $targetTable, string $aggregate, string $foreignColumn ) : db_generic_relationship_aggregate {
		return new db_generic_relationship_aggregate($this, $targetTable, $aggregate, $foreignColumn);
	}

	public function to_count( string $targetTable, string $foreignColumn ) : db_generic_relationship_aggregate {
		return $this->to_aggregate($targetTable, 'COUNT(1)', $foreignColumn);
	}

	/**
	 * @param class-string<db_generic_model> $targetClass
	 */
	public function to_many_through( string $targetClass, string $throughRelationship ) : db_generic_relationship_many_through {
		return new db_generic_relationship_many_through($this, $targetClass, $throughRelationship);
	}

	public function to_many_scalar( string $targetColumn, string $throughTable, string $foreignColumn ) : db_generic_relationship_many_scalar {
		return new db_generic_relationship_many_scalar($this, $targetColumn, $throughTable, $foreignColumn);
	}

}
