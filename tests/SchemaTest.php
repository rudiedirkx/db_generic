<?php

class SchemaTest extends DbTestCase {

	public function testTablesIncludesCreatedTable() : void {
		$this->assertArrayHasKey('users', $this->db->tables());
	}

	public function testColumnsListsColumns() : void {
		$columns = $this->db->columns('users');
		$this->assertArrayHasKey('id', $columns);
		$this->assertArrayHasKey('name', $columns);
		$this->assertArrayHasKey('age', $columns);
	}

	public function testTableReturnsExistingDefinition() : void {
		$this->assertNotNull($this->db->table('users'));
	}

	public function testTableReturnsNullForUnknown() : void {
		$this->assertNull($this->db->table('nope'));
	}

	public function testTableCreatesTable() : void {
		$this->db->table('tags', ['id' => ['pk' => true], 'label' => ['type' => 'varchar']]);
		$this->db->insert('tags', ['label' => 'php']);
		$this->assertSame(1, $this->db->count('tags'));
	}

	public function testTableReturnsSqlWithoutExecuting() : void {
		$sql = $this->db->table('tags', ['id' => ['pk' => true], 'label' => ['type' => 'varchar']], true);
		$this->assertStringContainsString('CREATE TABLE', $sql);
		$this->assertStringContainsString('"tags"', $sql);
		$this->assertArrayNotHasKey('tags', $this->db->tables());
	}

	public function testSchemaCreatesTableWithTypedColumns() : void {
		$result = $this->db->schema([
			'tags' => [
				'id' => ['pk' => true],
				'label' => ['type' => 'varchar'],
				'weight' => ['type' => 'int', 'default' => 5],
			],
		]);

		$this->assertTrue($result['tables']['tags']);

		$this->db->insert('tags', ['label' => 'php']);
		$row = $this->db->select_first('tags', ['id' => 1]);
		$this->assertSame('php', $row->label);
		$this->assertSame(5, $row->weight);
	}

	public function testSchemaWithExplicitColumnsKey() : void {
		$this->db->schema([
			'tags' => [
				'columns' => [
					'id' => ['pk' => true],
					'label' => [],
				],
			],
		]);

		$this->db->insert('tags', ['label' => 'php']);
		$this->assertSame(1, $this->db->count('tags'));
	}

	public function testSchemaWithSimpleColumnNames() : void {
		$this->db->schema([
			'kv' => ['id' => ['pk' => true], 'k', 'v'],
		]);

		$this->db->insert('kv', ['k' => 'a', 'v' => 'b']);
		$row = $this->db->select_first('kv', ['id' => 1]);
		$this->assertSame('a', $row->k);
		$this->assertSame('b', $row->v);
	}

	public function testSchemaUniqueColumnRejectsDuplicate() : void {
		$this->db->schema([
			'tags' => [
				'id' => ['pk' => true],
				'label' => ['unique' => true],
			],
		]);

		$this->db->insert('tags', ['label' => 'php']);

		$this->expectException(db_exception::class);
		$this->db->insert('tags', ['label' => 'php']);
	}

	public function testSchemaCreatesUniqueIndex() : void {
		$this->db->schema([
			'tags' => [
				'columns' => ['id' => ['pk' => true], 'label' => []],
				'indexes' => ['by_label' => ['columns' => ['label'], 'unique' => true]],
			],
		]);

		$this->db->insert('tags', ['label' => 'php']);

		$this->expectException(db_exception::class);
		$this->db->insert('tags', ['label' => 'php']);
	}

	public function testSchemaAddsMissingColumnToExistingTable() : void {
		$result = $this->db->schema([
			'users' => [
				'id' => ['pk' => true],
				'name' => [],
				'email' => [],
				'age' => ['type' => 'int'],
				'active' => ['type' => 'int'],
				'nickname' => ['type' => 'varchar', 'default' => 'anon'],
			],
		]);

		$this->assertTrue($result['columns']['users']['nickname']);

		$this->db->insert('users', ['name' => 'Dave']);
		$this->assertSame('anon', $this->db->select_one('users', 'nickname', ['name' => 'Dave']));
	}

	public function testSchemaSeedsDataForNewTables() : void {
		$result = $this->db->schema([
			'tables' => [
				'tags' => ['id' => ['pk' => true], 'label' => []],
			],
			'data' => [
				'tags' => [
					['label' => 'php'],
					['label' => 'sql'],
				],
			],
		]);

		$this->assertSame(2, $result['data']['tags']);
		$this->assertSame(2, $this->db->count('tags'));
	}

	public function testEnsureSchemaCreatesTables() : void {
		$changes = $this->db->ensureSchema([
			'version' => '1',
			'tables' => [
				'tags' => ['id' => ['pk' => true], 'label' => ['type' => 'varchar']],
			],
		]);

		$this->assertTrue($changes['tables']['tags']);

		$this->db->insert('tags', ['label' => 'php']);
		$this->assertSame(1, $this->db->count('tags'));
	}

	public function testEnsureSchemaIsIdempotent() : void {
		$schema = [
			'version' => '1',
			'tables' => [
				'tags' => ['id' => ['pk' => true], 'label' => ['type' => 'varchar']],
			],
		];

		$this->db->ensureSchema($schema);
		$changes = $this->db->ensureSchema($schema);

		$this->assertSame([], $changes);
	}

	public function testEnsureSchemaSeedsData() : void {
		$changes = $this->db->ensureSchema([
			'version' => '1',
			'tables' => [
				'tags' => ['id' => ['pk' => true], 'label' => ['type' => 'varchar']],
			],
			'data' => [
				'tags' => [
					['label' => 'php'],
					['label' => 'sql'],
				],
			],
		]);

		$this->assertSame(2, $changes['data']['tags']);
		$this->assertSame(2, $this->db->count('tags'));
	}

	public function testEnsureSchemaRunsUpdatesOnce() : void {
		$schema = [
			'version' => '1',
			'tables' => [
				'tags' => ['id' => ['pk' => true], 'label' => ['type' => 'varchar']],
			],
			'updates' => [
				'seed-a-tag' => function( $db ) {
					$db->insert('tags', ['label' => 'seeded']);
					return true;
				},
			],
		];

		$this->db->ensureSchema($schema);
		$this->db->ensureSchema($schema);

		$this->assertSame(1, $this->db->count('tags'));
		$this->assertSame('seeded', $this->db->select_one('tags', 'label', ['id' => 1]));
	}
}
