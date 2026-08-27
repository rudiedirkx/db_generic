<?php

class db_generic_relationship_many_scalar extends db_generic_relationship {

	protected string $throughTable;

	public function __construct( ?db_generic_model $source, string $targetColumn, string $throughTable, string $foreignColumn ) {
		parent::__construct($source, $targetColumn, $foreignColumn);

		$this->throughTable = $throughTable;
	}

	protected function fetch() : mixed {
		$db = $this->db();
		$where = $this->getWhereOrder([$this->foreign => $this->source->id]);
		return $db->select_fields_numeric($this->throughTable, $this->target, $where);
	}

	protected function fetchAll( array $objects ) : array {
		$name = $this->name;
		$db = $this->db();

		$ids = array_flip($this->getForeignIds($objects, 'id'));
		$where = $this->getWhereOrder([$this->foreign => array_keys($ids)]);
		$links = $db->fetch("select $this->foreign, $this->target from $this->throughTable where $where");

		foreach ( $objects as $object ) {
			$object->_set($name, []);
		}

		foreach ( $links as $link ) {
			$objects[ $ids[$link->{$this->foreign}] ]->$name[] = $link->{$this->target};
		}

		return array_column($links, $this->target);
	}

	public function getReturnType() : string {
		return 'scalar[]';
	}

}
