<?php

/**
 * This file provides an implementation of the most common functions needed
 * for the database drivers to work.
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

namespace ElkArte\Database;

use ElkArte\Debug;
use ElkArte\Errors\Errors;
use ElkArte\Exceptions\Exception;

/**
 * Abstract database class, implements database to control functions
 */
abstract class AbstractQuery implements QueryInterface
{
	/**
	 * Of course the character used to escape characters that have to be escaped
	 *
	 * @var string
	 */
	const ESCAPE_CHAR = '\\';

	/**
	 * Current connection to the database
	 *
	 * @var resource
	 */
	protected $connection = null;

	/**
	 * Number of queries run (may include queries from $_SESSION if is a redirect)
	 *
	 * @var int
	 */
	protected $_query_count = 0;

	/**
	 * The way to skip a database error
	 *
	 * @var bool
	 */
	protected $_skip_error = false;

	/**
	 * The tables prefix
	 *
	 * @var string
	 */
	protected $_db_prefix = '';

	/**
	 * String to match visible boards.
	 * By default set to a false, so that unless it is set, nothing is returned.
	 *
	 * @var string
	 */
	protected $query_see_board = '1!=1';

	/**
	 * String to match boards the user want to see.
	 * By default set to a false, so that unless it is set, nothing is returned.
	 *
	 * @var string
	 */
	protected $query_wanna_see_board = '1!=1';

	/**
	 * String that defines case insensitive like query operator
	 *
	 * @var string
	 */
	protected $ilike = '';

	/**
	 * String that defines case insensitive not-like query operator
	 *
	 * @var string
	 */
	protected $not_ilike = '';

	/**
	 * String that defines regular-expression-like query operator
	 *
	 * @var string
	 */
	protected $rlike = '';

	/**
	 * String that defines regular-expression-not-like query operator
	 *
	 * @var string
	 */
	protected $not_rlike = '';

	/**
	 * MySQL supports unbuffered queries, this remembers if we are running an
	 * unbuffered or not
	 *
	 * @var bool
	 */
	protected $_unbuffered = false;

	/**
	 * This holds the "values" used in the replacement__callback method
	 *
	 * @var array
	 */
	protected $_db_callback_values = array();

	/**
	 * Temporary variable to support the migration to the new db-layer
	 * Ideally to be removed before 2.0 shipment
	 *
	 * @var \ElkArte\Database\AbstractResult
	 */
	protected $result = null;

	/**
	 * Holds the resource from the dBMS of the last query run
	 *
	 * @var resource
	 */
	protected $_db_last_result = null;

	/**
	 * Comments that are allowed in a query are preg_removed.
	 * These replacements happen in the query checks.
	 *
	 * @var string[]
	 */
	protected $allowed_comments = [
		'from' => [
			'~\s+~s',
			'~/\*!40001 SQL_NO_CACHE \*/~',
			'~/\*!40000 USE INDEX \([A-Za-z\_]+?\) \*/~',
			'~/\*!40100 ON DUPLICATE KEY UPDATE id_msg = \d+ \*/~',
		],
		'to' => [
			' ',
			'',
			'',
			'',
		]
	];

	/**
	 * Holds some values (time, file, line, delta) to debug performance of the queries.
	 *
	 * @var mixed[]
	 */
	protected $db_cache = [];

	/**
	 * The debug object.
	 *
	 * @var \ElkArte\Debug
	 */
	protected $_debug = null;

	/**
	 * Constructor.
	 *
	 * @param string $db_prefix Guess what? The tables prefix
	 * @param resource|object $connection Obviously the database connection
	 */
	public function __construct($db_prefix, $connection)
	{
		global $db_show_debug;

		$this->_db_prefix = $db_prefix;
		$this->connection = $connection;

		// Debugging.
		if ($db_show_debug === true)
		{
			$this->_debug = Debug::instance();
		}
	}

