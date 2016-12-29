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
 * @version 1.1 beta 4
 *
 */

/**
 * Abstract database class, implements database to control functions
 */
abstract class Database_Abstract implements Database
{
	/**
	 * Current connection to the database
	 * @var mysqli|postgre|resource
	 */
	protected $_connection = null;

	/**
	 * Number of queries run (may include queries from $_SESSION if is a redirect)
	 * @var int
	 */
	protected $_query_count = 0;

	/**
	 * Yet another way to skip a database error
	 * @var boolean
	 */
	protected $_skip_error = false;

	/**
	 * This is used to remember the "previous" state of the skip_error parameter
	 * @var null|boolean
	 */
	protected $_old_skip_error = false;

	/**
	 * MySQL supports unbuffered queries, this remembers if we are running an
	 * unbuffered or not
	 * @var boolean
	 */
	protected $_unbuffered = false;

	/**
	 * This holds the "values" used in the replacement__callback method
	 * @var mixed[]
	 */
	protected $_db_callback_values = array();

	/**
	 * This contains the "connection" used in the replacement__callback method
	 * TBH I'm not sure why $this->_connection is not used
	 * @var resource|object
	 */
	protected $_db_callback_connection = null;

	/**
	 * Private constructor.
	 */
	protected function __construct()
	{
		// Objects should be created through initiate().
	}

