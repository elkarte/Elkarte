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
		if (empty($this->_clean_queries_parts))
			$this->_clean_queries_parts = $this->_queries_parts;

		foreach ($this->_clean_queries_parts as $part)
		{
			if (substr($part, -1) == ';')
			{
				echo $query . "\n" . $part . "\n";
				$this->_db->query($query . "\n" . $part);
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
		$fh = fopen(BOARDDIR . '/Settings.php', 'a');
		fwrite($fh, "\n" . '$test_enabled = 1;');
	}
}