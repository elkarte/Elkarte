<?php

/**
 * TestCase class for Table create_table with update option.
 */

use ElkArte\Database\Mysqli\Table as MysqliTable;
use ElkArte\Database\Postgresql\Table as PostgresqlTable;
use PHPUnit\Framework\TestCase;

class TableUpdateTest extends TestCase
{
	protected $backupGlobalsExcludeList = ['user_info'];

	/**
	 * Test rename_table on Mysqli Table
	 */
	public function testMysqliRenameTable()
	{
		$dbMock = $this->createMock(\stdClass::class);
		$db = $this->getMockBuilder(\stdClass::class)
			->addMethods(['query'])
			->getMock();

		$table = new MysqliTable($db, 'elkarte_');

		// Reserved table cannot be renamed
		$this->assertFalse($table->rename_table('{db_prefix}members', '{db_prefix}members_old'));

		// Custom table can be renamed
		$db->expects($this->once())
			->method('query')
			->with(
				$this->equalTo(''),
				$this->stringContains('RENAME TABLE `elkarte_custom_tbl` TO `elkarte_custom_tbl_old`'),
				$this->equalTo(['security_override' => true])
			);

		$this->assertTrue($table->rename_table('{db_prefix}custom_tbl', '{db_prefix}custom_tbl_old'));
	}

	/**
	 * Test rename_table on Postgresql Table
	 */
	public function testPostgresqlRenameTable()
	{
		$db = $this->getMockBuilder(\stdClass::class)
			->addMethods(['query'])
			->getMock();

		$table = new PostgresqlTable($db, 'elkarte_');

		// Reserved table cannot be renamed
		$this->assertFalse($table->rename_table('{db_prefix}messages', '{db_prefix}messages_old'));

		// Custom table can be renamed
		$db->expects($this->once())
			->method('query')
			->with(
				$this->equalTo(''),
				$this->stringContains('ALTER TABLE "elkarte_custom_tbl" RENAME TO "elkarte_custom_tbl_old"'),
				$this->equalTo(['security_override' => true])
			);

		$this->assertTrue($table->rename_table('{db_prefix}custom_tbl', '{db_prefix}custom_tbl_old'));
	}

	/**
	 * Test Mysqli create_table with if_exists => update
	 */
	public function testMysqliCreateTableUpdate()
	{
		$db = $this->getMockBuilder(\stdClass::class)
			->addMethods(['query', 'list_tables', 'transaction', 'skip_next_error'])
			->getMock();

		$table = $this->getMockBuilder(MysqliTable::class)
			->setConstructorArgs([$db, 'elkarte_'])
			->onlyMethods(['list_columns'])
			->getMock();

		// Simulate table exists
		$db->expects($this->any())
			->method('list_tables')
			->willReturn(['elkarte_test_addon']);

		// Old columns had id, col_a, col_removed
		$table->expects($this->once())
			->method('list_columns')
			->with('elkarte_test_addon_old')
			->willReturn(['id', 'col_a', 'col_removed']);

		$queries = [];
		$db->expects($this->any())
			->method('query')
			->willReturnCallback(function ($sub, $query, $params = []) use (&$queries) {
				$queries[] = trim($query);
				return true;
			});

		$columns = [
			[
				'name' => 'id',
				'type' => 'int',
				'size' => 10,
				'auto' => true,
				'null' => false,
			],
			[
				'name' => 'col_a',
				'type' => 'varchar',
				'size' => 255,
				'null' => false,
			],
			[
				'name' => 'col_new',
				'type' => 'varchar',
				'size' => 100,
				'null' => true,
			],
		];

		$indexes = [
			[
				'name' => 'primary',
				'type' => 'primary',
				'columns' => ['id'],
			],
		];

		$result = $table->create_table('{db_prefix}test_addon', $columns, $indexes, [
			'if_exists' => 'update',
		]);

		$this->assertTrue($result);

		// Verify table was renamed to _old
		$this->assertTrue(
			array_reduce($queries, fn($carry, $q) => $carry || str_contains($q, 'RENAME TABLE `elkarte_test_addon` TO `elkarte_test_addon_old`'), false),
			'Table rename query not found'
		);

		// Verify new CREATE TABLE was run
		$this->assertTrue(
			array_reduce($queries, fn($carry, $q) => $carry || str_starts_with($q, 'CREATE TABLE elkarte_test_addon'), false),
			'CREATE TABLE query not found'
		);

		// Verify INSERT INTO ... SELECT ... for common columns (`id`, `col_a`)
		$this->assertTrue(
			array_reduce($queries, fn($carry, $q) => $carry || (str_contains($q, 'INSERT INTO elkarte_test_addon (`id`, `col_a`)') && str_contains($q, 'SELECT `id`, `col_a`') && str_contains($q, 'FROM elkarte_test_addon_old')), false),
			'Data copy query for matching columns not found'
		);

		// Verify drop of old table
		$this->assertTrue(
			array_reduce($queries, fn($carry, $q) => $carry || str_contains($q, 'DROP TABLE elkarte_test_addon_old'), false),
			'DROP TABLE old query not found'
		);
	}

