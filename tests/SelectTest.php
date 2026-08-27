<?php

class SelectTest extends DbTestCase {

	public function testSelectWithArrayCondition() : void {
		$rows = $this->db->select('users', ['active' => 1]);
		$this->assertCount(2, $rows);
	}

	public function testSelectAll() : void {
		$rows = $this->db->select('users', '1=1');
		$this->assertCount(3, $rows);
		$this->assertInstanceOf(stdClass::class, $rows[0]);
	}

	public function testSelectFirst() : void {
		$row = $this->db->select_first('users', ['name' => 'Bob']);
		$this->assertSame(25, $row->age);
	}

	public function testSelectFirstReturnsNull() : void {
		$row = $this->db->select_first('users', ['name' => 'Nobody']);
		$this->assertNull($row);
	}

	public function testSelectAssoc() : void {
		$rows = $this->db->select_assoc('users', '1=1');
		$this->assertCount(3, $rows);
		$this->assertIsArray($rows[0]);
	}

	public function testSelectAssocFirst() : void {
		$row = $this->db->select_assoc_first('users', ['id' => 2]);
		$this->assertSame('Bob', $row['name']);
	}

	public function testSelectAssocFirstReturnsNull() : void {
		$row = $this->db->select_assoc_first('users', ['id' => 999]);
		$this->assertNull($row);
	}

	public function testSelectByField() : void {
		$rows = $this->db->select_by_field('users', 'id', '1=1');
		$this->assertArrayHasKey(1, $rows);
		$this->assertSame('Alice', $rows[1]->name);
	}

	public function testSelectOne() : void {
		$age = $this->db->select_one('users', 'age', ['id' => 3]);
		$this->assertSame(40, $age);
	}

	public function testSelectFields() : void {
		$map = $this->db->select_fields('users', ['id', 'name'], '1=1');
		$this->assertSame('Alice', $map[1]);
	}

	public function testSelectFieldsNumeric() : void {
		$names = $this->db->select_fields_numeric('users', 'name', ['active' => 1]);
		$this->assertCount(2, $names);
		$this->assertContains('Alice', $names);
		$this->assertContains('Bob', $names);
	}
}
