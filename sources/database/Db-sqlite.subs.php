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

	return $db->error_backtrace($error_message, $log_message, $error_type, $file, $line);
}

/**
 * Emulate UNIX_TIMESTAMP.
 */
function elk_udf_unix_timestamp()
{
	$db = database();

	return $db->udf_unix_timestamp();
}

/**
 * Emulate INET_ATON.
 *
 * @param $ip
 */
function elk_udf_inet_aton($ip)
{
	$db = database();

	return $db->udf_inet_aton($ip);
}

/**
 * Emulate INET_NTOA.
 *
 * @param $n
 */
function elk_udf_inet_ntoa($n)
{
	$db = database();

	return $db->udf_inet_ntoa($n);
}

/**
 * Emulate FIND_IN_SET.
 *
 * @param $find
 * @param $groups
 */
function elk_udf_find_in_set($find, $groups)
{
	$db = database();

	return $db->udf_find_in_set($find, $groups);
}

/**
 * Emulate YEAR.
 *
 * @param $date
 */
function elk_udf_year($date)
{
	$db = database();

	return $db->udf_year($date);
}

/**
 * Emulate MONTH.
 *
 * @param $date
 */
function elk_udf_month($date)
{
	$db = database();

	return $db->udf_month($date);
}

/**
 * Emulate DAYOFMONTH.
 *
 * @param $date
 */
function elk_udf_dayofmonth($date)
{
	$db = database();

	return $db->udf_dayofmonth($date);
}

/**
 * This function uses variable argument lists so that it can handle more then two parameters.
 * Emulates the CONCAT function.
 */
function elk_udf_concat()
{
	$db = database();

	return $db->udf_concat();
}

/**
 * We need to use PHP to locate the position in the string.
 *
 * @param string $find
 * @param string $string
 */
function elk_udf_locate($find, $string)
{
	$db = database();

	return $db->udf_locate($find, $string);
}

/**
 * This is used to replace RLIKE.
 *
 * @param string $exp
 * @param string $search
 */
function elk_udf_regexp($exp, $search)
{
	$db = database();

	return $db->udf_regexp($exp, $search);
}