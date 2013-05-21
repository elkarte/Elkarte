<?php

/**
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 1.0 Alpha
 *
 * This file has backwards compatible functions for database layer.
 *
 */

if (!defined('ELKARTE'))
	die('No access...');

/**
 * Callback for preg_replace_callback on the query.
 * It allows to replace on the fly a few pre-defined strings, for convenience ('query_see_board', 'query_wanna_see_board'), with
 * their current values from $user_info.
 * In addition, it performs checks and sanitization on the values sent to the database.
 *
 * @param $matches
 */
function elk_db_replacement__callback($matches)
{
	$db = database();

	return $db->replacement__callback($matches);
}

/**
 * Just like the db_query, escape and quote a string, but not executing the query.
 *
 * @param string $db_string
 * @param array $db_values
 * @param resource $connection = null
 */
function elk_db_quote($db_string, $db_values, $connection = null)
{
	$db = database();

	return $db->quote($db_string, $db_values, $connection);
}

/**
 * Do a query.  Takes care of errors too.
 *
 * @param string $identifier
 * @param string $db_string
 * @param array $db_values = array()
 * @param resource $connection = null
 */
function elk_db_query($identifier, $db_string, $db_values = array(), $connection = null)
{
	$db = database();

	return $db->query($identifier, $db_string, $db_values, $connection);
}

/**
 * insert_id
 *
 * @param string $table
 * @param string $field = null
 * @param resource $connection = null
 */
function elk_db_insert_id($table, $field = null, $connection = null)
{
	$db = database();

	return $db->insert_id($table, $field, $connection);
}

/**
 * Database error!
 * Backtrace, log, try to fix.
 *
 * @param string $db_string
 * @param resource $connection = null
 */
function elk_db_error($db_string, $connection = null)
{
	$db = database();

	return $db->error($db_string, $connection);
}

/**
 * Get an associative array
 *
 * @param $request
 * @param $counter
 */
function elk_db_fetch_assoc($request, $counter = false)
{
	$db = database();

	return $db->fetch_assoc($request, $counter);
}

/**
 * insert
 *
 * @param string $method - options 'replace', 'ignore', 'insert'
 * @param $table
 * @param $columns
 * @param $data
 * @param $keys
 * @param bool $disable_trans = false
 * @param resource $connection = null
 */
function elk_db_insert($method = 'replace', $table, $columns, $data, $keys, $disable_trans = false, $connection = null)
{
	$db = database();

	return $db->insert($method, $table, $columns, $data, $keys, $disable_trans, $connection);
}

/**
 * This function tries to work out additional error information from a back trace.
 *
 * @param $error_message
 * @param $log_message
 * @param $error_type
 * @param $file
 * @param $line
 */
function elk_db_error_backtrace($error_message, $log_message = '', $error_type = false, $file = null, $line = null)
{
	$db = database();

	$db->error_backtrace($error_message, $log_message, $error_type, $file, $line);
}