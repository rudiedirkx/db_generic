<?php

class TestUser extends db_generic_model {

	static public $_table = 'users';

	public function relate_posts() : db_generic_relationship_many {
		return $this->to_many(TestPost::class, 'user_id');
	}

	public function relate_tags() : db_generic_relationship_many {
		return $this->to_many(TestUserTag::class, 'user_id')->key('tag');
	}
}

class TestPost extends db_generic_model {

	static public $_table = 'posts';

	public function relate_author() : db_generic_relationship_one {
		return $this->to_one(TestUser::class, 'user_id');
	}
}

class TestUserTag extends db_generic_keyless_model {

	static public $_table = 'users_tags';

	public function relate_user() : db_generic_relationship_one {
		return $this->to_one(TestUser::class, 'user_id');
	}
}
