<?php

class AggregateTest extends DbTestCase {

	public function testCount() : void {
		$this->assertSame(3, $this->db->count('users'));
	}

	public function testCountWithCondition() : void {
		$this->assertSame(2, $this->db->count('users', ['active' => 1]));
	}

	public function testMax() : void {
		$this->assertSame(40, $this->db->max('users', 'age'));
	}

	public function testMin() : void {
		$this->assertSame(25, $this->db->min('users', 'age'));
	}

	public function testMaxWithCondition() : void {
		$this->assertSame(30, $this->db->max('users', 'age', ['active' => 1]));
	}

	public function testMaxOfEmptyReturnsNull() : void {
		$this->assertNull($this->db->max('users', 'age', ['id' => 999]));
	}

	public function testCountRows() : void {
		$this->assertSame(3, $this->db->count_rows('SELECT * FROM users'));
	}

	public function testCountRowsWithGroupBy() : void {
		$this->assertSame(2, $this->db->count_rows('SELECT active, COUNT(1) FROM users GROUP BY active'));
	}
}
