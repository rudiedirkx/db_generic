<?php

require 'inc.connect.php';

class MyCollection implements ArrayAccess, Iterator {

	public function combine( $field ) {
		$str = '';
		foreach ( $this AS $record ) {
			$str .= $record->$field;
		}

		return $str;
	}

	protected $items = array();
	protected $index = 0;

	// ArrayAccess
	public function offsetExists( $offset ) {
		return isset($this->items[$offset]);
	}

	public function offsetGet( $offset ) {
		return $this->items[$offset];
	}

	public function offsetSet( $offset, $value ) {
		if ( null === $offset ) {
			$this->items[] = $value;
		}
		else {
			$this->items[$offset] = $value;
		}
	}

	public function offsetUnset( $offset ) {
		unset($this->items[$offset]);
	}


	// Iterator
	public function current() {
		return $this->items[$this->index];
	}

	public function key() {
		return $this->index;
	}

	public function next() {
		$this->index++;
	}

	public function rewind() {
		$this->index = 0;
	}

	public function valid() {
		return isset($this->items[$this->index]);
	}


}

$query = $db->select('stuffs', '1 LIMIT 3');
$results = $query->all(array('collection' => 'MyCollection'));
print_r($results);

echo "\n";

var_dump($results->combine('stuff'));

echo "\n";

foreach ( $results AS $k => $record ) {
	var_dump($k);
	print_r($record);
}



echo "\n";

echo number_format(microtime(1) - $start, 4) . "\n";
