<?php

class KeylessTest extends DbTestCase {

	protected function setUp() : void {
		parent::setUp();

		$this->db->execute("
			CREATE TABLE users_tags (
				user_id INTEGER,
				tag TEXT
			)
		");

		$this->db->inserts('users_tags', [
			['user_id' => 1, 'tag' => 'admin'],
			['user_id' => 1, 'tag' => 'dev'],
			['user_id' => 2, 'tag' => 'dev'],
		]);
	}

	public function testAllReturnsEveryRow() : void {
		$tags = TestUserTag::all('1=1');
		$this->assertSame([0, 1, 2], array_keys($tags));
	}

	public function testQueryNeverKeysById() : void {
		$tags = TestUserTag::query('SELECT t.*, u.id FROM users_tags t JOIN users u ON u.id = t.user_id ORDER BY u.id DESC');
		$this->assertSame([0, 1, 2], array_keys($tags));
	}

	public function testToManyKeyIsSameLazyAndEager() : void {
		$user = TestUser::find(1);
		$lazy = array_keys($user->tags);

		$users = TestUser::all('1=1');
		TestUser::eager('tags', $users);
		$eager = array_keys($users[1]->tags);

		$this->assertSame(['admin', 'dev'], $lazy);
		$this->assertSame(['admin', 'dev'], $eager);
	}

	public function testToOneLazy() : void {
		$tags = TestUserTag::all('1=1 ORDER BY user_id, tag');
		$this->assertSame('Bob', $tags[2]->user->name);
	}

	public function testToOneEager() : void {
		$tags = TestUserTag::all('1=1 ORDER BY user_id, tag');
		TestUserTag::eager('user', $tags);
		$this->assertTrue(property_exists($tags[2], 'user'));
		$this->assertSame('Bob', $tags[2]->user->name);
	}

	public function testInsertIgnoresIdAndTrims() : void {
		TestUserTag::insert(['id' => 9, 'user_id' => 3, 'tag' => 'ops  ']);
		$tag = TestUserTag::first(['user_id' => 3]);
		$this->assertSame('ops', $tag->tag);
	}

	public function testInsertAllIgnoresId() : void {
		TestUserTag::insertAll([
			['id' => 8, 'user_id' => 3, 'tag' => 'ops'],
			['id' => 9, 'user_id' => 3, 'tag' => 'qa'],
		]);
		$this->assertSame(2, TestUserTag::count(['user_id' => 3]));
	}

}
