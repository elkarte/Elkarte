<?php

/**
 * This file has all the main functions in it that relate to the mysql database.
 *
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * This file contains code covered by:
 * copyright: 2011 Simple Machines (http://www.simplemachines.org)
 * copyright:    2004-2011, GreyWyvern - All rights reserved.
 *
 * @version 2.0 dev
 *
 */

namespace ElkArte\Database\Mysqli;

use ElkArte\Cache\Cache;
use ElkArte\Database\AbstractQuery;
use ElkArte\Database\AbstractResult;
use ElkArte\Errors\Errors;
use ElkArte\Helper\Util;
use ElkArte\Helper\ValuesContainer;
use ElkArte\Languages\Txt;

/**
 * SQL database class, implements database class to control mysql functions
 */
class Query extends AbstractQuery
{
	/** {@inheritDoc} */
	protected $ilike = ' LIKE ';

	/** {@inheritDoc} */
	protected $not_ilike = ' NOT LIKE ';

	/** {@inheritDoc} */
	protected $rlike = ' RLIKE ';

	/** {@inheritDoc} */
	protected $not_rlike = ' NOT RLIKE ';

	// Error number constants
	private const ERR_COMMAND_DENIED = 1142;
	private const ERR_TABLE_HANDLER = 1030;
	private const ERR_KEY_FILE = 1016;
	private const ERR_INCORRECT_KEY_FILE = 1034;
	private const ERR_OLD_KEY_FILE = 1035;
	private const ERR_LOCK_WAIT_TIMEOUT_EXCEEDED = 1205;
	private const ERR_DEADLOCK_FOUND = 1213;
	private const ERR_SERVER_HAS_GONE_AWAY = 2006;
	private const ERR_LOST_CONNECTION_TO_SERVER = 2013;

	/**
	 * {@inheritDoc}
	 */
	public function fix_prefix($db_prefix, $db_name)
	{
		return is_numeric(substr($db_prefix, 0, 1)) ? $db_name . '.' . $db_prefix : '`' . $db_name . '`.' . $db_prefix;
	}

	/**
	 * {@inheritDoc}
	 */
	public function transaction($type = 'commit')
	{
		if ($type === 'begin')
		{
			return @mysqli_query($this->connection, 'BEGIN');
		}

		if ($type === 'rollback')
		{
			return @mysqli_query($this->connection, 'ROLLBACK');
		}

		if ($type === 'commit')
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

		return false;
	}

	/**
	 * {@inheritDoc}
	 */
	public function insert($method, $table, $columns, $data, $keys, $disable_trans = false)
	{
		[$table, $indexed_columns, $insertRows] = $this->prepareInsert($table, $columns, $data);

		// Determine the method of insertion.
		$queryTitle = $method === 'replace' ? 'REPLACE' : ($method === 'ignore' ? 'INSERT IGNORE' : 'INSERT');

		// Do the insert.
		$this->result = $this->query('', '
			' . $queryTitle . ' INTO ' . $table . '(`' . implode('`, `', $indexed_columns) . '`)
			VALUES
				' . implode(',
				', $insertRows),
			array(
				'security_override' => true,
			)
		);

		$this->result->updateDetails([
			'connection' => $this->connection
		]);

		return $this->result;
	}

	/**
	 * {@inheritDoc}
	 */
	public function replace($table, $columns, $data, $keys, $disable_trans = false)
	{
		return $this->insert('replace', $table, $columns, $data, $keys, $disable_trans);
	}

	/**
	 * {@inheritDoc}
	 */
	protected function initialChecks($db_string, $db_values, $identifier = '')
	{
		// Use "ORDER BY null" to prevent Mysql doing filesorts for Group By clauses without an Order By
		if (strpos($db_string, 'GROUP BY') !== false
			&& strpos($db_string, 'ORDER BY') === false
			&& preg_match('~^\s+SELECT~i', $db_string))
		{
			if (($pos = strpos($db_string, 'LIMIT ')) !== false)
			{
				// Add before LIMIT
				$db_string = substr($db_string, 0, $pos) . "\t\t\tORDER BY null\n" . substr($db_string, $pos, strlen($db_string));
			}
			else
			{
				// Append it.
				$db_string .= "\n\t\t\tORDER BY null";
			}
		}

		return $db_string;
	}

