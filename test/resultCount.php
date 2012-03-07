<?php

require 'inc.connect.php';

$stuffs = $db->select('stuffs', '(1 OR stuff = ?) ORDER BY id LIMIT 30', array('A'));
print_r($stuffs);

var_dump($stuffs->notEmpty());
var_dump($stuffs->notEmpty());
var_dump($stuffs->notEmpty());
var_dump($stuffs->notEmpty());

print_r(iterator_to_array($stuffs));

foreach ( $stuffs AS $stuff ) {
	print_r($stuff);
}

print_r($stuffs);

echo "\n";

echo number_format(microtime(1) - $start, 4) . "\n";
