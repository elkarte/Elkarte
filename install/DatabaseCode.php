<?php

/**
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

class DbWrapper
{
	protected $db = null;
	protected $count_mode = false;
	protected $replaces = array();

	public function __construct($db, $replaces)
	{
		$this->db = $db;
		$this->replaces = $replaces;
	}

	public function __call($name, $args)
	{
		return call_user_func_array(array($this->db, $name), $args);
	}

	public function insert()
	{
		$args = func_get_args();

		if ($this->count_mode)
			return count($args[3]);

		foreach ($args[3] as $key => $data)
		{
			foreach ($data as $k => $v)
			{
				$args[3][$key][$k] = strtr($v, $this->replaces);
			}
		}

		call_user_func_array(array($this->db, 'insert'), $args);

		return $this->db->affected_rows();
	}

	public function countMode($on = true)
	{
		$this->count_mode = (bool) $on;
	}
}

class DbTableWrapper
{
	protected $db = null;

	public function __construct($db)
	{
		$this->db = $db;
	}

	public function __call($name, $args)
	{
		return call_user_func_array(array($this->db, $name), $args);
	}

	public function db_create_table()
	{
		$args = func_get_args();
		if (!isset($args[4]))
		{
			$args[4] = 'ignore';
		}

		// In this case errors are ignored, so the return is always true
		call_user_func_array(array($this->db, 'db_create_table'), $args);

		return true;
	}
	public function db_add_index()
	{
		$args = func_get_args();

		// In this case errors are ignored, so the return is always true
		call_user_func_array(array($this->db, 'db_add_index'), $args);

		return true;
	}
}

if (class_exists('DbTable_MySQL'))
{
	class DbTable_MySQL_Install extends DbTable_MySQL
	{
		public static $_tbl_inst = null;

		/**
		* DbTable_MySQL::construct
		*
		* @param object $db - A Database_MySQL object
		*/
		private function __construct($db)
		{
			// We are doing install, of course we want to do any remove on these
			$this->_reservedTables = array();

			// let's be sure.
			$this->_package_log = array();

			// This executes queries and things
			$this->_db = $db;
		}

		/**
		* During upgrade (and install at that point) we do unbuffered alter queries
		* to let the script time out professionally.
		*
		* @todo this is still broken. The idea is that if the query is
		*       started and the index is not yet ready, SHOW FULL PROCESSLIST
		*       should list if the query is still running and we should be able to
		*       wait until the query is completed.
		*       While this is true for MySQL it may not be for other DBMS, so for
		*       there are a couple of options (like implement it for MySQL and hope
		*       others will not break), but for the timebeing I'll leave it here broken.
		*       See protected_alter in ToRefactrCode.php
		*
		* @param string $table_name
		* @param string $statement
		*/
		protected function _alter_table($table_name, $statement)
		{
			// First of all discover the type of statement we are dealing with.
			$type = $this->_find_alter_type($statement);
			switch ($type)
			{
				case 'add_index':
					// Check if the index exists
					break;
				case 'drop_index':
					// Check if the index still exists
					break;
				case 'add_column':
					// Check if the column exists
					break;
				case 'drop_column':
					// Check if the column still exists
					break;
			}
			return $this->_db->query('', '
				ALTER TABLE ' . $table_name . '
				' . $statement,
				array(
					'security_override' => true,
				)
			);
		}

		/**
		* Discovers what type of data we are altering.
		*
		* @param string $statement
		*/
		protected function _find_alter_type($statement)
		{
			/** MySQL cases */
			// Adding a column is:
			//    ADD `column_name`
			// Removing a column is:
			//    DROP COL UMN
			// Changing a column is:
			//    CHANGE C OLUMN
			// Adding indexes:
			//    ADD PRIM ARY KEY
			//    ADD UNIQ UE
			//    ADD INDE X
			// Dropping indexes:
			//    DROP PRI MARY KEY
			//    DROP IND EX
			$short = substr(trim($statement), 0, 8);

			if (in_array($short, array('DROP COL', 'CHANGE C')))
			{
				return 'drop_column';
			}

			elseif (substr($short, 0, 5) === 'ADD `')
			{
				return 'add_column';
			}
			elseif (in_array($short, array('ADD PRIM', 'ADD UNIQ', 'ADD INDE')))
			{
				return 'add_index';
			}
			elseif (in_array($short, array('DROP PRI', 'DROP IND')))
			{
				return 'drop_index';
			}
			else
			{
				return false;
			}
		}

		/**
		* Static method that allows to retrieve or create an instance of this class.
		*
		* @param object $db - A Database_MySQL object
		* @return object - A DbTable_MySQL_Install object
		*/
		public static function db_table($db)
		{
			if (is_null(self::$_tbl_inst))
			{
				self::$_tbl_inst = new DbTable_MySQL_Install($db);
			}

			return self::$_tbl_inst;
		}
	}
}

