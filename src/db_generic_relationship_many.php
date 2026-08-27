<?php

class db_generic_relationship_many extends db_generic_relationship {

	protected function fetch() : mixed {
		$where = $this->getWhereOrder([$this->foreign => $this->source->id]);
		$options = $this->key ? ['id' => $this->key] : [];
		$targets = call_user_func([$this->target, 'all'], $where, [], $options);
		count($targets) and $this->loadEagers($targets);
		return $targets;
	}

	protected function fetchAll( array $objects ) : array {
		$name = $this->name;
		$ids = array_flip($this->getForeignIds($objects, 'id'));
		$foreignColumn = $this->foreign;
		$where = $this->getWhereOrder([$foreignColumn => array_keys($ids)]);

		$targets = call_user_func([$this->target, 'all'], $where);

		foreach ( $objects as $object ) {
			$object->_set($name, []);
		}

		foreach ( $targets as $target ) {
			$object = $objects[ $ids[ $target->$foreignColumn ] ];
			$key = $this->key ?: 'id';
			$object->$name[ $target->$key ] = $target;
		}

		count($targets) and $this->loadEagers($targets);

		return $targets;
	}

	public function getReturnType() : string {
		return $this->target . '[]';
	}

}
