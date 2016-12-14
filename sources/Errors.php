<?php

/**
 * The purpose of this file is... errors. (hard to guess, I guess?)  It takes
 * care of logging, error messages, error handling, database errors, and
 * error log administration.
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * This file contains code covered by:
 * copyright:	2011 Simple Machines (http://www.simplemachines.org)
 * license:		BSD, See included LICENSE.TXT for terms and conditions.
 *
 * @version 1.1 beta 3
 *
 */

/**
 * Log an error, if the error logging is enabled.
 *
 * - filename and line should be __FILE__ and __LINE__, respectively.
 *
 * Example use:
 *  die(log_error($msg));
 *
 * @param string $error_message
 * @param string|boolean $error_type = 'general'
 * @param string|null $file = null
 * @param int|null $line = null
 *
 * @deprecated since 1.1
 */
function log_error($error_message, $error_type = 'general', $file = null, $line = null)
{
	Errors::instance()->log_error($error_message, $error_type, $file, $line);
}

/**
 * Similar to log_error, it accepts a language index as error, takes care of
 * loading the forum default language and log the error (forwarding to log_error)
 *
 * @param string $error
 * @param string $error_type = 'general'
 * @param string|mixed[] $sprintf = array()
 * @param string|null $file = null
 * @param int|null $line = null
 *
 * @deprecated since 1.1
 */
function log_lang_error($error, $error_type = 'general', $sprintf = array(), $file = null, $line = null)
{
	Errors::instance()->log_lang_error($error, $error_type, $sprintf, $file, $line);
}

/**
 * An irrecoverable error. This function stops execution and displays an error message.
 * It logs the error message if $log is specified.
 *
 * @param string         $error
 * @param string|boolean $log defaults to  'general', use false to skip setup_fatal_error_context
 *
 * @throws Elk_Exception
 * @deprecated since 1.1
 */
function fatal_error($error, $log = 'general')
{
	throw new Elk_Exception($error, $log);
}

/**
 * Shows a fatal error with a message stored in the language file.
 *
 * What it does:
 * - This function stops execution and displays an error message by key.
 * - uses the string with the error_message_key key.
 * - logs the error in the forum's default language while displaying the error
 * message in the user's language.
 * - uses Errors language file and applies the $sprintf information if specified.
 * - the information is logged if log is specified.
 *
 * @param string         $error
 * @param string|boolean $log defaults to 'general' false will skip logging, true will use general
 * @param string[]       $sprintf defaults to empty array()
 *
 * @throws Elk_Exception
 * @deprecated since 1.1
 */
function fatal_lang_error($error, $log = 'general', $sprintf = array())
{
	throw new Elk_Exception($error, $log, $sprintf);
}

/**
 * Show a message for the (full block) maintenance mode.
 *
 * What it does:
 * - It shows a complete page independent of language files or themes.
 * - It is used only if $maintenance = 2 in Settings.php.
 * - It stops further execution of the script.
 *
 * @deprecated since 1.1
 */
function display_maintenance_message()
{
	Errors::instance()->display_maintenance_message();
}

/**
 * Show an error message for the connection problems.
 *
 * What it does:
 * - It shows a complete page independent of language files or themes.
 * - It is used only if there's no way to connect to the database.
 * - It stops further execution of the script.
 *
 * @deprecated since 1.1
 */
function display_db_error()
{
	Errors::instance()->display_db_error();
}

/**
 * Show an error message for load average blocking problems.
 *
 * What it does:
 * - It shows a complete page independent of language files or themes.
 * - It is used only if the load averages are too high to continue execution.
 * - It stops further execution of the script.
 *
 * @deprecated since 1.1
 */
function display_loadavg_error()
{
	Errors::instance()->display_loadavg_error();
}
