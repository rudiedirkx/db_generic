<?php

class RelationshipTest extends DbTestCase {

	protected function setUp() : void {
		parent::setUp();

		$this->db->execute("
			CREATE TABLE posts (
				id INTEGER PRIMARY KEY AUTOINCREMENT,
				user_id INTEGER,
				title TEXT
			)
		");

		$this->db->inserts('posts', [
			['user_id' => 1, 'title' => 'Alice one'],
			['user_id' => 1, 'title' => 'Alice two'],
			['user_id' => 2, 'title' => 'Bob one'],
		]);
	}

	public function testToManyLoadsRelated() : void {
		$user = TestUser::find(1);
		$this->assertCount(2, $user->posts);
		$this->assertContainsOnlyInstancesOf(TestPost::class, $user->posts);
	}

	public function testToManyEmpty() : void {
		$user = TestUser::find(3);
		$this->assertCount(0, $user->posts);
	}

	public function testToOneLoadsRelated() : void {
		$post = TestPost::find(1);
		$author = $post->author;
		$this->assertInstanceOf(TestUser::class, $author);
		$this->assertSame('Alice', $author->name);
	}

	public function testToManyIsCachedOnModel() : void {
		$user = TestUser::find(1);
		$first = $user->posts;
		$second = $user->posts;
		$this->assertSame($first, $second);
	}
}
