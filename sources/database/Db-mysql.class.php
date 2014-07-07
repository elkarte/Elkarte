<?php

/**
 * This file has all the main functions in it that relate to the mysql database.
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

// Let's define the name of the class so that we will be able to use it in the instantiations
if (!defined('DB_TYPE'))
	define('DB_TYPE', 'MySQL');

/**
 * SQL database class, implements database class to control mysql functions
 */
class Database_MySQL implements Database
{
	/**
	 * Holds current instance of the class
	 * @var Database_MySQL
	 */
	private static $_db = null;

	/**
	 * Current connetcion to the database
	 * @var resource
	 */
	private $_connection = null;

	/**
	 * Private constructor.
	 */
	private function __construct()
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
	public static function initiate($db_server, $db_name, $db_user, $db_passwd, $db_prefix, $db_options = array())
	{
		global $mysql_set_mode;

		// Initialize the instance... if not done already!
		if (self::$_db === null)
			self::$_db = new self();

		// Non-standard port
		if (!empty($db_options['port']))
			$db_port = (int) $db_options['port'];
		else
			$db_port = 0;

		// Select the database. Maybe.
		if (empty($db_options['dont_select_db']))
			$connection = @mysqli_connect((!empty($db_options['persist']) ? 'p:' : '') . $db_server, $db_user, $db_passwd, $db_name, $db_port);
		else
			$connection = @mysqli_connect((!empty($db_options['persist']) ? 'p:' : '') . $db_server, $db_user, $db_passwd, '', $db_port);

		// Something's wrong, show an error if its fatal (which we assume it is)
		if (!$connection)
		{
			if (!empty($db_options['non_fatal']))
				return null;
			else
				display_db_error();
		}

		// This makes it possible to automatically change the sql_mode and autocommit if needed.
		if (isset($mysql_set_mode) && $mysql_set_mode === true)
			self::$_db->query('', 'SET sql_mode = \'\', AUTOCOMMIT = 1',
			array(),
			false
		);

		self::$_db->_connection = $connection;

		// Few databases still have not set UTF-8 as their default input charset
		self::$_db->query('', '
			SET NAMES UTF8',
			array(
			)
		);

		return $connection;
	}

	/**
	 * Fix up the prefix so it doesn't require the database to be selected.
	 *
	 * @param string $db_prefix
	 * @param string $db_name
	 *
	 * @return string
	 */
	public function fix_prefix($db_prefix, $db_name)
	{
		$db_prefix = is_numeric(substr($db_prefix, 0, 1)) ? $db_name . '.' . $db_prefix : '`' . $db_name . '`.' . $db_prefix;

		return $db_prefix;
	}

