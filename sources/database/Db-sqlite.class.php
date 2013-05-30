<?php

/**
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * This file contains code covered by:
 * copyright:	2011 Simple Machines (http://www.simplemachines.org)
 * license:  	BSD, See included LICENSE.TXT for terms and conditions.
 *
 * @version 1.0 Alpha
 *
 * This file has all the main functions in it that relate to the database.
 *
 */

if (!defined('ELKARTE'))
	die('No access...');

class Database_SQLite implements Database
{
	private static $_db = null;

	private function __construct()
	{
		// Private constructor.
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
	 * @param array $db_options
	 *
	 * @return resource
	 */
	static function initiate($db_server, $db_name, $db_user, $db_passwd, $db_prefix, $db_options = array())
	{
		global $db_in_transact, $sqlite_error;

		if (substr($db_name, -3) != '.db')
			$db_name .= '.db';

		// initialize the instance... if not done already!
		if (self::$_db === null)
			self::$_db = new self();

		if (!empty($db_options['persist']))
			$connection = @sqlite_popen($db_name, 0666, $sqlite_error);
		else
			$connection = @sqlite_open($db_name, 0666, $sqlite_error);

		// Something's wrong, show an error if its fatal (which we assume it is)
		if (!$connection)
		{
			if (!empty($db_options['non_fatal']))
				return null;
			else
				display_db_error();
		}
		$db_in_transact = false;

		// This is frankly stupid - stop SQLite returning alias names!
		@sqlite_query('PRAGMA short_column_names = 1', $connection);

		// Make some user defined functions!
		sqlite_create_function($connection, 'unix_timestamp', 'elk_udf_unix_timestamp', 0);
		sqlite_create_function($connection, 'inet_aton', 'elk_udf_inet_aton', 1);
		sqlite_create_function($connection, 'inet_ntoa', 'elk_udf_inet_ntoa', 1);
		sqlite_create_function($connection, 'find_in_set', 'elk_udf_find_in_set', 2);
		sqlite_create_function($connection, 'year', 'elk_udf_year', 1);
		sqlite_create_function($connection, 'month', 'elk_udf_month', 1);
		sqlite_create_function($connection, 'dayofmonth', 'elk_udf_dayofmonth', 1);
		sqlite_create_function($connection, 'concat', 'elk_udf_concat');
		sqlite_create_function($connection, 'locate', 'elk_udf_locate', 2);
		sqlite_create_function($connection, 'regexp', 'elk_udf_regexp', 2);

		return $connection;
	}

	/**
	 * Fix db prefix if necessary.
	 * SQLite doesn't actually need this!
	 *
	 * @param type $db_prefix
	 * @param type $db_name
	 *
	 * @return string
	 */
	function fix_prefix($db_prefix, $db_name)
	{
		return $db_prefix;
	}

	/**
	 * Callback for preg_replace_calback on the query.
	 * It allows to replace on the fly a few pre-defined strings, for
	 * convenience ('query_see_board', 'query_wanna_see_board'), with
	 * their current values from $user_info.
	 * In addition, it performs checks and sanitization on the values
	 * sent to the database.
	 *
	 * @param $matches
	 */
	function replacement__callback($matches)
	{
		global $db_callback, $user_info, $db_prefix;

		list ($values, $connection) = $db_callback;

		// This should not happen, yet it does
		if (!is_resource($connection))
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
			$this->error_backtrace('The database value you\'re trying to insert does not exist: ' . htmlspecialchars($matches[2]), '', E_USER_ERROR, __FILE__, __LINE__);

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
				return sprintf('\'%1$s\'', sqlite_escape_string($replacement));
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
						$replacement[$key] = sprintf('\'%1$s\'', sqlite_escape_string($value));

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
	 * @param string $db_values
	 * @param resource $connection
	 */
	function quote($db_string, $db_values, $connection = null)
	{
		global $db_callback, $db_connection;

		// Only bother if there's something to replace.
		if (strpos($db_string, '{') !== false)
		{
			// This is needed by the callback function.
			$db_callback = array($db_values, $connection === null ? $db_connection : $connection);

			// Do the quoting and escaping
			$db_string = preg_replace_callback('~{([a-z_]+)(?::([a-zA-Z0-9_-]+))?}~', 'elk_db_replacement__callback', $db_string);

			// Clear this global variable.
			$db_callback = array();
		}

		return $db_string;
	}

	/**
	 * Do a query.  Takes care of errors too.
	 *
	 * @param string $identifier
	 * @param string $db_string
	 * @param string $db_values
	 * @param resource $connection
	 */
	function query($identifier, $db_string, $db_values = array(), $connection = null)
	{
		global $db_cache, $db_count, $db_connection, $db_show_debug, $time_start;
		global $db_unbuffered, $db_callback, $modSettings;

		// Decide which connection to use.
		$connection = $connection === null ? $db_connection : $connection;

		// Special queries that need processing.
		$replacements = array(
			'birthday_array' => array(
				'~DATE_FORMAT\(([^,]+),\s*([^\)]+)\s*\)~' => 'strftime($2, $1)'
			),
			'substring' => array(
				'~SUBSTRING~' => 'SUBSTR',
			),
			'truncate_table' => array(
				'~TRUNCATE~i' => 'DELETE FROM',
			),
			'user_activity_by_time' => array(
				'~HOUR\(FROM_UNIXTIME\((poster_time\s+\+\s+\{int:.+\})\)\)~' => 'strftime(\'%H\', datetime($1, \'unixepoch\'))',
			),
			'unread_fetch_topic_count' => array(
				'~\s*SELECT\sCOUNT\(DISTINCT\st\.id_topic\),\sMIN\(t\.id_last_msg\)(.+)$~is' => 'SELECT COUNT(id_topic), MIN(id_last_msg) FROM (SELECT DISTINCT t.id_topic, t.id_last_msg $1)',
			),
			'alter_table_boards' => array(
				'~(.+)~' => '',
			),
			'get_random_number' => array(
				'~RAND~' => 'RANDOM',
			),
			'set_character_set' => array(
				'~(.+)~' => '',
			),
			'themes_count' => array(
				'~\s*SELECT\sCOUNT\(DISTINCT\sid_member\)\sAS\svalue,\sid_theme.+FROM\s(.+themes)(.+)~is' => 'SELECT COUNT(id_member) AS value, id_theme FROM (SELECT DISTINCT id_member, id_theme, variable FROM $1) $2',
			),
			'attach_download_increase' => array(
				'~LOW_PRIORITY~' => '',
			),
			'pm_conversation_list' => array(
				'~ORDER BY id_pm~' => 'ORDER BY MAX(pm.id_pm)',
			),
			'boardindex_fetch_boards' => array(
				'~(.)$~' => '$1 ORDER BY b.board_order',
			),
			'messageindex_fetch_boards' => array(
				'~(.)$~' => '$1 ORDER BY b.board_order',
			),
			'order_by_board_order' => array(
				'~(.)$~' => '$1 ORDER BY b.board_order',
			),
			'spider_check' => array(
				'~(.)$~' => '$1 ORDER BY LENGTH(user_agent) DESC',
			),
		);

		if (isset($replacements[$identifier]))
			$db_string = preg_replace(array_keys($replacements[$identifier]), array_values($replacements[$identifier]), $db_string);

		// SQLite doesn't support count(distinct).
		$db_string = trim($db_string);
		$db_string = preg_replace('~^\s*SELECT\s+?COUNT\(DISTINCT\s+?(.+?)\)(\s*AS\s*(.+?))*\s*(FROM.+)~is', 'SELECT COUNT(*) $2 FROM (SELECT DISTINCT $1 $4)', $db_string);

		// Or RLIKE.
		$db_string = preg_replace('~AND\s*(.+?)\s*RLIKE\s*(\{string:.+?\})~', 'AND REGEXP(\1, \2)', $db_string);

		// INSTR?  No support for that buddy :(
		if (preg_match('~INSTR\((.+?),\s(.+?)\)~', $db_string, $matches) === 1)
		{
			$db_string = preg_replace('~INSTR\((.+?),\s(.+?)\)~', '$1 LIKE $2', $db_string);
			list(, $search) = explode(':', substr($matches[2], 1, -1));
			$db_values[$search] = '%' . $db_values[$search] . '%';
		}

		// Lets remove ASC and DESC from GROUP BY clause.
		if (preg_match('~GROUP BY .*? (?:ASC|DESC)~is', $db_string, $matches))
		{
			$replace = str_replace(array('ASC', 'DESC'), '', $matches[0]);
			$db_string = str_replace($matches[0], $replace, $db_string);
		}

		// SQLite doesn't support TO_DAYS but has the julianday function which can be used in the same manner.  But make sure it is being used to calculate a span.
		$db_string = preg_replace('~\(TO_DAYS\(([^)]+)\) - TO_DAYS\(([^)]+)\)\) AS span~', '(julianday($1) - julianday($2)) AS span', $db_string);

		// One more query....
		$db_count = !isset($db_count) ? 1 : $db_count + 1;

		if (empty($modSettings['disableQueryCheck']) && strpos($db_string, '\'') !== false && empty($db_values['security_override']))
			$this->error_backtrace('Hacking attempt...', 'Illegal character (\') used in query...', true, __FILE__, __LINE__);

		if (empty($db_values['security_override']) && (!empty($db_values) || strpos($db_string, '{db_prefix}') !== false))
		{
			// Pass some values to the global space for use in the callback function.
			$db_callback = array($db_values, $connection);

			// Inject the values passed to this function.
			$db_string = preg_replace_callback('~{([a-z_]+)(?::([a-zA-Z0-9_-]+))?}~', 'elk_db_replacement__callback', $db_string);

			// This shouldn't be residing in global space any longer.
			$db_callback = array();
		}

		// Debugging.
		if (isset($db_show_debug) && $db_show_debug === true)
		{
			// Get the file and line number this function was called.
			list ($file, $line) = $this->error_backtrace('', '', 'return', __FILE__, __LINE__);

			// Initialize $db_cache if not already initialized.
			if (!isset($db_cache))
				$db_cache = array();

			if (!empty($_SESSION['debug_redirect']))
			{
				$db_cache = array_merge($_SESSION['debug_redirect'], $db_cache);
				$db_count = count($db_cache) + 1;
				$_SESSION['debug_redirect'] = array();
			}

			$st = microtime(true);
			// Don't overload it.
			$db_cache[$db_count]['q'] = $db_count < 50 ? $db_string : '...';
			$db_cache[$db_count]['f'] = $file;
			$db_cache[$db_count]['l'] = $line;
			$db_cache[$db_count]['s'] = array_sum(explode(' ', $st)) - array_sum(explode(' ', $time_start));
		}

		$ret = @sqlite_query($db_string, $connection, SQLITE_BOTH, $err_msg);
		if ($ret === false && empty($db_values['db_error_skip']))
			$ret = $this->error($db_string . '#!#' . $err_msg, $connection);

		// Debugging.
		if (isset($db_show_debug) && $db_show_debug === true)
			$db_cache[$db_count]['t'] = microtime(true) - $st;

		return $ret;
	}

	/**
	 * Affected rows from previous operation.
	 *
	 * @param $database
	 */
	function affected_rows($database = null)
	{
		global $db_connection;

		return sqlite_changes($database === null ? $db_connection : $database);
	}

	/**
	 * Last inserted id.
	 *
	 * @param string $table
	 * @param string $field = null
	 * @param resource $connection = null
	 */
	function insert_id($table, $field = null, $connection = null)
	{
		global $db_connection, $db_prefix;

		$table = str_replace('{db_prefix}', $db_prefix, $table);

		// SQLite doesn't need the table or field information.
		return sqlite_last_insert_rowid($connection === null ? $db_connection : $connection);
	}

	/**
	 * Do a transaction.
	 *
	 * @param string $type - the step to perform (i.e. 'begin', 'commit', 'rollback')
	 * @param resource $connection = null
	 */
	function db_transaction($type = 'commit', $connection = null)
	{
		global $db_connection, $db_in_transact;

		// Decide which connection to use
		$connection = $connection === null ? $db_connection : $connection;

		if ($type == 'begin')
		{
			$db_in_transact = true;
			return @sqlite_query('BEGIN', $connection);
		}
		elseif ($type == 'rollback')
		{
			$db_in_transact = false;
			return @sqlite_query('ROLLBACK', $connection);
		}
		elseif ($type == 'commit')
		{
			$db_in_transact = false;
			return @sqlite_query('COMMIT', $connection);
		}

		return false;
	}

	/**
	 * Return last error string from the database server
	 *
	 * @param resource $database = null
	 */
	function last_error($database = null)
	{
		global $db_connection, $sqlite_error;

		$query_errno = sqlite_last_error(empty($database) ? $db_connection : $database);
		return $query_errno || empty($sqlite_error) ? sqlite_error_string($query_errno) : $sqlite_error;
	}

	/**
	 * Database error!
	 * Backtrace, log, try to fix.
	 *
	 * @param string $db_string
	 * @param resource $connection = null
	 */
	function error($db_string, $connection = null)
	{
		global $txt, $context, $webmaster_email, $modSettings;
		global $forum_version, $db_connection, $db_last_error, $db_persist;
		global $db_server, $db_user, $db_passwd, $db_name, $db_show_debug, $ssi_db_user, $ssi_db_passwd;

		// We'll try recovering the file and line number the original db query was called from.
		list ($file, $line) = $this->error_backtrace('', '', 'return', __FILE__, __LINE__);

		// Decide which connection to use
		$connection = $connection === null ? $db_connection : $connection;

		// This is the error message...
		$query_errno = sqlite_last_error($connection);
		$query_error = sqlite_error_string($query_errno);

		// Get the extra error message.
		$errStart = strrpos($db_string, '#!#');
		$query_error .= '<br />' . substr($db_string, $errStart + 3);
		$db_string = substr($db_string, 0, $errStart);

		// Log the error.
		if (function_exists('log_error'))
			log_error($txt['database_error'] . ': ' . $query_error . (!empty($modSettings['enableErrorQueryLogging']) ? "\n\n" .$db_string : ''), 'database', $file, $line);

		// Sqlite optimizing - the actual error message isn't helpful or user friendly.
		if (strpos($query_error, 'no_access') !== false || strpos($query_error, 'database schema has changed') !== false)
		{
			if (!empty($context) && !empty($txt) && !empty($txt['error_sqlite_optimizing']))
				fatal_error($txt['error_sqlite_optimizing'], false);
			else
			{
				// Don't cache this page!
				header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
				header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
				header('Cache-Control: no-cache');

				// Send the right error codes.
				header('HTTP/1.1 503 Service Temporarily Unavailable');
				header('Status: 503 Service Temporarily Unavailable');
				header('Retry-After: 3600');

				die('Sqlite is optimizing the database, the forum can not be accessed until it has finished.  Please try refreshing this page momentarily.');
			}
		}

		// Nothing's defined yet... just die with it.
		if (empty($context) || empty($txt))
			die($query_error);

		// Show an error message, if possible.
		$context['error_title'] = $txt['database_error'];
		if (allowedTo('admin_forum'))
			$context['error_message'] = nl2br($query_error) . '<br />' . $txt['file'] . ': ' . $file . '<br />' . $txt['line'] . ': ' . $line;
		else
			$context['error_message'] = $txt['try_again'];

		// A database error is often the sign of a database in need of updgrade.  Check forum versions, and if not identical suggest an upgrade... (not for Demo/CVS versions!)
		if (allowedTo('admin_forum') && !empty($forum_version) && $forum_version != 'ELKARTE ' . @$modSettings['elkVersion'] && strpos($forum_version, 'Demo') === false && strpos($forum_version, 'CVS') === false)
			$context['error_message'] .= '<br /><br />' . sprintf($txt['database_error_versions'], $forum_version, $modSettings['elkVersion']);

		if (allowedTo('admin_forum') && isset($db_show_debug) && $db_show_debug === true)
		{
			$context['error_message'] .= '<br /><br />' . nl2br($db_string);
		}

		// It's already been logged... don't log it again.
		fatal_error($context['error_message'], false);
	}

	/**
	 * Insert data.
	 *
	 * @param string $method, options 'replace', 'ignore', 'insert'
	 * @param $table
	 * @param $columns
	 * @param $data
	 * @param $keys
	 * @param bool $disable_trans = false
	 * @param resource $connection = null
	 */
	function insert($method = 'replace', $table, $columns, $data, $keys, $disable_trans = false, $connection = null)
	{
		global $db_in_transact, $db_connection, $db_prefix;

		$connection = $connection === null ? $db_connection : $connection;

		if (empty($data))
			return;

		if (!is_array($data[array_rand($data)]))
			$data = array($data);

		// Replace the prefix holder with the actual prefix.
		$table = str_replace('{db_prefix}', $db_prefix, $table);

		$priv_trans = false;
		if (count($data) > 1 && !$db_in_transact && !$disable_trans)
		{
			$this->db_transaction('begin', $connection);
			$priv_trans = true;
		}

		if (!empty($data))
		{
			// Create the mold for a single row insert.
			$insertData = '(';
			foreach ($columns as $columnName => $type)
			{
				// Are we restricting the length?
				if (strpos($type, 'string-') !== false)
					$insertData .= sprintf('SUBSTR({string:%1$s}, 1, ' . substr($type, 7) . '), ', $columnName);
				else
					$insertData .= sprintf('{%1$s:%2$s}, ', $type, $columnName);
			}
			$insertData = substr($insertData, 0, -2) . ')';

			// Create an array consisting of only the columns.
			$indexed_columns = array_keys($columns);

			// Here's where the variables are injected to the query.
			$insertRows = array();
			foreach ($data as $dataRow)
				$insertRows[] = $this->quote($insertData, array_combine($indexed_columns, $dataRow), $connection);

			foreach ($insertRows as $entry)
				// Do the insert.
				$this->query('',
					(($method === 'replace') ? 'REPLACE' : (' INSERT' . ($method === 'ignore' ? ' OR IGNORE' : ''))) . ' INTO ' . $table . '(' . implode(', ', $indexed_columns) . ')
					VALUES
						' . $entry,
					array(
						'security_override' => true,
						'db_error_skip' => $table === $db_prefix . 'log_errors',
					),
					$connection
				);
		}

		if ($priv_trans)
			$this->db_transaction('commit', $connection);
	}

	/**
	 * free_result. Doesn't do anything on sqlite!
	 *
	 * @param resource $handle = false
	 */
	function free_result($handle = false)
	{
		return true;
	}

	/**
	 * Get the number of rows in the result.
	 *
	 * @param resource $result
	 */
	function num_rows($result)
	{
		// simply delegate to the native function
		return sqlite_num_rows($result);
	}

	/**
	 * Get the number of fields in the resultset.
	 */
	function num_fields($request)
	{
		return sqlite_num_fields($request);
	}

	/**
	 * Reset the internal result pointer.
	 * SQLite implementation does not use the $request parameter, and will ignore it.
	 *
	 * @param $request
	 * @param $counter
	 */
	function data_seek($request, $counter)
	{
		// delegate to native sqlite function
		return sqlite_seek($counter);
	}

	/**
	 * Fetch a row of next data.
	 * SQLite method doesn't use the second parameter.
	 *
	 * @param $request
	 * @param bool $counter = false
	 */
	function fetch_row($handle, $counter = false)
	{
		// Make sure we return no string indexes
		return sqlite_fetch_array($handle, SQLITE_NUM);
	}

	/**
	 * Unescape an escaped string!
	 *
	 * @param $string
	 */
	function unescape_string($string)
	{
		return strtr($string, array('\'\'' => '\''));
	}

	/**
	 * This function tries to work out additional error information from a back trace.
	 *
	 * @param $error_message
	 * @param $log_message
	 * @param $error_type
	 * @param $file
	 * @param $line
	 */
	function error_backtrace($error_message, $log_message = '', $error_type = false, $file = null, $line = null)
	{
		if (empty($log_message))
			$log_message = $error_message;

		foreach (debug_backtrace() as $step)
		{
			// Found it?
			if (strpos($step['function'], 'query') === false && !in_array(substr($step['function'], 0, 7), array('smf_db_', 'preg_re', 'db_erro', 'call_us')) && strpos($step['function'], '__') !== 0)
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
			fatal_error($error_message, $error_type);

			// Cannot continue...
			exit;
		}
		elseif ($error_type)
			trigger_error($error_message . ($line !== null ? '<em>(' . basename($file) . '-' . $line . ')</em>' : ''), $error_type);
		else
			trigger_error($error_message . ($line !== null ? '<em>(' . basename($file) . '-' . $line . ')</em>' : ''));
	}

	/**
	 * Emulate UNIX_TIMESTAMP.
	 */
	function udf_unix_timestamp()
	{
		return strftime('%s', 'now');
	}

	/**
	 * Emulate INET_ATON.
	 *
	 * @param $ip
	 */
	function udf_inet_aton($ip)
	{
		$chunks = explode('.', $ip);
		return @$chunks[0] * pow(256, 3) + @$chunks[1] * pow(256, 2) + @$chunks[2] * 256 + @$chunks[3];
	}

	/**
	 * Emulate INET_NTOA.
	 *
	 * @param $n
	 */
	function udf_inet_ntoa($n)
	{
		$t = array(0, 0, 0, 0);
		$msk = 16777216.0;
		$n += 0.0;
			if ($n < 1)
				return '0.0.0.0';

		for ($i = 0; $i < 4; $i++)
		{
			$k = (int) ($n / $msk);
			$n -= $msk * $k;
			$t[$i] = $k;
			$msk /= 256.0;
		};

		$a = join('.', $t);
		return $a;
	}

	/**
	 * Emulate FIND_IN_SET.
	 *
	 * @param $find
	 * @param $groups
	 */
	function udf_find_in_set($find, $groups)
	{
		foreach (explode(',', $groups) as $key => $group)
		{
			if ($group == $find)
				return $key + 1;
		}

		return 0;
	}

	/**
	 * Emulate YEAR.
	 *
	 * @param $date
	 */
	function udf_year($date)
	{
		return substr($date, 0, 4);
	}

	/**
	 * Emulate MONTH.
	 *
	 * @param $date
	 */
	function udf_month($date)
	{
		return substr($date, 5, 2);
	}

	/**
	 * Emulate DAYOFMONTH.
	 *
	 * @param $date
	 */
	function udf_dayofmonth($date)
	{
		return substr($date, 8, 2);
	}

	/**
	 * This function uses variable argument lists so that it can handle more then two parameters.
	 * Emulates the CONCAT function.
	 */
	function udf_concat()
	{
		// Since we didn't specify any arguments we must get them from PHP.
		$args = func_get_args();

		// It really doesn't matter if there were 0 to 100 arguments, just slap them all together.
		return implode('', $args);
	}

	/**
	 * We need to use PHP to locate the position in the string.
	 *
	 * @param string $find
	 * @param string $string
	 */
	function udf_locate($find, $string)
	{
		return strpos($string, $find);
	}

	/**
	 * This is used to replace RLIKE.
	 *
	 * @param string $exp
	 * @param string $search
	 */
	function udf_regexp($exp, $search)
	{
		if (preg_match($exp, $match))
			return 1;
		return 0;
	}

	/**
	 * Escape the LIKE wildcards so that they match the character and not the wildcard.
	 * The optional second parameter turns human readable wildcards into SQL wildcards.
	 */
	function escape_wildcard_string($string, $translate_human_wildcards=false)
	{
		$replacements = array(
			'%' => '\%',
			'\\' => '\\\\',
		);

		if ($translate_human_wildcards)
			$replacements += array(
				'*' => '%',
			);

		return strtr($string, $replacements);
	}

	/**
	 * Returns whether the database system supports ignore.
	 *
	 * @return bool
	 */
	function support_ignore()
	{
		return false;
	}

	/**
	 * Gets all the necessary INSERTs for the table named table_name.
	 * It goes in 250 row segments.
	 *
	 * @param string $tableName - the table to create the inserts for.
	 * @param bool new_table
	 * @return string the query to insert the data back in, or an empty string if the table was empty.
	 */
	function insert_sql($tableName, $new_table = false)
	{
		global $db_prefix;

		static $start = 0, $num_rows, $fields, $limit;

		if ($new_table)
		{
			$limit = strstr($tableName, 'log_') !== false ? 500 : 250;
			$start = 0;
		}

		$data = '';
		$tableName = str_replace('{db_prefix}', $db_prefix, $tableName);

		// This will be handy...
		$crlf = "\r\n";

		$result = $this->query('', '
			SELECT *
			FROM ' . $tableName . '
			LIMIT ' . $start . ', ' . $limit,
			array(
				'security_override' => true,
			)
		);

		// The number of rows, just for record keeping and breaking INSERTs up.
		$num_rows = $this->num_rows($result);

		if ($num_rows == 0)
			return '';

		if ($new_table)
		{
			$fields = array_keys($this->fetch_assoc($result));

			// SQLite fetches an array so we need to filter out the numberic index for the columns.
			foreach ($fields as $key => $name)
				if (is_numeric($name))
					unset($fields[$key]);

			$this->data_seek($result, 0);
		}

		// Start it off with the basic INSERT INTO.
		$data = 'BEGIN TRANSACTION;' . $crlf;
		$insert_msg = $crlf . 'INSERT INTO ' . $tableName . $crlf . "\t" . '(' . implode(', ', $fields) . ')' . $crlf . 'VALUES ' . $crlf . "\t";

		// Loop through each row.
		while ($row = $this->fetch_assoc($result))
		{
			// Get the fields in this row...
			$field_list = array();

			foreach ($row as $key => $item)
			{
				// Try to figure out the type of each field. (NULL, number, or 'string'.)
				if (!isset($item))
					$field_list[] = 'NULL';
				elseif (is_numeric($item) && (int) $item == $item)
					$field_list[] = $item;
				else
					$field_list[] = '\'' . $this->escape_string($item) . '\'';
			}

			// 'Insert' the data.
			$data .= $insert_msg . '(' . implode(', ', $field_list) . ');' . $crlf;
		}
		$this->free_result($result);

		$data .= $crlf;

		$start += $limit;

		return $data;
	}

	/**
	 * Dumps the schema (CREATE) for a table.
	 *
	 * @param string $tableName - the table
	 * @return string - the CREATE statement as string
	 */
	function db_table_sql($tableName)
	{
		global $db_prefix;

		$tableName = str_replace('{db_prefix}', $db_prefix, $tableName);

		// This will be needed...
		$crlf = "\r\n";

		// Start the create table...
		$schema_create = '';
		$index_create = '';

		// Let's get the create statement directly from SQLite.
		$result = $this->query('', '
			SELECT sql
			FROM sqlite_master
			WHERE type = {string:type}
				AND name = {string:table_name}',
			array(
				'type' => 'table',
				'table_name' => $tableName,
			)
		);
		list ($schema_create) = $this->fetch_row($result);
		$this->free_result($result);

		// Now the indexes.
		$result = $this->query('', '
			SELECT sql
			FROM sqlite_master
			WHERE type = {string:type}
				AND tbl_name = {string:table_name}',
			array(
				'type' => 'index',
				'table_name' => $tableName,
			)
		);
		$indexes = array();
		while ($row = $this->fetch_assoc($result))
			if (trim($row['sql']) != '')
				$indexes[] = $row['sql'];
		$this->free_result($result);

		$index_create .= implode(';' . $crlf, $indexes);
		$schema_create = empty($indexes) ? rtrim($schema_create) : $schema_create . ';' . $crlf . $crlf;

		return $schema_create . $index_create;
	}

	/**
	 * This function lists all tables in the database.
	 * The listing could be filtered according to $filter.
	 *
	 * @param mixed $db_name_str string holding the database name, or false, default false
	 * @param mixed $filter string to filter by, or false, default false
	 * @return array an array of table names. (strings)
	 */
	function db_list_tables($db_name_str = false, $filter = false)
	{
		$filter = $filter == false ? '' : ' AND name LIKE \'' . str_replace("\_", "_", $filter) . '\'';

		$request = $this->query('', '
			SELECT name
			FROM sqlite_master
			WHERE type = {string:type}
			{raw:filter}
			ORDER BY name',
			array(
				'type' => 'table',
				'filter' => $filter,
			)
		);
		$tables = array();
		while ($row = $this->fetch_row($request))
			$tables[] = $row[0];
		$this->free_result($request);

		return $tables;
	}

	/**
	 * This function optimizes a table.
	 *
	 * @param string $table - the table to be optimized
	 * @return how much it was gained
	 */
	function db_optimize_table($table)
	{
		global $db_prefix;

		$table = str_replace('{db_prefix}', $db_prefix, $table);

		$request = $this->query('', '
			VACUUM {raw:table}',
			array(
				'table' => $table,
			)
		);
		if (!$request)
			return -1;

		$row = $this->fetch_assoc($request);
		$this->free_result($request);

		// The function returns nothing.
		return 0;
	}

	/**
	 * Backup $table to $backup_table.
	 *
	 * @param string $table
	 * @param string $backup_table
	 * @return resource -the request handle to the table creation query
	 */
	function db_backup_table($table, $backup_table)
	{
		global $db_prefix;

		$table = str_replace('{db_prefix}', $db_prefix, $table);

		$result = $this->query('', '
			SELECT sql
			FROM sqlite_master
			WHERE type = {string:txttable}
				AND name = {string:table}',
			array(
				'table' => $table,
				'txttable' => 'table'
			)
		);
		list ($create) = $this->fetch_row($result);
		$this->free_result($result);

		$create = preg_split('/[\n\r]/', $create);
		$auto_inc = '';

		// Remove the first line and check to see if the second one contain useless info.
		unset($create[0]);
		if (trim($create[1]) == '(')
			unset($create[1]);
		if (trim($create[count($create)]) == ')')
			unset($create[count($create)]);

		foreach ($create as $k => $l)
		{
			// Get the name of the auto_increment column.
			if (strpos($l, 'primary') || strpos($l, 'PRIMARY'))
				$auto_inc = trim($l);

			// Skip everything but keys...
			if ((strpos($l, 'KEY') !== false && strpos($l, 'PRIMARY KEY') === false) || strpos($l, $table) !== false || strpos(trim($l), 'PRIMARY KEY') === 0)
				unset($create[$k]);
		}

		if (!empty($create))
			$create = '(
				' . implode('
				', $create) . ')';
		else
			$create = '';

		// Is there an extra junk at the end?
		if (substr($create, -2, 1) == ',')
			$create = substr($create, 0, -2) . ')';
		if (substr($create, -2) == '))')
			$create = substr($create, 0, -1);

		$this->query('', '
			DROP TABLE {raw:backup_table}',
			array(
				'backup_table' => $backup_table,
				'db_error_skip' => true,
			)
		);

		$request = $this->quote('
			CREATE TABLE {raw:backup_table} {raw:create}',
			array(
				'backup_table' => $backup_table,
				'create' => $create,
		));

		$this->query('', '
			CREATE TABLE {raw:backup_table} {raw:create}',
			array(
				'backup_table' => $backup_table,
				'create' => $create,
		));

		$request = $this->query('', '
			INSERT INTO {raw:backup_table}
			SELECT *
			FROM {raw:table}',
			array(
				'backup_table' => $backup_table,
				'table' => $table,
		));

		return $request;
	}

	/**
	 * Simply return the database - and die.
	 * Only needed for SQLite.
	 * Used by DumpDatabase.php.
	 */
	function db_get_backup()
	{
		global $db_name;

		$db_file = substr($db_name, -3) === '.db' ? $db_name : $db_name . '.db';

		// Add more info if zipped...
		$ext = '';
		if (isset($_REQUEST['compress']) && function_exists('gzencode'))
			$ext = '.gz';

		// Do the remaining headers.
		header('Content-Disposition: attachment; filename="' . $db_file . $ext . '"');
		header('Cache-Control: private');
		header('Connection: close');

		// Literally dump the contents.  Try reading the file first.
		if (@readfile($db_file) == null)
			echo file_get_contents($db_file);

		obExit(false);
	}

	/**
	 * Get the server version number.
	 * For sqlite, that means the library version.
	 *
	 * @return string - the version
	 */
	function db_server_version()
	{
		return sqlite_libversion();
	}

	/**
	 * Escape string for the database input
	 *
	 * @param string $string
	 */
	function escape_string($string)
	{
		return sqlite_escape_string($string);
	}

	/**
	 * Fetch next result as association.
	 * The sqlite implementation simply delegates to sqlite_fetch_array().
	 * It requires $request parameter to be int.
	 * It ignores $counter parameter.
	 *
	 * @param int $request
	 * @param mixed counter = false
	 */
	function fetch_assoc($request, $counter = false)
	{
		return sqlite_fetch_array($request);
	}

	/**
	 * Return server info.
	 *
	 * @return string - the version
	 */
	function db_server_info()
	{
		// give info on library version
		return sqlite_libversion();
	}

	/**
	 * Get the client version number.
	 *
	 * @return string - the version
	 */
	function db_client_version()
	{
		return sqlite_libversion();
	}

	/**
	 * Get the name (title) of the database system.
	 */
	function db_title()
	{
		return 'SQLite';
	}

	/**
	 * Whether the database system is case sensitive.
	 *
	 * @return bool
	 */
	function db_case_sensitive()
	{
		return true;
	}

/**
	 * Select database.
	 *
	 * @param string $dbName = null
	 * @param resource $connection = null
	 */
	function select_db($dbName = null, $connection = null)
	{
		return true;
	}

	/**
	 * Returns a reference to the existing instance
	 */
	static function db()
	{
		return self::$_db;
	}
}
