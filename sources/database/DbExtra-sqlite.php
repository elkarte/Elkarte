<?php

/**
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 1.0 Alpha
 *
 * This file contains rarely used extended database functionality.
 *
 */

if (!defined('ELKARTE'))
	die('No access...');

/**
 * Add the functions implemented in this file to the $smcFunc array.
 */
function db_extra_init()
{
	global $smcFunc;

	if (!isset($smcFunc['db_backup_table']) || $smcFunc['db_backup_table'] != 'elk_db_backup_table')
		$smcFunc += array(
			'db_backup_table' => 'elk_db_backup_table',
			'db_optimize_table' => 'elk_db_optimize_table',
			'db_insert_sql' => 'elk_db_insert_sql',
			'db_table_sql' => 'elk_db_table_sql',
			'db_list_tables' => 'elk_db_list_tables',
			'db_get_backup' => 'elk_db_get_backup',
			'db_get_version' => 'elk_db_get_version',
		);
}

/**
 * Backup $table to $backup_table.
 *
 * @param string $table
 * @param string $backup_table
 * @return resource -the request handle to the table creation query
 */
function elk_db_backup_table($table, $backup_table)
{
	$db = database();

	return $db->db_backup_table($table, $backup_table);
}

/**
 * This function optimizes a table.
 *
 * @param string $table - the table to be optimized
 * @return how much it was gained
 */
function elk_db_optimize_table($table)
{
	$db = database();

	return $db->db_optimize_table($table);
}

/**
 * This function lists all tables in the database.
 * The listing could be filtered according to $filter.
 *
 * @param mixed $db_name_str string holding the database name, or false, default false
 * @param mixed $filter string to filter by, or false, default false
 * @return array an array of table names. (strings)
 */
function elk_db_list_tables($db_name_str = false, $filter = false)
{
	$db = database();

	return $db->db_list_tables($$db_name_str, $filter);
}

/**
 * Gets all the necessary INSERTs for the table named table_name.
 * It goes in 250 row segments.
 *
 * @param string $tableName - the table to create the inserts for.
 * @param bool new_table
 * @return string the query to insert the data back in, or an empty string if the table was empty.
 */
function elk_db_insert_sql($tableName, $new_table = false)
{
	$db = database();

	return $db->insert_sql($tableName, $new_table);
}

/**
 * Dumps the schema (CREATE) for a table.
 *
 * @param string $tableName - the table
 * @return string - the CREATE statement as string
 */
function elk_db_table_sql($tableName)
{
	$db = database();

	return $db->db_table_sql($tableName);
}

/**
 *  Get the version number.
 *  @return string - the version
 */
function elk_db_get_version()
{
	$db = database();

	return $db->db_get_version();
}

/**
 * Simply return the database - and die!
 * Used by DumpDatabase.php.
 */
function elk_db_get_backup()
{
	$db = database();

	$db->db_get_backup();
}