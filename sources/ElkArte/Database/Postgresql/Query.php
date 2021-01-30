<?php

/**
 * This file has all the main functions in it that relate to the Postgre database.
 *
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * This file contains code covered by:
 * copyright: 2011 Simple Machines (http://www.simplemachines.org)
 *
 * @version 2.0 dev
 *
 */

namespace ElkArte\Database\Postgresql;

use ElkArte\Database\AbstractQuery;
use ElkArte\Errors\Errors;
use ElkArte\Exceptions\Exception;
use ElkArte\ValuesContainer;

/**
 * PostgreSQL database class, implements database class to control mysql functions
 */
class Query extends AbstractQuery
{
	/**
	 * Holds last query result
	 *
	 * @var resource
	 */
	private $_db_last_result = null;

	/**
	 * Since PostgreSQL doesn't support INSERT REPLACE we are using this to remember
	 * the rows affected by the delete
	 *
	 * @var int
	 */
	private $_in_transaction = false;

	/**
	 * {@inheritDoc}
	 */
	protected $ilike = ' ILIKE ';

	/**
	 * {@inheritDoc}
	 */
	protected $not_ilike = ' NOT ILIKE ';

	/**
	 * {@inheritDoc}
	 */
	protected $rlike = ' ~* ';

	/**
	 * {@inheritDoc}
	 */
	protected $not_rlike = ' !~* ';

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
	public function last_error()
	{
		if (is_resource($this->connection))
		{
			return pg_last_error($this->connection);
		}

		return false;
	}

