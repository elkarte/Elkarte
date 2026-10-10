<?php

namespace ElkArte\Database;

use ElkArte\Database\Mysqli\Connection as MysqliConnection;
use ElkArte\Database\Mysqli\Dump as MysqliDump;
use ElkArte\Database\Mysqli\Search as MysqliSearch;
use ElkArte\Database\Mysqli\Table as MysqliTable;
use ElkArte\Database\Postgresql\Connection as PostgresqlConnection;
use ElkArte\Database\Postgresql\Dump as PostgresqlDump;
use ElkArte\Database\Postgresql\Search as PostgresqlSearch;
use ElkArte\Database\Postgresql\Table as PostgresqlTable;
use PHPUnit\Framework\TestCase;
use Throwable;

class DatabaseConnectionFactoryTest extends TestCase
{
	protected $backupGlobalsExcludeList = ['user_info'];

	public function testResolveType(): void
	{
		$this->assertSame('mysqli', DatabaseConnectionFactory::resolveType('mysql'));
		$this->assertSame('mysqli', DatabaseConnectionFactory::resolveType('MYSQL'));
		$this->assertSame('mysqli', DatabaseConnectionFactory::resolveType('mysqli'));
		$this->assertSame('postgresql', DatabaseConnectionFactory::resolveType('postgresql'));
	}

	public function testResolveClass(): void
	{
		$factoryMysql = new DatabaseConnectionFactory([], 'mysql');
		$this->assertSame('\\' . MysqliConnection::class, $factoryMysql->resolveClass('Connection'));
		$this->assertSame('\\' . MysqliTable::class, $factoryMysql->resolveClass('Table'));
		$this->assertSame('\\' . MysqliSearch::class, $factoryMysql->resolveClass('Search'));
		$this->assertSame('\\' . MysqliDump::class, $factoryMysql->resolveClass('Dump'));

		$factoryPgsql = new DatabaseConnectionFactory([], 'postgresql');
		$this->assertSame('\\' . PostgresqlConnection::class, $factoryPgsql->resolveClass('Connection'));
		$this->assertSame('\\' . PostgresqlTable::class, $factoryPgsql->resolveClass('Table'));
	}

	public function testTableCreationAndCaching(): void
	{
		$dbMock = $this->createMock(QueryInterface::class);

		$factory = new DatabaseConnectionFactory(['prefix' => 'elk_'], 'mysqli');
		$table1 = $factory->getTable($dbMock);
		$this->assertInstanceOf(MysqliTable::class, $table1);

		$table2 = $factory->getTable($dbMock);
		$this->assertInstanceOf(MysqliTable::class, $table2);

		$tablePg = (new DatabaseConnectionFactory([], 'postgresql'))->createTable($dbMock, 'elk_');
		$this->assertInstanceOf(PostgresqlTable::class, $tablePg);
	}

	public function testSearchCreationAndCaching(): void
	{
		$dbMock = $this->createMock(QueryInterface::class);

		$factory = new DatabaseConnectionFactory([], 'mysqli');
		$search1 = $factory->getSearch($dbMock);
		$this->assertInstanceOf(MysqliSearch::class, $search1);

		$searchPg = (new DatabaseConnectionFactory([], 'postgresql'))->createSearch($dbMock);
		$this->assertInstanceOf(PostgresqlSearch::class, $searchPg);
	}

	public function testDumpCreationAndCaching(): void
	{
		$dbMock = $this->createMock(QueryInterface::class);
		$tableMock = $this->createMock(AbstractTable::class);

		$factory = new DatabaseConnectionFactory([], 'mysqli');
		$dump1 = $factory->getDump($dbMock, $tableMock);
		$this->assertInstanceOf(MysqliDump::class, $dump1);

		$dumpPg = (new DatabaseConnectionFactory([], 'postgresql'))->createDump($dbMock, $tableMock);
		$this->assertInstanceOf(PostgresqlDump::class, $dumpPg);
	}

	public function testNonFatalConnectionFailureThrows(): void
	{
		$this->expectException(Throwable::class);

		$factory = new DatabaseConnectionFactory([
			'server' => '127.0.0.1',
			'user' => 'invalid_user_99999',
			'password' => 'invalid_pass_99999',
			'name' => 'invalid_db_99999',
			'port' => 65534,
		], 'postgresql');

		$factory->getDatabase(false);
	}
}
