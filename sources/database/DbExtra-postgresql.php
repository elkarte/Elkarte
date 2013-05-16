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