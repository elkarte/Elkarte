<?php

/**
 * This file has all the main functions in it that relate to the mysql database.
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * This file contains code covered by:
 * copyright:	2011 Simple Machines (http://www.simplemachines.org)
 * license:  	BSD, See included LICENSE.TXT for terms and conditions.
 *
 * copyright:	2004-2011, GreyWyvern - All rights reserved.
 * license:  	BSD, See included LICENSE.TXT for terms and conditions.
 *
 * @version 2.0 dev
 *
 */

// Let's define the name of the class so that we will be able to use it in the instantiations
if (!defined('DB_TYPE'))
	define('DB_TYPE', 'DummyPg');

/**
 * SQL database class, implements database class to control mysql functions
 */
class Database_DummyPg extends Database_PostgreSQL
{
	/**
	 * Holds current instance of the class
	 *
	 * @var Database_DummyPg
	 */
	private static $_db = null;

	/**
	 * does nothing
	 */
	public static function initiate($db_server, $db_name, $db_user, $db_passwd, $db_prefix, $db_options = array())
	{
		return $this;
	}
}
