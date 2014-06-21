<?php

/**
 * This file provides an implementation of the most common functions needed
 * for the database drivers to work.
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * This file contains code covered by:
 * copyright:	2011 Simple Machines (http://www.simplemachines.org)
 * license:  	BSD, See included LICENSE.TXT for terms and conditions.
 *
 * @version 1.0 Release Candidate 1
 *
 */

if (!defined('ELK'))
	die('No access...');

/**
 * PostgreSQL database class, implements database class to control mysql functions
 */
class Database_Abstract implements Database
{
	/**
	 * Current connetcion to the database
	 * @var resource
	 */
	protected $_connection = null;

	/**
	 * Number of queries run (may include queries from $_SESSION if is a redirect)
	 * @var resource
	 */
	protected $_query_count = 0;

	/**
	 * Private constructor.
	 */
	protected function __construct()
	{
		// Objects should be created through initiate().
	}

	/**
	 * Initializes a database connection.
	 * It returns the connection, if successful.
	 *
	 * @param string $db_server
	 * @param string $db_name
	 * @param string $db_user
	 * @param string $db_passwd
	 * @param string $db_prefix
	 * @param mixed[] $db_options
	 *
	 * @return resource
	 */
	public static function initiate($db_server, $db_name, $db_user, $db_passwd, $db_prefix, $db_options = array());

	/**
	 * Fix the database prefix if necessary.
	 *
	 * @param string $db_prefix
	 * @param string $db_name
	 *
	 * @return string
	 */
	public function fix_prefix($db_prefix, $db_name);

	/**
	 * Callback for preg_replace_callback on the query.
	 * It allows to replace on the fly a few pre-defined strings, for
	 * convenience ('query_see_board', 'query_wanna_see_board'), with
	 * their current values from $user_info.
	 * In addition, it performs checks and sanitization on the values
	 * sent to the database.
	 *
	 * @param mixed[] $matches
	 */
	public function replacement__callback($matches)
	{
		global $db_callback, $user_info, $db_prefix;

		list ($values, $connection) = $db_callback;

		// Connection gone???  This should *never* happen at this point, yet it does :'(
		if (!$this->_validConnection($connection))
			display_db_error();

		if ($matches[1] === 'db_prefix')
			return $db_prefix;

		if ($matches[1] === 'query_see_board')
			return $user_info['query_see_board'];

		if ($matches[1] === 'query_wanna_see_board')
			return $user_info['query_wanna_see_board'];

		if (!isset($matches[2]))
			$this->error_backtrace('Invalid value inserted or no type specified.', '', E_USER_ERROR, __FILE__, __LINE__);

		if (!isset($values[$matches[2]]))
			$this->error_backtrace('The database value you\'re trying to insert does not exist: ' . htmlspecialchars($matches[2], ENT_COMPAT, 'UTF-8'), '', E_USER_ERROR, __FILE__, __LINE__);

		$replacement = $values[$matches[2]];

		switch ($matches[1])
		{
			case 'int':
				if (!is_numeric($replacement) || (string) $replacement !== (string) (int) $replacement)
					$this->error_backtrace('Wrong value type sent to the database. Integer expected. (' . $matches[2] . ')', '', E_USER_ERROR, __FILE__, __LINE__);
				return (string) (int) $replacement;
			break;

			case 'string':
			case 'text':
				return sprintf('\'%1$s\'', $this->escape_string($replacement));
			break;

			case 'array_int':
				if (is_array($replacement))
				{
					if (empty($replacement))
						$this->error_backtrace('Database error, given array of integer values is empty. (' . $matches[2] . ')', '', E_USER_ERROR, __FILE__, __LINE__);

					foreach ($replacement as $key => $value)
					{
						if (!is_numeric($value) || (string) $value !== (string) (int) $value)
							$this->error_backtrace('Wrong value type sent to the database. Array of integers expected. (' . $matches[2] . ')', '', E_USER_ERROR, __FILE__, __LINE__);

						$replacement[$key] = (string) (int) $value;
					}

					return implode(', ', $replacement);
				}
				else
					$this->error_backtrace('Wrong value type sent to the database. Array of integers expected. (' . $matches[2] . ')', '', E_USER_ERROR, __FILE__, __LINE__);

			break;

			case 'array_string':
				if (is_array($replacement))
				{
					if (empty($replacement))
						$this->error_backtrace('Database error, given array of string values is empty. (' . $matches[2] . ')', '', E_USER_ERROR, __FILE__, __LINE__);

					foreach ($replacement as $key => $value)
						$replacement[$key] = sprintf('\'%1$s\'', $this->escape_string($value));

					return implode(', ', $replacement);
				}
				else
					$this->error_backtrace('Wrong value type sent to the database. Array of strings expected. (' . $matches[2] . ')', '', E_USER_ERROR, __FILE__, __LINE__);
			break;

			case 'date':
				if (preg_match('~^(\d{4})-([0-1]?\d)-([0-3]?\d)$~', $replacement, $date_matches) === 1)
					return sprintf('\'%04d-%02d-%02d\'', $date_matches[1], $date_matches[2], $date_matches[3]);
				else
					$this->error_backtrace('Wrong value type sent to the database. Date expected. (' . $matches[2] . ')', '', E_USER_ERROR, __FILE__, __LINE__);
			break;

			case 'float':
				if (!is_numeric($replacement))
					$this->error_backtrace('Wrong value type sent to the database. Floating point number expected. (' . $matches[2] . ')', '', E_USER_ERROR, __FILE__, __LINE__);
				return (string) (float) $replacement;
			break;

			case 'identifier':
				return '`' . strtr($replacement, array('`' => '', '.' => '')) . '`';
			break;

			case 'raw':
				return $replacement;
			break;

			default:
				$this->error_backtrace('Undefined type used in the database query. (' . $matches[1] . ':' . $matches[2] . ')', '', false, __FILE__, __LINE__);
			break;
		}
	}

