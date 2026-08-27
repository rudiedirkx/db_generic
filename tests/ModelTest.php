<?php

class ModelTest extends DbTestCase {

	public function testFind() : void {
		$user = TestUser::find(1);
		$this->assertInstanceOf(TestUser::class, $user);
		$this->assertSame('Alice', $user->name);
	}

	public function testFindReturnsNullForUnknown() : void {
		$this->assertNull(TestUser::find(999));
	}

	public function testFindReturnsNullForNull() : void {
		$this->assertNull(TestUser::find(null));
	}

	public function testAll() : void {
		$users = TestUser::all('1=1');
		$this->assertCount(3, $users);
		$this->assertContainsOnlyInstancesOf(TestUser::class, $users);
	}

	public function testAllKeyedById() : void {
		$users = TestUser::all('1=1');
		$this->assertArrayHasKey(1, $users);
		$this->assertSame('Alice', $users[1]->name);
	}

	public function testAllWithCondition() : void {
		$users = TestUser::all(['active' => 1]);
		$this->assertCount(2, $users);
	}

	public function testFirst() : void {
		$user = TestUser::first(['name' => 'Bob']);
		$this->assertInstanceOf(TestUser::class, $user);
		$this->assertSame(25, $user->age);
	}

	public function testFirstReturnsNull() : void {
		$this->assertNull(TestUser::first(['name' => 'Nobody']));
	}

	public function testQuery() : void {
		$users = TestUser::query('SELECT * FROM users WHERE active = ?', [1]);
		$this->assertCount(2, $users);
		$this->assertContainsOnlyInstancesOf(TestUser::class, $users);
	}

	public function testCount() : void {
		$this->assertSame(3, TestUser::count('1=1'));
	}

	public function testInsertReturnsTrue() : void {
		$this->assertTrue(TestUser::insert(['name' => 'Dave', 'age' => 50]));
		$this->assertSame(4, $this->db->insert_id());
	}

	public function testCreate() : void {
		$user = TestUser::create(['name' => 'Dave', 'age' => 50]);
		$this->assertInstanceOf(TestUser::class, $user);
		$this->assertSame('Dave', $user->name);
		$this->assertSame(4, $user->id);
	}

	public function testUpdate() : void {
		$user = TestUser::find(1);
		$this->assertTrue($user->update(['age' => 99]));
		$this->assertEquals(99, $user->age);
		$this->assertSame(99, $this->db->select_one('users', 'age', ['id' => 1]));
	}

	public function testDelete() : void {
		$user = TestUser::find(3);
		$this->assertTrue($user->delete());
		$this->assertSame(2, TestUser::count('1=1'));
	}

	public function testDeleteAll() : void {
		$deleted = TestUser::deleteAll(['active' => 0]);
		$this->assertSame(1, $deleted);
		$this->assertSame(2, TestUser::count('1=1'));
	}

	public function testUpdateAll() : void {
		$updated = TestUser::updateAll(['active' => 0], '1=1');
		$this->assertSame(3, $updated);
		$this->assertSame(0, TestUser::count(['active' => 1]));
	}

	public function testInsertAll() : void {
		TestUser::insertAll([
			['name' => 'Dave'],
			['name' => 'Eve'],
		]);
		$this->assertSame(5, TestUser::count('1=1'));
	}

	public function testFinds() : void {
		$users = TestUser::finds([1, 2]);
		$this->assertCount(2, $users);
	}

	public function testFields() : void {
		$names = TestUser::fields(['id', 'name'], '1=1');
		$this->assertSame('Alice', $names[1]);
	}

	public function testCacheReturnsSameInstance() : void {
		$a = TestUser::find(1);
		$b = TestUser::find(1);
		$this->assertSame($a, $b);
	}

	public function testCacheDisabledReturnsFreshInstances() : void {
		db_generic_model::$_cache = false;

		$a = TestUser::find(1);
		$b = TestUser::find(1);

		$this->assertNotSame($a, $b);
		$this->assertSame('Alice', $a->name);
		$this->assertSame('Alice', $b->name);
	}

	public function testCacheDisabledDoesNotShareState() : void {
		db_generic_model::$_cache = false;

		$a = TestUser::find(1);
		$a->name = 'Changed';
		$b = TestUser::find(1);

		$this->assertSame('Alice', $b->name);
	}

	public function testCacheDisabledOnAll() : void {
		db_generic_model::$_cache = false;

		$first = TestUser::all('1=1');
		$second = TestUser::all('1=1');

		$this->assertNotSame($first[1], $second[1]);
	}
}
