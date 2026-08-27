<?php

class db_generic_relationship_aggregate extends db_generic_relationship {

	protected string $aggregate;
	protected string|Closure $caster = 'intval';

	public function __construct( ?db_generic_model $source, string $targetTable, string $aggregate, string $foreignColumn ) {
		parent::__construct($source, $targetTable, $foreignColumn);

		$this->aggregate = $aggregate;
	}

	public function caster( callable $caster ) : static {
		$this->caster = $caster;
		return $this;
	}

	protected function castAggregate( mixed $value ) : mixed {
		return /*$value === null ? null :*/ call_user_func($this->caster, $value);
	}

	protected function fetch() : mixed {
		$db = $this->db();
		$where = $this->getWhereOrder([$this->foreign => $this->source->id]);
		return $this->castAggregate($db->select_one($this->target, $this->aggregate, $where));
	}

	protected function fetchAll( array $objects ) : array {
		$name = $this->name;
		$db = $this->db();

		$ids = array_flip($this->getForeignIds($objects, 'id'));
		$foreignColumn = $this->foreign;
		$qForeignColumn = $db->escapeAndQuoteColumn($foreignColumn);
		$where = $this->getWhereOrder([$foreignColumn => array_keys($ids)]);
		$where .= ' GROUP BY ' . $qForeignColumn;

		$targets = $db->select_fields($this->target, $qForeignColumn . ', ' . $this->aggregate, $where);

		foreach ( $ids as $id => $index ) {
			$objects[$index]->_set($name, $this->castAggregate($targets[$id] ?? null));
		}

		return $targets;
	}

	public function getReturnType() : string {
		return 'scalar';
	}

}
