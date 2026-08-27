<?php

namespace rdx\db\PhpStan;

use db_generic;
use db_generic_result;

class FakeDb extends db_generic {

	public function __construct() {
	}

	public function connect() : void {
	}

	protected function result( string $query ) : db_generic_result {
		throw new \Exception('FakeDb has no results; it is never queried');
	}

	protected function escapeValue( $value ) : string {
		return '';
	}

	public function enableForeignKeys() {
	}

	public function begin() : true {
		return true;
	}

	public function commit() : true {
		return true;
	}

	public function rollback() : true {
		return true;
	}

	public function execute( $query ) : true {
		return true;
	}

	public function affected_rows() {
		return 0;
	}

	public function insert_id() {
		return 0;
	}

	public function tables() {
		return [];
	}

	public function columns( $tableName ) {
		return [];
	}

	public function column( $tableName, $columnName, $columnDefinition = null, $returnSQL = false ) {
		return null;
	}

	public function indexes( $tableName ) {
		return [];
	}

	public function index( $tableName, $indexName, $indexDefinition = null, $returnSQL = false ) {
		return null;
	}

}
