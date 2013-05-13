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
	global $smcFunc, $mysql_set_mode;

	require_once(SOURCEDIR . '/database/Db.class.php');
	require_once(SOURCEDIR . '/database/Db-' . $db_type . '.subs.php');
	require_once(SOURCEDIR . '/database/Db-' . $db_type . '.class.php');

	// quick 'n dirty initialization of the right database class.
	if (strtolower($db_type) === 'mysql')
		$db = new Database_MySQL();
	elseif (strtolower($db_type) === 'postgresql')
		$db = new Database_PostgreSQL();
	elseif (strtolower($db_type) === 'sqlite')
		$db = new Database_SQLite();

	return $db->initiate($db_server, $db_name, $db_user, $db_passwd, $db_prefix, $db_options = array());
}

/**
 * Extend the database functionality. It calls the respective class initialization method.
 *
 * @param string $type = 'extra'
 */
function db_extend ($type = 'extra')
{
	global $db_type, $db_search, $db_packages, $db_extra;

	require_once(SOURCEDIR . '/database/Db' . strtoupper($type[0]) . substr($type, 1) . '-' . $db_type . '.php');

	// $type = 'search' is now handled by classes
	if ($type == 'search')
	{
		// Bah. Quick 'n dirty :P
		if ($db_type == 'mysql')
			$db_search = new DbSearch_MySQL();
		elseif ($db_type == 'postgresql')
			$db_search = new DbSearch_PostgreSQL();
		elseif ($db_type == 'sqlite')
			$db_search = new DbSearch_SQLite();

		$db_search->initialize();
	}
	elseif ($type == 'extra')
	{
		// Nothing... :P
	}
	else
	{
		// packages... make sure the compatibility initialization
		// (of $smcFunc) is called at least once.
		db_table();
	}
}

/**
 * Retrieve existing instance of the active database class
 */
function database()
{
	global $db_type;

	// quick 'n dirty retrieval
	if (strtolower($db_type) === 'mysql')
		$db = Database_MySQL::db();
	elseif (strtolower($db_type) === 'postgresql')
		$db = Database_PostgreSQL::db();
	elseif (strtolower($db_type) === 'sqlite')
		$db = Database_SQLite::db();

	return $db;
}

function db_table()
{
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