	protected function executeQuery($db_string)
	{
		if (!$this->_unbuffered)
		{
			$this->_db_last_result = @mysqli_query($this->connection, $db_string);
		}
		else
		{
			$this->_db_last_result = @mysqli_query($this->connection, $db_string, MYSQLI_USE_RESULT);
		}

		$this->result = new Result($this->_db_last_result,
			new ValuesContainer([
				'connection' => $this->connection
			])
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function error($db_string)
	{
		global $txt, $modSettings;

		[$file, $line] = $this->backtrace_message();
		$file = $file ?? __FILE__;
		$line = $line ?? __LINE__;

		$query_error = mysqli_error($this->connection);
		$query_errno = mysqli_errno($this->connection);

		// See if there is a recovery we can attempt
		switch ($query_errno)
		{
			case self::ERR_COMMAND_DENIED:
				$check = $this->handleCommandDeniedError($db_string, $query_error, $file, $line);
				break;
			case self::ERR_TABLE_HANDLER:
			case self::ERR_KEY_FILE:
			case self::ERR_INCORRECT_KEY_FILE:
			case self::ERR_OLD_KEY_FILE:
				$check = $this->handleTableOrKeyFileError($db_string, $query_errno, $query_error);
				break;
			case self::ERR_SERVER_HAS_GONE_AWAY:
			case self::ERR_LOST_CONNECTION_TO_SERVER:
				$check = $this->handleConnectionError($db_string);
				break;
			default:
				$check = null;
				break;
		}

		// Did we attempt to do a repair, return those results
		if ($check !== null)
		{
			return $check;
		}

		// Log the error.
		$query_error = $this->handleSpaceError($query_errno, $query_error);
		if ($query_errno !== self::ERR_DEADLOCK_FOUND && $query_errno !== self::ERR_LOCK_WAIT_TIMEOUT_EXCEEDED)
		{
			Errors::instance()->log_error($txt['database_error'] . ': ' . $query_error . (empty($modSettings['enableErrorQueryLogging']) ? '' : "\n\n$db_string"), 'database', $file, $line);
		}

		// Argh, enough said.
		$this->throwError($db_string, $query_error, $file, $line);
	}

	/**
	 * Handles the command denied error and takes appropriate action.
	 *
	 * @param string $db_string The database query string.
	 * @param string $query_error The error message related to the query.
	 * @param string $file The file where the error occurred.
	 * @param int $line The line number where the error occurred.
	 * @return bool|null Returns false if the command is DELETE, UPDATE, or INSERT. Returns null otherwise.
	 */
	private function handleCommandDeniedError($db_string, $query_error, $file, $line)
	{
		global $txt, $modSettings;

		// We cannot do something, try to find out what and act accordingly
		$command = substr(trim($db_string), 0, 6);
		if ($command === 'DELETE' || $command === 'UPDATE' || $command === 'INSERT')
		{
			// We can try to ignore it (warning the admin though it's a thing to do) \
			// and serve the page just SELECTing
			$_SESSION['query_command_denied'][$command] = $query_error;

			// Let the admin know there is a command denied issue
			Errors::instance()->log_error($txt['database_error'] . ': ' . $query_error . (empty($modSettings['enableErrorQueryLogging']) ? '' : "\n\n$db_string"), 'database', $file, $line);

			return false;
		}

		return null;
	}

	/**
	 * Handles table or key file errors in the database.
	 *
	 * @param string $db_string The database query string.
	 * @param int $query_errno The error code of the query.
	 * @param string $query_error The error message of the query.
	 *
	 * @return null|AbstractResult Returns the result of the query if successful, otherwise null.
	 */
	private function handleTableOrKeyFileError($db_string, $query_errno, $query_error)
	{
		global $modSettings;

		if (function_exists('\\ElkArte\\Cache\\Cache::instance()->get')
			&& (!isset($modSettings['autoFixDatabase']) || $modSettings['autoFixDatabase'] === '1'))
		{
			$db_last_error = db_last_error();
			$cache = Cache::instance();

			// Force caching on, just for the error checking.
			$old_cache = $cache->getLevel();
			if ($cache->isEnabled() === false)
			{
				$cache->setLevel(1);
			}

			$temp = null;
			if ($cache->getVar($temp, 'db_last_error', 600))
			{
				$db_last_error = max($db_last_error, $temp);
			}

			// Check for errors like 145... only fix it once every three days, and send an email. (can't use empty because it might not be set yet...)
			if ($db_last_error < time() - 3600 * 24 * 3)
			{
				// We know there's a problem... but what?  Try to auto-detect.
				$fix_tables = $this->getTablesToRepair($db_string, $query_errno, $query_error);
				if ($fix_tables !== false)
				{
					return $this->attemptRepair($fix_tables, $db_string);
				}
			}

			$modSettings['cache_enable'] = $old_cache;
		}

		return null;
	}

	/**
	 * Handles connection errors and tries to reconnect.
	 *
	 * @param string $db_string The database query string.
	 * @return null|AbstractResult Returns the result of the query if successful, otherwise null.
	 */
	private function handleConnectionError($db_string)
	{
		global $db_persist, $db_server, $db_user, $db_passwd, $db_name, $ssi_db_user, $ssi_db_passwd, $db_port;

		// Check for the "lost connection" or "deadlock found" errors - and try it just one more time.
		$new_connection = false;

		// Are we in SSI mode?  If so try that username and password first
		if (ELK === 'SSI' && !empty($ssi_db_user) && !empty($ssi_db_passwd))
		{
			$new_connection = @mysqli_connect((empty($db_persist) ? '' : 'p:') . $db_server, $ssi_db_user, $ssi_db_passwd, $db_name, $db_port ?? null);
		}

		// Fall back to the regular username and password if need be
		if (!$new_connection)
		{
			$new_connection = @mysqli_connect((empty($db_persist) ? '' : 'p:') . $db_server, $db_user, $db_passwd, $db_name, $db_port ?? null);
		}

		if ($new_connection)
		{
			$this->connection = $new_connection;

			// Try a deadlock more than once more.
			for ($n = 0; $n < 4; $n++)
			{
				$ret = $this->query('', $db_string, false);

				$new_errno = mysqli_errno($new_connection);
				if ($ret->hasResults() || in_array($new_errno, [self::ERR_LOCK_WAIT_TIMEOUT_EXCEEDED, self::ERR_DEADLOCK_FOUND]))
				{
					break;
				}
			}

			// If it failed again, shucks to be you... we're not trying it over and over.
			if ($ret->hasResults())
			{
				return $ret;
			}
		}

		return null;
	}

	/**
	 * Handle space error in a database query.
	 *
	 * @param int $query_errno The error number of the query.
	 * @param string $query_error The error message of the query.
	 * @return string The updated error message with space error handling.
	 */
	private function handleSpaceError($query_errno, $query_error)
	{
		global $txt;

		if ($query_errno === self::ERR_TABLE_HANDLER &&
			(strpos($query_error, ' -1 ') !== false
				|| strpos($query_error, ' 28 ') !== false
				|| strpos($query_error, ' 12 ') !== false))
		{
			if (!isset($txt))
			{
				$query_error .= ' - check database storage space.';
			}
			else
			{
				if (!isset($txt['mysql_error_space']))
				{
					Txt::load('Errors');
				}

				$query_error .= $txt['mysql_error_space'] ?? ' - check database storage space.';
			}
		}

		return $query_error;
	}

	/**
	 * Returns an array of tables to repair based on the provided parameters.
	 *
	 * @param string $db_string The database query string.
	 * @param int $query_errno The error number associated with the query.
	 * @param string $query_error The error message associated with the query.
	 * @return array|bool An array of tables to repair, or false if no tables to repair.
	 */
	private function getTablesToRepair($db_string, $query_errno, $query_error)
	{
		if ($query_errno === self::ERR_TABLE_HANDLER && strpos($query_error, ' 127 ') !== false)
		{
			preg_match_all('~(?:[\n\r]|^)[^\']+?(?:FROM|JOIN|UPDATE|TABLE) ((?:[^\n\r(]+?(?:, )?)*)~', $db_string, $matches);

			$fix_tables = [];
			foreach ($matches[1] as $tables)
			{
				$tables = array_unique(explode(',', $tables));
				foreach ($tables as $table)
				{
					// Now, it's still theoretically possible this could be an injection.  So backtick it!
					if (trim($table) !== '')
					{
						$fix_tables[] = '`' . strtr(trim($table), ['`' => '']) . '`';
					}
				}
			}

			return array_unique($fix_tables);
		}

		// Table crashed.  Let's try to fix it.
		if (($query_errno === self::ERR_KEY_FILE)
			&& preg_match('~\'([^.\']+)~', $query_error, $match) === 1)
		{
			return ['`' . $match[1] . '`'];
		}

		// Indexes crashed.  Should be easy to fix!
		if (($query_errno === self::ERR_INCORRECT_KEY_FILE || $query_errno === self::ERR_OLD_KEY_FILE)
			&& preg_match("~'([^']+?)'~", $query_error, $match) === 1)
		{
			return ['`' . $match[1] . '`'];
		}

		return false;
	}

	/**
	 * Attempt to repair the specified tables in the database.
	 *
	 * @param array $fix_tables An array containing the names of the tables to be repaired.
	 * @param string $db_string The database query string to be executed after attempting the repair.
	 *
	 * @return null|AbstractResult Returns the query results if the repair was successful, null otherwise.
	 */
	private function attemptRepair($fix_tables, $db_string)
	{
		global $webmaster_email, $txt;

		// sources/Logging.php for logLastDatabaseError(), subs/Mail.subs.php for sendmail().
		// @todo this should go somewhere else, not into the db-mysql layer I think
		require_once(SOURCEDIR . '/Logging.php');
		require_once(SUBSDIR . '/Mail.subs.php');

		// Make a note of the REPAIR...
		$cache = Cache::instance();
		$cache->put('db_last_error', time(), 600);
		if (!$cache->getVar($temp, 'db_last_error', 600))
		{
			logLastDatabaseError();
		}

		// Attempt to find and repair the broken table.
		foreach ($fix_tables as $table)
		{
			$this->query('', '
				REPAIR TABLE ' . $table, false);
		}

		// And send off an email!
		sendmail($webmaster_email, $txt['database_error'], $txt['tried_to_repair']);

		// Try the query again...?
		$ret = $this->query('', $db_string, false);
		if ($ret->hasResults())
		{
			return $ret;
		}

		return null;
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
			array()
		);
		[$ver] = $request->fetch_row();
		$request->free_result();

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
		$string = Util::clean_4byte_chars($string);

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
			array()
		);
		[$ver] = $request->fetch_row();
		$request->free_result();

		return $ver;
	}

	/**
	 * {@inheritDoc}
	 */
	public function select_db($dbName = null)
	{
		return mysqli_select_db($this->connection, $dbName);
	}

	/**
	 * {@inheritDoc}
	 */
	public function validConnection()
	{
		return is_object($this->connection);
	}

	/**
	 * {@inheritDoc}
	 */
	public function list_tables($db_name_str = false, $filter = false)
	{
		return (new Dump($this))->list_tables($db_name_str, $filter);
	}

	/**
	 * {@inheritDoc}
	 */
	public function supportMediumtext()
	{
		return true;
	}

	/**
	 * {@inheritDoc}
	 */
	protected function _replaceStringCaseSensitive($replacement)
	{
		return 'BINARY ' . $this->_replaceString($replacement);
	}

	/**
	 * {@inheritDoc}
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
}
