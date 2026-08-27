<?php

class db_mysql_pdo extends db_pdo {

	use db_mysql_schema;

	public function __construct(
		protected string $database,
		protected ?string $host = null,
		protected ?string $user = null,
		protected ?string $pass = null,
	) {}

	/**
	 * @phpstan-assert PDO $this->db
	 */
	public function connect() : void {
		if ( isset($this->db) ) {
			return;
		}

		$pass = $this->pass;
		$this->pass = null;

		try {
			$this->db = new PDO('mysql:host=' . $this->host . ';dbname=' . $this->database, $this->user, $pass, [
				PDO::ATTR_CASE => PDO::CASE_NATURAL,
				PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
				PDO::ATTR_ORACLE_NULLS => PDO::NULL_NATURAL,
				PDO::ATTR_STRINGIFY_FETCHES => false,
				PDO::ATTR_EMULATE_PREPARES => false,
			]);
		}
		catch ( PDOException $ex ) {
			$this->except('', $ex->getMessage(), $ex->getCode(), $ex);
		}

		$this->postConnect();
	}

	protected function postConnect() : void {
		$this->execute("SET NAMES 'utf8' COLLATE 'utf8_general_ci'");
	}

	protected function escapeValue( $value ) : string {
		return str_replace("'", "''", (string) $value);
	}

}