	/**
	 * Test Postgresql create_table with if_exists => update
	 */
	public function testPostgresqlCreateTableUpdate()
	{
		$db = $this->getMockBuilder(\stdClass::class)
			->addMethods(['query', 'list_tables', 'transaction', 'skip_next_error'])
			->getMock();

		$table = $this->getMockBuilder(PostgresqlTable::class)
			->setConstructorArgs([$db, 'elkarte_'])
			->onlyMethods(['list_columns'])
			->getMock();

		// Simulate table exists
		$db->expects($this->any())
			->method('list_tables')
			->willReturn(['elkarte_test_pg_addon']);

		// Old columns
		$table->expects($this->once())
			->method('list_columns')
			->with('elkarte_test_pg_addon_old')
			->willReturn(['id', 'col_a', 'col_removed']);

		$queries = [];
		$db->expects($this->any())
			->method('query')
			->willReturnCallback(function ($sub, $query, $params = []) use (&$queries) {
				$queries[] = trim($query);
				return true;
			});

		$columns = [
			[
				'name' => 'id',
				'type' => 'int',
				'auto' => true,
				'null' => false,
			],
			[
				'name' => 'col_a',
				'type' => 'varchar',
				'size' => 255,
				'null' => false,
			],
			[
				'name' => 'col_new',
				'type' => 'text',
				'null' => true,
			],
		];

		$indexes = [
			[
				'name' => 'primary',
				'type' => 'primary',
				'columns' => ['id'],
			],
		];

		$result = $table->create_table('{db_prefix}test_pg_addon', $columns, $indexes, [
			'if_exists' => 'update',
		]);

		$this->assertTrue($result);

		// Verify table was renamed to _old
		$this->assertTrue(
			array_reduce($queries, fn($carry, $q) => $carry || str_contains($q, 'ALTER TABLE "elkarte_test_pg_addon" RENAME TO "elkarte_test_pg_addon_old"'), false),
			'ALTER TABLE RENAME query not found'
		);

		// Verify sequence was recreated
		$this->assertTrue(
			array_reduce($queries, fn($carry, $q) => $carry || str_contains($q, 'CREATE SEQUENCE elkarte_test_pg_addon_seq'), false),
			'CREATE SEQUENCE query not found'
		);

		// Verify INSERT INTO ... SELECT ... with double quotes for PostgreSQL
		$this->assertTrue(
			array_reduce($queries, fn($carry, $q) => $carry || (str_contains($q, 'INSERT INTO elkarte_test_pg_addon ("id", "col_a")') && str_contains($q, 'SELECT "id", "col_a"') && str_contains($q, 'FROM elkarte_test_pg_addon_old')), false),
			'Data copy query with double quotes not found'
		);

		// Verify sequence sync with setval
		$this->assertTrue(
			array_reduce($queries, fn($carry, $q) => $carry || str_contains($q, 'setval(\'elkarte_test_pg_addon_seq\''), false),
			'Sequence setval sync query not found'
		);

		// Verify drop of old table
		$this->assertTrue(
			array_reduce($queries, fn($carry, $q) => $carry || str_contains($q, 'DROP TABLE elkarte_test_pg_addon_old'), false),
			'DROP TABLE old query not found'
		);
	}

	/**
	 * Test create_table with if_exists => update when table does not exist
	 */
	public function testCreateTableUpdateWhenTableDoesNotExist()
	{
		$db = $this->getMockBuilder(\stdClass::class)
			->addMethods(['query', 'list_tables', 'transaction', 'skip_next_error'])
			->getMock();

		$table = $this->getMockBuilder(MysqliTable::class)
			->setConstructorArgs([$db, 'elkarte_'])
			->onlyMethods(['list_columns'])
			->getMock();

		// Table does not exist
		$db->expects($this->any())
			->method('list_tables')
			->willReturn([]);

		// list_columns should NOT be called since table didn't exist
		$table->expects($this->never())
			->method('list_columns');

		$queries = [];
		$db->expects($this->any())
			->method('query')
			->willReturnCallback(function ($sub, $query, $params = []) use (&$queries) {
				$queries[] = trim($query);
				return true;
			});

		$columns = [
			[
				'name' => 'id',
				'type' => 'int',
				'size' => 10,
				'auto' => true,
				'null' => false,
			],
		];

		$result = $table->create_table('{db_prefix}new_table', $columns, [], [
			'if_exists' => 'update',
		]);

		$this->assertTrue($result);

		// Verify no RENAME or DROP old queries
		$this->assertFalse(
			array_reduce($queries, fn($carry, $q) => $carry || str_contains($q, 'RENAME TABLE'), false)
		);
		$this->assertFalse(
			array_reduce($queries, fn($carry, $q) => $carry || str_contains($q, '_old'), false)
		);
		// Verify CREATE TABLE ran
		$this->assertTrue(
			array_reduce($queries, fn($carry, $q) => $carry || str_starts_with($q, 'CREATE TABLE elkarte_new_table'), false)
		);
	}
}
