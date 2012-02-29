<?php

require 'inc.connect.php';


/**
 * ArrayAccess is good for backward compat.
 */


$stuff = $db->select('stuffs', 'stuff <> ? ORDER BY RAND() LIMIT 1', array('Q'), true);
print_r($stuff);

echo "\n";

var_dump($stuff->id);
//~ var_dump($stuff->xid); // Notice ... in __FILE__
var_dump($stuff['stuff']);
//~ var_dump($stuff['xstuff']); // Notice ... in db_generic.php =(


echo "\n\n\n";


echo "without ArrayAccess:\n";
db_generic_result::$return_object_class = 'stdClass';

$stuff = $db->select('stuffs', 'stuff <> ? ORDER BY RAND() LIMIT 1', array('Q'), true);
var_dump($stuff->id);
//var_dump($stuff['stuff']); // Fatal error ... in __FILE__



echo "\n";

echo number_format(microtime(1) - $start, 4) . "\n\n";

print_r($db);


