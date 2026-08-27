<?php

class WriteTest extends DbTestCase {

	public function testInsertReturnsTrue() : void {
		$result = $this->db->insert('users', ['name' => 'Dave', 'age' => 50, 'active' => 1]);
		$this->assertTrue($result);
	}

	public function testInsertId() : void {
		$this->db->insert('users', ['name' => 'Dave']);
		$this->assertSame(4, $this->db->insert_id());
	}

	public function testInserts() : void {
		$this->db->inserts('users', [
			['name' => 'Dave', 'age' => 50],
			['name' => 'Eve', 'age' => 22],
		]);
		$this->assertSame(5, $this->db->count('users'));
	}

	public function testInsertsEmptyReturnsTrue() : void {
		$this->assertTrue($this->db->inserts('users', []));
	}

	public function testUpdateReturnsTrue() : void {
		$result = $this->db->update('users', ['age' => 99], ['id' => 1]);
		$this->assertTrue($result);
		$this->assertSame(99, $this->db->select_one('users', 'age', ['id' => 1]));
	}

	public function testUpdateAffectedRows() : void {
		$this->db->update('users', ['active' => 0], '1=1');
		$this->assertSame(3, $this->db->affected_rows());
	}

	public function testDeleteReturnsTrue() : void {
		$result = $this->db->delete('users', ['id' => 3]);
		$this->assertTrue($result);
		$this->assertSame(2, $this->db->count('users'));
	}

	public function testReplace() : void {
		$this->db->replace('users', ['id' => 1, 'name' => 'Alicia', 'age' => 31, 'active' => 1]);
		$this->assertSame('Alicia', $this->db->select_one('users', 'name', ['id' => 1]));
		$this->assertSame(3, $this->db->count('users'));
	}

	public function testInsertEscapesQuotes() : void {
		$this->db->insert('users', ['name' => "O'Brien"]);
		$name = $this->db->select_one('users', 'name', ['name' => "O'Brien"]);
		$this->assertSame("O'Brien", $name);
	}
}
