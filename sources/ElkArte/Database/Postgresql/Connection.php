<?php

/**
 * This file has all the main functions in it that relate to the mysql database.
 *
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * @version 2.0 dev
 *
 */

namespace ElkArte\Database\Postgresql;

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
	public static function initiate($db_server, $db_name, $db_user, $db_passwd, $db_prefix, $db_options = array())
	{
		$db_port = empty($db_options['port']) ? '' : ' port=' . (int) $db_options['port'];

		if (!empty($db_options['persist']))
		{
			$connection = @pg_pconnect('host=' . $db_server . $db_port . ' dbname=' . $db_name . " user='" . $db_user . "' password='" . $db_passwd . "'");
		}
		else
		{
			$connection = @pg_connect('host=' . $db_server . $db_port . ' dbname=' . $db_name . " user='" . $db_user . "' password='" . $db_passwd . "'");
		}

		// Something's wrong, show an error if its fatal (which we assume it is)
		// If the connection fails more than once (e.g. wrong password) the exception
		// should be thrown only once.
		if (!$connection && !self::$failed_once)
		{
			self::$failed_once = true;
			throw new \RuntimeException('\\ElkArte\\Database\\Postgresql\\Connection::initiate');
		}

		return new Query($db_prefix, $connection);
	}
}
