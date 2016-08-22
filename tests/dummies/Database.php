<?php

namespace ElkArte\Tests\Dummies;

class Database extends \Database_Abstract
{
	protected $expectedQueries = array();
	protected static $_db = null;

	public function addQuery($string, $results)
	{
		$this->expectedQueries[md5($string)] = $results;
	}
	public function removeAll()
	{
		$this->expectedQueries = array();
	}

	public function query($identifier, $db_string, $db_values = array(), $connection = null)
	{
		$string = $this->quote($db_string, $db_values);
// 		print_r(array('read', md5($string), $string, $this->returnExpectedResult($string)));
		return new DatabaseResult($this->returnExpectedResult($string));
	}
	public function fetchQuery($db_string, $db_values = array(), $seeds = null)
	{
		$string = $this->quote($db_string, $db_values);
		return $this->returnExpectedResult($string);
	}
	public function fetchQueryCallback($db_string, $db_values = array(), $callback = '', $seeds = null)
	{
		if ($callback === '')
			return $this->returnExpectedResult($db_string);

		$string = $this->quote($db_string, $db_values);
		$request = $this->returnExpectedResult($string);

		$results = $seeds !== null ? $seeds : array();
		foreach ($request as $row)
		{
			$results[] = $callback($row);
		}

		return $results;
	}
	public function fetch_assoc($request, $counter = false)
	{
		return $request->fetchNext();
	}
	public function fetch_row($result, $counter = false)
	{
		return $result->fetchNext();
	}
	public function num_rows($result)
	{
		return $result;
	}
	public function insert($method = 'replace', $table, $columns, $data, $keys, $disable_trans = false, $connection = null)
	{
	}

	protected function returnExpectedResult($string)
	{
		if (isset($this->expectedQueries[md5($string)]))
		{
			return $this->expectedQueries[md5($string)];
		}
		else
		{
			return false;
		}
	}

	public static function db()
	{
		if (self::$_db === null)
		{
			self::$_db = new \ElkArte\Tests\Dummies\Database();
		}

		return self::$_db;
	}


	public function fix_prefix($db_prefix, $db_name)
	{
	}
	public function free_result($result)
	{
	}
	public function num_fields($request)
	{
	}
	public function data_seek($request, $counter)
	{
	}
	public function affected_rows()
	{
	}
	public function insert_id($table, $field = null, $connection = null)
	{
	}
	public function db_transaction($type = 'commit', $connection = null)
	{
	}
	public function error($db_string, $connection = null)
	{
	}
	public function skip_error($set = true)
	{
	}
	public function error_backtrace($error_message, $log_message = '', $error_type = false, $file = null, $line = null)
	{
	}
	public function escape_string($string)
	{
		return $string;
	}
	public function escape_wildcard_string($string, $translate_human_wildcards = false)
	{
		return $string;
	}
	public function unescape_string($string)
	{
		return $string;
	}
	public function last_error($connection = null)
	{
	}
	public function support_ignore()
	{
		return true;
	}
	public function db_title()
	{
		return 'Dummy';
	}
	public function db_case_sensitive()
	{
		return false;
	}
	public function insert_sql($tableName, $new_table = false)
	{
	}
	public function select_db($dbName = null, $connection = null)
	{
	}
	public function num_queries()
	{
	}
	public function connection()
	{
	}
	public function db_list_tables($db_name_str = false, $filter = false)
	{
	}
	public function db_table_sql($tableName)
	{
	}
	public function validConnection($connection = null)
	{
		return true;
	}
}

class DatabaseResult
{
	protected $results = array();
	protected $pointer = 0;

	public function __construct($results)
	{
		$this->results = $results;
	}

	public function fetchNext()
	{
		if (isset($this->results[$this->pointer]))
		{
			return $this->results[$this->pointer++];
		}
		else
		{
			return false;
		}
	}
}