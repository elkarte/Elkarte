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
 * copyright:	2004-2011, GreyWyvern - All rights reserved.
 * license:  	BSD, See included LICENSE.TXT for terms and conditions.
 *
 * @version 2.0 dev
 *
 */

namespace ElkArte\Database\Mysqli;

use ElkArte\Database\AbstractQuery;

/**
 * SQL database class, implements database class to control mysql functions
 */
class Query extends AbstractQuery
{
	/**
	 * {@inheritDoc}
	 */
	public function fix_prefix($db_prefix, $db_name)
	{
		$db_prefix = is_numeric(substr($db_prefix, 0, 1)) ? $db_name . '.' . $db_prefix : '`' . $db_name . '`.' . $db_prefix;

		return $db_prefix;
	}

	/**
	 * {@inheritDoc}
	 */
	public function query($identifier, $db_string, $db_values = array())
	{
		global $db_show_debug, $time_start, $modSettings;

		// One more query....
		$this->_query_count++;

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

		$db_string = $this->_prepareQuery($db_string, $db_values);

		// Debugging.
		$this->_preQueryDebug($db_string);

		$this->_doSanityCheck($db_string, '\\');

		if ($this->_unbuffered === false)
			$ret = @mysqli_query($this->connection, $db_string);
		else
			$ret = @mysqli_query($this->connection, $db_string, MYSQLI_USE_RESULT);

		if ($ret === false && $this->_skip_error === false)
		{
			$ret = $this->error($db_string);
		}

		// Revert not to skip errors
		if ($this->_skip_error === true)
		{
			$this->_skip_error = false;
		}

		// Debugging.
		$this->_postQueryDebug();

		$this->result = new \ElkArte\Database\Mysqli\Result($ret,
			new \ElkArte\ValuesContainer([
				'connection' => $this->connection
			])
		);

		// This is here only for compatibility with the previous database code.
		// To remove when all the instances are fixed.
		if ($ret === false)
		{
			return false;
		}
		else
		{
			return $this->result;
		}
	}

	/**
	 * Checks if the string contains any 4byte chars and if so,
	 * converts them into HTML entities.
	 *
	 * This is necessary because MySQL utf8 doesn't know how to store such
	 * characters and would generate an error any time one is used.
	 * The 4byte chars are used by emoji
	 *
	 * @param string $string
	 * @return string
	 */
	protected function _clean_4byte_chars($string)
	{
		global $modSettings;

		if (!empty($modSettings['using_utf8mb4']))
			return $string;

		$result = $string;
		$ord = array_map('ord', str_split($string));

		// If we are in the 4-byte range
		if (max($ord) >= 240)
		{
			// Byte length
			$length = strlen($string);
			$result = '';

			// Look for a 4byte marker
			for ($i = 0; $i < $length; $i++)
			{
				// The first byte of a 4-byte character encoding starts with the bytes 0xF0-0xF4 (240 <-> 244)
				// but look all the way to 247 for safe measure
				$ord1 = $ord[$i];
				if ($ord1 >= 240 && $ord1 <= 247)
				{
					// Replace it with the corresponding html entity
					$entity = $this->_uniord(chr($ord[$i]) . chr($ord[$i + 1]) . chr($ord[$i + 2]) . chr($ord[$i + 3]));
					if ($entity === false)
						$result .= "\xEF\xBF\xBD";
					else
						$result .= '&#x' . dechex($entity) . ';';
					$i += 3;
				}
				else
					$result .= $string[$i];
			}
		}

		return $result;
	}

