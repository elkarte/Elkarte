<?php

/**
 * This file has all the main functions in it that relate to the mysql database.
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

namespace ElkArte\Database\Mysqli;

use ElkArte\Database\ConnectionInterface;

/**
 * SQL database class, implements database class to control mysql functions
 */
class Connection implements ConnectionInterface
{
	private static $failed_once = false;

	/**
	 * {@inheritDoc}
	 */
	public static function initiate($db_server, $db_name, $db_user, $db_passwd, $db_prefix, $db_options = [])
	{
		$db_port = (int) ($db_options['port'] ?? 0);
		$db_name = empty($db_options['select_db']) ? '' : $db_name;
		$db_server = (empty($db_options['persist']) ? '' : 'p:') . $db_server;

		try
		{
			$connection = mysqli_init();
			$connection->real_connect($db_server, $db_user, $db_passwd, $db_name, $db_port);

			$query = new Query($db_prefix, $connection);

			// This makes it possible to automatically change the sql_mode and autocommit if needed.
			if (!empty($db_options['mysql_set_mode']))
			{
				$query->query('', "SET sql_mode = '', AUTOCOMMIT = 1", []);
			}

			// Few databases still have not set UTF-8 as their default input charset
			$query->query('', 'SET NAMES UTF8', []);

			// PHP 8.1 default is to throw exceptions, this reverts it to the <=php8 semantics
			mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_INDEX & ~MYSQLI_REPORT_STRICT);
		}
		catch (\mysqli_sql_exception $e)
        {
	        // Something's wrong, show an error if its fatal (which we assume it is)
	        // If the connection fails more than once (e.g. wrong password) the exception
	        // should be thrown only once.
	        self::$failed_once = true;

	        throw new \RuntimeException('Db initialization failed ' . $e->getMessage());
        }

		return $query;
	}
}
