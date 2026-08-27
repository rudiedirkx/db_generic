<?php

class db_pdo_result extends db_generic_result {

	public function __construct(
		protected PDOStatement $result,
	) {}

	public function nextObject() {
		/** @var ?stdClass */
		$object = $this->result->fetch(PDO::FETCH_OBJ) ?: null;
		return $object;
	}

	public function nextAssocArray() {
		return $this->result->fetch(PDO::FETCH_ASSOC) ?: null;
	}

	public function nextNumericArray() {
		return $this->result->fetch(PDO::FETCH_NUM) ?: null;
	}

}