	/**
	 * Converts a 4byte char into the corresponding HTML entity code.
	 *
	 * This function is derived from:
	 * http://www.greywyvern.com/code/php/utf8_html.phps
	 *
	 * @param string $c
	 * @return integer|false
	 */
	protected function _uniord($c)
	{
		if (ord($c[0]) >= 0 && ord($c[0]) <= 127)
			return ord($c[0]);
		if (ord($c[0]) >= 192 && ord($c[0]) <= 223)
			return (ord($c[0]) - 192) * 64 + (ord($c[1]) - 128);
		if (ord($c[0]) >= 224 && ord($c[0]) <= 239)
			return (ord($c[0]) - 224) * 4096 + (ord($c[1]) - 128) * 64 + (ord($c[2]) - 128);
		if (ord($c[0]) >= 240 && ord($c[0]) <= 247)
			return (ord($c[0]) - 240) * 262144 + (ord($c[1]) - 128) * 4096 + (ord($c[2]) - 128) * 64 + (ord($c[3]) - 128);
		if (ord($c[0]) >= 248 && ord($c[0]) <= 251)
			return (ord($c[0]) - 248) * 16777216 + (ord($c[1]) - 128) * 262144 + (ord($c[2]) - 128) * 4096 + (ord($c[3]) - 128) * 64 + (ord($c[4]) - 128);
		if (ord($c[0]) >= 252 && ord($c[0]) <= 253)
			return (ord($c[0]) - 252) * 1073741824 + (ord($c[1]) - 128) * 16777216 + (ord($c[2]) - 128) * 262144 + (ord($c[3]) - 128) * 4096 + (ord($c[4]) - 128) * 64 + (ord($c[5]) - 128);
		if (ord($c[0]) >= 254 && ord($c[0]) <= 255)
			return false;
	}

	/**
	 * {@inheritDoc}
	 */
	public function transaction($type = 'commit')
	{
		if ($type == 'begin')
		{
			return @mysqli_query($this->connection, 'BEGIN');
		}
		elseif ($type == 'rollback')
		{
			return @mysqli_query($this->connection, 'ROLLBACK');
		}
		elseif ($type == 'commit')
		{
			return @mysqli_query($this->connection, 'COMMIT');
		}

		return false;
	}

	/**
	 * {@inheritDoc}
	 */
	public function last_error()
	{
		if (is_object($this->connection))
		{
			return mysqli_error($this->connection);
		}
		else
		{
			return false;
		}
	}

