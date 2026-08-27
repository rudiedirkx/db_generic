<?php

abstract class db_generic_result {

	/**
	 * @return ?stdClass
	 */
	abstract public function nextObject();

	/**
	 * @return ?array<string, ?scalar>
	 */
	abstract public function nextAssocArray();

	/**
	 * @return ?list<?scalar>
	 */
	abstract public function nextNumericArray();

	/**
	 * @return list<stdClass>
	 */
	public function all() : array {
		$rows = [];
		while ( $object = $this->nextObject() ) {
			$rows[] = $object;
		}
		return $rows;
	}

	/**
	 * @return array<int|string, stdClass>
	 */
	public function by( string $field ) : array {
		$rows = [];
		while ( $object = $this->nextObject() ) {
			$rows[ $object->$field ] = $object;
		}
		return $rows;
	}

	/**
	 * @return list<array<string, ?scalar>>
	 */
	public function allAssocArrays() : array {
		$rows = [];
		while ( $row = $this->nextAssocArray() ) {
			$rows[] = $row;
		}
		return $rows;
	}

	public function first() : ?stdClass {
		return $this->nextObject();
	}

}
