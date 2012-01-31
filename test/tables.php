<?php

header('Content-type: text/plain');
$start = microtime(1);

require '../db_sqlite.php';

$db = db_sqlite::open(array('database' => './stuff.sqlite3'));

// create table `stuffs`
$definition = array(
  'id' => array('pk' => true),
  'stuff' => array('type' => 'text', 'default' => 'a'),
);
var_dump($db->table('stuffs', $definition));

echo "\n";

// fill table `stuffs` with loads and loads of stuff
$db->begin();
for ( $i=0, $L=rand(50, 150); $i<$L; $i++ ) {
	$stuff = chr(rand(65, 90));
	$data = array('stuff' => $stuff);
	$db->insert('stuffs', $data);
}
$db->commit();

echo "# records in `stuffs`:\n";
var_dump($db->count('stuffs'));

echo "\n";

// get `stuffs` table definition
var_dump($db->table('stuffs'));

echo "\n";

// get all tables, assoc by table name
print_r($db->tables());

echo "\n";

echo number_format(microtime(1) - $start, 4) . "\n";
