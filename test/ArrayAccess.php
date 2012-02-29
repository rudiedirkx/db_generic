<?php

require 'inc.connect.php';


/**
 * ArrayAccess is good for backward compat.
 */


$stuff = $db->select('stuffs', 'stuff <> ? ORDER BY RAND() LIMIT 1', array('Q'), true);
print_r($stuff);

echo "\n";

var_dump($stuff->id);
//~ var_dump($stuff->xid); // Notice ... in ArrayAccess.php
var_dump($stuff['stuff']);
//~ var_dump($stuff['xstuff']); // Notice ... in db_generic.php =(



echo "\n";

echo number_format(microtime(1) - $start, 4) . "\n\n";

print_r($db);


