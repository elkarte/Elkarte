<?php

/**
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 1.0 Alpha
 *
 * This file has all the main functions in it that set up the database connection
 * and initializes the appropriate adapters.
 *
 */

/**
 * Initialize database classes and connection.
 *
 * @param string $db_server
 * @param string $db_name
 * @param string $db_user
 * @param string $db_passwd
 * @param string $db_prefix
 * @param array $db_options
 * @return null
 */
function elk_db_initiate($db_server, $db_name, $db_user, $db_passwd, $db_prefix, $db_options = array(), $db_type = 'mysql')
{
	global $mysql_set_mode;

	require_once(SOURCEDIR . '/database/Db.php');
	require_once(SOURCEDIR . '/database/Db-' . $db_type . '.subs.php');
	require_once(SOURCEDIR . '/database/Db-' . $db_type . '.class.php');

	// quick 'n dirty initialization of the right database class.
	if ($db_type == 'mysql')
		return Database_MySQL::initiate($db_server, $db_name, $db_user, $db_passwd, $db_prefix, $db_options);
	elseif ($db_type == 'postgresql')
		return Database_PostgreSQL::initiate($db_server, $db_name, $db_user, $db_passwd, $db_prefix, $db_options);
	elseif ($db_type == 'sqlite')
		return Database_SQLite::initiate($db_server, $db_name, $db_user, $db_passwd, $db_prefix, $db_options);
}

/**
 * Extend the database functionality.
 *
 * @param string $type = 'extra'
 */
function db_extend ($type = 'extra')
{
	// this can be removed.
}

/**
 * Retrieve existing instance of the active database class.
 *
 * @return Database
 */
function database()
{
	global $db_type;

	// quick 'n dirty retrieval
	if ($db_type == 'mysql')
		$db = Database_MySQL::db();
	elseif ($db_type == 'postgresql')
		$db = Database_PostgreSQL::db();
	elseif ($db_type == 'sqlite')
		$db = Database_SQLite::db();

	return $db;
}

/**
 * This function retrieves an existing instance of DbTable
 * and returns it.
 *
 * @return DbTable
 */
function db_table()
{
	global $db_type;

	require_once(SOURCEDIR . '/database/DbTable.class.php');
	require_once(SOURCEDIR . '/database/DbTable-' . $db_type . '.php');

	$tbl = null;

	// quick 'n dirty retrieval
	if ($db_type == 'mysql')
		$tbl = DbTable_MySQL::db_table();
	elseif ($db_type == 'postgresql')
		$tbl = DbTable_PostgreSQL::db_table();
	elseif ($db_type == 'sqlite')
		$tbl = DbTable_SQLite::db_table();

	return $tbl;
}

/**
 * This function returns an instance of DbSearch,
 * specifically designed for database utilities related to search.
 *
 * @return DbSearch
 *
 */
function db_search()
{
	global $db_type;

	require_once(SOURCEDIR . '/database/DbSearch.php');
	require_once(SOURCEDIR . '/database/DbSearch-' . $db_type . '.php');

	$db_search = null;

	// quick 'n dirty retrieval
	if ($db_type == 'mysql')
		$db_search = DbSearch_MySQL::db_search();
	elseif ($db_type == 'postgresql')
		$db_search = DbSearch_PostgreSQL::db_search();
	elseif ($db_type == 'sqlite')
		$db_search = DbSearch_SQLite::db_search();

	return $db_search;
}