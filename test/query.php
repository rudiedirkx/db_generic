<?php

require __DIR__ . '/inc.bootstrap.php';

$query = [
	'table' => 'people',
	'conditions' => [
		'enabled' => 1,
		['id <> ?', [0]],
		['name like ?', ["%j'ales%"]],
		['type not in (?)', [[1, 2, 3]]],
		['bdate between ? and ?', ['2000', '2002']],
		['? between minage and maxage', [22]],
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
		['p.x = ?', [17]],
		['length(a.role) = ?', [3]],
		['p.y > ?', ['blup']],
	],
	'join' => [
		[['actors', 'a'], ['p.person_id = a.id', 'a.type not in (1, 2)']],
		['movies', ['a.movie_id = movies.movie_id', ['a.age > ?', [4]]]],
	],
];
print_r($query);

echo "\n" . $db->buildSelectQuery($query);