	/**
	 * {@inheritDoc}
	 */
	public function error($db_string)
	{
		global $txt, $context, $webmaster_email, $modSettings, $db_persist;
		global $db_server, $db_user, $db_passwd, $db_name, $db_show_debug, $ssi_db_user, $ssi_db_passwd;

		// Get the file and line numbers.
		list ($file, $line) = $this->error_backtrace('', '', 'return', __FILE__, __LINE__);

		// This is the error message...
		$query_error = mysqli_error($this->connection);
		$query_errno = mysqli_errno($this->connection);

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
				if (class_exists('Errors'))
				{
					\ElkArte\Errors\Errors::instance()->log_error($txt['database_error'] . ': ' . $query_error . (!empty($modSettings['enableErrorQueryLogging']) ? "\n\n$db_string" : ''), 'database', $file, $line);
				}

				return false;
			}
		}

		// Log the error.
		if ($query_errno != 1213 && $query_errno != 1205 && class_exists('Errors'))
		{
			\ElkArte\Errors\ErrorsErrors::instance()->log_error($txt['database_error'] . ': ' . $query_error . (!empty($modSettings['enableErrorQueryLogging']) ? "\n\n$db_string" : ''), 'database', $file, $line);
		}

		// Database error auto fixing ;).
		if (function_exists('\\ElkArte\\Cache\\Cache::instance()->get') && (!isset($modSettings['autoFixDatabase']) || $modSettings['autoFixDatabase'] == '1'))
		{
			$db_last_error = db_last_error();

			// Force caching on, just for the error checking.
			$old_cache = isset($modSettings['cache_enable']) ? $modSettings['cache_enable'] : null;
			$modSettings['cache_enable'] = '1';
			$temp = null;

			if (\ElkArte\Cache\Cache::instance()->getVar($temp, 'db_last_error', 600))
				$db_last_error = max($db_last_error, $temp);

			if ($db_last_error < time() - 3600 * 24 * 3)
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
				// @todo this should go somewhere else, not into the db-mysql layer I think
				require_once(SUBSDIR . '/Admin.subs.php');
				require_once(SUBSDIR . '/Mail.subs.php');

				// Make a note of the REPAIR...
				\ElkArte\Cache\Cache::instance()->put('db_last_error', time(), 600);
				if (!\ElkArte\Cache\Cache::instance()->getVar($temp, 'db_last_error', 600))
					updateDbLastError(time());

				// Attempt to find and repair the broken table.
				foreach ($fix_tables as $table)
				{
					$this->query('', "
						REPAIR TABLE $table", false, false);
				}

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
				$new_connection = false;
				if (in_array($query_errno, array(2006, 2013)))
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
					$this->connection = $new_connection;

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
						theme()->getTemplates()->loadLanguageFile('Errors');

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
			$message = nl2br($query_error) . '<br />' . $txt['file'] . ': ' . $file . '<br />' . $txt['line'] . ': ' . $line;
		else
			$message = $txt['try_again'];

		// Add database version that we know of, for the admin to know. (and ask for support)
		if (allowedTo('admin_forum'))
			$message .= '<br /><br />' . sprintf($txt['database_error_versions'], $modSettings['elkVersion']);

		if (allowedTo('admin_forum') && $db_show_debug === true)
			$message .= '<br /><br />' . nl2br($db_string);

		// It's already been logged... don't log it again.
		throw new \ElkArte\Exceptions\Exception($message, false);
	}

	/**
	 * {@inheritDoc}
	 */
	public function insert($method = 'replace', $table, $columns, $data, $keys, $disable_trans = false)
	{
		// With nothing to insert, simply return.
		if (empty($data))
			return;

		// Inserting data as a single row can be done as a single array.
		if (!is_array($data[array_rand($data)]))
			$data = array($data);

		// Replace the prefix holder with the actual prefix.
		$table = str_replace('{db_prefix}', $this->_db_prefix, $table);

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
		{
			$insertRows[] = $this->quote($insertData, $this->_array_combine($indexed_columns, $dataRow));
		}

		// Determine the method of insertion.
		$queryTitle = $method === 'replace' ? 'REPLACE' : ($method == 'ignore' ? 'INSERT IGNORE' : 'INSERT');

		$skip_error = $table === $this->_db_prefix . 'log_errors';
		$this->_skip_error = $skip_error;
		// Do the insert.
		$ret = $this->query('', '
			' . $queryTitle . ' INTO ' . $table . '(`' . implode('`, `', $indexed_columns) . '`)
			VALUES
				' . implode(',
				', $insertRows),
			array(
				'security_override' => true,
			)
		);

		$this->result = new \ElkArte\Database\Mysqli\Result(
			is_object($ret) ? $ret->getResultObject() : $ret,
			new \ElkArte\ValuesContainer([
				'connection' => $this->connection
			])
		);

		return $this->result;
	}

	/**
	 * Unescape an escaped string!
	 *
	 * @param string $string
	 *
	 * @return string
	 */
	public function unescape_string($string)
	{
		return stripslashes($string);
	}

	/**
	 * {@inheritDoc}
	 */
	public function support_ignore()
	{
		return true;
	}

	/**
	 * {@inheritDoc}
	 */
	public function server_version()
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
	 * {@inheritDoc}
	 */
	public function title()
	{
		return 'MySQL';
	}

	/**
	 * {@inheritDoc}
	 */
	public function case_sensitive()
	{
		return false;
	}

	/**
	 * {@inheritDoc}
	 */
	public function escape_string($string)
	{
		$string = $this->_clean_4byte_chars($string);

		return mysqli_real_escape_string($this->connection, $string);
	}

	/**
	 * {@inheritDoc}
	 */
	public function server_info()
	{
		return mysqli_get_server_info($this->connection);
	}

	/**
	 * {@inheritDoc}
	 */
	public function client_version()
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
	 * {@inheritdoc}
	 */
	public function select_db($dbName = null)
	{
		return mysqli_select_db($this->connection, $dbName);
	}

	/**
	 * {@inheritdoc}
	 */
	public function validConnection()
	{
		return is_object($this->connection);
	}

	/**
	 * {@inheritdoc}
	 */
	protected function _replaceStringCaseSensitive($replacement)
	{
		return 'BINARY ' . $this->_replaceString($replacement);
	}

	/**
	 * {@inheritdoc}
	 */
	protected function _replaceStringCaseInsensitive($replacement)
	{
		return $this->_replaceString($replacement);
	}

	/**
	 * Casts the column to LOWER(column_name) for replacement__callback.
	 *
	 * @param mixed $replacement
	 * @return string
	 */
	protected function _replaceColumnCaseInsensitive($replacement)
	{
		return $replacement;
	}

	/**
	 * {@inheritDoc}
	 */
	public function list_tables($db_name_str = false, $filter = false)
	{
		$dump = new Dump($this);
		return $dump->list_tables($db_name_str, $filter);
	}

	/**
	 * {@inheritDoc}
	 */
	public function supportMediumtext()
	{
		return true;
	}
}