if (class_exists('DbTable_PostgreSQL'))
{
	class DbTable_PostgreSQL_Install extends DbTable_PostgreSQL
	{
		public static $_tbl_inst = null;

		/**
		* DbTable_PostgreSQL::construct
		*
		* @param object $db - A DbTable_PostgreSQL object
		*/
		private function __construct($db)
		{
			// We are doing install, of course we want to do any remove on these
			$this->_reservedTables = array();

			// let's be sure.
			$this->_package_log = array();

			// This executes queries and things
			$this->_db = $db;
		}

		/**
		* During upgrade (and install at that point) we do unbuffered alter queries
		* to let the script time out professionally.
		*
		* @todo this is still broken. The idea is that if the query is
		*       started and the index is not yet ready, SHOW FULL PROCESSLIST
		*       should list if the query is still running and we should be able to
		*       wait until the query is completed.
		*       While this is true for MySQL it may not be for other DBMS, so for
		*       there are a couple of options (like implement it for MySQL and hope
		*       others will not break), but for the timebeing I'll leave it here broken.
		*       See protected_alter in ToRefactrCode.php
		*
		* @param string $table_name
		* @param string $statement
		*/
		protected function _alter_table($table_name, $statement)
		{
			// First of all discover the type of statement we are dealing with.
			$type = $this->_find_alter_type($statement);
			switch ($type)
			{
				case 'add_index':
					// Check if the index exists
					break;
				case 'drop_index':
					// Check if the index still exists
					break;
				case 'add_column':
					// Check if the column exists
					break;
				case 'drop_column':
					// Check if the column still exists
					break;
			}
			return $this->_db->query('', '
				ALTER TABLE ' . $table_name . '
				' . $statement,
				array(
					'security_override' => true,
				)
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
			// Removing a column is:
			//    DROP COL UMN
			// Rename a column is:
			//    RENAME C OLUMN
			// Altering a column is:
			//    ALTER CO LUMN
			// Adding a column is:
			//    ADD COLU MN
			// Adding indexes:
			//    ADD PRIM ARY KEY
			//    CREATE
			//    CREATE U NIQUE
			//    CREATE I NDEX
			// Dropping indexes:
			//    DROP CON STRAINT
			//    DROP IND EX
			$short = substr(trim($statement), 0, 8);
			if (in_array($short, array('DROP COL', 'RENAME C')))
			{
				return 'drop_column';
			}

			if (in_array($short, array('ALTER CO', 'ADD COLU')))
			{
				return 'add_column';
			}
			elseif (in_array($short, array('ADD PRIM', 'CREATE U', 'CREATE I')) || substr($short, 0, 6) === 'CREATE ')
			{
				return 'add_index';
			}
			elseif (in_array($short, array('DROP CON', 'DROP IND')))
			{
				return 'drop_index';
			}
			else
			{
				return false;
			}
		}

		/**
		* Static method that allows to retrieve or create an instance of this class.
		*
		* @param object $db - A DbTable_PostgreSQL object
		 *
		* @return object - A DbTable_PostgreSQL_Install object
		*/
		public static function db_table($db)
		{
			if (is_null(self::$_tbl_inst))
			{
				self::$_tbl_inst = new DbTable_PostgreSQL_Install($db);
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
	else
	{
		echo 'Error: ' . $errstr . ' File: ' . $errfile . ' Line: ' . $errline;
	}
}