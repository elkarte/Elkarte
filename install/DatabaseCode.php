<?php

/**
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

use ElkArte\Database\Mysqli\Table;
use ElkArte\Database\QueryInterface;

/**
 * Wrapper for database methods
 */
class DbWrapper
{
	/** @var $db QueryInterface */
	protected $db;

	/** @var $count_mode bool */
	protected $count_mode = false;

	/**
	 * Constructs a new instance of the class.
	 *
	 * @param mixed $db The database object or connection that will be used.
	 * @param array $replaces The array of replacement values to be used in the class.
	 *
	 * @return void
	 */
	public function __construct($db, protected $replaces)
	{
		$this->db = $db;
	}

	/**
	 * Magic method that allows calling inaccessible methods on an object.
	 *
	 * @param string $name The name of the method being called.
	 * @param array $args An array of arguments to be passed to the method.
	 *
	 * @return mixed The result of the method call.
	 */
	public function __call($name, $args)
	{
		return call_user_func_array([$this->db, $name], $args);
	}

	/**
	 * Inserts data into the database.
	 *
	 * This method can be used to insert data into the database using the specified database object or connection.
	 * The method supports multiple ways of passing the data to be inserted by accepting variable number of arguments as an array:
	 *   - The first argument must be the table name.
	 *   - The second argument must be an array of column names.
	 *   - The third argument must be an array of values to be inserted.
	 *   - Additional arguments may be accepted depending on the implementation of the "insert" method of the database object.
	 *
	 * @return int|void If the count mode is enabled, returns the number of rows to be inserted as an integer.
	 *                 Otherwise, returns the number of affected rows after the insertion as an integer.
	 */
	public function insert()
	{
		$args = func_get_args();

		if ($this->count_mode)
		{
			return count($args[3]);
		}

		foreach ($args[3] as $key => $data)
		{
			foreach ($data as $k => $v)
			{
				$args[3][$key][$k] = strtr($v, $this->replaces);
			}
		}

		$this->db->insert(...$args);

		return $this->db->affected_rows();
	}

	/**
	 * Sets the count mode for the class.
	 *
	 * @param bool $on Boolean value representing whether count mode is enabled or disabled. Default is true.
	 *
	 * @return void
	 */
	public function countMode($on = true)
	{
		$this->count_mode = (bool) $on;
	}
}

/**
 * Wrapper for database table functions
 */
class DbTableWrapper
{
	/** @var $db */
	protected $db;

	/**
	 * Set the db for the class
	 *
	 * @param  $db
	 */
	public function __construct($db)
	{
		$this->db = $db;
	}

	/**
	 * Magic method to handle dynamic method calls
	 *
	 * @param mixed $name The name of the method being called
	 * @param array $args The arguments passed to the method being called
	 * @return mixed The result of the method being called
	 */
	public function __call($name, $args)
	{
		return call_user_func_array([$this->db, $name], $args);
	}

	/**
	 * Create a table using the given arguments
	 *
	 * @return bool True on success, false on failure
	 */
	public function create_table()
	{
		$args = func_get_args();
		if (!isset($args[4]))
		{
			$args[4] = 'ignore';
		}

		// In this case errors are ignored, so the return is always true
		$this->db->create_table(...$args);

		return true;
	}

	/**
	 * Add a table index
	 *
	 * @return bool
	 */
	public function add_index()
	{
		$args = func_get_args();

		// In this case errors are ignored, so the return is always true
		$this->db->add_index(...$args);

		return true;
	}
}

if (class_exists(Table::class))
{
	/**
	 * MySQL specific implementation of the database table installer.
	 *
	 * @package ElkArte
	 * @subpackage Database
	 */
	class DbTable_MySQL_Install extends Table
	{
		public static $_tbl_inst;

		/**
		 * DbTable_MySQL::construct
		 *
		 * @param object $db - A Database_MySQL object
		 */
		public function __construct($db, $db_prefix)
		{
			// We are doing install, of course we want to do any remove on these
			$this->_reservedTables = [];

			// let's be sure.
			$this->_package_log = [];

			// This executes queries and things
			$this->_db = $db;
			$this->_db_prefix = $db_prefix;
		}

		/**
		 * During upgrade (and install at that point) we do unbuffered alter queries
		 * to let the script time out professionally.
		 *
		 * @param string $table_name
		 * @param string $statement
		 * @todo this is still broken. The idea is that if the query is
		 *       started and the index is not yet ready, SHOW FULL PROCESSLIST
		 *       should list if the query is still running and we should be able to
		 *       wait until the query is completed.
		 *       While this is true for MySQL it may not be for other DBMS, so for
		 *       there are a couple of options (like implement it for MySQL and hope
		 *       others will not break), but for the timebeing I'll leave it here broken.
		 *       See protected_alter in ToRefactrCode.php
		 */
		protected function _alter_table($table_name, $statement)
		{
			// First of all discover the type of statement we are dealing with.
			$type = $this->_find_alter_type($statement);
			switch ($type)
			{
				case 'add_index':
				case 'drop_index':
				case 'add_column':
				case 'drop_column':
					// Check if the column still exists
					break;
			}

			return $this->_db->query('', '
				ALTER TABLE ' . $table_name . '
				' . $statement,
				[
					'security_override' => true,
				]
			);
		}

		/**
		 * Determines the type of ALTER statement based on the given statement.
		 *
		 * @param string $statement The ALTER statement to analyze.
		 * @return string|false The type of ALTER statement or false if no match is found.
		 */
		protected function _find_alter_type($statement)
		{
			/** MySQL cases */
			// Adding a column is:   ADD `column_name`
			// Removing a column is: DROP COL UMN
			// Changing a column is: CHANGE C OLUMN
			// Adding indexes:
			//    - ADD PRIM ARY KEY
			//    - ADD UNIQ UE
			//    - ADD INDE X
			// Dropping indexes:
			//    - DROP PRI MARY KEY
			//    - DROP IND EX
			$short = substr(trim($statement), 0, 8);

			if (in_array($short, ['DROP COL', 'CHANGE C']))
			{
				return 'drop_column';
			}

			if (strpos($short, 'ADD `') === 0)
			{
				return 'add_column';
			}

			if (in_array($short, ['ADD PRIM', 'ADD UNIQ', 'ADD INDE']))
			{
				return 'add_index';
			}

			if (in_array($short, ['DROP PRI', 'DROP IND']))
			{
				return 'drop_index';
			}

			return false;
		}

		/**
		 * Static method that allows to retrieve or create an instance of this class.
		 *
		 * @param object $db - A Database_MySQL object
		 * @return object - A DbTable_MySQL_Install object
		 */
		public static function db_table($db, $db_prefix)
		{
			if (is_null(self::$_tbl_inst))
			{
				self::$_tbl_inst = new DbTable_MySQL_Install($db, $db_prefix);
			}

			return self::$_tbl_inst;
		}
	}

	class DbTable_MySQLi_Install extends DbTable_MySQL_Install
	{
	}
}