	/**
	 * Callback for preg_replace_callback on the query.
	 * It allows to replace on the fly a few pre-defined strings, for
	 * convenience ('query_see_board', 'query_wanna_see_board'), with
	 * their current values from $user_info.
	 * In addition, it performs checks and sanitation on the values
	 * sent to the database.
	 *
	 * @param mixed[] $matches
	 */
	public function replacement__callback($matches)
	{
		global $user_info, $db_prefix;

		// Connection gone???  This should *never* happen at this point, yet it does :'(
		if (!$this->validConnection($this->_db_callback_connection))
			Errors::instance()->display_db_error();

		if ($matches[1] === 'db_prefix')
			return $db_prefix;

		if ($matches[1] === 'query_see_board')
			return $user_info['query_see_board'];

		if ($matches[1] === 'query_wanna_see_board')
			return $user_info['query_wanna_see_board'];

		if (!isset($matches[2]))
			$this->error_backtrace('Invalid value inserted or no type specified.', '', E_USER_ERROR, __FILE__, __LINE__);

		if (!isset($this->_db_callback_values[$matches[2]]))
			$this->error_backtrace('The database value you\'re trying to insert does not exist: ' . htmlspecialchars($matches[2], ENT_COMPAT, 'UTF-8'), '', E_USER_ERROR, __FILE__, __LINE__);

		$replacement = $this->_db_callback_values[$matches[2]];

		switch ($matches[1])
		{
			case 'int':
				return $this->_replaceInt($matches[2], $replacement);
			case 'string':
			case 'text':
				return $this->_replaceString($replacement);
			case 'array_int':
				return $this->_replaceArrayInt($matches[2], $replacement);
			case 'array_string':
				return $this->_replaceArrayString($matches[2], $replacement);
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
	 * This function works like $this->query(), escapes and quotes a string,
	 * but it doesn't execute the query.
	 *
	 * @param string $db_string
	 * @param mixed[] $db_values
	 * @param mysqli|postgre|null $connection = null
	 */
	public function quote($db_string, $db_values, $connection = null)
	{
		// Only bother if there's something to replace.
		if (strpos($db_string, '{') !== false)
		{
			// This is needed by the callback function.
			$this->_db_callback_values = $db_values;
			if ($connection === null)
			{
				$this->_db_callback_connection = $this->_connection;
			}
			else
			{
				$this->_db_callback_connection = $connection;
			}

			// Do the quoting and escaping
			$db_string = preg_replace_callback('~{([a-z_]+)(?::([a-zA-Z0-9_-]+))?}~', array($this, 'replacement__callback'), $db_string);

			// Clear this variables.
			$this->_db_callback_values = array();
			$this->_db_callback_connection = null;
		}

		return $db_string;
	}

	/**
	 * {@inheritDoc}
	 */
	public function fetchQuery($db_string, $db_values = array(), $seeds = null)
	{
		$request = $this->query('', $db_string, $db_values);

		$results = $seeds !== null ? $seeds : array();
		while ($row = $this->fetch_assoc($request))
			$results[] = $row;
		$this->free_result($request);

		return $results;
	}

	/**
	 * {@inheritDoc}
	 */
	public function fetchQueryCallback($db_string, $db_values = array(), $callback = '', $seeds = null)
	{
		if ($callback === '')
			return $this->fetchQuery($db_string, $db_values);

		$request = $this->query('', $db_string, $db_values);

		$results = $seeds !== null ? $seeds : array();
		while ($row = $this->fetch_assoc($request))
			$results[] = $callback($row);
		$this->free_result($request);

		return $results;
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
	 * Tests and casts arrays of strings for replacement__callback.
	 *
	 * @param string $identifier
	 * @param mixed[] $replacement
	 * @return string
	 */
	protected function _replaceArrayString($identifier, $replacement)
	{
		if (is_array($replacement))
		{
			if (empty($replacement))
				$this->error_backtrace('Database error, given array of string values is empty. (' . $identifier . ')', '', E_USER_ERROR, __FILE__, __LINE__);

			foreach ($replacement as $key => $value)
				$replacement[$key] = sprintf('\'%1$s\'', $this->escape_string($value));

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
	 * This function tries to work out additional error information from a back trace.
	 *
	 * @param string         $error_message
	 * @param string         $log_message
	 * @param string|boolean $error_type
	 * @param string|null    $file
	 * @param integer|null   $line
	 *
	 * @return array
	 * @throws Elk_Exception
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
		if (class_exists('Errors'))
		{
			Errors::instance()->log_error($log_message, 'critical', $file, $line);
		}

		if (class_exists('Elk_Exception'))
		{
			throw new Elk_Exception($error_message, false);
		}
		elseif ($error_type)
			trigger_error($error_message . ($line !== null ? '<em>(' . basename($file) . '-' . $line . ')</em>' : ''), $error_type);
		else
			trigger_error($error_message . ($line !== null ? '<em>(' . basename($file) . '-' . $line . ')</em>' : ''));

		return array('', '');
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
	 * This function optimizes a table.
	 *
	 * - reclaims storage occupied by dead tuples. In normal PostgreSQL operation, tuples
	 * that are deleted or obsoleted by an update are not physically removed from their table;
	 * they remain present until a VACUUM is done. Therefore it's necessary to do VACUUM periodically,
	 * especially on frequently-updated tables.
	 *
	 * @param string $table - the table to be optimized
	 *
	 * @deprecated since 1.1 - the function was moved to DbTable class
	 *
	 * @return int how much it was gained
	 */
	public function db_optimize_table($table)
	{
		$db_table = db_table();

		return $db_table->optimize($table);
	}

	/**
	 * Retrieve the connection object
	 *
	 * @return resource what? The connection
	 */
	public function connection()
	{
		// find it, find it
		return $this->_connection;
	}

	/**
	 * Return the number of queries executed
	 *
	 * @return int
	 */
	public function num_queries()
	{
		return $this->_query_count;
	}

	/**
	 * Defines if the class should or not return the error in case of failures.
	 *
	 * @param null|boolean $set if true the query method will not return any error
	 *                     if null will restore the last known value of skip_error
	 */
	public function skip_error($set = true)
	{
		if ($set === null)
		{
			$set = $this->_old_skip_error;
		}
		else
		{
			$this->_old_skip_error = $this->_skip_error;
		}

		$this->_skip_error = $set;
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
	 * @param mysqli|postgre|null $connection = null
	 */
	public function validConnection($connection = null)
	{
		return (bool) $connection;
	}
}
