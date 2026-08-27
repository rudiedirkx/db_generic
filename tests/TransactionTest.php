<?php

class TransactionTest extends DbTestCase {

	public function testTransactionCommits() : void {
		$this->db->transaction(function($db) {
			$db->insert('users', ['name' => 'Dave']);
		});
		$this->assertSame(4, $this->db->count('users'));
	}

	public function testTransactionReturnsValue() : void {
		$result = $this->db->transaction(function($db) {
			return 'hello';
		});
		$this->assertSame('hello', $result);
	}

	public function testTransactionReThrowsException() : void {
		$this->expectException(RuntimeException::class);
		$this->db->transaction(function($db) {
			throw new RuntimeException('boom');
		});
	}

	public function testTransactionRollsBackOnException() : void {
		try {
			$this->db->transaction(function($db) {
				$db->insert('users', ['name' => 'Dave']);
				throw new RuntimeException('boom');
			});
		}
		catch ( RuntimeException $ex ) {
		}

		$this->assertSame(3, $this->db->count('users'));
	}

	public function testManualBeginCommit() : void {
		$this->assertTrue($this->db->begin());
		$this->db->insert('users', ['name' => 'Dave']);
		$this->assertTrue($this->db->commit());
		$this->assertSame(4, $this->db->count('users'));
	}

	public function testManualBeginRollback() : void {
		$this->db->begin();
		$this->db->insert('users', ['name' => 'Dave']);
		$this->assertTrue($this->db->rollback());
		$this->assertSame(3, $this->db->count('users'));
	}
}