if (class_exists(\ElkArte\Database\Postgresql\Table::class))
{
	class DbTable_PostgreSQL_Install extends \ElkArte\Database\Postgresql\Table
	{
		public static $_tbl_inst;

		/**
		 * DbTable_PostgreSQL::construct
		 *
		 * @param object $db - A DbTable_PostgreSQL object
		 */
		public function __construct($db, $db_prefix)
		{
			// We are doing install, of course we want to do any remove on these
			$this->_reservedTables = [];

			// let's be sure.
			$this->_package_log = [];

			// This executes queries and things
			$this->_db = $db;
			$this->_db_prefix = $db_prefix;
		}

		/**
		 * During upgrade (and install at that point) we do unbuffered alter queries
		 * to let the script time out professionally.
		 *
		 * @param string $table_name
		 * @param string $statement
		 * @todo this is still broken. The idea is that if the query is
		 *       started and the index is not yet ready, SHOW FULL PROCESSLIST
		 *       should list if the query is still running and we should be able to
		 *       wait until the query is completed.
		 *       While this is true for MySQL it may not be for other DBMS, so for
		 *       there are a couple of options (like implement it for MySQL and hope
		 *       others will not break), but for the timebeing I'll leave it here broken.
		 *       See protected_alter in ToRefactrCode.php
		 *
		 */
		protected function _alter_table($table_name, $statement)
		{
			// First of all discover the type of statement we are dealing with.
			$type = $this->_find_alter_type($statement);
			switch ($type)
			{
				case 'add_index':
				case 'drop_index':
				case 'add_column':
				case 'drop_column':
					// Check if the column still exists
					break;
			}

			return $this->_db->query('', '
				ALTER TABLE ' . $table_name . '
				' . $statement,
				[
					'security_override' => true,
				]
			);
		}

		/**
		 * Discovers what type of data we are altering.
		 *
		 * @param string $statement
		 */
		protected function _find_alter_type($statement)
		{
			/** PostgreSQL cases */
			// Removing a column is: DROP COL UMN
			// Rename a column is:   RENAME C OLUMN
			// Altering a column is: ALTER CO LUMN
			// Adding a column is:   ADD COLU MN
			// Adding indexes:
			//   - ADD PRIM ARY KEY
			//   - CREATE
			//   - CREATE U NIQUE
			//   - CREATE I NDEX
			// Dropping indexes:
			//    - DROP CON STRAINT
			//    - DROP IND EX
			$short = substr(trim($statement), 0, 8);
			if (in_array($short, ['DROP COL', 'RENAME C']))
			{
				return 'drop_column';
			}

			if (in_array($short, ['ALTER CO', 'ADD COLU']))
			{
				return 'add_column';
			}

			if (in_array($short, ['ADD PRIM', 'CREATE U', 'CREATE I']) || substr($short, 0, 6) === 'CREATE ')
			{
				return 'add_index';
			}

			if (in_array($short, ['DROP CON', 'DROP IND']))
			{
				return 'drop_index';
			}

			return false;
		}

		/**
		 * Static method that allows to retrieve or create an instance of this class.
		 *
		 * @param object $db - A DbTable_PostgreSQL object
		 *
		 * @return object - A DbTable_PostgreSQL_Install object
		 */
		public static function db_table($db, $db_prefix)
		{
			if (is_null(self::$_tbl_inst))
			{
				self::$_tbl_inst = new DbTable_PostgreSQL_Install($db, $db_prefix);
			}

			return self::$_tbl_inst;
		}
	}
}

/**
 * Our custom error handler - does nothing but does stop public errors from XML!
 *
 * @param int $errno
 * @param string $errstr
 * @param string $errfile
 * @param string $errline
 */
function sql_error_handler($errno, $errstr, $errfile, $errline)
{
	global $support_js;

	if ($support_js)
	{
		return true;
	}

	echo 'Error: ' . $errstr . ' File: ' . $errfile . ' Line: ' . $errline;
}
