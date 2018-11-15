<?php

/**
 * Handles the postgresql actions for travis-ci
 *
 * Called by setup-elkarte.sh as part of the install: directive in .travis.yml
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 2.0 dev
 *
 */

/**
 * Sets up a Database_PostgreSQL object
 */
class DbTable_PostgreSQL_Install extends \ElkArte\Database\Postgresql\Table
{
	public static $_tbl_inst = null;

	/**
	 * DbTable_PostgreSQL::construct
	 *
	 * @param object $db - A Database_PostgreSQL object
	 */
	public function __construct($db, $db_prefix)
	{
		global $db_prefix;

		// We are doing install, of course we want to do any remove on these
		$this->_reservedTables = array();

		foreach ($this->_reservedTables as $k => $table_name)
		{
			$this->_reservedTables[$k] = strtolower($db_prefix . $table_name);
		}

		// let's be sure.
		$this->_package_log = array();

		// This executes queries and things
		$this->_db = $db;
		$this->_db_prefix = $db_prefix;
	}

	/**
	 * Static method that allows to retrieve or create an instance of this class.
	 *
	 * @param object $db - A Database_PostgreSQL object
	 * @return object - A DbTable_PostgreSQL object
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

/**
 * Extend Elk_Testing_Setup with PostgreSQL values
 */
class Elk_Testing_psql extends Elk_Testing_Setup
{
	public function init()
	{
		global $db_name, $db_prefix, $modSettings;

		$this->_boardurl = 'http://127.0.0.1';
		$this->_db_server = 'localhost';
		$this->_db_type = 'postgresql';
		$db_name = $this->_db_name = 'elkarte_test';
		$this->_db_user = 'postgres';
		$this->_db_passwd = '';
		$db_prefix = $this->_db_prefix = 'elkarte_';

		// Start the database interface
		$this->_db = \ElkArte\Database\Postgresql\Connection::initiate($this->_db_server, $this->_db_name, $this->_db_user, $this->_db_passwd, $this->_db_prefix);
		$this->_db_table = DbTable_PostgreSQL_Install::db_table($this->_db, $this->_db_prefix);

		$modSettings['disableQueryCheck'] = 1;
		// Load the postgre install sql queries
		$this->load_queries(BOARDDIR . '/install/install_' . DB_SCRIPT_VERSION . '_postgresql.php');
		$this->run_queries();
		$modSettings['disableQueryCheck'] = 0;

		// Now the rest normally
		$this->load_queries(BOARDDIR . '/install/install_' . DB_SCRIPT_VERSION . '.php');
		$this->run_queries();

		// Prepare Settings.php, add a member, set time
		$this->prepare();
	}
}