	/**
	 * {@inheritDoc}
	 */
	abstract public function transaction($type = 'commit');

	/**
	 * {@inheritDoc}
	 */
	abstract public function last_error();

	/**
	 * Public setter for the string that defines which boards the user can see.
	 *
	 * @param string $string
	 */
	public function setSeeBoard($string)
	{
		$this->query_see_board = $string;
	}

	/**
	 * Public setter for the string that defines which boards the user want to see.
	 *
	 * @param string $string
	 */
	public function setWannaSeeBoard($string)
	{
		$this->query_wanna_see_board = $string;
	}

	/**
	 * {@inheritDoc}
	 */
	public function quote($db_string, $db_values)
	{
		// Only bother if there's something to replace.
		if (strpos($db_string, '{') !== false)
		{
			// This is needed by the callback function.
			$this->_db_callback_values = $db_values;

			// Do the quoting and escaping
			$db_string = preg_replace_callback('~{([a-z_]+)(?::([\.a-zA-Z0-9_-]+))?}~',
				function ($matches) {
					return $this->replacement__callback($matches);
				}, $db_string);

			// Clear this variables.
			$this->_db_callback_values = array();
		}

		return $db_string;
	}

	/**
	 * Callback for preg_replace_callback on the query.
	 * It allows to replace on the fly a few pre-defined strings, for
	 * convenience ('query_see_board', 'query_wanna_see_board'), with
	 * their current values from User::$info.
	 * In addition, it performs checks and sanitation on the values
	 * sent to the database.
	 *
	 * @param mixed[] $matches
	 *
	 * @return mixed|string
	 * @throws \ElkArte\Exceptions\Exception
	 */
	public function replacement__callback($matches)
	{
		// Connection gone???  This should *never* happen at this point, yet it does :'(
		if (!$this->validConnection())
		{
			Errors::instance()->display_db_error('ElkArte\\Database\\AbstractQuery::replacement__callback');
		}

		switch ($matches[1])
		{
			case 'db_prefix':
				return $this->_db_prefix;
			case 'query_see_board':
				return $this->query_see_board;
			case 'query_wanna_see_board':
				return $this->query_wanna_see_board;
			case 'ilike':
				return $this->ilike;
			case 'not_ilike':
				return $this->not_ilike;
			case 'rlike':
				return $this->rlike;
			case 'not_rlike':
				return $this->not_rlike;
			case 'column_case_insensitive':
				return $this->_replaceColumnCaseInsensitive($matches[2]);
		}

		if (!isset($matches[2]))
		{
			$this->error_backtrace('Invalid value inserted or no type specified.', '', E_USER_ERROR, __FILE__, __LINE__);
		}

		if (!isset($this->_db_callback_values[$matches[2]]))
		{
			$this->error_backtrace('The database value you\'re trying to insert does not exist: ' . htmlspecialchars($matches[2], ENT_COMPAT, 'UTF-8'), '', E_USER_ERROR, __FILE__, __LINE__);
		}

		$replacement = $this->_db_callback_values[$matches[2]];

		switch ($matches[1])
		{
			case 'int':
				return $this->_replaceInt($matches[2], $replacement);
			case 'string':
			case 'text':
				return $this->_replaceString($replacement);
			case 'string_case_sensitive':
				return $this->_replaceStringCaseSensitive($replacement);
			case 'string_case_insensitive':
				return $this->_replaceStringCaseInsensitive($replacement);
			case 'array_int':
				return $this->_replaceArrayInt($matches[2], $replacement);
			case 'array_string':
				return $this->_replaceArrayString($matches[2], $replacement);
			case 'array_string_case_insensitive':
				return $this->_replaceArrayStringCaseInsensitive($matches[2], $replacement);
			case 'date':
				return $this->_replaceDate($matches[2], $replacement);
			case 'float':
				return $this->_replaceFloat($matches[2], $replacement);
			case 'identifier':
				return $this->_replaceIdentifier($replacement);
			case 'raw':
				return $replacement;
			default:
				$this->error_backtrace('Undefined type used in the database query. (' . $matches[1] . ':' . $matches[2] . ')', '', false, __FILE__, __LINE__);
				break;
		}

		return '';
	}

