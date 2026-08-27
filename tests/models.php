<?php

class TestUser extends db_generic_model {

	static public $_table = 'users';

	public function relate_posts() : db_generic_relationship_many {
		return $this->to_many(TestPost::class, 'user_id');
	}
}

class TestPost extends db_generic_model {

	static public $_table = 'posts';

	public function relate_author() : db_generic_relationship_one {
		return $this->to_one(TestUser::class, 'user_id');
	}
}
