<?php

/**
 * Handles the postgresql actions
 *
 * Called by setup-database.sh as part of the install
 *
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
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
 * Extend ElkTestingSetup with PostgreSQL values
 *
 * return int 0|1
 */
class Elk_Testing_psql extends ElkTestingSetup
{
	public function init()
	{
		global $db_name, $db_prefix, $db_type, $boardurl, $db_server, $db_user, $db_passwd;
		global $modSettings;

		$boardurl = $this->_boardurl = 'http://127.0.0.1';
		$db_server = $this->_db_server = '127.0.0.1';
		$db_type = $this->_db_type = 'postgresql';
		$db_name = $this->_db_name = 'elkarte_test';
		$db_user = $this->_db_user = 'postgres';
		$db_passwd = $this->_db_passwd = 'postgres';
		$db_prefix = $this->_db_prefix = 'elkarte_';

		$link = pg_connect('host=' . $this->_db_server . ' dbname=' . $this->_db_name . ' user=\'' . $this->_db_user . '\' password=\'' . $this->_db_passwd . '\'');
		if (!$link)
		{
			echo 'Could not connect: ' . pg_last_error($link);
			return 1;
		}

		$v = pg_version($link);
		printf("PostgreSQL server version: %s\n", $v['client']);

		// Start the database interface
		try
		{
			// Start the database interface
			$this->_db = \ElkArte\Database\Postgresql\Connection::initiate($this->_db_server, $this->_db_name, $this->_db_user, $this->_db_passwd, $this->_db_prefix);
			$this->_db_table = DbTable_PostgreSQL_Install::db_table($this->_db, $this->_db_prefix);
		}
		catch (\Exception $e)
		{
			echo $e->getMessage();
			return 1;
		}

		// Load the postgre install sql queries
		$modSettings['disableQueryCheck'] = 1;
		$this->load_queries(BOARDDIR . '/install/install_' . DB_SCRIPT_VERSION . '_postgresql.php');
		$this->run_queries();
		$modSettings['disableQueryCheck'] = 0;

		// Now the rest normally
		$this->load_queries(BOARDDIR . '/install/install_' . DB_SCRIPT_VERSION . '.php');
		$result = $this->run_queries();

		if (empty($result))
			return 1;


		// Prepare Settings.php, add a member, set time
		$this->prepare();
		return 0;
	}
}