	/**
	 * This function works like $this->query(), escapes and quotes a string,
	 * but it doesn't execute the query.
	 *
	 * @param string $db_string
	 * @param mixed[] $db_values
	 * @param resource|null $connection
	 */
	public function quote($db_string, $db_values, $connection = null)
	{
		global $db_callback;

		// Only bother if there's something to replace.
		if (strpos($db_string, '{') !== false)
		{
			// This is needed by the callback function.
			$db_callback = array($db_values, $connection === null ? $this->_connection : $connection);

			// Do the quoting and escaping
			$db_string = preg_replace_callback('~{([a-z_]+)(?::([a-zA-Z0-9_-]+))?}~', array($this, 'replacement__callback'), $db_string);

			// Clear this global variable.
			$db_callback = array();
		}

		return $db_string;
	}

	/**
	 * Do a query.  Takes care of errors too.
	 * Special queries may need additional replacements to be appropriate
	 * for PostgreSQL.
	 *
	 * @param string $identifier
	 * @param string $db_string
	 * @param mixed[] $db_values
	 * @param resource|null $connection
	 * @return resource|boolean
	 */
	public function query($identifier, $db_string, $db_values = array(), $connection = null);

	/**
	 * Affected rows from previous operation.
	 *
	 * @param resource|null $result
	 */
	public function affected_rows($result = null);

	/**
	 * Last inserted id.
	 *
	 * @param string $table
	 * @param string|null $field = null
	 * @param resource|null $connection = null
	 */
	public function insert_id($table, $field = null, $connection = null);

	/**
	 * Tracking the current row.
	 * Fetch a row from the resultset given as parameter.
	 *
	 * @param resource $request
	 * @param integer|bool $counter = false
	 */
	public function fetch_row($request, $counter = false);

	/**
	 * Free the resultset.
	 *
	 * @param resource $result
	 */
	public function free_result($result);

	/**
	 * Get the number of rows in the result.
	 *
	 * @param resource $result
	 */
	public function num_rows($result);

	/**
	 * Get the number of fields in the resultset.
	 *
	 * @param resource $request
	 */
	public function num_fields($request);

	/**
	 * Reset the internal result pointer.
	 *
	 * @param boolean $request
	 * @param integer $counter
	 */
	public function data_seek($request, $counter);

	/**
	 * Do a transaction.
	 *
	 * @param string $type - the step to perform (i.e. 'begin', 'commit', 'rollback')
	 * @param resource|null $connection = null
	 */
	public function db_transaction($type = 'commit', $connection = null);

	/**
	 * Return last error string from the database server
	 *
	 * @param resource|null $connection = null
	 */
	public function last_error($connection = null);

	/**
	 * Database error.
	 * Backtrace, log, try to fix.
	 *
	 * @param string $db_string
	 * @param resource|null $connection = null
	 */
	public function error($db_string, $connection = null);

	/**
	 * Insert data.
	 *
	 * @param string $method - options 'replace', 'ignore', 'insert'
	 * @param string $table
	 * @param mixed[] $columns
	 * @param mixed[] $data
	 * @param mixed[] $keys
	 * @param bool $disable_trans = false
	 * @param resource|null $connection = null
	 */
	public function insert($method = 'replace', $table, $columns, $data, $keys, $disable_trans = false, $connection = null);

