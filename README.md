
The most elegant, simple, beautiful DBAL ever.
====


Drivers / adapters / databases
----

* SQLite 3 ([via PDO](http://nl3.php.net/manual/en/ref.pdo-sqlite.php))
* MySQLi

No SQLite 2 or procedural MySQL. What is it, 2003?


Used in
----

* https://github.com/rudiedirkx/series
* https://github.com/rudiedirkx/Blogs-feed


Where to start
----

Check out the `test/` folder. It contains a few **simple** tests/examples.

Check out the projects where it's used. A powerful, **very useful** feature
is the schema 'sync': create tables, columns, indexes, relations and fixtures,
all in 1 clean array.

Do it! You can create other drivers. Just extend `db_generic` and
`db_generic_result`. You can call it `db_pgsql` =) NoSQL won't work, because
there's no Query builder (and there won't be).


To do
----

* Always UTF-8, everywhere, all the time.
* Schema stuff for MySQL.
* Maybe do a pgSQL driver?


Show me examples!
----

Okay.

Simple select. Will return an Iterable.

	$users = $db->select('users', 'lastname <> ?', array("De'sander"));
	print_r($users); // NOT a list of users

Get the first result object.

	$user = $users->nextObject();
	var_dump($user->lastname);

Do a GROUP BY and get a 2D array.

	$users_by_lastname = $db->select_fields('users', 'lastname, COUNT(1)', 'active = ? GROUP BY lastname', array(1));
	var_dump($users_by_lastname["De'sander"]);

Another one. Perfect for HTML `<option>`s.

	$options = $db->select_fields('countries', 'code, name', array('active' => 1));

Return objects in a different class. Voila, Active Records. Use `->all()` to fetch all objects.

	$sessions = $db->fetch('SELECT s.* from sessions s, people p WHERE p.access_level = ? AND p.id = s.person_id', array(
		'params' => array(4),
		'class' => 'UserSession',
	))->all();
	var_dump(get_class($sessions[0])); // UserSession

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

And ofcourse there's updating and inserting etc.

	$bool = $db->update('people', array('enabled' => 0), array(
		'last_login' => 0,
		'favourite_pizza IS NULL',
	));
	$affected = $db->affected_rows();
	
	$bool = $db->insert('people', array(
		'name' => 'De Rudie',
		'awesomeness' => true,
		'favourite_pizza' => null,
	));
	$pk = $db->insert_id();


