<?php

require __DIR__ . '/inc.bootstrap.php';

try {
	$db->execute('PRAGMA foreign_keys = ON');
}
catch ( db_exception $ex ) {
	// SQLite only, fine
}

$schema = array(
	'relationships' => true,
	'tables' => array(
		'users' => array(
			'columns' => array(
				'id' => array('pk' => true),
				'provider' => array('unsigned' => true, 'null' => false, 'references' => array('providers', 'id')),
				'identity' => array('null' => false),
				'name' => array('null' => false),
				'email',
				'friendsCount' => array('unsigned' => true),
				'birthdate' => array('type' => 'date'),
				'gender' => array('type' => 'enum', 'options' => array('f', 'm')),
			),
		),
		'providers' => array(
			'columns' => array(
				'id' => array('pk' => true),
				'name',
				'url',
			),
		),
		'sessions' => array(
			'columns' => array(
				'id' => array('pk' => true),
				'user_id' => array('unsigned' => true, 'null' => false, 'references' => array('users', 'id')),
				'started_on' => array('unsigned' => true, 'null' => false),
			),
		),
	),
	'data' => array(
		'providers' => array(
			array('id' => 1, 'name' => 'facebook', 'url' => 'facebook.com'),
			array('id' => 2, 'name' => 'twitter', 'url' => 'twitter.com'),
		),
	),
);

try {
	var_dump($db->schema($schema));
}
catch ( db_exception $ex ) {
	echo $ex->getQuery();
	exit(1);
}

echo "\n";

echo number_format(microtime(1) - $start, 4) . "\n\n";

print_r($db);


