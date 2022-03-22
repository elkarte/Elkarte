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

/**
 * PostgreSQL database class, implements database class to control mysql functions
 */
class Query extends AbstractQuery
{
	/**
	 * {@inheritDoc}
	 */
	const ESCAPE_CHAR = '\'\'';

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
	 * Used by insert to deal with conflicts (mostly for replace)
	 */
	private $on_conflict = '';

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
		if (is_resource($this->connection) || $this->connection instanceof \PgSql\Connection)
		{
			return pg_last_error($this->connection);
		}

		return false;
	}

	/**
	 * {@inheritDoc}
	 */
	public function insert($method, $table, $columns, $data, $keys, $disable_trans = false)
	{
		// Compatibility check meant to support the old way of doing REPLACE's
		if ($method === 'replace')
		{
			return $this->replace($table, $columns, $data, $keys, $disable_trans);
		}

		if ($method === 'ignore')
		{
			$this->on_conflict = ' ON CONFLICT (' . implode(', ', $keys) . ') DO NOTHING';
		}

		list($table, $indexed_columns, $insertRows) = $this->prepareInsert($table, $columns, $data);

		// Do the insert.
		$this->result = $this->query('', '
			INSERT INTO ' . $table . '("' . implode('", "', $indexed_columns) . '")
			VALUES
			' . implode(',
			', $insertRows) . $this->on_conflict,
			array(
				'security_override' => true,
			)
		);
		if ($method === 'ignore')
		{
			$this->on_conflict = '';
		}

		if(is_resource($this->_db_last_result) || $this->_db_last_result instanceof \PgSQL\Result)
		{
			$inserted_results = pg_affected_rows($this->_db_last_result);
		}
		else
		{
			$inserted_results = 0;
		}
		$last_inserted_id = $this->insert_id($table);

		$this->result->updateDetails([
			'insert_id' => $last_inserted_id,
			'insertedResults' => $inserted_results,
			'lastResult' => $this->_db_last_result,
		]);

		return $this->result;
	}

	/**
	 * {@inheritDoc}
	 */
	public function replace($table, $columns, $data, $keys, $disable_trans = false)
	{
		$sets = [];
		foreach ($columns as $columnName => $type)
		{
			$sets[] = $columnName . ' = EXCLUDED.' . $columnName;
		}
		$this->on_conflict = '
			ON CONFLICT (' . implode(', ', $keys) . ') DO
			UPDATE SET ' . implode(',
				', $sets);

		$this->insert('', $table, $columns, $data, $keys, $disable_trans);
		$this->on_conflict = '';

		$this->result->updateDetails([
			'replaceResults' => $this->result->getDetail('insertedResults')
		]);

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

	/**
	 * {@inheritDoc}
	 */
	protected function initialChecks($db_string, $db_values, $identifier = '')
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
		return preg_replace('~\sLIMIT\s(\d+|{int:.+}),\s*(\d+|{int:.+})(.*)~i', ' LIMIT $2 OFFSET $1 $3', $db_string);
	}

	/**
	 * {@inheritDoc}
	 */
	protected function executeQuery($db_string)
	{
		$this->_db_last_result = @pg_query($this->connection, $db_string);

		$this->result = new Result($this->_db_last_result);
	}

	/**
	 * {@inheritDoc}
	 */
	public function error($db_string)
	{
		global $txt, $modSettings;

		// We'll try recovering the file and line number the original db query was called from.
		list ($file, $line) = $this->backtrace_message();

		// Just in case nothing can be found from debug_backtrace
		$file = $file ?? __FILE__;
		$line = $line ?? __LINE__;

		// Decide which connection to use
		// This is the error message...
		$query_error = @pg_last_error($this->connection);

		// Log the error.
		Errors::instance()->log_error($txt['database_error'] . ': ' . $query_error . (!empty($modSettings['enableErrorQueryLogging']) ? "\n\n" . $db_string : ''), 'database', $file, $line);

		$this->throwError($db_string, $query_error, $file, $line);
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
			array('security_override' => true)
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
		return (is_resource($this->connection) || $this->connection instanceof \PgSql\Connection);
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
	protected function _replaceIdentifier($replacement)
	{
		if (preg_match('~[a-z_][0-9a-zA-Z$,_]{0,60}~', $replacement) !== 1)
		{
			$this->error_backtrace('Wrong value type sent to the database. Invalid identifier used. (' . $replacement . ')', '', E_USER_ERROR, __FILE__, __LINE__);
		}

		return '"' . $replacement . '"';
	}
}
