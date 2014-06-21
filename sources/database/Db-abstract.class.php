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
 * @version 1.0 Release Candidate 1
 *
 */

if (!defined('ELK'))
	die('No access...');

/**
 * PostgreSQL database class, implements database class to control mysql functions
 */
abstract class Database_Abstract implements Database
{
	/**
	 * Current connetcion to the database
	 * @var resource
	 */
	protected $_connection = null;

	/**
	 * Number of queries run (may include queries from $_SESSION if is a redirect)
	 * @var resource
	 */
	protected $_query_count = 0;

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
	 * In addition, it performs checks and sanitization on the values
	 * sent to the database.
	 *
	 * @param mixed[] $matches
	 */
	public function replacement__callback($matches)
	{
		global $db_callback, $user_info, $db_prefix;

		list ($values, $connection) = $db_callback;

		// Connection gone???  This should *never* happen at this point, yet it does :'(
		if (!$this->_validConnection($connection))
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
				return sprintf('\'%1$s\'', $this->escape_string($replacement));
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
						$replacement[$key] = sprintf('\'%1$s\'', $this->escape_string($value));

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
	 * This function works like $this->query(), escapes and quotes a string,
	 * but it doesn't execute the query.
	 *
	 * @param string $db_string
	 * @param mixed[] $db_values
	 * @param resource|null $connection
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
	function num_queries()
	{
		return $this->_query_count;
	}
}