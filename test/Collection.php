<?php

require 'inc.connect.php';

class MyCollection extends db_generic_collection {

	public $items; // For demo purposes only.

	public function combine( $field ) {
		$str = '';
		foreach ( $this AS $record ) {
			$str .= $record->$field;
		}

		return $str;
	}

}

$query = $db->select('stuffs', '1 LIMIT 3');
$results = $query->all(array('collection' => 'MyCollection'));
print_r($results);
echo count($results) . " results\n";
echo 'is_array: ';
var_dump(is_array($results));
echo 'instanceof db_generic_collection: ';
var_dump($results instanceof db_generic_collection);


echo "\n";

var_dump($results->combine('stuff'));

echo "\n";

$results->oele = 4;
$results[5] = new db_generic_record;
$results[5]->id = 0;
$results[5]->stuff = 'x';
print_r($results);

echo "\n";

foreach ( $results AS $k => $record ) {
	var_dump($k);
	print_r($record);
}

echo "\n";

foreach ( $results AS $a => $record ) {
	echo 'a: ' . $a . ' = ' . $record->id . "\n";
	foreach ( $results AS $b => $record ) {
		echo '  b: ' . $b . ' = ' . $record->id . "\n";
	}
}

echo "\n";

echo 'first: ';
print_r($results->first());
echo 'key: ';
var_dump(key($results->items));

echo 'last: ';
print_r($results->last());
echo 'key: ';
var_dump(key($results->items));



echo "\n";

echo number_format(microtime(1) - $start, 4) . "\n";
