<?php

/**
 * This file provides an implementation of the most common functions needed
 * for the database drivers to work.
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

namespace ElkArte\Database;

/**
 * Abstract database class, implements database to control functions
 */
abstract class AbstractQuery implements QueryInterface
{
	/**
	 * Current connection to the database
	 * @var mysqli|postgre|resource
	 */
	protected $connection = null;

	/**
	 * Number of queries run (may include queries from $_SESSION if is a redirect)
	 * @var int
	 */
	protected $_query_count = 0;

	/**
	 * The way to skip a database error
	 * @var boolean
	 */
	protected $_skip_error = false;

	/**
	 * The tables prefix
	 * @var string
	 */
	protected $_db_prefix = '';

	/**
	 * MySQL supports unbuffered queries, this remembers if we are running an
	 * unbuffered or not
	 * @var boolean
	 */
	protected $_unbuffered = false;

	/**
	 * This holds the "values" used in the replacement__callback method
	 * @var array
	 */
	protected $_db_callback_values = array();

	/**
	 * Temporary variable to support the migration to the new db-layer
	 * Ideally to be removed before 2.0 shipment
	 * @var \ElkArte\Database\AbstractResult
	 */
	protected $result = null;

	/**
	 * Comments that are allowed in a query are preg_removed.
	 * These replacements happen in the query checks.
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
	 * Holds some values (time, file, line, delta) to debug performancs of the queries.
	 * @var string[]
	 */
	protected $db_cache = [];

	/**
	 * The debug object.
	 * @var \ElkArte\Debug
	 */
	protected $_debug = null;

	/**
	 * Constructor.
	 *
	 * @param string $db_prefix Guess what? The tables prefix
	 * @param resource $connection Obviously the database connection
	 */
	public function __construct($db_prefix, $connection)
	{
		global $db_show_debug;

		$this->_db_prefix = $db_prefix;
		$this->connection = $connection;

		// Debugging.
		if ($db_show_debug === true)
		{
			$this->_debug = \ElkArte\Debug::instance();
		}
	}

	/**
	 * {@inheritDoc}
	 */
	abstract public function query($identifier, $db_string, $db_values = array());

	/**
	 * {@inheritDoc}
	 */
	abstract public function transaction($type = 'commit');

	/**
	 * {@inheritDoc}
	 */
	abstract public function last_error();

	/**
	 * Callback for preg_replace_callback on the query.
	 * It allows to replace on the fly a few pre-defined strings, for
	 * convenience ('query_see_board', 'query_wanna_see_board'), with
	 * their current values from $user_info.
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
		global $user_info;

		// Connection gone???  This should *never* happen at this point, yet it does :'(
		if (!$this->validConnection($this->connection))
		{
			\ElkArte\Errors\Errors::instance()->display_db_error('ElkArte\\Database\\AbstractQuery::replacement__callback');
		}

		if ($matches[1] === 'db_prefix')
			return $this->_db_prefix;

		if ($matches[1] === 'query_see_board')
			return $user_info['query_see_board'];

		if ($matches[1] === 'query_wanna_see_board')
			return $user_info['query_wanna_see_board'];

		if ($matches[1] === 'column_case_insensitive')
			return $this->_replaceColumnCaseInsensitive($matches[2]);

		if (!isset($matches[2]))
			$this->error_backtrace('Invalid value inserted or no type specified.', '', E_USER_ERROR, __FILE__, __LINE__);

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
			$db_string = preg_replace_callback('~{([a-z_]+)(?::([\.a-zA-Z0-9_-]+))?}~', array($this, 'replacement__callback'), $db_string);

			// Clear this variables.
			$this->_db_callback_values = array();
		}

		return $db_string;
	}

	/**
	 * {@inheritDoc}
	 */
	public function fetchQuery($db_string, $db_values = array())
	{
		return $this->query('', $db_string, $db_values);
	}

