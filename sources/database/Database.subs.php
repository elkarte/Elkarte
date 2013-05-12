<?php

/**
 *  Initialize database classes and connection.
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
	global $smcFunc, $mysql_set_mode, $db;

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
	global $db_type;

	require_once(SOURCEDIR . '/database/Db' . strtoupper($type[0]) . substr($type, 1) . '-' . $db_type . '.php');
	$db_extend_class = 'Db' . strtoupper($type[0]) . substr($type, 1) . '_' . $db_type;
	$db_extend = new $db_extend_class();
	$db_extend->initialize();
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