<?php

require __DIR__ . '/inc.bootstrap.php';

// Query & response type
class OneStuff {
	function zebra() {
		return (int)$this->id % 2 ? 'odd' : 'even';
	}
}

$query = $db->select('stuffs', '1 LIMIT 15', null, 'OneStuff');
echo "query:\n";
print_r($query);


// filter: only odd id's
#$query->filter(function($record, $i) { return (int)$record->id % 2; });

// map: only field `stuff`
#$query->map(function($record, $i) { return $record->stuff; });

// map: the char index (ord) of the `stuff`
#$query->map(function($record, $i) { return ord($record); });

// map: zebra terms
#$query->map(function($record, $i) { return $record->zebra(); });

// filter & map in one: the `id` of odd records
/**/
$query->filter(function(&$record) {
	if ( (int)$record->id % 2 ) {
		$record->id *= 100;
		$record = (int)$record->id;
		return true;
	}
});
/**/


// let php do it
#$records = iterator_to_array($query);
#print_r($records);

// let db_generic do it
$records = $query->all();
print_r($records);

// do-it-yourself
#foreach ( $query AS $record ) {
#	var_dump($record);
#}

echo "\n";

echo "Filtered objects:\n";
print_r($query->filtered);

echo "\n";

echo number_format(microtime(1) - $start, 4) . "\n";
