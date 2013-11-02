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
		$this->fix_query_string();
		$this->run_queries();
		$this->prepare_settings();
	}

	public function fix_query_string()
	{
		foreach ($this->_queries_parts as $line)
			if (!empty($line[0]) && $line[0] != '#')
				$this->_clean_queries_parts[] = str_replace(array('{$current_time}', '{$sched_task_offset}'), array(time(), '1'), $line);
	}
}

$setup = new Elk_Testing_psql();
$setup->init();
