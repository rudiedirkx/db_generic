<?php

namespace rdx\db\PhpStan;

final class DbSchema {

	/** @var array<string, array<string, SchemaFieldDefinition>> */
	protected array $tables = [];

	public function __construct(
		string $schemaFile,
	) {
		$this->prepare(require $schemaFile);
	}

	public function hasTable(string $tableName) : bool {
		return isset($this->tables[$tableName]);
	}

	public function getField(string $tableName, string $colName) : ?SchemaFieldDefinition {
		return $this->tables[$tableName][$colName] ?? null;
	}

	/**
	 * @return array<string, SchemaFieldDefinition>
	 */
	public function getFields(string $tableName) : array {
		return $this->tables[$tableName] ?? [];
	}

	/**
	 * @param array<string, mixed> $schema
	 */
	protected function prepare(array $schema) : void {
		$tables = $schema['tables'] ?? $schema;
		foreach ($tables as $tableName => $info) { // @phpstan-ignore foreach.nonIterable
			$columns = $info['columns'] ?? $info; // @phpstan-ignore offsetAccess.nonOffsetAccessible
			foreach ($columns as $colName => $info) { // @phpstan-ignore foreach.nonIterable
				if (is_int($colName)) {
					$colName = $info;
					$info = [];
				}

				$type = $info['type'] ?? 'string'; // @phpstan-ignore offsetAccess.nonOffsetAccessible
				if (!empty($info['pk'])) { // @phpstan-ignore offsetAccess.nonOffsetAccessible
					$type = 'int';
				}
				elseif (isset($info['unsigned'])) { // @phpstan-ignore offsetAccess.nonOffsetAccessible
					$type = 'int';
				}

				$this->tables[$tableName][$colName] = new SchemaFieldDefinition(strtoupper($type), true);
			}
		}
	}

}
