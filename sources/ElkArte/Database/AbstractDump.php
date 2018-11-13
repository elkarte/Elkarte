<?php

/**
 * This file provides an implementation of the most common functions needed
 * for the database drivers to work.
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * This file contains code covered by:
 * copyright:	2011 Simple Machines (http://www.simplemachines.org)
 * license:  	BSD, See included LICENSE.TXT for terms and conditions.
 *
 * @version 2.0 dev
 *
 */

namespace ElkArte\Database;

/**
 * Abstract database class, implements database to control functions
 */
abstract class AbstractDump
{
	/**
	 * Holds current instance of the class
	 *
	 * @var \ElkArte\Database\Mysqli\Query
	 */
	private static $_db = null;

	/**
	 * Initializes a database connection.
	 * It returns the connection, if successful.
	 *
	 * @param \ElkArte\Database\Mysqli\Query $db
	 */
	public function __construct($db)
	{
		$this->_db = $db;
	}

	/**
	 * Dumps the schema (CREATE) for a table.
	 *
	 * @param string $tableName - the table
	 *
	 * @return string - the CREATE statement as string
	 * @throws Elk_Exception
	 */
	abstract public function db_table_sql($tableName);

	/**
	 * This function lists all tables in the database.
	 * The listing could be filtered according to $filter.
	 *
	 * @param string|bool $db_name_str string holding the database name, or false, default false
	 * @param string|bool $filter string to filter by, or false, default false
	 *
	 * @return string[] an array of table names. (strings)
	 */
	abstract public function db_list_tables($db_name_str = false, $filter = false);

	/**
	 * Backup $table_name to $backup_table.
	 *
	 * @param string $table_name
	 * @param string $backup_table
	 *
	 * @return bool|mysqli_result - the request handle to the table creation query
	 * @throws Elk_Exception
	 */
	abstract public function db_backup_table($table_name, $backup_table);

	/**
	 * Gets all the necessary INSERTs for the table named table_name.
	 * It goes in 250 row segments.
	 *
	 * @param string $tableName - the table to create the inserts for.
	 * @param bool $new_table
	 *
	 * @return string the query to insert the data back in, or an empty string if the table was empty.
	 * @throws \ElkArte\Exceptions\Exception
	 */
	abstract public function insert_sql($tableName, $new_table = false);
}
