The most elegant, simple, beautiful DBAL ever.
====


Drivers / adapters / databases
----

* `db_sqlite`: SQLite 3 (via PDO)
* `db_mysql`: MySQL (mysqli)
* `db_mysql_pdo`: MySQL (via PDO)
* `db_pgsql`: PostgreSQL (via PDO)

No SQLite 2 or procedural MySQL. What is it, 2003?


Where to start
----

Check out the `tests/` folder. It's a PHPUnit suite that exercises the whole public API.

Check out the projects where it's used. A powerful, **very useful** feature
is the schema 'sync': create tables, columns, indexes, relations and fixtures,
all in 1 clean array.

Want another driver? Extend `db_generic` (and `db_generic_result` for the engine
cursor). NoSQL won't work, because there's no query builder.


Show me examples!
----

Okay.

Simple select. Returns a plain array of `stdClass` rows.

	$users = $db->select('users', 'lastname <> ?', array("De'sander"));
	var_dump($users[0]->lastname);

Just the first row, or `null`.

	$user = $db->select_first('users', array('username' => 'sander'));
	var_dump($user->lastname);

Do a GROUP BY and get a 2D array.

	$users_by_lastname = $db->select_fields('users', 'lastname, COUNT(1)', 'active = ? GROUP BY lastname', array(1));
	var_dump($users_by_lastname["De'sander"]);

Another one. Perfect for HTML `<option>`s.

	$options = $db->select_fields('countries', 'code, name', array('active' => 1));

Raw queries return `stdClass` rows too. (For typed rows in your own class, use a model, see below.)

	$sessions = $db->fetch('SELECT s.* FROM sessions s JOIN people p ON p.id = s.person_id WHERE p.access_level = 4');
	var_dump($sessions[0]->id);

More advanced conditions.

	$people = $db->select('people', array(
		// Conditions
		'enabled' => 1,
		'age >= ?',
		'age <= ?',
		'lastname <> ?',
		'(a < b OR b IS NULL)'
	), array(
		// Params
		18,
		65,
		"De'sander",
	));

And of course there's updating and inserting. Write methods return `true` and throw on failure.

	$db->update('people', array('enabled' => 0), array(
		'last_login' => 0,
		'favourite_pizza IS NULL',
	));
	$affected = $db->affected_rows();

	$db->insert('people', array(
		'name' => 'De Rudie',
		'awesomeness' => true,
		'favourite_pizza' => null,
	));
	$pk = $db->insert_id();


Active Record Models
----

You can create models by extending `db_generic_model`:

	class User extends db_generic_model {
		static public $_table = 'users';
	}

And then make all procedures easier:

	$user = User::find(12); // User|null

	$user = User::first(['username' => 'sander']); // User|null
	
	$users = User::all(['country_id' => 12]); // User[]

All objects are statically cached (unless `db_generic_model::$_cache == false`), so calling
`find(X)` 6 times, takes it from the cache 5 times.

Create records:

	User::insert(['username' => 'sander']); // true

	$user = User::create(['username' => 'sander']); // User|null

Do active things to active objects:

	$user->update(['disabled' => true]);

	$user->delete();

Or without the objects:

	User::updateAll(['disabled' => true], ['username' => 'sander']);

	User::deleteAll(['disabled' => true]);

Add dynamic properties with `get_NAME()`.

	class User extends db_generic_model {
		function get_fullname() {
			return "$this->firstname $this->lastname";
		}
	}

	echo $user->fullname;

Properties are cached in the object, so the getter is called only once.

Add relationships with `relate_NAME()`:

	class User extends db_generic_model {
		function relate_country() {
			return $this->to_one(Country::class, 'country_id');
		}

		function relate_hobbies() {
			return $this->to_many(Hobby::class, 'user_id');
		}

		function relate_num_groups() {
			return $this->to_count(UserGroup::class, 'user_id');
		}

		function relate_group_ids() {
			return $this->to_many_scalar('group_id', 'users_groups', 'user_id');
		}

		function relate_groups() {
			return $this->to_many_through(Group::class, 'group_ids');
		}
	}

	echo $user->country->name;
	print_r($user->hobbies);
	print_r($user->groups);
	echo $user->num_groups;
	print_r($user->group_ids);

All primary keys **must be** `id`. Foreign keys can be anything.
