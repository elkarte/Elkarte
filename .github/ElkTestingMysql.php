<?php

/**
 * Handles the mysql / mariadb db actions
 * Called by setup-database.sh as part of the install
 *
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * @version 2.0 dev
 *
 */

use ElkArte\Database\Mysqli\Connection;
use ElkArte\Database\Mysqli\Table;

/**
 * Sets up a Database_MySQL object
 */
class DbTable_MySQL_Install extends Table
{
	public static $_tbl_inst = null;

	/**
	 * DbTable_MySQL::construct
	 *
	 * @param object $db - A Database_MySQL object
	 */
	public function __construct($db, $db_prefix)
	{
		global $db_prefix;

		// We are installing, of course we want to do any remove on these
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
`	 *
	 * @param object $db - A Database_MySQL object
	 *
	 * @return object - A DbTable_MySQL object
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

/**
 * Extend ElkTestingSetup with MySql values
 *
 * return int 0|1
 */
class ElkTestingMysql extends ElkTestingSetup
{
	public function init()
	{
		global $db_name, $db_prefix, $db_type, $boardurl, $db_server, $db_user, $db_passwd;

		$boardurl = $this->_boardurl = 'http://127.0.0.1';
		$db_server = $this->_db_server = '127.0.0.1';
		$db_type = $this->_db_type = 'mysql';
		$db_name = $this->_db_name = 'elkarte_test';
		$db_user = $this->_db_user = 'root';
		$db_passwd = $this->_db_passwd = '';
		$db_prefix = $this->_db_prefix = 'elkarte_';

		$link = mysqli_connect($this->_db_server, $this->_db_user, $this->_db_passwd);
		if (!$link)
		{
			echo 'Could not connect: ' . mysqli_error($link);
			return 1;
		}

		printf("MySQL server version: %s\n", mysqli_get_server_info($link));

		try
		{
			// Start the database interface
			$this->_db = Connection::initiate($this->_db_server, $this->_db_name, $this->_db_user, $this->_db_passwd, $this->_db_prefix, ['select_db' => true]);
			$this->_db_table = DbTable_MySQL_Install::db_table($this->_db, $this->_db_prefix);
		}
		catch (Exception $e)
		{
			echo $e->getMessage();
			return 1;
		}

		// Load the mysql install queries
		$this->load_queries(BOARDDIR . '/install/install_' . DB_SCRIPT_VERSION . '.php');

		$result = $this->run_queries();

		if (empty($result))
			return 1;

		// Prepare Settings.php, add a member, set time
		$this->prepare();
		return 0;
	}
}
