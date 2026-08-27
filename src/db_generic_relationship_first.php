<?php

class db_generic_relationship_first extends db_generic_relationship {

	protected function fetch() : mixed {
		$where = $this->getWhereOrder([$this->foreign => $this->source->id]);
		$object = call_user_func([$this->target, 'first'], $where);
		$object and $this->loadEagers([$object]);
		return $object;
	}

	protected function fetchAll( array $objects ) : array {
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
			$object->_set($name, $indexed[$object->id] ?? null);
		}

		count($targets) and $this->loadEagers($targets);

		return $targets;
	}

	public function getReturnType() : string {
		return '?' . $this->target;
	}

}