	/**
	 * Finds out if the connection is still valid.
	 *
	 * @return bool
	 */
	public function validConnection()
	{
		return (bool) $this->connection;
	}

	/**
	 * Casts the column to LOWER(column_name) for replacement__callback.
	 *
	 * @param mixed $replacement
	 * @return string
	 */
	protected function _replaceColumnCaseInsensitive($replacement)
	{
		return 'LOWER(' . $replacement . ')';
	}

	/**
	 * Scans the debug_backtrace output looking for the place where the
	 * actual error happened
	 *
	 * @return mixed[]
	 */
	protected function backtrace_message()
	{
		$log_message = '';
		$file = null;
		$line = null;
		foreach (debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS) as $step)
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
		return [$file, $line, $log_message];
	}

	/**
	 * This function tries to work out additional error information from a back trace.
	 *
	 * @param string $error_message
	 * @param string $log_message
	 * @param string|bool $error_type
	 * @param string|null $file_fallback
	 * @param int|null $line_fallback
	 *
	 * @throws \ElkArte\Exceptions\Exception
	 */
	protected function error_backtrace($error_message, $log_message = '', $error_type = false, $file_fallback = null, $line_fallback = null)
	{
		if (empty($log_message))
		{
			$log_message = $error_message;
		}

		// We'll try recovering the file and line number the original db query was called from.
		list ($file, $line, $backtrace_message) = $this->backtrace_message();

		// Just in case nothing can be found from debug_backtrace
		$file = $file ?? $file_fallback;
		$line = $line ?? $line_fallback;
		$log_message .= $backtrace_message;

		// Is always a critical error.
		Errors::instance()->log_error($log_message, 'critical', $file, $line);

		throw new Exception([false, $error_message], false);
	}

	/**
	 * Tests and casts integers for replacement__callback.
	 *
	 * @param mixed $identifier
	 * @param mixed $replacement
	 * @return string
	 * @throws \ElkArte\Exceptions\Exception
	 */
	protected function _replaceInt($identifier, $replacement)
	{
		if (!is_numeric($replacement) || (string) $replacement !== (string) (int) $replacement)
		{
			$this->error_backtrace('Wrong value type sent to the database. Integer expected. (' . $identifier . ')', '', E_USER_ERROR, __FILE__, __LINE__);
		}

		return (string) (int) $replacement;
	}

	/**
	 * Casts values to string for replacement__callback.
	 *
	 * @param mixed $replacement
	 * @return string
	 */
	protected function _replaceString($replacement)
	{
		return sprintf('\'%1$s\'', $this->escape_string($replacement));
	}

	/**
	 * Escape string for the database input
	 *
	 * @param string $string
	 *
	 * @return string
	 */
	abstract public function escape_string($string);

	/**
	 * Casts values to string for replacement__callback and in the DBMS that
	 * require this solution makes it so that the comparison will be case sensitive.
	 *
	 * @param mixed $replacement
	 * @return string
	 */
	protected function _replaceStringCaseSensitive($replacement)
	{
		return $this->_replaceString($replacement);
	}

	/**
	 * Casts values to LOWER(string) for replacement__callback.
	 *
	 * @param mixed $replacement
	 * @return string
	 */
	protected function _replaceStringCaseInsensitive($replacement)
	{
		return 'LOWER(' . $this->_replaceString($replacement) . ')';
	}

	/**
	 * Tests and casts arrays of integers for replacement__callback.
	 *
	 * @param string $identifier
	 * @param mixed[] $replacement
	 * @return string
	 * @throws \ElkArte\Exceptions\Exception
	 */
	protected function _replaceArrayInt($identifier, $replacement)
	{
		if (is_array($replacement))
		{
			if (empty($replacement))
			{
				$this->error_backtrace('Database error, given array of integer values is empty. (' . $identifier . ')', '', E_USER_ERROR, __FILE__, __LINE__);
			}

			foreach ($replacement as $key => $value)
			{
				if (!is_numeric($value) || (string) $value !== (string) (int) $value)
				{
					$this->error_backtrace('Wrong value type sent to the database. Array of integers expected. (' . $identifier . ')', '', E_USER_ERROR, __FILE__, __LINE__);
				}

				$replacement[$key] = (string) (int) $value;
			}

			return implode(', ', $replacement);
		}
		else
		{
			$this->error_backtrace('Wrong value type sent to the database. Array of integers expected. (' . $identifier . ')', '', E_USER_ERROR, __FILE__, __LINE__);
		}
	}

	/**
	 * Tests and casts arrays of strings for replacement__callback.
	 *
	 * @param string $identifier
	 * @param mixed[] $replacement
	 * @return string
	 * @throws \ElkArte\Exceptions\Exception
	 */
	protected function _replaceArrayString($identifier, $replacement)
	{
		if (is_array($replacement))
		{
			if (empty($replacement))
			{
				$this->error_backtrace('Database error, given array of string values is empty. (' . $identifier . ')', '', E_USER_ERROR, __FILE__, __LINE__);
			}

			foreach ($replacement as $key => $value)
			{
				$replacement[$key] = sprintf('\'%1$s\'', $this->escape_string($value));
			}

			return implode(', ', $replacement);
		}
		else
		{
			$this->error_backtrace('Wrong value type sent to the database. Array of strings expected. (' . $identifier . ')', '', E_USER_ERROR, __FILE__, __LINE__);
		}
	}

	/**
	 * Tests and casts to LOWER(column_name) (if needed) arrays of strings
	 * for replacement__callback.
	 *
	 * @param string $identifier
	 * @param mixed[] $replacement
	 * @return string
	 * @throws \ElkArte\Exceptions\Exception
	 */
	protected function _replaceArrayStringCaseInsensitive($identifier, $replacement)
	{
		if (is_array($replacement))
		{
			if (empty($replacement))
			{
				$this->error_backtrace('Database error, given array of string values is empty. (' . $identifier . ')', '', E_USER_ERROR, __FILE__, __LINE__);
			}

			foreach ($replacement as $key => $value)
			{
				$replacement[$key] = $this->_replaceStringCaseInsensitive($value);
			}

			return implode(', ', $replacement);
		}
		else
		{
			$this->error_backtrace('Wrong value type sent to the database. Array of strings expected. (' . $identifier . ')', '', E_USER_ERROR, __FILE__, __LINE__);
		}
	}

	/**
	 * Tests and casts date for replacement__callback.
	 *
	 * @param mixed $identifier
	 * @param mixed $replacement
	 * @return string
	 * @throws \ElkArte\Exceptions\Exception
	 */
	protected function _replaceDate($identifier, $replacement)
	{
		if (preg_match('~^(\d{4})-([0-1]?\d)-([0-3]?\d)$~', $replacement, $date_matches) === 1)
		{
			return sprintf('\'%04d-%02d-%02d\'', $date_matches[1], $date_matches[2], $date_matches[3]);
		}
		else
		{
			$this->error_backtrace('Wrong value type sent to the database. Date expected. (' . $identifier . ')', '', E_USER_ERROR, __FILE__, __LINE__);
		}
	}

	/**
	 * Tests and casts floating numbers for replacement__callback.
	 *
	 * @param mixed $identifier
	 * @param mixed $replacement
	 * @return string
	 * @throws \ElkArte\Exceptions\Exception
	 */
	protected function _replaceFloat($identifier, $replacement)
	{
		if (!is_numeric($replacement))
		{
			$this->error_backtrace('Wrong value type sent to the database. Floating point number expected. (' . $identifier . ')', '', E_USER_ERROR, __FILE__, __LINE__);
		}

		return (string) (float) $replacement;
	}

	/**
	 * Quotes identifiers for replacement__callback.
	 *
	 * @param mixed $replacement
	 * @return string
	 * @throws \ElkArte\Exceptions\Exception
	 */
	protected function _replaceIdentifier($replacement)
	{
		if (preg_match('~[a-z_][0-9a-zA-Z$,_]{0,60}~', $replacement) !== 1)
		{
			$this->error_backtrace('Wrong value type sent to the database. Invalid identifier used. (' . $replacement . ')', '', E_USER_ERROR, __FILE__, __LINE__);
		}

		return '`' . $replacement . '`';
	}

	/**
	 * {@inheritDoc}
	 */
	public function fetchQuery($db_string, $db_values = array())
	{
		return $this->query('', $db_string, $db_values);
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
			throw new \Exception('Query string empty');
		}

		$db_string = $this->_prepareQuery($db_string, $db_values);

		$this->_preQueryDebug($db_string);

		$this->_doSanityCheck($db_string);

		$this->executeQuery($db_string);

		if ($this->_db_last_result === false && !$this->_skip_error)
		{
			$this->_db_last_result = $this->error($db_string);
		}

		// Revert not to skip errors
		if ($this->_skip_error)
		{
			$this->_skip_error = false;
		}

		// Debugging.
		$this->_postQueryDebug();

		return $this->result;
	}

	/**
	 * Actually execute the DBMS-specific code to run the query
	 *
	 * @param string $db_string
	 */
	abstract protected function executeQuery($db_string);

	/**
	 * {@inheritDoc}
	 */
	abstract public function error($db_string);

	/**
	 * Prepares the strings to show the error to the user/admin and stop
	 * the code execution
	 *
	 * @param string $db_string
	 * @param string $query_error
	 * @param string $file
	 * @param int $line
	 * @throws \ElkArte\Exceptions\Exception
	 */
	protected function throwError($db_string, $query_error, $file, $line)
	{
		global $context, $txt, $modSettings, $db_show_debug;

		// Nothing's defined yet... just die with it.
		if (empty($context) || empty($txt))
		{
			die($query_error);
		}

		// Show an error message, if possible.
		$context['error_title'] = $txt['database_error'];
		$message = $txt['try_again'];

		// Add database version that we know of, for the admin to know. (and ask for support)
		if (allowedTo('admin_forum'))
		{
			$message = nl2br($query_error) . '<br />' . $txt['file'] . ': ' . $file . '<br />' . $txt['line'] . ': ' . $line .
				'<br /><br />' . sprintf($txt['database_error_versions'], $modSettings['elkVersion']);

			if ($db_show_debug === true)
			{
				$message .= '<br /><br />' . nl2br($db_string);
			}
		}

		// It's already been logged... don't log it again.
		throw new Exception($message, false);
	}

	/**
	 * {@inheritDoc}
	 */
	abstract public function insert($method, $table, $columns, $data, $keys, $disable_trans = false);

	/**
	 * Prepares the data that will be later implode'd into the actual query string
	 *
	 * @param string $table
	 * @param mixed[] $columns
	 * @param mixed[] $data
	 * @return mixed[]
	 * @throws \Exception
	 */
	protected function prepareInsert($table, $columns, $data)
	{
		// With nothing to insert, simply return.
		if (empty($data))
		{
			throw new \Exception('No data to insert');
		}

		// Inserting data as a single row can be done as a single array.
		if (!is_array($data[array_rand($data)]))
		{
			$data = [$data];
		}

		// Replace the prefix holder with the actual prefix.
		$table = str_replace('{db_prefix}', $this->_db_prefix, $table);
		$this->_skip_error = $table === $this->_db_prefix . 'log_errors';

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
		$insertRows = [];
		foreach ($data as $dataRow)
		{
			$insertRows[] = $this->quote($insertData, $this->_array_combine($indexed_columns, $dataRow));
		}
		return [$table, $indexed_columns, $insertRows];
	}

	/**
	 * {@inheritDoc}
	 */
	abstract public function replace($table, $columns, $data, $keys, $disable_trans = false);

	/**
	 * {@inheritDoc}
	 */
	public function escape_wildcard_string($string, $translate_human_wildcards = false)
	{
		$replacements = array(
			'%' => '\%',
			'_' => '\_',
			'\\' => '\\\\',
		);

		if ($translate_human_wildcards)
		{
			$replacements += array(
				'*' => '%',
			);
		}

		return strtr($string, $replacements);
	}

	/**
	 * {@inheritDoc}
	 */
	public function connection()
	{
		// find it, find it
		return $this->connection;
	}

	/**
	 * {@inheritDoc}
	 */
	public function num_queries()
	{
		return $this->_query_count;
	}

	/**
	 * {@inheritDoc}
	 */
	public function skip_next_error()
	{
		$this->_skip_error = true;
	}

	/**
	 * {@inheritDoc}
	 */
	public function truncate($table)
	{
		return $this->fetchQuery('
			TRUNCATE ' . $table,
			[]
		);
	}

	/**
	 * Set the unbuffered state for the connection
	 *
	 * @param bool $state
	 */
	public function setUnbuffered($state)
	{
		$this->_unbuffered = (bool) $state;
	}

	/**
	 *  Get the version number.
	 *
	 * @return string - the version
	 * @throws \ElkArte\Exceptions\Exception
	 */
	abstract public function client_version();

	/**
	 * Return server info.
	 *
	 * @return string
	 */
	abstract public function server_info();

	/**
	 * Whether the database system is case sensitive.
	 *
	 * @return bool
	 */
	abstract public function case_sensitive();

	/**
	 * Get the name (title) of the database system.
	 *
	 * @return string
	 */
	abstract public function title();

	/**
	 * Returns whether the database system supports ignore.
	 *
	 * @return false
	 */
	abstract public function support_ignore();

	/**
	 * Get the version number.
	 *
	 * @return string - the version
	 * @throws \ElkArte\Exceptions\Exception
	 */
	abstract public function server_version();

	/**
	 * Temporary function to support migration to the new schema of the db layer
	 *
	 * @deprecated since 2.0
	 */
	public function fetch_row($result)
	{
// 		\ElkArte\Errors\Errors::instance()->log_deprecated('Query::fetch_row()', 'Result::fetch_row()');
		if ($result === false)
		{
			return false;
		}
		else
		{
			return $result->fetch_row();
		}
	}

	/**
	 * Temporary function to support migration to the new schema of the db layer
	 *
	 * @deprecated since 2.0
	 */
	public function fetch_assoc($result)
	{
// 		\ElkArte\Errors\Errors::instance()->log_deprecated('Query::fetch_assoc()', 'Result::fetch_assoc()');
		if ($result === false)
		{
			return false;
		}
		else
		{
			return $result->fetch_assoc();
		}
	}

	/**
	 * Temporary function to support migration to the new schema of the db layer
	 *
	 * @deprecated since 2.0
	 */
	public function free_result($result)
	{
// 		\ElkArte\Errors\Errors::instance()->log_deprecated('Query::free_result()', 'Result::free_result()');
		if ($result === false)
		{
			return;
		}
		else
		{
			return $result->free_result();
		}
	}

	/**
	 * Temporary function to support migration to the new schema of the db layer
	 *
	 * @deprecated since 2.0
	 */
	public function affected_rows()
	{
// 		\ElkArte\Errors\Errors::instance()->log_deprecated('Query::affected_rows()', 'Result::affected_rows()');
		return $this->result->affected_rows();
	}

	/**
	 * Temporary function to support migration to the new schema of the db layer
	 *
	 * @deprecated since 2.0
	 */
	public function num_rows($result)
	{
// 		\ElkArte\Errors\Errors::instance()->log_deprecated('Query::num_rows()', 'Result::num_rows()');
		if ($result === false)
		{
			return 0;
		}
		else
		{
			return $result->num_rows();
		}
	}

	/**
	 * Temporary function to support migration to the new schema of the db layer
	 *
	 * @deprecated since 2.0
	 */
	public function num_fields($result)
	{
// 		\ElkArte\Errors\Errors::instance()->log_deprecated('Query::num_fields()', 'Result::num_fields()');
		if ($result === false)
		{
			return 0;
		}
		else
		{
			return $result->num_fields();
		}
	}

	/**
	 * Temporary function to support migration to the new schema of the db layer
	 *
	 * @deprecated since 2.0
	 */
	public function insert_id($table)
	{
// 		\ElkArte\Errors\Errors::instance()->log_deprecated('Query::insert_id()', 'Result::insert_id()');
		return $this->result->insert_id();
	}

	/**
	 * Temporary function to support migration to the new schema of the db layer
	 *
	 * @deprecated since 2.0
	 */
	public function data_seek($result, $counter)
	{
// 		\ElkArte\Errors\Errors::instance()->log_deprecated('Query::data_seek()', 'Result::data_seek()');
		return $result->data_seek($counter);
	}

	/**
	 * Temporary function: I'm not sure this is the best place to have it, though it was
	 * convenient while fixing other issues.
	 *
	 * @deprecated since 2.0
	 */
	public function supportMediumtext()
	{
		return false;
	}

	/**
	 * Temporary function to support migration to the new schema of the db layer
	 *
	 * @deprecated since 2.0
	 */
	abstract public function list_tables($db_name_str = false, $filter = false);

	/**
	 * This function combines the keys and values of the data passed to db::insert.
	 *
	 * @param int[] $keys
	 * @param mixed[] $values
	 * @return mixed[]
	 */
	protected function _array_combine($keys, $values)
	{
		$is_numeric = array_filter(array_keys($values), 'is_numeric');

		if (!empty($is_numeric))
		{
			return array_combine($keys, $values);
		}
		else
		{
			$combined = array();
			foreach ($keys as $key)
			{
				if (isset($values[$key]))
				{
					$combined[$key] = $values[$key];
				}
			}

			// @todo should throw an E_WARNING if count($combined) != count($keys)
			return $combined;
		}
	}

	/**
	 * Checks for "illegal characters" and runs replacement__callback if not
	 * overridden.
	 * In case of problems, the method can ends up dying.
	 *
	 * @param string $db_string
	 * @param mixed $db_values
	 * @return string
	 * @throws \ElkArte\Exceptions\Exception
	 */
	protected function _prepareQuery($db_string, $db_values)
	{
		global $modSettings;

		if (empty($modSettings['disableQueryCheck']) && strpos($db_string, '\'') !== false && empty($db_values['security_override']))
		{
			$this->error_backtrace('Hacking attempt...', 'Illegal character (\') used in query...', true, __FILE__, __LINE__);
		}

		if (empty($db_values['security_override']) && (!empty($db_values) || strpos($db_string, '{db_prefix}') !== false))
		{
			// Store these values for use in the callback function.
			$this->_db_callback_values = $db_values;

			// Inject the values passed to this function.
			$count = -1;
			while (($count > 0 && isset($db_values['recursive'])) || $count === -1)
			{
				$db_string = preg_replace_callback('~{([a-z_]+)(?::([\.a-zA-Z0-9_-]+))?}~',
					function ($matches) {
						return $this->replacement__callback($matches);
					}, $db_string, -1, $count);
			}

			// No need for them any longer.
			$this->_db_callback_values = array();
		}

		return $db_string;
	}

	/**
	 * Some initial checks and replacement of text insside the query string
	 *
	 * @param string $db_string
	 * @param mixed $db_values
	 * @param string $identifier The old (now mostly unused) query identifier
	 * @return string
	 */
	abstract protected function initialChecks($db_string, $db_values, $identifier = '');

	/**
	 * Tracks the initial status (time, file/line, query) for performance evaluation.
	 *
	 * @param string $db_string
	 */
	protected function _preQueryDebug($db_string)
	{
		global $db_show_debug, $time_start;

		// Debugging.
		if ($db_show_debug === true)
		{
			// We'll try recovering the file and line number the original db query was called from.
			list ($file, $line) = $this->backtrace_message();

			// Just in case nothing can be found from debug_backtrace
			$file = $file ?? __FILE__;
			$line = $line ?? __LINE__;

			if (!empty($_SESSION['debug_redirect']))
			{
				$this->_debug->merge_db($_SESSION['debug_redirect']);
				// @todo this may be off by 1
				$this->_query_count += count($_SESSION['debug_redirect']);
				$_SESSION['debug_redirect'] = array();
			}

			// Don't overload it.
			$st = microtime(true);
			$this->db_cache = [];
			$this->db_cache['q'] = $this->_query_count < 50 ? $db_string : '...';
			$this->db_cache['f'] = $file;
			$this->db_cache['l'] = $line;
			$this->db_cache['s'] = $st - $time_start;
			$this->db_cache['st'] = $st;
		}
	}

	/**
	 * Closes up the tracking and stores everything in the debug class.
	 */
	protected function _postQueryDebug()
	{
		global $db_show_debug;

		if ($db_show_debug === true)
		{
			$this->db_cache['t'] = microtime(true) - $this->db_cache['st'];
			$this->_debug->db_query($this->db_cache);
			$this->db_cache = [];
		}
	}

	/**
	 * Checks the query doesn't have nasty stuff in it.
	 * In case of problems, the method can ends up dying.
	 *
	 * @param string $db_string
	 * @throws \ElkArte\Exceptions\Exception
	 */
	protected function _doSanityCheck($db_string)
	{
		global $modSettings;

		// First, we clean strings out of the query, reduce whitespace, lowercase, and trim - so we can check it over.
		$clean = '';
		if (empty($modSettings['disableQueryCheck']))
		{
			$old_pos = 0;
			$pos = -1;
			while (true)
			{
				$pos = strpos($db_string, '\'', $pos + 1);
				if ($pos === false)
				{
					break;
				}
				$clean .= substr($db_string, $old_pos, $pos - $old_pos);

				while (true)
				{
					$pos1 = strpos($db_string, '\'', $pos + 1);
					$pos2 = strpos($db_string, static::ESCAPE_CHAR, $pos + 1);

					if ($pos1 === false)
					{
						break;
					}
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
			$clean = trim(strtolower(preg_replace($this->allowed_comments['from'], $this->allowed_comments['to'], $clean)));

			// Comments?  We don't use comments in our queries, we leave 'em outside!
			if (strpos($clean, '/*') > 2 || strpos($clean, '--') !== false || strpos($clean, ';') !== false)
			{
				$fail = true;
			}
			// Trying to change passwords, slow us down, or something?
			elseif (strpos($clean, 'sleep') !== false && preg_match('~(^|[^a-z])sleep($|[^[_a-z])~s', $clean) != 0)
			{
				$fail = true;
			}
			elseif (strpos($clean, 'benchmark') !== false && preg_match('~(^|[^a-z])benchmark($|[^[a-z])~s', $clean) != 0)
			{
				$fail = true;
			}

			if (!empty($fail) && class_exists('\\ElkArte\\Errors\\Errors'))
			{
				$this->error_backtrace('Hacking attempt...', 'Hacking attempt...' . "\n" . $db_string, E_USER_ERROR, __FILE__, __LINE__);
			}
		}
	}
}
