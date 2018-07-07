<?php

require __DIR__ . '/inc.bootstrap.php';

$query = [
	'table' => 'people',
	'conditions' => [
		['name', "%j'ales%", 'like'],
		['enabled', 1],
		['id', 0, '<>'],
	],
	'order' => [
		'x',
		['a', 'y', 'desc'],
	],
];
print_r($query);

echo "\n" . $db->buildSelectQuery($query);

echo "\n====\n\n";

$query = [
	'fields' => ['oele', 'count(1)', 'a' => '*', 'p' => ['name', 'birthdate']],
	'table' => ['people', 'p'],
	'conditions' => [
		'p.x' => 17,
		'length(a.role)' => 3,
		[['p', 'y'], 'blup', '>'],
	],
	'join' => [
		[['actors', 'a'], ['p.person_id = a.id']],
		['movies', ['a.movie_id = movies.movie_id', ['a.age > ?', [4]]]],
	],
];
print_r($query);

echo "\n" . $db->buildSelectQuery($query);

