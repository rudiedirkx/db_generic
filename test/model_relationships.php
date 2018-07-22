<?php

require __DIR__ . '/inc.bootstrap.php';

db_generic_model::$_db = $db;

$db->schema([
	'tables' => [
		'mr_users' => [
			'id' => ['pk' => true],
			'name',
		],
		'mr_groups' => [
			'id' => ['pk' => true],
			'name',
		],
		'mr_memberships' => [
			'id' => ['pk' => true],
			'user_id' => ['unsigned' => true, 'null' => false, 'references' => ['mr_users', 'id', 'cascade']],
			'group_id' => ['unsigned' => true, 'null' => false, 'references' => ['mr_groups', 'id', 'cascade']],
		],
	],
	'data' => [
		'mr_users' => [
			['id' => 1, 'name' => 'Alice'],
			['id' => 2, 'name' => 'Bob'],
		],
		'mr_groups' => [
			['id' => 1, 'name' => 'Aeronautical'],
			['id' => 2, 'name' => 'Beaches'],
			['id' => 3, 'name' => 'Colombia'],
		],
		'mr_memberships' => [
			['user_id' => 1, 'group_id' => 1],
			['user_id' => 1, 'group_id' => 2],
			['user_id' => 1, 'group_id' => 3],
			['user_id' => 2, 'group_id' => 2],
		],
	],
]);

class User extends db_generic_model {
	static $_table = 'mr_users';

	function relate_memberships() {
		return $this->to_many(Membership::class, 'user_id')/*->eager(['group'])*/;
	}

	function relate_groups() {
		return $this->to_many_through(Group::class, Membership::$_table, 'user_id', 'group_id');
	}
}

class Membership extends db_generic_model {
	static $_table = 'mr_memberships';

	function relate_user() {
		return $this->to_one(User::class, 'user_id');
	}

	function relate_group() {
		return $this->to_one(Group::class, 'group_id');
	}
}

class Group extends db_generic_model {
	static $_table = 'mr_groups';

	function relate_memberships() {
		return $this->to_many(Membership::class, 'group_id')->eager(['user']);
	}

	function relate_users() {
		return $this->to_many_through(User::class, Membership::$_table, 'group_id', 'user_id');
	}
}

$_queryStart = count($db->queries);

$user = User::find(1);
print_r($user);
print_r($user->groups);
print_r($user->memberships);

print_r(array_slice($db->queries, $_queryStart));
$_queryStart = count($db->queries);

echo "\n\n\n";

$group = Group::find(2);
print_r($group->relate_users()->eager(['memberships'])->load());

print_r(array_slice($db->queries, $_queryStart));
$_queryStart = count($db->queries);

echo "\n\n\n";

var_dump(count(User::all('1')));
var_dump(count(User::all('id IN (?)', [[1, 2, 3]])));

print_r(array_slice($db->queries, $_queryStart));
$_queryStart = count($db->queries);
