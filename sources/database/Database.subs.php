<?php

/**
 * This file has all the main functions in it that set up the database connection
 * and initializes the appropriate adapters.
 *
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * @version 2.0 Beta 1
 *
 */

use ElkArte\Database\AbstractDump;
use ElkArte\Database\AbstractSearch;
use ElkArte\Database\AbstractTable;
use ElkArte\Database\DatabaseConnectionFactory;
use ElkArte\Database\QueryInterface;

/**
 * Initialize database classes and connection.
 * Deprecated: Use DatabaseConfig instead.
 *
 * @param array $dbOptions
 * @param string $dbType
 *
 * @return QueryInterface
 */
function elk_db_initiate(array $dbOptions, string $dbType = 'mysqli'): QueryInterface
{
	return (new DatabaseConnectionFactory($dbOptions, $dbType))->getDatabase();
}

/**
 * Resolve the database type to the appropriate class name.
 *
 * @param string $type
 *
 * @return string
 */
function resolveType(string $type): string
{
	return DatabaseConnectionFactory::resolveType($type);
}

/**
 * Retrieve existing instance of the active database class.
 *
 * @param bool $fatal - Stop the execution or throw an \Exception
 * @param bool $force - Force the re-creation of the database instance.
 *
 * @return QueryInterface
 * @throws Exception if fatal is false
 */
function database($fatal = true, $force = false): QueryInterface
{
	global $db_instance;
	static $db = null;

	if ($db_instance !== null)
	{
		return $db_instance;
	}

	if ($db === null || $force === true)
	{
		global $db_persist, $db_server, $db_user, $db_passwd, $db_port;
		global $db_type, $db_name, $db_prefix, $mysql_set_mode;

		$db_options = [
			'persist' => $db_persist,
			'select_db' => true,
			'port' => (int) $db_port,
			'mysql_set_mode' => (bool) ($mysql_set_mode ?? false),
			'server' => $db_server,
			'name' => $db_name,
			'user' => $db_user,
			'password' => $db_passwd,
			'passwd' => $db_passwd,
			'prefix' => $db_prefix,
		];

		$factory = new DatabaseConnectionFactory($db_options, $db_type ?? 'mysqli');
		$db = $factory->getDatabase($fatal);
	}

	return $db;
}

/**
 * This function retrieves an existing instance of AbstractTable
 * and returns it.
 *
 * @param object|null $db - A database object (e.g. \ElkArte\Mysqli\Query)
 * @param bool $fatal - Stop the execution or throw an \Exception
 *
 * @return AbstractTable
 */
function db_table($db = null, $fatal = false): AbstractTable
{
	global $db_prefix, $db_type;
	static $db_table = null;

	if ($db_table === null)
	{
		if ($db === null)
		{
			$db = database();
		}

		$factory = new DatabaseConnectionFactory(['prefix' => $db_prefix], $db_type ?? 'mysqli');
		$db_table = $factory->getTable($db, $db_prefix, $fatal);
	}

	return $db_table;
}

/**
 * This function returns an instance of AbstractSearch,
 * specifically designed for database utilities related to search.
 *
 * @return AbstractSearch
 */
function db_search(): AbstractSearch
{
	global $db_type;
	static $db_search = null;

	if ($db_search === null)
	{
		$db = database();
		$factory = new DatabaseConnectionFactory([], $db_type ?? 'mysqli');
		$db_search = $factory->getSearch($db);
	}

	return $db_search;
}

/**
 * This function returns an instance of AbstractDump,
 * specifically designed for database utilities related to table dumps.
 *
 * @return AbstractDump
 */
function db_dump(): AbstractDump
{
	global $db_type;
	static $db_dump = null;

	if ($db_dump === null)
	{
		$db = database();
		$db_table = db_table($db);
		$factory = new DatabaseConnectionFactory([], $db_type ?? 'mysqli');
		$db_dump = $factory->getDump($db, $db_table);
	}

	return $db_dump;
}
