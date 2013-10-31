<?php
define('TESTDIR', dirname(__FILE__));
define('BOARDDIR', dirname(__FILE__) . '/../..');
define('ELK', 1);

require_once(TESTDIR . '/setup.php');
require_once(BOARDDIR . '/sources/database/Db.php');
require_once(BOARDDIR . '/sources/database/Db-postgresql.class.php');

Class Elk_Testing_psql extends Elk_Testing_Setup
{
	public function init()
	{
		$this->_server = 'localhost';
		$this->_type = 'postgresql';
		$this->_name = 'hello_world_test';
		$this->_user = 'postgres';
		$this->_passwd = '';
		$this->_prefix = 'elkarte_';
		$connection = Database_PostgreSQL::initiate($this->_server, $this->_name, $this->_user, $this->_passwd, $this->_prefix);
		$this->_db = Database_PostgreSQL::db();

		$this->load_queries(BOARDDIR . '/install/install_1-0_postgresql.sql');
		$this->run_queries();
		$this->prepare_settings();
		$this->update();
	}
}

$setup = new Elk_Testing_psql();
$setup->init();
