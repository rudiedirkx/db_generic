<?php

class db_generic_relationship_many_through extends db_generic_relationship {

	protected string $throughRelationship;

	public function __construct( ?db_generic_model $source, string $targetClass, string $throughRelationship ) {
		parent::__construct($source, $targetClass, null);

		$this->throughRelationship = $throughRelationship;
	}

	protected function fetch() : mixed {
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

	protected function fetchAll( array $objects ) : array {
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
			$object->_set($name, $grouped[$object->id] ?? []);
		}

		count($targets) and $this->loadEagers($targets);

		return $targets;
	}

	public function getReturnType() : string {
		return $this->target . '[]';
	}

}
