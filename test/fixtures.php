<?php

// Init
error_reporting(E_ALL & ~E_STRICT);
header('Content-type: text/plain');
$start = microtime(1);

require 'inc.connect.php';

$schema = array(
	'tables' => array(
		'people' => array(
			'columns' => array(
				'id' => array('pk' => true),
				'firstname',
				'middlename',
				'lastname',
				'email' => array('null' => false),
				'gayness' => array('unsigned' => true, 'default' => 0),
			),
			'indexes' => array(
				'gayness' => array('gayness'),
			),
		),
		'homies' => array(
			'columns' => array(
				'homie1' => array('unsigned' => true),
				'homie2' => array('unsigned' => true),
				'when' => array('unsigned' => true, 'default' => 0),
			),
			'indexes' => array(
				'friendliness' => array('homie1', 'homie2'),
			),
		),
	),
	'data' => array(
		'people' => array(
			array('id' => 10, 'firstname' => 'John', 'lastname' => 'Hancock', 'email' => 'jh@names.org'),
			array('firstname' => 'Rudie', 'lastname' => 'Dirkx', 'email' => 'oele@names.org'),
			array('id' => 7, 'firstname' => 'Bertrand', 'middlename' => 'van', 'lastname' => 'Bergenson', 'email' => 'bb@names.org'),
		),
		'homies' => array(
			array('homie1' => 10, 'homie2' => 7),
		),
	),
);

$updates = $db->schema($schema);
print_r($updates);





print_r($db);


