<?php

require 'inc.connect.php';

var_dump($db->count_rows('SELECT * FROM stuffs WHERE stuff <> ? GROUP BY stuff', array('Q')));


echo "\n\n";


$stuffs = $db->fetch_by_field('SELECT * FROM stuffs WHERE stuff <> ? ORDER BY RAND() LIMIT 200', 'id', array('Q'));
print_r($stuffs);

print_r(iterator_to_array($stuffs));


echo "\n";

echo number_format(microtime(1) - $start, 4) . "\n\n";

print_r($db);


