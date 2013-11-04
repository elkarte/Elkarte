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

if (!defined('ELK'))
	die('No access...');

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

	$db->error_backtrace($error_message, $log_message, $error_type, $file, $line);
}