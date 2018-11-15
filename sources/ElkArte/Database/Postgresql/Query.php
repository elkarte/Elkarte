<?php

/**
 * This file has all the main functions in it that relate to the Postgre database.
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * This file contains code covered by:
 * copyright:	2011 Simple Machines (http://www.simplemachines.org)
 * license:  	BSD, See included LICENSE.TXT for terms and conditions.
 *
 * @version 2.0 dev
 *
 */

namespace ElkArte\Database\Postgresql;

use ElkArte\Database\AbstractQuery;

/**
 * PostgreSQL database class, implements database class to control mysql functions
 */
class Query extends AbstractQuery
{
	/**
	 * Holds last query result
	 * @var string
	 */
	private $_db_last_result = null;

	/**
	 * Since PostgreSQL doesn't support INSERT REPLACE we are using this to remember
	 * the rows affected by the delete
	 * @var int
	 */
	private $_db_replace_result = null;

	/**
	 * {@inheritDoc}
	 */
	public function fix_prefix($db_prefix, $db_name)
	{
		return $db_prefix;
	}

	/**
	 * {@inheritDoc}
	 */
	public function query($identifier, $db_string, $db_values = array())
	{
		global $db_show_debug, $time_start, $modSettings;

		// Special queries that need processing.
		$replacements = array(
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
			'attach_download_increase' => array(
				'~LOW_PRIORITY~' => '',
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
			'pm_conversation_list' => array(
				'~ORDER\\s+BY\\s+\\{raw:sort\\}~' => 'ORDER BY ' . (isset($db_values['sort']) ? ($db_values['sort'] === 'pm.id_pm' ? 'MAX(pm.id_pm)' : $db_values['sort']) : ''),
			),
			'profile_board_stats' => array(
				'~COUNT\(\*\) \/ MAX\(b.num_posts\)~' => 'CAST(COUNT(*) AS DECIMAL) / CAST(b.num_posts AS DECIMAL)',
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
		$this->_query_count++;
		$this->_db_replace_result = null;

		if (empty($modSettings['disableQueryCheck']) && strpos($db_string, '\'') !== false && empty($db_values['security_override']))
			$this->error_backtrace('Hacking attempt...', 'Illegal character (\') used in query...', true, __FILE__, __LINE__);

		if (empty($db_values['security_override']) && (!empty($db_values) || strpos($db_string, '{db_prefix}') !== false))
		{
			// Store these values for use in the callback function.
			$this->_db_callback_values = $db_values;

			// Inject the values passed to this function.
			$db_string = preg_replace_callback('~{([a-z_]+)(?::([a-zA-Z0-9_-]+))?}~', array($this, 'replacement__callback'), $db_string);

			// No need for them any longer.
			$this->_db_callback_values = array();
		}

		// Debugging.
		if ($db_show_debug === true)
		{
			$debug = \ElkArte\Debug::instance();

			// Get the file and line number this function was called.
			list ($file, $line) = $this->error_backtrace('', '', 'return', __FILE__, __LINE__);

			if (!empty($_SESSION['debug_redirect']))
			{
				$debug->merge_db($_SESSION['debug_redirect']);
				// @todo this may be off by 1
				$this->_query_count += count($_SESSION['debug_redirect']);
				$_SESSION['debug_redirect'] = array();
			}

			// Don't overload it.
			$st = microtime(true);
			$db_cache = array();
			$db_cache['q'] = $this->_query_count < 50 ? $db_string : '...';
			$db_cache['f'] = $file;
			$db_cache['l'] = $line;
			$db_cache['s'] = $st - $time_start;
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
					$pos2 = strpos($db_string, '\'\'', $pos + 1);

					if ($pos1 === false)
						break;
					elseif ($pos2 === false || $pos2 > $pos1)
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

			if (!empty($fail) && class_exists('\\ElkArte\\Errors\\Errors'))
			{
				$this->error_backtrace('Hacking attempt...', 'Hacking attempt...' . "\n" . $db_string, E_USER_ERROR, __FILE__, __LINE__);
			}

			// If we are updating something, better start a transaction so that indexes may be kept consistent
			if (!$this->_in_transaction && strpos($clean, 'update') !== false)
				$this->transaction('begin');
		}

		$this->_db_last_result = @pg_query($this->connection, $db_string);

		if ($this->_db_last_result === false && !$this->_skip_error)
		{
			$this->error($db_string);
		}

		// Revert not to skip errors
		if ($this->_skip_error === true)
		{
			$this->_skip_error = false;
		}

		if ($this->_in_transaction)
			$this->transaction('commit');

		// Debugging.
		if ($db_show_debug === true)
		{
			$db_cache['t'] = microtime(true) - $st;
			$debug->db_query($db_cache);
		}

		$this->result = new \ElkArte\Database\Postgresql\Result($this->_db_last_result);

		return $this->result;
	}

	/**
	 * {@inheritDoc}
	 */
	public function transaction($type = 'commit')
	{
		if ($type == 'begin')
		{
			$this->_in_transaction = true;
			return @pg_query($this->connection, 'BEGIN');
		}
		elseif ($type == 'rollback')
			return @pg_query($this->connection, 'ROLLBACK');
		elseif ($type == 'commit')
		{
			$this->_in_transaction = false;
			return @pg_query($this->connection, 'COMMIT');
		}

		return false;
	}

	/**
	 * {@inheritDoc}
	 */
	public function last_error()
	{
		if (is_resource($this->connection))
		{
			return pg_last_error($this->connection);
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
		global $txt, $context, $modSettings, $db_show_debug;

		// We'll try recovering the file and line number the original db query was called from.
		list ($file, $line) = $this->error_backtrace('', '', 'return', __FILE__, __LINE__);

		// Decide which connection to use
		// This is the error message...
		$query_error = @pg_last_error($this->connection);

		// Log the error.
		if (class_exists('\\ElkArte\\Errors\\Errors'))
		{
			\ElkArte\Errors\Errors::instance()->log_error($txt['database_error'] . ': ' . $query_error . (!empty($modSettings['enableErrorQueryLogging']) ? "\n\n" . $db_string : ''), 'database', $file, $line);
		}

		// Nothing's defined yet... just die with it.
		if (empty($context) || empty($txt))
		{
			die($query_error);
		}

		// Show an error message, if possible.
		$context['error_title'] = $txt['database_error'];
		if (allowedTo('admin_forum'))
		{
			$context['error_message'] = nl2br($query_error) . '<br />' . $txt['file'] . ': ' . $file . '<br />' . $txt['line'] . ': ' . $line;
		}
		else
		{
			$context['error_message'] = $txt['try_again'];
		}

		// Add database version that we know of, for the admin to know. (and ask for support)
		if (allowedTo('admin_forum'))
		{
			$context['error_message'] .= '<br /><br />' . sprintf($txt['database_error_versions'], $modSettings['elkVersion']);
		}

		if (allowedTo('admin_forum') && $db_show_debug === true)
		{
			$context['error_message'] .= '<br /><br />' . nl2br($db_string);
		}

		// It's already been logged... don't log it again.
		throw new \ElkArte\Exceptions\Exception($context['error_message'], false);
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

		$priv_trans = false;
		if ((count($data) > 1 || $method == 'replace') && !$this->_in_transaction && !$disable_trans)
		{
			$this->transaction('begin');
			$priv_trans = true;
		}

		// PostgreSQL doesn't support replace: we implement a MySQL-compatible behavior instead
		if ($method == 'replace')
		{
			$count = 0;
			$where = '';
			$db_replace_result = 0;
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
						$entry
					);
					$db_replace_result += (!$this->_db_last_result ? 0 : pg_affected_rows($this->_db_last_result));
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
				$insertRows[] = $this->quote($insertData, $this->_array_combine($indexed_columns, $dataRow));

			$inserted_results = 0;
			$skip_error = $method == 'ignore' || $table === $this->_db_prefix . 'log_errors';
			$this->_skip_error = $skip_error;

			// Do the insert.
			$ret = $this->query('', '
				INSERT INTO ' . $table . '("' . implode('", "', $indexed_columns) . '")
				VALUES
				' . implode(',
				', $insertRows),
				array(
					'security_override' => true,
				)
			);
			$inserted_results += (!$this->_db_last_result ? 0 : pg_affected_rows($this->_db_last_result));

			$last_inserted_id = $this->insert_id($table);

			if (isset($db_replace_result))
				$this->_db_replace_result = $db_replace_result + $inserted_results;
		}

		if ($priv_trans)
		{
			$this->transaction('commit');
		}

		$this->result = new \ElkArte\Database\Postgresql\Result(
			$ret->getResultObject,
			new \ElkArte\ValuesContainer([
				'insert_id' => $last_inserted_id,
				'replaceResults' => $this->_db_replace_result,
				'lastResult' => $this->_db_last_result,
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
		return strtr($string, array('\'\'' => '\''));
	}

	/**
	 * {@inheritDoc}
	 */
	public function support_ignore()
	{
		return false;
	}

	/**
	 * {@inheritDoc}
	 */
	public function server_version()
	{
		$version = pg_version();

		return $version['server'];
	}

	/**
	 * {@inheritDoc}
	 */
	public function title()
	{
		return 'PostgreSQL';
	}

	/**
	 * {@inheritDoc}
	 */
	public function case_sensitive()
	{
		return true;
	}

	/**
	 * {@inheritDoc}
	 */
	protected function _replaceIdentifier($replacement)
	{
		if (preg_match('~[a-z_][0-9,a-z,A-Z$_]{0,60}~', $replacement) !== 1)
		{
			$this->error_backtrace('Wrong value type sent to the database. Invalid identifier used. (' . $replacement . ')', '', E_USER_ERROR, __FILE__, __LINE__);
		}

		return '"' . $replacement . '"';
	}

	/**
	 * {@inheritDoc}
	 */
	public function escape_string($string)
	{
		return pg_escape_string($string);
	}

	/**
	 * {@inheritDoc}
	 */
	public function server_info()
	{
		// give info on client! we use it in install and upgrade and such things.
		$version = pg_version();

		return $version['client'];
	}

	/**
	 * {@inheritDoc}
	 */
	public function client_version()
	{
		$version = pg_version();

		return $version['client'];
	}

	/**
	 * Dummy function really. Doesn't do anything in PostgreSQL.
	 *
	 * {@inheritDoc}
	 */
	public function select_db($db_name = null)
	{
		return true;
	}

	/**
	 * Returns the number of rows affected by a REPALCE statement
	 *
	 * @return int|null
	 */
	public function replaceResults()
	{
		return $this->_db_replace_result;
	}

	/**
	 * Returns the number of rows from the last query executed
	 *
	 * @return int|null
	 */
	public function lastResult()
	{
		return $this->_db_last_result;
	}

	/**
	 * Last inserted id.
	 *
	 * @param string $table
	 *
	 * @return bool|int
	 * @throws \ElkArte\Exceptions\Exception
	 */
	public function insert_id($table)
	{
		$table = str_replace('{db_prefix}', $this->_db_prefix, $table);

		// Try get the last ID for the auto increment field.
		$request = $this->query('', 'SELECT CURRVAL(\'' . $table . '_seq\') AS insertID',
			array(
			),
			$this->connection
		);

		if (!$request)
		{
			return false;
		}

		list ($lastID) = $this->fetch_row($request);
		$this->free_result($request);

		return $lastID;
	}

	/**
	 * {@inheritDoc}
	 */
	public function validConnection()
	{
		return is_resource($this->connection);
	}
}
