<?php

class FetchTest extends DbTestCase {

	public function testFetchReturnsListOfObjects() : void {
		$rows = $this->db->fetch('SELECT * FROM users ORDER BY id');
		$this->assertCount(3, $rows);
		$this->assertInstanceOf(stdClass::class, $rows[0]);
		$this->assertSame('Alice', $rows[0]->name);
		$this->assertSame(30, $rows[0]->age);
	}

	public function testFetchEmptyReturnsEmptyArray() : void {
		$rows = $this->db->fetch("SELECT * FROM users WHERE name = 'Nobody'");
		$this->assertSame([], $rows);
	}

	public function testFetchFirstReturnsObject() : void {
		$row = $this->db->fetch_first('SELECT * FROM users ORDER BY id');
		$this->assertInstanceOf(stdClass::class, $row);
		$this->assertSame('Alice', $row->name);
	}

	public function testFetchFirstReturnsNull() : void {
		$row = $this->db->fetch_first("SELECT * FROM users WHERE name = 'Nobody'");
		$this->assertNull($row);
	}

	public function testFetchAssocReturnsArrays() : void {
		$rows = $this->db->fetch_assoc('SELECT * FROM users ORDER BY id');
		$this->assertCount(3, $rows);
		$this->assertIsArray($rows[0]);
		$this->assertSame('Alice', $rows[0]['name']);
	}

	public function testFetchByField() : void {
		$rows = $this->db->fetch_by_field('SELECT * FROM users', 'name');
		$this->assertArrayHasKey('Alice', $rows);
		$this->assertArrayHasKey('Bob', $rows);
		$this->assertSame(25, $rows['Bob']->age);
	}

	public function testFetchOne() : void {
		$name = $this->db->fetch_one('SELECT name FROM users WHERE id = 1', 'name');
		$this->assertSame('Alice', $name);
	}

	public function testFetchOneReturnsNull() : void {
		$name = $this->db->fetch_one('SELECT name FROM users WHERE id = 999', 'name');
		$this->assertNull($name);
	}

	public function testFetchFieldsAssoc() : void {
		$map = $this->db->fetch_fields('SELECT id, name FROM users ORDER BY id');
		$this->assertSame([1 => 'Alice', 2 => 'Bob', 3 => 'Carol'], $map);
	}

	public function testFetchFieldsNumeric() : void {
		$names = $this->db->fetch_fields_numeric('SELECT name FROM users ORDER BY id');
		$this->assertSame(['Alice', 'Bob', 'Carol'], $names);
	}
}
