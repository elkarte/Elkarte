<?php

/**
 * This file has a single job - database backup.
 *
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * This file contains code covered by:
 * copyright: 2011 Simple Machines (http://www.simplemachines.org)
 *
 * @version 2.0 dev
 *
 */

use ElkArte\Util;

/**
 * Dumps the database.
 *
 * What it does:
 *
 * - It writes all of the database to standard output.
 * - It uses gzip compression if compress is set in the URL/post data.
 * - It may possibly time out, and mess up badly if you were relying on it. :P
 * - The data dumped depends on whether "struct" and "data" are passed.
 * - It is called from ManageMaintenance.controller.php.
 */
function DumpDatabase2()
{
	global $db_name, $scripturl, $modSettings, $db_prefix, $db_show_debug;

	// We'll need a db to dump :P
	$database = database();

	// We don't need debug when dumping the database
	$modSettings['disableQueryCheck'] = true;
	$db_show_debug = false;

	// You can't dump nothing!
	if (!isset($_REQUEST['struct']) && !isset($_REQUEST['data']))
	{
		$_REQUEST['data'] = true;
	}

	// Attempt to stop from dying...
	detectServer()->setTimeLimit(600);
	$time_limit = ini_get('max_execution_time');
	$start_time = time();

	// @todo ... fail on not getting the requested memory?
	detectServer()->setMemoryLimit('256M');
	$memory_limit = memoryReturnBytes(ini_get('memory_limit')) / 4;
	$current_used_memory = 0;
	$db_backup = '';
	$output_function = 'un_compressed';
	$headers = \ElkArte\Http\Headers::instance();

	@ob_end_clean();

	// Start saving the output... (don't do it otherwise for memory reasons.)
	if (isset($_REQUEST['compress']) && function_exists('gzencode'))
	{
		$output_function = 'gzencode';

		// Send faked headers so it will just save the compressed output as a gzip.
		$headers
			->removeHeader('all')
			->contentType('application/x-gzip', '')
			->header('Accept-Ranges', 'bytes');

		// The file extension will include .gz...
		$extension = '.sql.gz';
	}
	else
	{
		// Get rid of the gzipping already being done.
		if (!empty($modSettings['enableCompressedOutput']))
		{
			@ob_end_clean();
		}
		// If we can, clean anything already sent from the output buffer...
		elseif (ob_get_length() != 0)
		{
			ob_clean();
		}

		// Tell the client to save this file, even though it's text.
		$headers
			->removeHeader('all')
			->contentType('application/octet-stream', '');

		// This time the extension should just be .sql.
		$extension = '.sql';
	}

	// This should turn off the session URL parser.
	$scripturl = '';

	// Send the proper headers to let them download this file.
	$filename = $db_name . '-' . (empty($_REQUEST['struct']) ? 'data' : (empty($_REQUEST['data']) ? 'structure' : 'complete')) . '_' . strftime('%Y-%m-%d') . $extension . '"';

	$headers
		->header('Content-Disposition', 'attachment; filename="' . $filename . '"')
		->header('Cache-Control', 'private')
		->header('Connection', 'close')
		->sendHeaders();

	// This makes things simpler when using it so very very often.
	$crlf = "\r\n";

	// SQL Dump Header.
	$db_chunks =
		'-- ==========================================================' . $crlf .
		'--' . $crlf .
		'-- Database dump of tables in `' . $db_name . '`' . $crlf .
		'-- ' . standardTime(time(), false) . $crlf .
		'--' . $crlf .
		'-- ==========================================================' . $crlf .
		$crlf;

	// Get all tables in the database....for our installation
	$real_prefix = preg_match('~^(`?)(.+?)\\1\\.(.*?)$~', $db_prefix, $match) === 1 ? $match[3] : $db_prefix;
	$tables = $database->list_tables(false, $real_prefix . '%');

	// Dump each table.
	foreach ($tables as $tableName)
	{
		// Are we dumping the structures?
		if (isset($_REQUEST['struct']))
		{
			$db_chunks .=
				$crlf .
				'--' . $crlf .
				'-- Table structure for table `' . $tableName . '`' . $crlf .
				'--' . $crlf .
				$crlf .
				$database->table_sql($tableName) . ';' . $crlf;
		}
		// This is needed to speedup things later
		else
		{
			$database->table_sql($tableName);
		}

		// How about the data?
		if (!isset($_REQUEST['data']) || substr($tableName, -10) === 'log_errors')
		{
			continue;
		}

		$first_round = true;
		$close_table = false;

		// Are there any rows in this table?
		while (($get_rows = $database->insert_sql($tableName, $first_round)))
		{
			if (empty($get_rows))
			{
				break;
			}

			// Time is what we need here!
			if (function_exists('apache_reset_timeout'))
			{
				@apache_reset_timeout();
			}
			elseif (!empty($time_limit) && (((int) $start_time + (int) $time_limit - 20) > time()))
			{
				$start_time = time();
				detectServer()->setTimeLimit(150);
			}

			// for the first pass, start the output with a custom line...
			if ($first_round)
			{
				$db_chunks .=
					$crlf .
					'--' . $crlf .
					'-- Dumping data in `' . $tableName . '`' . $crlf .
					'--' . $crlf .
					$crlf;
				$first_round = false;
			}
			$db_chunks .= $get_rows;
			$current_used_memory += Util::strlen($db_chunks);

			$db_backup .= $db_chunks;
			unset($db_chunks);
			$db_chunks = '';
			if ($current_used_memory > $memory_limit)
			{
				echo $output_function($db_backup);
				$current_used_memory = 0;

				// This is probably redundant
				unset($db_backup);
				$db_backup = '';
			}
			$close_table = true;
		}

		// No rows to get - skip it.
		if ($close_table)
		{
			$db_backup .=
				'-- --------------------------------------------------------' . $crlf;
		}
	}

	// write the last line
	$db_backup .= $crlf . '-- Done' . $crlf;

	echo $output_function($db_backup);

	exit;
}

/**
 * Dummy/helper function, it simply returns the string passed as argument
 *
 * @param string $string - string to uncompress
 *
 * @return string
 */
function un_compressed($string = '')
{
	return $string;
}
