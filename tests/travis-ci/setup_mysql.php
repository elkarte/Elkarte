<?php
define('TESTDIR', dirname(__FILE__));
define('BOARDDIR', dirname(__FILE__) . '/../..');
define('ELK', 1);

require_once(TESTDIR . '/setup.php');
// require_once(BOARDDIR . '/sources/database/Db-mysql.class.php');

Class Elk_Testing_mysql extends Elk_Testing_Setup
{
	public function init()
	{
		$this->_boardurl = 'http://127.0.0.1';
		$this->_db_server = 'localhost';
		$this->_db_type = 'mysql';
		$this->_db_name = 'hello_world_test';
		$this->_db_user = 'root';
		$this->_db_passwd = '';
		$this->_db_prefix = 'elkarte_';
		$connection = Database_MySQL::initiate($this->_db_server, $this->_db_name, $this->_db_user, $this->_db_passwd, $this->_db_prefix);
		$this->_db = Database_MySQL::db();

		$this->load_queries(BOARDDIR . '/install/install_1-0_mysql.sql');
		$this->prepare();
	}
}

$setup = new Elk_Testing_mysql();
$setup->init();