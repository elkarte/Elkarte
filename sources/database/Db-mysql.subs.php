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