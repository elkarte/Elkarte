<?php

Class Elk_Testing_Setup
{
	protected $_db;
	protected $_queries_parts;
	protected $_clean_queries_parts;
	protected $_queries;
	protected $_dbserver;
	protected $_name;
	protected $_user;
	protected $_passwd;
	protected $_prefix;

	public function run_queries()
	{
		$query = '';

		if (empty($this->_clean_queries_parts))
			$this->_clean_queries_parts = $this->_queries_parts;

		foreach ($this->_clean_queries_parts as $part)
		{
			if (substr($part, -1) == ';')
			{
				$result = $this->_db->query('', $query . "\n" . $part, array('security_override' => true));
				if ($result === false)
					echo 'Query failed: ' . "\n" . $query . "\n" . $part . "\n";

				$query = '';
			}
			else
			{
				$query .= "\n" . $part;
			}
		}
	}

	public function load_queries($file)
	{
		$this->_queries = str_replace('{$db_prefix}', 'elk_', file_get_contents($file));
		$this->_queries_parts = explode("\n", $this->_queries);
	}

	public function prepare_settings()
	{
		$file = file_get_contents(BOARDDIR . '/Settings.php');
		$file = str_replace(array(
			'$boardurl = \'http://127.0.0.1/elkarte\';',
			'$db_type = \'mysql\';',
			'$db_name = \'elkarte\';',
			'$db_user = \'root\';',
			'$db_prefix = \'elkarte_\';'
		),
		array(
			'$boardurl = \'http://127.0.0.1\';',
			'$db_type = \'' . $this->_type . '\';',
			'$db_name = \'' . $this->_name . '\';',
			'$db_user = \'' . $this->_user . '\';',
			'$db_prefix = \'' . $this->_prefix . '\';'
		),
		$file);
		$file .= "\n" . '$test_enabled = 1;';

		file_put_contents(BOARDDIR . '/Settings.php', $file);
	}
}