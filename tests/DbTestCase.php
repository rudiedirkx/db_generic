<?php

use PHPUnit\Framework\TestCase;

abstract class DbTestCase extends TestCase {

	protected db_sqlite $db;

	protected function setUp() : void {
		$this->db = new db_sqlite(':memory:');

		db_generic_model::$_db = $this->db;
		db_generic_model::$_cache = [];

		$this->db->execute("
			CREATE TABLE users (
				id INTEGER PRIMARY KEY AUTOINCREMENT,
				name TEXT,
				email TEXT,
				age INTEGER,
				active INTEGER
			)
		");

		$this->db->inserts('users', [
			['name' => 'Alice', 'email' => 'alice@example.com', 'age' => 30, 'active' => 1],
			['name' => 'Bob', 'email' => 'bob@example.com', 'age' => 25, 'active' => 1],
			['name' => 'Carol', 'email' => 'carol@example.com', 'age' => 40, 'active' => 0],
		]);
	}
}