	/**
	 * This function tries to work out additional error information from a back trace.
	 *
	 * @param string $error_message
	 * @param string $log_message
	 * @param string|boolean $error_type
	 * @param string|null $file
	 * @param integer|null $line
	 */
	public function error_backtrace($error_message, $log_message = '', $error_type = false, $file = null, $line = null)
	{
		if (empty($log_message))
			$log_message = $error_message;

		foreach (debug_backtrace() as $step)
		{
			// Found it?
			if (!method_exists($this, $step['function']) && !in_array(substr($step['function'], 0, 7), array('elk_db_', 'preg_re', 'db_erro', 'call_us')))
			{
				$log_message .= '<br />Function: ' . $step['function'];
				break;
			}

			if (isset($step['line']))
			{
				$file = $step['file'];
				$line = $step['line'];
			}
		}

		// A special case - we want the file and line numbers for debugging.
		if ($error_type == 'return')
			return array($file, $line);

		// Is always a critical error.
		if (function_exists('log_error'))
			log_error($log_message, 'critical', $file, $line);

		if (function_exists('fatal_error'))
		{
			fatal_error($error_message, false);

			// Cannot continue...
			exit;
		}
		elseif ($error_type)
			trigger_error($error_message . ($line !== null ? '<em>(' . basename($file) . '-' . $line . ')</em>' : ''), $error_type);
		else
			trigger_error($error_message . ($line !== null ? '<em>(' . basename($file) . '-' . $line . ')</em>' : ''));
	}

	/**
	 * Escape the LIKE wildcards so that they match the character and not the wildcard.
	 *
	 * @param string $string
	 * @param bool $translate_human_wildcards = false, if true, turns human readable wildcards into SQL wildcards.
	 */
	public function escape_wildcard_string($string, $translate_human_wildcards = false)
	{
		$replacements = array(
			'%' => '\%',
			'_' => '\_',
			'\\' => '\\\\',
		);

		if ($translate_human_wildcards)
			$replacements += array(
				'*' => '%',
			);

		return strtr($string, $replacements);
	}

	/**
	 * Unescape an escaped string!
	 *
	 * @param string $string
	 */
	public function unescape_string($string);

	/**
	 * Returns whether the database system supports ignore.
	 *
	 * @return false
	 */
	public function support_ignore();

	/**
	 * Gets all the necessary INSERTs for the table named table_name.
	 * It goes in 250 row segments.
	 *
	 * @param string $tableName - the table to create the inserts for.
	 * @param bool $new_table
	 *
	 * @return string the query to insert the data back in, or an empty string if the table was empty.
	 */
	public function insert_sql($tableName, $new_table = false);

	/**
	 * Dumps the schema (CREATE) for a table.
	 *
	 * @param string $tableName - the table
	 *
	 * @return string - the CREATE statement as string
	 */
	public function db_table_sql($tableName);

	/**
	 * This function lists all tables in the database.
	 * The listing could be filtered according to $filter.
	 *
	 * @param string|false $db_name_str string holding the database name, or false, default false
	 * @param string|false $filter string to filter by, or false, default false
	 *
	 * @return string[] an array of table names. (strings)
	 */
	public function db_list_tables($db_name_str = false, $filter = false);

	/**
	 * This function optimizes a table.
	 *
	 * - reclaims storage occupied by dead tuples. In normal PostgreSQL operation, tuples
	 * that are deleted or obsoleted by an update are not physically removed from their table;
	 * they remain present until a VACUUM is done. Therefore it's necessary to do VACUUM periodically,
	 * especially on frequently-updated tables.
	 *
	 * @param string $table - the table to be optimized
	 *
	 * @deprecated since 1.1 - the function was moved to DbTable class
	 *
	 * @return int how much it was gained
	 */
	public function db_optimize_table($table)
	{
		$db_table = db_table();

		return $db_table->optimize($table);
	}

	/**
	 * Backup $table to $backup_table.
	 *
	 * @param string $table
	 * @param string $backup_table
	 */
	public function db_backup_table($table, $backup_table);

	/**
	 * Get the server version number.
	 *
	 * @return string - the version
	 */
	public function db_server_version();

	/**
	 * Get the name (title) of the database system.
	 *
	 * @return string
	 */
	public function db_title();

	/**
	 * Whether the database system is case sensitive.
	 *
	 * @return true
	 */
	public function db_case_sensitive();

	/**
	 * Escape string for the database input
	 *
	 * @param string $string
	 */
	public function escape_string($string);

	/**
	 * Fetch next result as association.
	 *
	 * @param resource $request
	 * @param int|false $counter = false
	 */
	public function fetch_assoc($request, $counter = false);

	/**
	 * Return server info.
	 *
	 * @param resource|null $connection
	 *
	 * @return string
	 */
	public function db_server_info($connection = null);

	/**
	 * Return client version.
	 *
	 * @return string - the version
	 */
	public function db_client_version();

	/**
	 * Select database.
	 *
	 * @param string|null $db_name = null
	 * @param resource|null $connection = null
	 *
	 * @return true
	 */
	public function select_db($db_name = null, $connection = null);

	/**
	 * Retrieve the connection object
	 *
	 * @return resource what? The connection
	 */
	public function connection()
	{
		// find it, find it
		return $this->_connection;
	}

	/**
	 * Return the number of queries executed
	 *
	 * @return int
	 */
	function num_queries()
	{
		return $this->_query_count;
	}

	/**
	 * Finds out if the connection is still valid.
	 *
	 * @param resource|object|null $connection = null
	 */
	protected function _validConnection($connection = null);
}