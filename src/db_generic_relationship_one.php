<?php

class db_generic_relationship_one extends db_generic_relationship {

	protected function fetch() : mixed {
		$object = call_user_func([$this->target, 'find'], $this->source->{$this->foreign});
		$object and $this->loadEagers([$object]);
		return $object;
	}

	protected function fetchAll( array $objects ) : array {
		$name = $this->name;
		$foreignColumn = $this->foreign;

		$foreignIds = $this->getForeignIds($objects, $foreignColumn);
		$targets = call_user_func([$this->target, 'all'], ['id' => array_unique($foreignIds)]);

		foreach ( $objects as $object ) {
			$object->_set($name, $targets[$object->$foreignColumn] ?? null);
		}

		count($targets) and $this->loadEagers($targets);

		return $targets;
	}

	public function getReturnType() : string {
		return '?' . $this->target;
	}

}
