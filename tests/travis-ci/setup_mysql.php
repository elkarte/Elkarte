<?php
define('TESTDIR', dirname(__FILE__));
define('BOARDDIR', dirname(__FILE__) . '/../..');
define('ELK', 1);

require_once(TESTDIR . '/setup.php');
require_once(BOARDDIR . '/sources/database/Db-mysql.class.php');
require_once(BOARDDIR . '/sources/database/DbTable.class.php');
require_once(BOARDDIR . '/sources/database/DbTable-mysql.php');

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
		global $db_prefix;

		// We are doing install, of course we want to do any remove on these
		$this->_reservedTables = array();

		foreach ($this->_reservedTables as $k => $table_name)
			$this->_reservedTables[$k] = strtolower($db_prefix . $table_name);

		// let's be sure.
		$this->_package_log = array();

		// This executes queries and things
		$this->_db = $db;
	}

	/**
	* Static method that allows to retrieve or create an instance of this class.
	*
	* @param object $_db - A Database_MySQL object
	* @return object - A DbTable_MySQL object
	*/
	public static function db_table($db)
	{
		if (is_null(self::$_tbl_inst))
			self::$_tbl_inst = new DbTable_MySQL_Install($db);
		return self::$_tbl_inst;
	}
}

Class Elk_Testing_mysql extends Elk_Testing_Setup
{
	public function init()
	{
		global $db_name, $db_prefix;

		$this->_boardurl = 'http://127.0.0.1';
		$this->_db_server = 'localhost';
		$this->_db_type = 'mysql';
		$db_name = $this->_db_name = 'hello_world_test';
		$this->_db_user = 'root';
		$this->_db_passwd = '';
		$db_prefix = $this->_db_prefix = 'elkarte_';
		$connection = Database_MySQL::initiate($this->_db_server, $this->_db_name, $this->_db_user, $this->_db_passwd, $this->_db_prefix);
		$this->_db = Database_MySQL::db();
		$this->_db_table = DbTable_MySQL_Install::db_table($this->_db);
	}
}

$setup = new Elk_Testing_mysql();
$setup->init();