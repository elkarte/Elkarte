<?php

/**
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * This software is a derived product, based on:
 *
 * Simple Machines Forum (SMF)
 * copyright:	2011 Simple Machines (http://www.simplemachines.org)
 * license:  	BSD, See included LICENSE.TXT for terms and conditions.
 *
 * @version 1.0 Alpha
 *
 * This file has all the main functions in it that relate to the database.
 *
 */

if (!defined('ELKARTE'))
	die('No access...');

/**
 *  Maps the implementations in this file (smf_db_function_name)
 *  to the $smcFunc['db_function_name'] variable.
 *
 * @param string $db_server
 * @param string $db_name
 * @param string $db_user
 * @param string $db_passwd
 * @param string $db_prefix
 * @param array $db_options
 * @return null
 */
function elk_db_initiate($db_server, $db_name, $db_user, $db_passwd, $db_prefix, $db_options = array())
{
	global $smcFunc, $mysql_set_mode, $db;

	// Map some database specific functions, only do this once.
	if (!isset($smcFunc['db_fetch_assoc']) || $smcFunc['db_fetch_assoc'] != 'mysql_fetch_assoc')
		$smcFunc += array(
			'db_query' => 'smf_db_query',
			'db_quote' => 'smf_db_quote',
			'db_fetch_assoc' => 'mysql_fetch_assoc',
			'db_fetch_row' => 'mysql_fetch_row',
			'db_free_result' => 'mysql_free_result',
			'db_insert' => 'smf_db_insert',
			'db_insert_id' => 'smf_db_insert_id',
			'db_num_rows' => 'mysql_num_rows',
			'db_data_seek' => 'mysql_data_seek',
			'db_num_fields' => 'mysql_num_fields',
			'db_escape_string' => 'addslashes',
			'db_unescape_string' => 'stripslashes',
			'db_server_info' => 'mysql_get_server_info',
			'db_affected_rows' => 'smf_db_affected_rows',
			'db_transaction' => 'smf_db_transaction',
			'db_error' => 'mysql_error',
			'db_select_db' => 'mysql_select_db',
			'db_title' => 'MySQL',
			'db_sybase' => false,
			'db_case_sensitive' => false,
			'db_escape_wildcard_string' => 'smf_db_escape_wildcard_string',
		);

	if (!empty($db_options['persist']))
		$connection = @mysql_pconnect($db_server, $db_user, $db_passwd);
	else
		$connection = @mysql_connect($db_server, $db_user, $db_passwd);

	// Something's wrong, show an error if its fatal (which we assume it is)
	if (!$connection)
	{
		if (!empty($db_options['non_fatal']))
			return null;
		else
			display_db_error();
	}

	// Select the database, unless told not to
	if (empty($db_options['dont_select_db']) && !@mysql_select_db($db_name, $connection) && empty($db_options['non_fatal']))
		display_db_error();

	// This makes it possible to have ELKARTE automatically change the sql_mode and autocommit if needed.
	if (isset($mysql_set_mode) && $mysql_set_mode === true)
		$smcFunc['db_query']('', 'SET sql_mode = \'\', AUTOCOMMIT = 1',
		array(),
		false
	);

	require_once(SOURCEDIR . '/database/Db-mysql.class.php');
	$db = new Database_MySQL();

	return $connection;
}

/**
 * Fix up the prefix so it doesn't require the database to be selected.
 *
 * @param string &db_prefix
 * @param string $db_name
 */
function db_fix_prefix(&$db_prefix, $db_name)
{
	global $db;

	$db->db_fix_prefix(&$db_prefix, $db_name);
}

/**
 * Callback for preg_replace_callback on the query.
 * It allows to replace on the fly a few pre-defined strings, for convenience ('query_see_board', 'query_wanna_see_board'), with
 * their current values from $user_info.
 * In addition, it performs checks and sanitization on the values sent to the database.
 *
 * @param $matches
 */
function smf_db_replacement__callback($matches)
{
	global $db;

	return $db->smf_db_replacement_callback($matches);
}

/**
 * Just like the db_query, escape and quote a string, but not executing the query.
 *
 * @param string $db_string
 * @param array $db_values
 * @param resource $connection = null
 */
function smf_db_quote($db_string, $db_values, $connection = null)
{
	global $db;

	return $db->smf_db_quote($db_string, $db_values, $connection);
}

/**
 * Do a query.  Takes care of errors too.
 *
 * @param string $identifier
 * @param string $db_string
 * @param array $db_values = array()
 * @param resource $connection = null
 */
function smf_db_query($identifier, $db_string, $db_values = array(), $connection = null)
{
	global $db;

	return $db->smf_db_query($identifier, $db_string, $db_values, $connection);
}

/**
 * affected_rows
 * @param resource $connection
 */
function smf_db_affected_rows($connection = null)
{
	global $db;

	return $db->smf_db_affected_rows($connection);
}

/**
 * insert_id
 *
 * @param string $table
 * @param string $field = null
 * @param resource $connection = null
 */
function smf_db_insert_id($table, $field = null, $connection = null)
{
	global $db;

	return $db->smf_db_insert_id($table, $field, $connection);
}

/**
 * Do a transaction.
 *
 * @param string $type - the step to perform (i.e. 'begin', 'commit', 'rollback')
 * @param resource $connection = null
 */
function smf_db_transaction($type = 'commit', $connection = null)
{
	global $db;

	return $db->smf_db_transaction($type, $connection);
}

/**
 * Database error!
 * Backtrace, log, try to fix.
 *
 * @param string $db_string
 * @param resource $connection = null
 */
function smf_db_error($db_string, $connection = null)
{
	global $db;

	return $db->smf_db_error($db_string, $connection);
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
function smf_db_insert($method = 'replace', $table, $columns, $data, $keys, $disable_trans = false, $connection = null)
{
	global $db;

	return $db->smf_db_insert($method, $table, $columns, $data, $key, $disable_trans, $connection);
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
function smf_db_error_backtrace($error_message, $log_message = '', $error_type = false, $file = null, $line = null)
{
	global $db;

	$db->smf_db_error_backtrace($error_message, $log_message, $error_type, $file, $line);
}

/**
 * Escape the LIKE wildcards so that they match the character and not the wildcard.
 *
 * @param $string
 * @param bool $translate_human_wildcards = false, if true, turns human readable wildcards into SQL wildcards.
 */
function smf_db_escape_wildcard_string($string, $translate_human_wildcards=false)
{
	global $db;

	return $db->smf_db_escape_wildcard_string($string, $translate_human_wildcards);
}