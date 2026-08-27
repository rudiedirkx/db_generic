<?php

class ConditionsTest extends DbTestCase {

	public function testArrayEquality() : void {
		$rows = $this->db->select('users', ['name' => 'Alice']);
		$this->assertCount(1, $rows);
		$this->assertSame('Alice', $rows[0]->name);
	}

	public function testPlaceholders() : void {
		$rows = $this->db->select('users', 'age > ?', [26]);
		$this->assertCount(2, $rows);
	}

	public function testInCondition() : void {
		$rows = $this->db->select('users', ['id' => [1, 3]]);
		$this->assertCount(2, $rows);
	}

	public function testNullCondition() : void {
		$this->db->insert('users', ['name' => 'Nobody', 'email' => null, 'age' => 0, 'active' => 0]);
		$rows = $this->db->select('users', ['email' => null]);
		$this->assertCount(1, $rows);
		$this->assertSame('Nobody', $rows[0]->name);
	}

	public function testMultipleConditions() : void {
		$rows = $this->db->select('users', ['active' => 1, 'age' => 30]);
		$this->assertCount(1, $rows);
		$this->assertSame('Alice', $rows[0]->name);
	}

	public function testTooFewParamsThrows() : void {
		$this->expectException(InvalidArgumentException::class);
		$this->db->replaceholders('a = ? AND b = ?', [1]);
	}

	public function testTooManyParamsThrows() : void {
		$this->expectException(InvalidArgumentException::class);
		$this->db->replaceholders('a = ?', [1, 2]);
	}

	public function testMultiplePlaceholders() : void {
		$rows = $this->db->select('users', 'active = ? AND name <> ?', [1, 'Bob']);
		$this->assertCount(1, $rows);
		$this->assertSame('Alice', $rows[0]->name);
	}

	public function testNamedParamsAreNotSupported() : void {
		$this->expectException(InvalidArgumentException::class);
		$this->db->select('users', 'name = :name', ['name' => 'Alice']);
	}
}
