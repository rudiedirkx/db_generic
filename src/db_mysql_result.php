<?php

class db_mysql_result extends db_generic_result {

	public function __construct(
		protected mysqli_result $result,
	) {}

	public function nextObject() {
		/** @var ?stdClass */
		$object = $this->result->fetch_object() ?: null;
		return $object;
	}

	public function nextAssocArray() {
		return $this->result->fetch_assoc();
	}

	public function nextNumericArray() {
		return $this->result->fetch_row();
	}

}
