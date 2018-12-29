<?php

/**
 * This file has all the main functions in it that relate to the Postgre database.
 *
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * This file contains code covered by:
 * copyright:	2011 Simple Machines (http://www.simplemachines.org)
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
	 * @var resource
	 */
	private $_db_last_result = null;

	/**
	 * Since PostgreSQL doesn't support INSERT REPLACE we are using this to remember
	 * the rows affected by the delete
	 * @var int
	 */
	private $_db_replace_result = null;

	/**
	 * Since PostgreSQL doesn't support INSERT REPLACE we are using this to remember
	 * the rows affected by the delete
	 * @var int
	 */
	private $_in_transaction = false;

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
		{
			$db_string = preg_replace(array_keys($replacements[$identifier]), array_values($replacements[$identifier]), $db_string);
		}

		// Limits need to be a little different.
		$db_string = preg_replace('~\sLIMIT\s(\d+|{int:.+}),\s*(\d+|{int:.+})\s*$~i', 'LIMIT $2 OFFSET $1', $db_string);

		if (trim($db_string) == '')
		{
			return false;
		}

		// One more query....
		$this->_query_count++;
		$this->_db_replace_result = null;

		$db_string = $this->_prepareQuery($db_string, $db_values);

		// Debugging.
		$this->_preQueryDebug($db_string);

		$this->_doSanityCheck($db_string, '\'\'');

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

		// Debugging.
		$this->_postQueryDebug();

		$this->result = new Result($this->_db_last_result);

		// This is here only for compatibility with the previous database code.
		// To remove when all the instances are fixed.
		if ($this->_db_last_result === false)
		{
			return false;
		}
		else
		{
			return $this->result;
		}
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

			if (isset($db_replace_result))
				$this->_db_replace_result = $db_replace_result + $inserted_results;
		}

		if ($priv_trans)
		{
			$this->transaction('commit');
		}

		if (!empty($data))
		{
			$last_inserted_id = $this->insert_id($table);
		}

		$this->result = new Result(
			is_object($ret) ? $ret->getResultObject() : $ret,
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

		$this->skip_next_error();
		// Try get the last ID for the auto increment field.
		$request = $this->query('', 'SELECT CURRVAL(\'' . $table . '_seq\') AS insertID',
			array(
			)
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

	/**
	 * {@inheritDoc}
	 */
	public function list_tables($db_name_str = false, $filter = false)
	{
		$dump = new Dump($this);
		return $dump->list_tables($db_name_str, $filter);
	}
}
