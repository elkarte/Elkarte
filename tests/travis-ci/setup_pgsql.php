<?php
define('TESTDIR', dirname(__FILE__));
define('BOARDDIR', dirname(__FILE__) . '/../..');
define('ELK', 1);

require_once(TESTDIR . '/setup.php');
require_once(BOARDDIR . '/sources/database/Db-postgresql.class.php');

Class Elk_Testing_psql extends Elk_Testing_Setup
{
	public function init()
	{
		$this->_boardurl = 'http://127.0.0.1';
		$this->_db_server = 'localhost';
		$this->_db_type = 'postgresql';
		$this->_db_name = 'hello_world_test';
		$this->_db_user = 'postgres';
		$this->_db_passwd = '';
		$this->_db_prefix = 'elkarte_';
		$connection = Database_PostgreSQL::initiate($this->_db_server, $this->_db_name, $this->_db_user, $this->_db_passwd, $this->_db_prefix);
		$this->_db = Database_PostgreSQL::db();

		$this->load_queries(BOARDDIR . '/install/install_1-0_postgresql.sql');
		$this->prepare();
	}
}

$setup = new Elk_Testing_psql();
$setup->init();
