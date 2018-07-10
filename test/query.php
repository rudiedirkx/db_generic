<?php

require __DIR__ . '/inc.bootstrap.php';

$query = $db->newQuery();
$query->field($query->and()->where('p.title LIKE ?', ['x%']), 'xtitle');
$query->field($query->and()->where('p.sector1 IN (?)', [[1, 2, 3]]), 's1');
$query->field($query->and()->where('p.sector2 IN (?)', [[1, 2, 3]]), 's2');
$query->table('professions', 'p');
$query->condition('p.disabled <> ?', [1]);
$query->condition($query->or()->where('sector1 IS NOT NULL')->where('sector2 IS NOT NULL'));
print_r($query);

echo "\n" . $query->buildSelect();

echo "\n====\n\n";

$query = $db->newQuery([
	'tables' => ['people'],
	'conditions' => [
		'enabled' => 1,
		'enabled = 1',
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
]);
print_r($query);

echo "\n" . $query->buildSelect();

echo "\n====\n\n";

$query = $db->newQuery([
	'fields' => ['oele', 'count(1) AS num', 'a.*', 'p.name', 'p.birthdate'],
	'table' => [['people', 'p']],
	'conditions' => [
		['p.x = ?', [17]],
		['length(a.role) = ?', [3]],
		['p.y > ?', ['blup']],
	],
	'join' => [
		[null, ['actors', 'a'], ['p.person_id = a.id', 'a.type not in (1, 2)']],
		[null, 'movies', ['a.movie_id = movies.movie_id', ['a.age > ?', [4]]]],
	],
]);
print_r($query);

echo "\n" . $query->buildSelect();
