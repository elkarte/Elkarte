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

class Database_PostgreSQL implements Database
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
	 * @param type $db_server
	 * @param type $db_name
	 * @param type $db_user
	 * @param type $db_passwd
	 * @param type $db_prefix
	 * @param type $db_options
	 *
	 * @return resource
	 */
	static function initiate($db_server, $db_name, $db_user, $db_passwd, &$db_prefix, $db_options = array())
	{
		// initialize the instance... if not done already!
		if (self::$_db === null)
			self::$_db = new self();

		if (!empty($db_options['persist']))
			$connection = @pg_pconnect('host=' . $db_server . ' dbname=' . $db_name . ' user=\'' . $db_user . '\' password=\'' . $db_passwd . '\'');
		else
			$connection = @pg_connect( 'host=' . $db_server . ' dbname=' . $db_name . ' user=\'' . $db_user . '\' password=\'' . $db_passwd . '\'');

		// Something's wrong, show an error if its fatal (which we assume it is)
		if (!$connection)
		{
			if (!empty($db_options['non_fatal']))
			{
				return null;
			}
			else
			{
				display_db_error();
			}
		}

		return $connection;
	}

	/**
	 * Fix the database prefix if necessary.
	 * Do nothing on postgreSQL
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

		// Connection gone?
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
				return sprintf('\'%1$s\'', pg_escape_string($replacement));
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
						$replacement[$key] = sprintf('\'%1$s\'', pg_escape_string($value));

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
	 * @param type $connection
	 * @return type
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
	 * Special queries may need additional replacements to be appropriate
	 * for PostgreSQL.
	 *
	 * @param string $identifier
	 * @param string $db_string
	 * @param string $db_values
	 * @param type $connection
	 * @return boolean
	 */
	function query($identifier, $db_string, $db_values = array(), $connection = null)
	{
		global $db_cache, $db_count, $db_connection, $db_show_debug, $time_start;
		global $db_unbuffered, $db_callback, $db_last_result, $db_replace_result, $modSettings;

		// Decide which connection to use.
		$connection = $connection === null ? $db_connection : $connection;

		// Special queries that need processing.
		$replacements = array(
			'alter_table_boards' => array(
				'~(.+)~' => '',
			),
			'alter_table_icons' => array(
				'~(.+)~' => '',
			),
			'alter_table_smileys' => array(
				'~(.+)~' => '',
			),
			'alter_table_spiders' => array(
				'~(.+)~' => '',
			),
			'ban_suggest_error_ips' => array(
				'~RLIKE~' => '~',
				'~\\.~' => '\.',
			),
			'ban_suggest_message_ips' => array(
				'~RLIKE~' => '~',
				'~\\.~' => '\.',
			),
			'consolidate_spider_stats' => array(
				'~MONTH\(log_time\), DAYOFMONTH\(log_time\)~' => 'MONTH(CAST(CAST(log_time AS abstime) AS timestamp)), DAYOFMONTH(CAST(CAST(log_time AS abstime) AS timestamp))',
			),
			'delete_subscription' => array(
				'~LIMIT 1~' => '',
			),
			'display_get_post_poster' => array(
				'~GROUP BY id_msg\s+HAVING~' => 'AND',
			),
			'attach_download_increase' => array(
				'~LOW_PRIORITY~' => '',
			),
			'boardindex_fetch_boards' => array(
				'~IFNULL\(lb.id_msg, 0\) >= b.id_msg_updated~' => 'CASE WHEN IFNULL(lb.id_msg, 0) >= b.id_msg_updated THEN 1 ELSE 0 END',
				'~(.)$~' => '$1 ORDER BY b.board_order',
			),
			'get_random_number' => array(
				'~RAND~' => 'RANDOM',
			),
			'insert_log_search_topics' => array(
				'~NOT RLIKE~' => '!~',
			),
			'insert_log_search_results_no_index' => array(
				'~NOT RLIKE~' => '!~',
			),
			'insert_log_search_results_subject' => array(
				'~NOT RLIKE~' => '!~',
			),
			'messageindex_fetch_boards' => array(
				'~(.)$~' => '$1 ORDER BY b.board_order',
			),
			'select_message_icons' => array(
				'~(.)$~' => '$1 ORDER BY icon_order',
			),
			'set_character_set' => array(
				'~SET\\s+NAMES\\s([a-zA-Z0-9\\-_]+)~' => 'SET NAMES \'$1\'',
			),
			'pm_conversation_list' => array(
				'~ORDER\\s+BY\\s+\\{raw:sort\\}~' => 'ORDER BY ' . (isset($db_values['sort']) ? ($db_values['sort'] === 'pm.id_pm' ? 'MAX(pm.id_pm)' : $db_values['sort']) : ''),
			),
			'top_topic_starters' => array(
				'~ORDER BY FIND_IN_SET\(id_member,(.+?)\)~' => 'ORDER BY STRPOS(\',\' || $1 || \',\', \',\' || id_member|| \',\')',
			),
			'order_by_board_order' => array(
				'~(.)$~' => '$1 ORDER BY b.board_order',
			),
			'spider_check' => array(
				'~(.)$~' => '$1 ORDER BY LENGTH(user_agent) DESC',
			),
			'unread_replies' => array(
				'~SELECT\\s+DISTINCT\\s+t.id_topic~' => 'SELECT t.id_topic, {raw:sort}',
			),
			'profile_board_stats' => array(
				'~COUNT\(\*\) \/ MAX\(b.num_posts\)~' => 'CAST(COUNT(*) AS DECIMAL) / CAST(b.num_posts AS DECIMAL)',
			),
			'set_smiley_order' => array(
				'~(.+)~' => '',
			),
		);

		if (isset($replacements[$identifier]))
			$db_string = preg_replace(array_keys($replacements[$identifier]), array_values($replacements[$identifier]), $db_string);

		// Limits need to be a little different.
		$db_string = preg_replace('~\sLIMIT\s(\d+|{int:.+}),\s*(\d+|{int:.+})\s*$~i', 'LIMIT $2 OFFSET $1', $db_string);

		if (trim($db_string) == '')
			return false;

		// Comments that are allowed in a query are preg_removed.
		static $allowed_comments_from = array(
			'~\s+~s',
			'~/\*!40001 SQL_NO_CACHE \*/~',
			'~/\*!40000 USE INDEX \([A-Za-z\_]+?\) \*/~',
			'~/\*!40100 ON DUPLICATE KEY UPDATE id_msg = \d+ \*/~',
		);
		static $allowed_comments_to = array(
			' ',
			'',
			'',
			'',
		);

		// One more query....
		$db_count = !isset($db_count) ? 1 : $db_count + 1;
		$db_replace_result = 0;

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

		// First, we clean strings out of the query, reduce whitespace, lowercase, and trim - so we can check it over.
		if (empty($modSettings['disableQueryCheck']))
		{
			$clean = '';
			$old_pos = 0;
			$pos = -1;
			while (true)
			{
				$pos = strpos($db_string, '\'', $pos + 1);
				if ($pos === false)
					break;
				$clean .= substr($db_string, $old_pos, $pos - $old_pos);

				while (true)
				{
					$pos1 = strpos($db_string, '\'', $pos + 1);
					$pos2 = strpos($db_string, '\\', $pos + 1);
					if ($pos1 === false)
						break;
					elseif ($pos2 == false || $pos2 > $pos1)
					{
						$pos = $pos1;
						break;
					}

					$pos = $pos2 + 1;
				}
				$clean .= ' %s ';

				$old_pos = $pos + 1;
			}
			$clean .= substr($db_string, $old_pos);
			$clean = trim(strtolower(preg_replace($allowed_comments_from, $allowed_comments_to, $clean)));

			// We don't use UNION, at least so far.  But it's useful for injections.
			if (strpos($clean, 'union') !== false && preg_match('~(^|[^a-z])union($|[^[a-z])~s', $clean) != 0)
				$fail = true;
			// Comments?  We don't use comments in our queries, we leave 'em outside!
			elseif (strpos($clean, '/*') > 2 || strpos($clean, '--') !== false || strpos($clean, ';') !== false)
				$fail = true;
			// Trying to change passwords, slow us down, or something?
			elseif (strpos($clean, 'sleep') !== false && preg_match('~(^|[^a-z])sleep($|[^[_a-z])~s', $clean) != 0)
				$fail = true;
			elseif (strpos($clean, 'benchmark') !== false && preg_match('~(^|[^a-z])benchmark($|[^[a-z])~s', $clean) != 0)
				$fail = true;
			// Sub selects?  We don't use those either.
			elseif (preg_match('~\([^)]*?select~s', $clean) != 0)
				$fail = true;

			if (!empty($fail) && function_exists('log_error'))
				$this->error_backtrace('Hacking attempt...', 'Hacking attempt...' . "\n" . $db_string, E_USER_ERROR, __FILE__, __LINE__);
		}

		$db_last_result = @pg_query($connection, $db_string);

		if ($db_last_result === false && empty($db_values['db_error_skip']))
			$db_last_result = $this->error($db_string, $connection);

		// Debugging.
		if (isset($db_show_debug) && $db_show_debug === true)
			$db_cache[$db_count]['t'] = microtime(true) - $st;

		return $db_last_result;
	}

	/**
	 * Affected rows from previous operation.
	 */
	function affected_rows()
	{
		global $db_last_result, $db_replace_result;

		if ($db_replace_result)
			return $db_replace_result;
		elseif ($result === null && !$db_last_result)
			return 0;

		return pg_affected_rows($db_last_result);
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

		if ($connection === false)
			$connection = $db_connection;

		// Try get the last ID for the auto increment field.
		$request = $this->query('', 'SELECT CURRVAL(\'' . $table . '_seq\') AS insertID',
			array(
			)
		);
		if (!$request)
			return false;
		list ($lastID) = $this->fetch_row($request);
		$this->free_result($request);

		return $lastID;
	}

	/**
	 * Free the resultset.
	 *
	 * @param resource $result
	 */
	function free_result($result)
	{
		// Just delegate to the native function
		pg_free_result($result);
	}

	/**
	 * Get the number of rows in the result.
	 *
	 * @param resource $result
	 */
	function num_rows($result)
	{
		// simply delegate to the native function
		return pg_num_rows($result);
	}

	/**
	 * Get the number of fields in the resultset.
	 */
	function num_fields($request)
	{
		return pg_num_fields($request);
	}

	/**
	 * Do a transaction.
	 *
	 * @param string $type - the step to perform (i.e. 'begin', 'commit', 'rollback')
	 * @param resource $connection = null
	 */
	function db_transaction($type = 'commit', $connection = null)
	{
		global $db_connection;

		// Decide which connection to use
		$connection = $connection === null ? $db_connection : $connection;

		if ($type == 'begin')
			return @pg_query($connection, 'BEGIN');
		elseif ($type == 'rollback')
			return @pg_query($connection, 'ROLLBACK');
		elseif ($type == 'commit')
			return @pg_query($connection, 'COMMIT');

		return false;
	}

	/**
	 * Return last error string from the database server
	 *
	 * @param resource $connection = null
	 */
	function last_error($connection = null)
	{
		return pg_last_error($connection);
	}

	/**
	 * Database error.
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
		$query_error = @pg_last_error($connection);

		// Log the error.
		if (function_exists('log_error'))
			log_error($txt['database_error'] . ': ' . $query_error . (!empty($modSettings['enableErrorQueryLogging']) ? "\n\n" .$db_string : ''), 'database', $file, $line);

		// Nothing's defined yet... just die with it.
		if (empty($context) || empty($txt))
			die($query_error);

		// Show an error message, if possible.
		$context['error_title'] = $txt['database_error'];
		if (allowedTo('admin_forum'))
			$context['error_message'] = nl2br($query_error) . '<br />' . $txt['file'] . ': ' . $file . '<br />' . $txt['line'] . ': ' . $line;
		else
			$context['error_message'] = $txt['try_again'];

		// A database error is often the sign of a database in need of upgrade.  Check forum versions, and if not identical suggest an upgrade... (not for Demo/CVS versions!)
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
	 * Tracking the current row.
	 * Fetch a row from the resultset given as parameter.
	 *
	 * @param resource $request
	 * @param bool $counter = false
	 */
	function fetch_row($request, $counter = false)
	{
		global $db_row_count;

		if ($counter !== false)
			return pg_fetch_row($request, $counter);

		// Reset the row counter...
		if (!isset($db_row_count[(int) $request]))
			$db_row_count[(int) $request] = 0;

		// Return the right row.
		return @pg_fetch_row($request, $db_row_count[(int) $request]++);
	}

	/**
	 * Get an associative array
	 *
	 * @param $request
	 * @param $counter
	 */
	function fetch_assoc($request, $counter = false)
	{
		global $db_row_count;

		if ($counter !== false)
			return pg_fetch_assoc($request, $counter);

		// Reset the row counter...
		if (!isset($db_row_count[(int) $request]))
			$db_row_count[(int) $request] = 0;

		// Return the right row.
		return @pg_fetch_assoc($request, $db_row_count[(int) $request]++);
	}

	/**
	 * Reset the internal result pointer.
	 *
	 * @param $request
	 * @param $counter
	 */
	function data_seek($request, $counter)
	{
		global $db_row_count;

		$db_row_count[(int) $request] = $counter;

		return true;
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
	 * Insert data.
	 *
	 * @param string $method - options 'replace', 'ignore', 'insert'
	 * @param $table
	 * @param $columns
	 * @param $data
	 * @param $keys
	 * @param bool $disable_trans = false
	 * @param resource $connection = null
	 */
	function insert($method = 'replace', $table, $columns, $data, $keys, $disable_trans = false, $connection = null)
	{
		global $db_replace_result, $db_in_transact, $db_connection, $db_prefix;

		$connection = $connection === null ? $db_connection : $connection;

		if (empty($data))
			return;

		if (!is_array($data[array_rand($data)]))
			$data = array($data);

		// Replace the prefix holder with the actual prefix.
		$table = str_replace('{db_prefix}', $db_prefix, $table);

		$priv_trans = false;
		if ((count($data) > 1 || $method == 'replace') && !$db_in_transact && !$disable_trans)
		{
			$this->db_transaction('begin', $connection);
			$priv_trans = true;
		}

		// PostgreSQL doesn't support replace: we implement a MySQL-compatible behavior instead
		if ($method == 'replace')
		{
			$count = 0;
			$where = '';
			foreach ($columns as $columnName => $type)
			{
				// Are we restricting the length?
				if (strpos($type, 'string-') !== false)
					$actualType = sprintf($columnName . ' = SUBSTRING({string:%1$s}, 1, ' . substr($type, 7) . '), ', $count);
				else
					$actualType = sprintf($columnName . ' = {%1$s:%2$s}, ', $type, $count);

				// A key? That's what we were looking for.
				if (in_array($columnName, $keys))
					$where .= (empty($where) ? '' : ' AND ') . substr($actualType, 0, -2);
				$count++;
			}

			// Make it so.
			if (!empty($where) && !empty($data))
			{
				foreach ($data as $k => $entry)
				{
					$this->query('', '
						DELETE FROM ' . $table .
						' WHERE ' . $where,
						$entry, $connection
					);
				}
			}
		}

		if (!empty($data))
		{
			// Create the mold for a single row insert.
			$insertData = '(';
			foreach ($columns as $columnName => $type)
			{
				// Are we restricting the length?
				if (strpos($type, 'string-') !== false)
					$insertData .= sprintf('SUBSTRING({string:%1$s}, 1, ' . substr($type, 7) . '), ', $columnName);
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
				$this->query('', '
					INSERT INTO ' . $table . '("' . implode('", "', $indexed_columns) . '")
					VALUES
						' . $entry,
					array(
						'security_override' => true,
						'db_error_skip' => $method == 'ignore' || $table === $db_prefix . 'log_errors',
					),
					$connection
				);
		}

		if ($priv_trans)
			$this->db_transaction('commit', $connection);
	}

	/**
	 * Dummy function really. Doesn't do anything on PostgreSQL.
	 *
	 * @param string $db_name = null
	 * @param resource $db_connection = null
	 */
	function select_db($db_name = null, $db_connection = null)
	{
		return true;
	}

	/**
	 * Get the name (title) of the database system.
	 */
	function db_title()
	{
		return 'PostgreSQL';
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
	 * Escape the LIKE wildcards so that they match the character and not the wildcard.
	 *
	 * @param $string
	 * @param bool $translate_human_wildcards = false, if true, turns human readable wildcards into SQL wildcards.
	 */
	function escape_wildcard_string($string, $translate_human_wildcards=false)
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
			$this->data_seek($result, 0);
		}

		// Start it off with the basic INSERT INTO.
		$data = '';
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
		$schema_create = 'CREATE TABLE ' . $tableName . ' (' . $crlf;
		$index_create = '';
		$seq_create = '';

		// Find all the fields.
		$result = $this->query('', '
			SELECT column_name, column_default, is_nullable, data_type, character_maximum_length
			FROM information_schema.columns
			WHERE table_name = {string:table}
			ORDER BY ordinal_position',
			array(
				'table' => $tableName,
			)
		);
		while ($row = $this->fetch_assoc($result))
		{
			if ($row['data_type'] == 'character varying')
				$row['data_type'] = 'varchar';
			elseif ($row['data_type'] == 'character')
				$row['data_type'] = 'char';
			if ($row['character_maximum_length'])
				$row['data_type'] .= '(' . $row['character_maximum_length'] . ')';

			// Make the CREATE for this column.
			$schema_create .= ' "' . $row['column_name'] . '" ' . $row['data_type'] . ($row['is_nullable'] != 'YES' ? ' NOT NULL' : '');

			// Add a default...?
			if (trim($row['column_default']) != '')
			{
				$schema_create .= ' default ' . $row['column_default'] . '';

				// Auto increment?
				if (preg_match('~nextval\(\'(.+?)\'(.+?)*\)~i', $row['column_default'], $matches) != 0)
				{
					// Get to find the next variable first!
					$count_req = $this->query('', '
						SELECT MAX("{raw:column}")
						FROM {raw:table}',
						array(
							'column' => $row['column_name'],
							'table' => $tableName,
						)
					);
					list ($max_ind) = $this->fetch_row($count_req);
					$this->free_result($count_req);
					// Get the right bloody start!
					$seq_create .= 'CREATE SEQUENCE ' . $matches[1] . ' START WITH ' . ($max_ind + 1) . ';' . $crlf . $crlf;
				}
			}

			$schema_create .= ',' . $crlf;
		}
		$this->free_result($result);

		// Take off the last comma.
		$schema_create = substr($schema_create, 0, -strlen($crlf) - 1);

		$result = $this->query('', '
			SELECT CASE WHEN i.indisprimary THEN 1 ELSE 0 END AS is_primary, pg_get_indexdef(i.indexrelid) AS inddef
			FROM pg_class AS c
				INNER JOIN pg_index AS i ON (i.indrelid = c.oid)
				INNER JOIN pg_class AS c2 ON (c2.oid = i.indexrelid)
			WHERE c.relname = {string:table}',
			array(
				'table' => $tableName,
			)
		);
		$indexes = array();
		while ($row = $this->fetch_assoc($result))
		{
			if ($row['is_primary'])
			{
				if (preg_match('~\(([^\)]+?)\)~i', $row['inddef'], $matches) == 0)
					continue;

				$index_create .= $crlf . 'ALTER TABLE ' . $tableName . ' ADD PRIMARY KEY ("' . $matches[1] . '");';
			}
			else
				$index_create .= $crlf . $row['inddef'] . ';';
		}
		$this->free_result($result);

		// Finish it off!
		$schema_create .= $crlf . ');';

		return $seq_create . $schema_create . $index_create;
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
		$request = $this->query('', '
			SELECT tablename
			FROM pg_tables
			WHERE schemaname = {string:schema_public}' . ($filter == false ? '' : '
				AND tablename LIKE {string:filter}') . '
			ORDER BY tablename',
			array(
				'schema_public' => 'public',
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
				VACUUM ANALYZE {raw:table}',
				array(
					'table' => $table,
				)
			);
		if (!$request)
			return -1;

		$row = $this->fetch_assoc($request);
		$this->free_result($request);

		if (isset($row['Data_free']))
			return $row['Data_free'] / 1024;
		else
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

		// Do we need to drop it first?
		$tables = $this->db_list_tables(false, $backup_table);
		if (!empty($tables))
			$this->query('', '
				DROP TABLE {raw:backup_table}',
				array(
					'backup_table' => $backup_table,
				)
			);

		// @todo Should we create backups of sequences as well?
		$this->query('', '
			CREATE TABLE {raw:backup_table}
			(
				LIKE {raw:table}
				INCLUDING DEFAULTS
			)',
			array(
				'backup_table' => $backup_table,
				'table' => $table,
			)
		);
		$this->query('', '
			INSERT INTO {raw:backup_table}
			SELECT * FROM {raw:table}',
			array(
				'backup_table' => $backup_table,
				'table' => $table,
			)
		);
	}

	/**
	 * Get the server version number.
	 *
	 * @return string - the version
	 */
	function db_server_version()
	{
		$version = pg_version();

		return $version['server'];
	}

	/**
	 * Escape string for the database input
	 *
	 * @param string $string
	 */
	function escape_string($string)
	{
		return pg_escape_string($string);
	}

	/**
	 * Return server info.
	 *
	 * @return string
	 */
	function db_server_info()
	{
		// give info on client! we use it in install and upgrade and such things.
		$version = pg_version();

		return $version['client'];
	}

	/**
	 * Return client version.
	 *
	 * @return string - the version
	 */
	function db_client_version()
	{
		$version = pg_version();

		return $version['client'];
	}

	/**
	 * Returns a reference to the existing instance
	 */
	static function db()
	{
		return self::$_db;
	}
}
