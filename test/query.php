<?php

require 'inc.connect.php';

$query = array(
	'table' => 'people',
	'conditions' => array(
		array('name', "%j'ales%", 'like'),
		array('enabled', 1),
		array('id', 0, '<>'),
	),
	'order' => array(
		'x',
		array('a', 'y', 'desc'),
	),
);
print_r($query);

echo "\n" . $db->buildSelectQuery($query);

echo "\n====\n\n";

$query = array(
	'fields' => array('oele', 'count(1)', 'a' => '*', 'p' => array('name', 'birthdate')),
	'table' => array('people', 'p'),
	'conditions' => array(
		'p.x' => 17,
		'length(a.role)' => 3,
		array(array('p', 'y'), 'blup', '>'),
	),
	'join' => array(
		array(array('actors', 'a'), array('p.person_id = a.person_id')),
		array('movies', array('a.movie_id = movies.movie_id')),
	),
);
print_r($query);

echo "\n" . $db->buildSelectQuery($query);