	/**
	 * Callback for preg_replace_callback on the query.
	 * It allows to replace on the fly a few pre-defined strings, for convenience ('query_see_board', 'query_wanna_see_board'), with
	 * their current values from $user_info.
	 * In addition, it performs checks and sanitization on the values sent to the database.
	 *
	 * @param mixed[] $matches
	 */
	public function replacement__callback($matches)
	{
		global $db_callback, $user_info, $db_prefix;

		list ($values, $connection) = $db_callback;

		// Connection gone???  This should *never* happen at this point, yet it does :'(
		if (!is_object($connection))
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
				return sprintf('\'%1$s\'', mysqli_real_escape_string($connection, $replacement));
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
						$replacement[$key] = sprintf('\'%1$s\'', mysqli_real_escape_string($connection, $value));

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
				// Backticks inside identifiers are supported as of MySQL 4.1. We don't need them for Elk.
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
	 * Just like the db_query, escape and quote a string, but not executing the query.
	 *
	 * @param string $db_string
	 * @param mixed[] $db_values
	 * @param resource|null $connection = null
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
	 *
	 * @param string $identifier
	 * @param string $db_string
	 * @param mixed[]|false $db_values = array()
	 * @param resource|false|null $connection = null
	 */
	public function query($identifier, $db_string, $db_values = array(), $connection = null)
	{
		global $db_cache, $db_count, $db_show_debug, $time_start;
		global $db_unbuffered, $db_callback, $modSettings;

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

		// Decide which connection to use.
		$connection = $connection === null ? $this->_connection : $connection;

		// One more query....
		$db_count = !isset($db_count) ? 1 : $db_count + 1;

		if (empty($modSettings['disableQueryCheck']) && strpos($db_string, '\'') !== false && empty($db_values['security_override']))
			$this->error_backtrace('Hacking attempt...', 'Illegal character (\') used in query...', true, __FILE__, __LINE__);

		// Use "ORDER BY null" to prevent Mysql doing filesorts for Group By clauses without an Order By
		if (strpos($db_string, 'GROUP BY') !== false && strpos($db_string, 'ORDER BY') === false && strpos($db_string, 'INSERT INTO') === false)
		{
			// Add before LIMIT
			if ($pos = strpos($db_string, 'LIMIT '))
				$db_string = substr($db_string, 0, $pos) . "\t\t\tORDER BY null\n" . substr($db_string, $pos, strlen($db_string));
			else
				// Append it.
				$db_string .= "\n\t\t\tORDER BY null";
		}

		if (empty($db_values['security_override']) && (!empty($db_values) || strpos($db_string, '{db_prefix}') !== false))
		{
			// Pass some values to the global space for use in the callback function.
			$db_callback = array($db_values, $connection);

			// Inject the values passed to this function.
			$db_string = preg_replace_callback('~{([a-z_]+)(?::([a-zA-Z0-9_-]+))?}~', array($this, 'replacement__callback'), $db_string);

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

			// Don't overload it.
			$st = microtime(true);
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

			// Comments?  We don't use comments in our queries, we leave 'em outside!
			if (strpos($clean, '/*') > 2 || strpos($clean, '--') !== false || strpos($clean, ';') !== false)
				$fail = true;
			// Trying to change passwords, slow us down, or something?
			elseif (strpos($clean, 'sleep') !== false && preg_match('~(^|[^a-z])sleep($|[^[_a-z])~s', $clean) != 0)
				$fail = true;
			elseif (strpos($clean, 'benchmark') !== false && preg_match('~(^|[^a-z])benchmark($|[^[a-z])~s', $clean) != 0)
				$fail = true;

			if (!empty($fail) && function_exists('log_error'))
				$this->error_backtrace('Hacking attempt...', 'Hacking attempt...' . "\n" . $db_string, E_USER_ERROR, __FILE__, __LINE__);
		}

		if (empty($db_unbuffered))
			$ret = @mysqli_query($connection, $db_string);
		else
			$ret = @mysqli_query($connection, $db_string, MYSQLI_USE_RESULT);

		if ($ret === false && empty($db_values['db_error_skip']))
			$ret = $this->error($db_string, $connection);

		// Debugging.
		if (isset($db_show_debug) && $db_show_debug === true)
			$db_cache[$db_count]['t'] = microtime(true) - $st;

		return $ret;
	}

	/**
	 * Affected rows from previous operation.
	 *
	 * @param resource|null $connection
	 */
	public function affected_rows($connection = null)
	{
		return mysqli_affected_rows($connection === null ? $this->_connection : $connection);
	}

	/**
	 * Last inserted id.
	 *
	 * @param string $table
	 * @param string|null $field = null
	 * @param resource|null $connection = null
	 */
	public function insert_id($table, $field = null, $connection = null)
	{
		// MySQL doesn't need the table or field information.
		return mysqli_insert_id($connection === null ? $this->_connection : $connection);
	}

	/**
	 * Fetch a row from the resultset given as parameter.
	 * MySQL implementation doesn't use $counter parameter.
	 *
	 * @param resource $result
	 * @param boolean $counter = false
	 */
	public function fetch_row($result, $counter = false)
	{
		// Just delegate to MySQL's function
		return mysqli_fetch_row($result);
	}

	/**
	 * Free the resultset.
	 *
	 * @param resource $result
	 */
	public function free_result($result)
	{
		// Just delegate to MySQL's function
		mysqli_free_result($result);
	}

	/**
	 * Get the number of rows in the result.
	 *
	 * @param resource $result
	 */
	public function num_rows($result)
	{
		// Simply delegate to the native function
		return mysqli_num_rows($result);
	}

	/**
	 * Get the number of fields in the resultset.
	 *
	 * @param resource $request
	 */
	public function num_fields($request)
	{
		return mysqli_num_fields($request);
	}

	/**
	 * Reset the internal result pointer.
	 *
	 * @param resource $request
	 * @param integer $counter
	 */
	public function data_seek($request, $counter)
	{
		// Delegate to native mysql function
		return mysqli_data_seek($request, $counter);
	}

	/**
	 * Do a transaction.
	 *
	 * @param string $type - the step to perform (i.e. 'begin', 'commit', 'rollback')
	 * @param resource|null $connection = null
	 */
	public function db_transaction($type = 'commit', $connection = null)
	{
		// Decide which connection to use
		$connection = $connection === null ? $this->_connection : $connection;

		if ($type == 'begin')
			return @mysqli_query($connection, 'BEGIN');
		elseif ($type == 'rollback')
			return @mysqli_query($connection, 'ROLLBACK');
		elseif ($type == 'commit')
			return @mysqli_query($connection, 'COMMIT');

		return false;
	}

	/**
	 * Return last error string from the database server
	 *
	 * @param resource|null $connection = null
	 */
	public function last_error($connection = null)
	{
		// Decide which connection to use
		$connection = $connection === null ? $this->_connection : $connection;

		if (is_object($connection))
			return mysqli_error($connection);
	}

	/**
	 * Database error.
	 * Backtrace, log, try to fix.
	 *
	 * @param string $db_string
	 * @param resource|null $connection = null
	 */
	public function error($db_string, $connection = null)
	{
		global $txt, $context, $webmaster_email, $modSettings;
		global $db_last_error, $db_persist;
		global $db_server, $db_user, $db_passwd, $db_name, $db_show_debug, $ssi_db_user, $ssi_db_passwd;

		// Get the file and line numbers.
		list ($file, $line) = $this->error_backtrace('', '', 'return', __FILE__, __LINE__);

		// Decide which connection to use
		$connection = $connection === null ? $this->_connection : $connection;

		// This is the error message...
		$query_error = mysqli_error($connection);
		$query_errno = mysqli_errno($connection);

		// Error numbers:
		//    1016: Can't open file '....MYI'
		//    1030: Got error ??? from table handler.
		//    1034: Incorrect key file for table.
		//    1035: Old key file for table.
		//    1142: Command denied
		//    1205: Lock wait timeout exceeded.
		//    1213: Deadlock found.
		//    2006: Server has gone away.
		//    2013: Lost connection to server during query.

		// We cannot do something, try to find out what and act accordingly
		if ($query_errno == 1142)
		{
			$command = substr(trim($db_string), 0, 6);
			if ($command === 'DELETE' || $command === 'UPDATE' || $command === 'INSERT')
			{
				// We can try to ignore it (warning the admin though it's a thing to do)
				// and serve the page just SELECTing
				$_SESSION['query_command_denied'][$command] = $query_error;

				// Let the admin know there is a command denied issue
				if (function_exists('log_error'))
					log_error($txt['database_error'] . ': ' . $query_error . (!empty($modSettings['enableErrorQueryLogging']) ? "\n\n$db_string" : ''), 'database', $file, $line);

				return false;
			}
		}

		// Log the error.
		if ($query_errno != 1213 && $query_errno != 1205 && function_exists('log_error'))
			log_error($txt['database_error'] . ': ' . $query_error . (!empty($modSettings['enableErrorQueryLogging']) ? "\n\n$db_string" : ''), 'database', $file, $line);

		// Database error auto fixing ;).
		if (function_exists('cache_get_data') && (!isset($modSettings['autoFixDatabase']) || $modSettings['autoFixDatabase'] == '1'))
		{
			// Force caching on, just for the error checking.
			$old_cache = isset($modSettings['cache_enable']) ? $modSettings['cache_enable'] : null;
			$modSettings['cache_enable'] = '1';

			if (($temp = cache_get_data('db_last_error', 600)) !== null)
				$db_last_error = max(@$db_last_error, $temp);

			if (@$db_last_error < time() - 3600 * 24 * 3)
			{
				// We know there's a problem... but what?  Try to auto detect.
				if ($query_errno == 1030 && strpos($query_error, ' 127 ') !== false)
				{
					preg_match_all('~(?:[\n\r]|^)[^\']+?(?:FROM|JOIN|UPDATE|TABLE) ((?:[^\n\r(]+?(?:, )?)*)~s', $db_string, $matches);

					$fix_tables = array();
					foreach ($matches[1] as $tables)
					{
						$tables = array_unique(explode(',', $tables));
						foreach ($tables as $table)
						{
							// Now, it's still theoretically possible this could be an injection.  So backtick it!
							if (trim($table) != '')
								$fix_tables[] = '`' . strtr(trim($table), array('`' => '')) . '`';
						}
					}

					$fix_tables = array_unique($fix_tables);
				}
				// Table crashed.  Let's try to fix it.
				elseif ($query_errno == 1016)
				{
					if (preg_match('~\'([^\.\']+)~', $query_error, $match) != 0)
						$fix_tables = array('`' . $match[1] . '`');
				}
				// Indexes crashed.  Should be easy to fix!
				elseif ($query_errno == 1034 || $query_errno == 1035)
				{
					preg_match('~\'([^\']+?)\'~', $query_error, $match);
					$fix_tables = array('`' . $match[1] . '`');
				}
			}

			// Check for errors like 145... only fix it once every three days, and send an email. (can't use empty because it might not be set yet...)
			if (!empty($fix_tables))
			{
				// subs/Admin.subs.php for updateDbLastError(), subs/Mail.subs.php for sendmail().
				require_once(SUBSDIR . '/Admin.subs.php');
				require_once(SUBSDIR . '/Mail.subs.php');

				// Make a note of the REPAIR...
				cache_put_data('db_last_error', time(), 600);
				if (($temp = cache_get_data('db_last_error', 600)) === null)
					updateDbLastError(time());

				// Attempt to find and repair the broken table.
				foreach ($fix_tables as $table)
					$this->query('', "
						REPAIR TABLE $table", false, false);

				// And send off an email!
				sendmail($webmaster_email, $txt['database_error'], $txt['tried_to_repair']);

				$modSettings['cache_enable'] = $old_cache;

				// Try the query again...?
				$ret = $this->query('', $db_string, false, false);
				if ($ret !== false)
					return $ret;
			}
			else
				$modSettings['cache_enable'] = $old_cache;

			// Check for the "lost connection" or "deadlock found" errors - and try it just one more time.
			if (in_array($query_errno, array(1205, 1213, 2006, 2013)))
			{
				if (in_array($query_errno, array(2006, 2013)) && $this->_connection == $connection)
				{
					// Are we in SSI mode?  If so try that username and password first
					if (ELK == 'SSI' && !empty($ssi_db_user) && !empty($ssi_db_passwd))
						$new_connection = @mysqli_connect((!empty($db_persist) ? 'p:' : '') . $db_server, $ssi_db_user, $ssi_db_passwd, $db_name);

					// Fall back to the regular username and password if need be
					if (!$new_connection)
						$new_connection = @mysqli_connect((!empty($db_persist) ? 'p:' : '') . $db_server, $db_user, $db_passwd, $db_name);
				}

				if ($new_connection)
				{
					$this->_connection = $new_connection;

					// Try a deadlock more than once more.
					for ($n = 0; $n < 4; $n++)
					{
						$ret = $this->query('', $db_string, false, false);

						$new_errno = mysqli_errno($new_connection);
						if ($ret !== false || in_array($new_errno, array(1205, 1213)))
							break;
					}

					// If it failed again, shucks to be you... we're not trying it over and over.
					if ($ret !== false)
						return $ret;
				}
			}
			// Are they out of space, perhaps?
			elseif ($query_errno == 1030 && (strpos($query_error, ' -1 ') !== false || strpos($query_error, ' 28 ') !== false || strpos($query_error, ' 12 ') !== false))
			{
				if (!isset($txt))
					$query_error .= ' - check database storage space.';
				else
				{
					if (!isset($txt['mysql_error_space']))
						loadLanguage('Errors');

					$query_error .= !isset($txt['mysql_error_space']) ? ' - check database storage space.' : $txt['mysql_error_space'];
				}
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

		// Add database version that we know of, for the admin to know. (and ask for support)
		if (allowedTo('admin_forum'))
			$context['error_message'] .= '<br /><br />' . sprintf($txt['database_error_versions'], $modSettings['elkVersion']);

		if (allowedTo('admin_forum') && isset($db_show_debug) && $db_show_debug === true)
			$context['error_message'] .= '<br /><br />' . nl2br($db_string);

		// It's already been logged... don't log it again.
		fatal_error($context['error_message'], false);
	}

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
	public function insert($method = 'replace', $table, $columns, $data, $keys, $disable_trans = false, $connection = null)
	{
		global $db_prefix;

		$connection = $connection === null ? $this->_connection : $connection;

		// With nothing to insert, simply return.
		if (empty($data))
			return;

		// Replace the prefix holder with the actual prefix.
		$table = str_replace('{db_prefix}', $db_prefix, $table);

		// Inserting data as a single row can be done as a single array.
		if (!is_array($data[array_rand($data)]))
			$data = array($data);

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

		// Determine the method of insertion.
		$queryTitle = $method == 'replace' ? 'REPLACE' : ($method == 'ignore' ? 'INSERT IGNORE' : 'INSERT');

		// Do the insert.
		$this->query('', '
			' . $queryTitle . ' INTO ' . $table . '(`' . implode('`, `', $indexed_columns) . '`)
			VALUES
				' . implode(',
				', $insertRows),
			array(
				'security_override' => true,
				'db_error_skip' => $table === $db_prefix . 'log_errors',
			),
			$connection
		);
	}

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
	public function unescape_string($string)
	{
		return stripslashes($string);
	}

	/**
	 * Returns whether the database system supports ignore.
	 *
	 * @return true
	 */
	public function support_ignore()
	{
		return true;
	}

	/**
	 * Gets all the necessary INSERTs for the table named table_name.
	 * It goes in 250 row segments.
	 *
	 * @param string $tableName - the table to create the inserts for.
	 * @param bool $new_table
	 *
	 * @return string the query to insert the data back in, or an empty string if the table was empty.
	 */
	public function insert_sql($tableName, $new_table = false)
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
			SELECT /*!40001 SQL_NO_CACHE */ *
			FROM `' . $tableName . '`
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
		$data = 'INSERT INTO `' . $tableName . '`' . $crlf . "\t" . '(`' . implode('`, `', $fields) . '`)' . $crlf . 'VALUES ';

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

			$data .= '(' . implode(', ', $field_list) . '),' . $crlf . "\t";
		}

		$this->free_result($result);
		$data = substr(trim($data), 0, -1) . ';' . $crlf . $crlf;

		$start += $limit;

		return $data;
	}

	/**
	 * Dumps the schema (CREATE) for a table.
	 *
	 * @param string $tableName - the table
	 *
	 * @return string - the CREATE statement as string
	 */
	public function db_table_sql($tableName)
	{
		global $db_prefix;

		$tableName = str_replace('{db_prefix}', $db_prefix, $tableName);

		// This will be needed...
		$crlf = "\r\n";

		// Drop it if it exists.
		$schema_create = 'DROP TABLE IF EXISTS `' . $tableName . '`;' . $crlf . $crlf;

		// Start the create table...
		$schema_create .= 'CREATE TABLE `' . $tableName . '` (' . $crlf;

		// Find all the fields.
		$result = $this->query('', '
			SHOW FIELDS
			FROM `{raw:table}`',
			array(
				'table' => $tableName,
			)
		);
		while ($row = $this->fetch_assoc($result))
		{
			// Make the CREATE for this column.
			$schema_create .= ' `' . $row['Field'] . '` ' . $row['Type'] . ($row['Null'] != 'YES' ? ' NOT NULL' : '');

			// Add a default...?
			if (!empty($row['Default']) || $row['Null'] !== 'YES')
			{
				// Make a special case of auto-timestamp.
				if ($row['Default'] == 'CURRENT_TIMESTAMP')
					$schema_create .= ' /*!40102 NOT NULL default CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP */';
				// Text shouldn't have a default.
				elseif ($row['Default'] !== null)
				{
					// If this field is numeric the default needs no escaping.
					$type = strtolower($row['Type']);
					$isNumericColumn = strpos($type, 'int') !== false || strpos($type, 'bool') !== false || strpos($type, 'bit') !== false || strpos($type, 'float') !== false || strpos($type, 'double') !== false || strpos($type, 'decimal') !== false;

					$schema_create .= ' default ' . ($isNumericColumn ? $row['Default'] : '\'' . $this->escape_string($row['Default']) . '\'');
				}
			}

			// And now any extra information. (such as auto_increment.)
			$schema_create .= ($row['Extra'] != '' ? ' ' . $row['Extra'] : '') . ',' . $crlf;
		}
		$this->free_result($result);

		// Take off the last comma.
		$schema_create = substr($schema_create, 0, -strlen($crlf) - 1);

		// Find the keys.
		$result = $this->query('', '
			SHOW KEYS
			FROM `{raw:table}`',
			array(
				'table' => $tableName,
			)
		);
		$indexes = array();
		while ($row = $this->fetch_assoc($result))
		{
			// Is this a primary key, unique index, or regular index?
			$row['Key_name'] = $row['Key_name'] == 'PRIMARY' ? 'PRIMARY KEY' : (empty($row['Non_unique']) ? 'UNIQUE ' : ($row['Comment'] == 'FULLTEXT' || (isset($row['Index_type']) && $row['Index_type'] == 'FULLTEXT') ? 'FULLTEXT ' : 'KEY ')) . '`' . $row['Key_name'] . '`';

			// Is this the first column in the index?
			if (empty($indexes[$row['Key_name']]))
				$indexes[$row['Key_name']] = array();

			// A sub part, like only indexing 15 characters of a varchar.
			if (!empty($row['Sub_part']))
				$indexes[$row['Key_name']][$row['Seq_in_index']] = '`' . $row['Column_name'] . '`(' . $row['Sub_part'] . ')';
			else
				$indexes[$row['Key_name']][$row['Seq_in_index']] = '`' . $row['Column_name'] . '`';
		}
		$this->free_result($result);

		// Build the CREATEs for the keys.
		foreach ($indexes as $keyname => $columns)
		{
			// Ensure the columns are in proper order.
			ksort($columns);

			$schema_create .= ',' . $crlf . ' ' . $keyname . ' (' . implode(', ', $columns) . ')';
		}

		// Now just get the comment and type... (MyISAM, etc.)
		$result = $this->query('', '
			SHOW TABLE STATUS
			LIKE {string:table}',
			array(
				'table' => strtr($tableName, array('_' => '\\_', '%' => '\\%')),
			)
		);
		$row = $this->fetch_assoc($result);
		$this->free_result($result);

		// Probably MyISAM.... and it might have a comment.
		$schema_create .= $crlf . ') ENGINE=' . (isset($row['Type']) ? $row['Type'] : $row['Engine']) . ($row['Comment'] != '' ? ' COMMENT="' . $row['Comment'] . '"' : '');

		return $schema_create;
	}

	/**
	 * This function lists all tables in the database.
	 * The listing could be filtered according to $filter.
	 *
	 * @param string|false $db_name_str string holding the database name, or false, default false
	 * @param string|false $filter string to filter by, or false, default false
	 *
	 * @return string[] an array of table names. (strings)
	 */
	public function db_list_tables($db_name_str = false, $filter = false)
	{
		global $db_name;

		$db_name_str = $db_name_str == false ? $db_name : $db_name_str;
		$db_name_str = trim($db_name_str);
		$filter = $filter == false ? '' : ' LIKE \'' . $filter . '\'';

		$request = $this->query('', '
			SHOW TABLES
			FROM `{raw:db_name_str}`
			{raw:filter}',
			array(
				'db_name_str' => $db_name_str[0] == '`' ? strtr($db_name_str, array('`' => '')) : $db_name_str,
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
	 *
	 * @return int how much it was gained
	 */
	public function db_optimize_table($table)
	{
		global $db_prefix;

		$table = str_replace('{db_prefix}', $db_prefix, $table);

		// Get how much overhead there is.
		$request = $this->query('', '
			SHOW TABLE STATUS LIKE {string:table_name}',
			array(
				'table_name' => str_replace('_', '\_', $table),
			)
		);
		$row = $this->fetch_assoc($request);
		$this->free_result($request);

		// Optimize tables that will benefit from this operation.  We don't know what users may
		// have "tweaked" in their installation nor what addons may have installed we simply check
		if (isset($row['Engine']) && $row['Engine'] === 'MyISAM')
		{
			$data_before = isset($row['Data_free']) ? $row['Data_free'] : 0;
			$request = $this->query('', '
				OPTIMIZE TABLE `{raw:table}`',
				array(
					'table' => $table,
				)
			);
			if (!$request)
				return -1;

			// Check again to see what we have saved
			$request = $this->query('', '
				SHOW TABLE STATUS LIKE {string:table}',
				array(
					'table' => str_replace('_', '\_', $table),
				)
			);
			$row = $this->fetch_assoc($request);
			$this->free_result($request);

			// Savings for this table
			$total_change = isset($row['Data_free']) && $data_before > $row['Data_free'] ? $data_before / 1024 : 0;
		}
		else
			$total_change = 0;

		return $total_change;
	}

	/**
	 * Backup $table to $backup_table.
	 *
	 * @param string $table
	 * @param string $backup_table
	 *
	 * @return resource - the request handle to the table creation query
	 */
	public function db_backup_table($table, $backup_table)
	{
		global $db_prefix;

		$table = str_replace('{db_prefix}', $db_prefix, $table);

		// First, get rid of the old table.
		$this->query('', '
			DROP TABLE IF EXISTS {raw:backup_table}',
			array(
				'backup_table' => $backup_table,
			)
		);

		// Can we do this the quick way?
		$result = $this->query('', '
			CREATE TABLE {raw:backup_table} LIKE {raw:table}',
			array(
				'backup_table' => $backup_table,
				'table' => $table
		));
		// If this failed, we go old school.
		if ($result)
		{
			$request = $this->query('', '
				INSERT INTO {raw:backup_table}
				SELECT *
				FROM {raw:table}',
				array(
					'backup_table' => $backup_table,
					'table' => $table
				));

			// Old school or no school?
			if ($request)
				return $request;
		}

		// At this point, the quick method failed.
		$result = $this->query('', '
			SHOW CREATE TABLE {raw:table}',
			array(
				'table' => $table,
			)
		);
		list (, $create) = $this->fetch_row($result);
		$this->free_result($result);

		$create = preg_split('/[\n\r]/', $create);

		$auto_inc = '';

		// Default engine type.
		$engine = 'MyISAM';
		$charset = '';
		$collate = '';

		foreach ($create as $k => $l)
		{
			// Get the name of the auto_increment column.
			if (strpos($l, 'auto_increment'))
				$auto_inc = trim($l);

			// For the engine type, see if we can work out what it is.
			if (strpos($l, 'ENGINE') !== false || strpos($l, 'TYPE') !== false)
			{
				// Extract the engine type.
				preg_match('~(ENGINE|TYPE)=(\w+)(\sDEFAULT)?(\sCHARSET=(\w+))?(\sCOLLATE=(\w+))?~', $l, $match);

				if (!empty($match[1]))
					$engine = $match[1];

				if (!empty($match[2]))
					$engine = $match[2];

				if (!empty($match[5]))
					$charset = $match[5];

				if (!empty($match[7]))
					$collate = $match[7];
			}

			// Skip everything but keys...
			if (strpos($l, 'KEY') === false)
				unset($create[$k]);
		}

		if (!empty($create))
			$create = '(
				' . implode('
				', $create) . ')';
		else
			$create = '';

		$request = $this->query('', '
			CREATE TABLE {raw:backup_table} {raw:create}
			ENGINE={raw:engine}' . (empty($charset) ? '' : ' CHARACTER SET {raw:charset}' . (empty($collate) ? '' : ' COLLATE {raw:collate}')) . '
			SELECT *
			FROM {raw:table}',
			array(
				'backup_table' => $backup_table,
				'table' => $table,
				'create' => $create,
				'engine' => $engine,
				'charset' => empty($charset) ? '' : $charset,
				'collate' => empty($collate) ? '' : $collate,
			)
		);

		if ($auto_inc != '')
		{
			if (preg_match('~\`(.+?)\`\s~', $auto_inc, $match) != 0 && substr($auto_inc, -1, 1) == ',')
				$auto_inc = substr($auto_inc, 0, -1);

			$this->query('', '
				ALTER TABLE {raw:backup_table}
				CHANGE COLUMN {raw:column_detail} {raw:auto_inc}',
				array(
					'backup_table' => $backup_table,
					'column_detail' => $match[1],
					'auto_inc' => $auto_inc,
				)
			);
		}

		return $request;
	}

	/**
	 * Get the version number.
	 *
	 * @return string - the version
	 */
	public function db_server_version()
	{
		$request = $this->query('', '
			SELECT VERSION()',
			array(
			)
		);
		list ($ver) = $this->fetch_row($request);
		$this->free_result($request);

		return $ver;
	}

	/**
	 * Get the name (title) of the database system.
	 *
	 * @return string
	 */
	public function db_title()
	{
		return 'MySQL';
	}

	/**
	 * Whether the database system is case sensitive.
	 *
	 * @return false
	 */
	public function db_case_sensitive()
	{
		return false;
	}

	/**
	 * Escape string for the database input
	 *
	 * @param string $string
	 */
	public function escape_string($string)
	{
		return addslashes($string);
	}

	/**
	 * Fetch next result as association.
	 * The mysql implementation simply delegates to mysqli_fetch_assoc().
	 * It ignores $counter parameter.
	 *
	 * @param resource $request
	 * @param int|false $counter = false
	 */
	public function fetch_assoc($request, $counter = false)
	{
		return mysqli_fetch_assoc($request);
	}

	/**
	 * Return server info.
	 *
	 * @param resource|null $connection
	 *
	 * @return string
	 */
	public function db_server_info($connection = null)
	{
		// Decide which connection to use
		$connection = $connection === null ? $this->_connection : $connection;

		return mysqli_get_server_info($connection);
	}

	/**
	 *  Get the version number.
	 *
	 *  @return string - the version
	 */
	public function db_client_version()
	{
		$request = $this->query('', '
			SELECT VERSION()',
			array(
			)
		);
		list ($ver) = $this->fetch_row($request);
		$this->free_result($request);

		return $ver;
	}

	/**
	 * Select database.
	 *
	 * @param string|null $dbName = null
	 * @param resource|null $connection = null
	 */
	public function select_db($dbName = null, $connection = null)
	{
		// Decide which connection to use
		$connection = $connection === null ? $this->_connection : $connection;

		return mysqli_select_db($connection, $dbName);
	}

	/**
	 * Retrieve the connection object
	 *
	 * @return resource
	 */
	public function connection()
	{
		// find it, find it
		return $this->_connection;
	}

	/**
	 * Returns a reference to the existing instance
	 */
	public static function db()
	{
		return self::$_db;
	}
}