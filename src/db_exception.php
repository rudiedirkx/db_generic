<?php

class db_exception extends Exception {

	public string $query = '';

	/**
	 * @param array{query?: string} $options
	 */
	public function __construct( string $error = '', int $errno = -1, array $options = array(), ?Throwable $previous = null ) {
		parent::__construct($error, $errno, $previous);
		if ( isset($options['query']) ) {
			$this->query = $options['query'];
		}
	}

	public function getQuery() : string {
		return $this->query;
	}

}