	/**
	 * This function combines the keys and values of the data passed to db::insert.
	 *
	 * @param integer[] $keys
	 * @param mixed[] $values
	 * @return mixed[]
	 */
	protected function _array_combine($keys, $values)
	{
		$is_numeric = array_filter(array_keys($values), 'is_numeric');

		if (!empty($is_numeric))
			return array_combine($keys, $values);
		else
		{
			$combined = array();
			foreach ($keys as $key)
			{
				if (isset($values[$key]))
					$combined[$key] = $values[$key];
			}

			// @todo should throw an E_WARNING if count($combined) != count($keys)
			return $combined;
		}
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
			$this->error_backtrace('Wrong value type sent to the database. Integer expected. (' . $identifier . ')', '', E_USER_ERROR, __FILE__, __LINE__);
		return (string) (int) $replacement;
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
					$this->error_backtrace('Database error, given array of integer values is empty. (' . $identifier . ')', '', E_USER_ERROR, __FILE__, __LINE__);

				foreach ($replacement as $key => $value)
				{
					if (!is_numeric($value) || (string) $value !== (string) (int) $value)
						$this->error_backtrace('Wrong value type sent to the database. Array of integers expected. (' . $identifier . ')', '', E_USER_ERROR, __FILE__, __LINE__);

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
			return sprintf('\'%04d-%02d-%02d\'', $date_matches[1], $date_matches[2], $date_matches[3]);
		else
			$this->error_backtrace('Wrong value type sent to the database. Date expected. (' . $identifier . ')', '', E_USER_ERROR, __FILE__, __LINE__);
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
			$this->error_backtrace('Wrong value type sent to the database. Floating point number expected. (' . $identifier . ')', '', E_USER_ERROR, __FILE__, __LINE__);
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
		if (preg_match('~[a-z_][0-9,a-z,A-Z$_]{0,60}~', $replacement) !== 1)
		{
			$this->error_backtrace('Wrong value type sent to the database. Invalid identifier used. (' . $replacement . ')', '', E_USER_ERROR, __FILE__, __LINE__);
		}

		return '`' . $replacement . '`';
	}

	/**
	 * Checks for "illegal characters" and runs replacement__callback if not
	 * overriden.
	 * In case of problems, the method can ends up dying.
	 *
	 * @param string $db_string
	 * @param mixed $db_values
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
			$db_string = preg_replace_callback('~{([a-z_]+)(?::([\.a-zA-Z0-9_-]+))?}~', array($this, 'replacement__callback'), $db_string);

			// No need for them any longer.
			$this->_db_callback_values = array();
		}

		return $db_string;
	}

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
			// Get the file and line number this function was called.
			list ($file, $line) = $this->error_backtrace('', '', 'return', __FILE__, __LINE__);

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
	 * @param string $escape_char
	 */
	protected function _doSanityCheck($db_string, $escape_char)
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
					break;
				$clean .= substr($db_string, $old_pos, $pos - $old_pos);

				while (true)
				{
					$pos1 = strpos($db_string, '\'', $pos + 1);
					$pos2 = strpos($db_string, $escape_char, $pos + 1);

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
			$clean = trim(strtolower(preg_replace($this->allowed_comments['from'], $this->allowed_comments['to'], $clean)));

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
		}
	}

	/**
	 * {@inheritDoc}
	 */
	abstract public function error($db_string);

	/**
	 * {@inheritDoc}
	 */
	abstract public function insert($method = 'replace', $table, $columns, $data, $keys, $disable_trans = false);

	/**
	 * {@inheritDoc}
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
		if (class_exists('\\ElkArte\\Errors\\Errors'))
		{
			\ElkArte\Errors\Errors::instance()->log_error($log_message, 'critical', $file, $line);
		}

		if (class_exists('\\ElkArte\\Exceptions\\Exception'))
		{
			throw new \ElkArte\Exceptions\Exception([false, $error_message], false);
		}
		elseif ($error_type)
			trigger_error($error_message . ($line !== null ? '<em>(' . basename($file) . '-' . $line . ')</em>' : ''), $error_type);
		else
			trigger_error($error_message . ($line !== null ? '<em>(' . basename($file) . '-' . $line . ')</em>' : ''));

		return array('', '');
	}

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
			$replacements += array(
				'*' => '%',
			);

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
	 * Set the unbuffered state for the connection
	 *
	 * @param bool $state
	 */
	public function setUnbuffered($state)
	{
		$this->_unbuffered = (bool) $state;
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
	 *  Get the version number.
	 *
	 * @return string - the version
	 * @throws Elk_Exception
	 */
	abstract public function client_version();

	/**
	 * Return server info.
	 *
	 * @return string
	 */
	abstract public function server_info();

	/**
	 * Escape string for the database input
	 *
	 * @param string $string
	 *
	 * @return string
	 */
	abstract public function escape_string($string);

	/**
	 * Whether the database system is case sensitive.
	 *
	 * @return boolean
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
	 * Temporary function to supoprt migration to the new schema of the db layer
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
	 * Temporary function to supoprt migration to the new schema of the db layer
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
	 * Temporary function to supoprt migration to the new schema of the db layer
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
	 * Temporary function to supoprt migration to the new schema of the db layer
	 * @deprecated since 2.0
	 */
	public function affected_rows()
	{
// 		\ElkArte\Errors\Errors::instance()->log_deprecated('Query::affected_rows()', 'Result::affected_rows()');
		return $this->result->affected_rows();
	}

	/**
	 * Temporary function to supoprt migration to the new schema of the db layer
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
	 * Temporary function to supoprt migration to the new schema of the db layer
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
	 * Temporary function to supoprt migration to the new schema of the db layer
	 * @deprecated since 2.0
	 */
	public function insert_id($table)
	{
// 		\ElkArte\Errors\Errors::instance()->log_deprecated('Query::insert_id()', 'Result::insert_id()');
		return $this->result->insert_id($table);
	}

	/**
	 * Temporary function to supoprt migration to the new schema of the db layer
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
	 * @deprecated since 2.0
	 */
	public function supportMediumtext()
	{
		return false;
	}

	/**
	 * Temporary function to supoprt migration to the new schema of the db layer
	 * @deprecated since 2.0
	 */
	abstract public function list_tables($db_name_str = false, $filter = false);
}