	/**
	 * {@inheritDoc}
	 */
	public function insert($method = 'replace', $table, $columns, $data, $keys, $disable_trans = false)
	{
		// With nothing to insert, simply return.
		if (empty($data))
		{
			return;
		}

		// Inserting data as a single row can be done as a single array.
		if (!is_array($data[array_rand($data)]))
		{
			$data = array($data);
		}

		// Replace the prefix holder with the actual prefix.
		$table = str_replace('{db_prefix}', $this->_db_prefix, $table);

		$priv_trans = false;
		if ((count($data) > 1 || $method === 'replace') && !$this->_in_transaction && !$disable_trans)
		{
			$this->transaction('begin');
			$priv_trans = true;
		}

		// PostgreSQL doesn't support replace: we implement a MySQL-compatible behavior instead
		if ($method === 'replace')
		{
			$count = 0;
			$where = '';
			$db_replace_result = 0;
			foreach ($columns as $columnName => $type)
			{
				// Are we restricting the length?
				if (strpos($type, 'string-') !== false)
				{
					$actualType = sprintf($columnName . ' = SUBSTRING({string:%1$s}, 1, ' . substr($type, 7) . '), ', $count);
				}
				else
				{
					$actualType = sprintf($columnName . ' = {%1$s:%2$s}, ', $type, $count);
				}

				// A key? That's what we were looking for.
				if (in_array($columnName, $keys))
				{
					$where .= (empty($where) ? '' : ' AND ') . substr($actualType, 0, -2);
				}
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
					$db_replace_result += (!is_resource($this->_db_last_result) ? 0 : pg_affected_rows($this->_db_last_result));
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
				{
					$insertData .= sprintf('SUBSTRING({string:%1$s}, 1, ' . substr($type, 7) . '), ', $columnName);
				}
				else
				{
					$insertData .= sprintf('{%1$s:%2$s}, ', $type, $columnName);
				}
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

			$inserted_results = 0;
			$skip_error = $method === 'ignore' || $table === $this->_db_prefix . 'log_errors';
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
			$inserted_results += (!is_resource($this->_db_last_result) ? 0 : pg_affected_rows($this->_db_last_result));

			if ($method === 'replace')
			{
				$db_replace_result = $db_replace_result + $inserted_results;
			}
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
			new ValuesContainer([
				'insert_id' => $last_inserted_id,
				'replaceResults' => $db_replace_result ?? 0,
				'lastResult' => $this->_db_last_result,
			])
		);

		return $this->result;
	}

	/**
	 * {@inheritDoc}
	 */
	public function transaction($type = 'commit')
	{
		if ($type === 'begin')
		{
			$this->_in_transaction = true;

			return @pg_query($this->connection, 'BEGIN');
		}

		if ($type === 'rollback')
		{
			return @pg_query($this->connection, 'ROLLBACK');
		}

		if ($type === 'commit')
		{
			$this->_in_transaction = false;

			return @pg_query($this->connection, 'COMMIT');
		}

		return false;
	}

	protected function initialChecks($db_string, $db_values, $identifier)
	{
		// Special queries that need processing.
		$replacements = array(
			'pm_conversation_list' => array(
				'~ORDER\\s+BY\\s+\\{raw:sort\\}~' => 'ORDER BY ' . (isset($db_values['sort']) ? ($db_values['sort'] === 'pm.id_pm' ? 'MAX(pm.id_pm)' : $db_values['sort']) : ''),
			),
		);

		if (isset($replacements[$identifier]))
		{
			$db_string = preg_replace(array_keys($replacements[$identifier]), array_values($replacements[$identifier]), $db_string);
		}

		// Limits need to be a little different, left in place for non conformance addons
		$db_string = preg_replace('~\sLIMIT\s(\d+|{int:.+}),\s*(\d+|{int:.+})(.*)~i', ' LIMIT $2 OFFSET $1 $3', $db_string);

		return $db_string;
	}

	/**
	 * {@inheritDoc}
	 */
	public function query($identifier, $db_string, $db_values = array())
	{
		// One more query....
		$this->_query_count++;

		$db_string = $this->initialChecks($db_string, $db_values, $identifier);

		if (trim($db_string) === '')
		{
			return false;
		}

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
		if ($this->_skip_error)
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

		return $this->result;
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
			Errors::instance()->log_error($txt['database_error'] . ': ' . $query_error . (!empty($modSettings['enableErrorQueryLogging']) ? "\n\n" . $db_string : ''), 'database', $file, $line);
		}

		// Nothing's defined yet... just die with it.
		if (empty($context) || empty($txt))
		{
			die($query_error);
		}

		// Show an error message, if possible.
		$context['error_title'] = $txt['database_error'];
		$context['error_message'] = $txt['try_again'];

		// Add database version that we know of, for the admin to know. (and ask for support)
		if (allowedTo('admin_forum'))
		{
			$context['error_message'] = nl2br($query_error) . '<br />' . $txt['file'] . ': ' . $file . '<br />' . $txt['line'] . ': ' . $line .
				'<br /><br />' . sprintf($txt['database_error_versions'], $modSettings['elkVersion']);

			if ($db_show_debug === true)
			{
				$context['error_message'] .= '<br /><br />' . nl2br($db_string);
			}
		}

		// It's already been logged... don't log it again.
		throw new Exception($context['error_message'], false);
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
		$request = $this->query('', '
			SELECT CURRVAL(\'' . $table . '_seq\') AS insertID',
			array()
		);

		if (!$request)
		{
			return false;
		}

		list ($lastID) = $request->fetch_row();
		$request->free_result();

		return $lastID;
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
	 * Returns the result resouce of the last query executed
	 *
	 * @return resource
	 */
	public function lastResult()
	{
		return $this->_db_last_result;
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

	/**
	 * {@inheritDoc}
	 */
	protected function _replaceIdentifier($replacement)
	{
		if (preg_match('~[a-z_][0-9a-zA-Z$,_]{0,60}~', $replacement) !== 1)
		{
			$this->error_backtrace('Wrong value type sent to the database. Invalid identifier used. (' . $replacement . ')', '', E_USER_ERROR, __FILE__, __LINE__);
		}

		return '"' . $replacement . '"';
	}
}
