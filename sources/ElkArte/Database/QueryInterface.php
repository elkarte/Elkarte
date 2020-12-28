<?php

/**
 * This class is the base class for database drivers implementations.
 *
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * @version 2.0 dev
 *
 */

namespace ElkArte\Database;

/**
 * Database driver interface
 */
interface QueryInterface
{
	/**
	 * Fix up the prefix so it doesn't require the database to be selected.
	 *
	 * @param string $db_prefix
	 * @param string $db_name
	 *
	 * @return string
	 */
	public function fix_prefix($db_prefix, $db_name);

	/**
	 * Callback for preg_replace_callback on the query.
	 * It allows to replace on the fly a few pre-defined strings, for convenience ('query_see_board', 'query_wanna_see_board'), with
	 * their current values from User::$info.
	 * In addition, it performs checks and sanitation on the values sent to the database.
	 *
	 * @param mixed[] $matches
	 */
	public function replacement__callback($matches);

	/**
	 * This function works like $db->query(), escapes and quotes a string,
	 * but it doesn't execute the query.
	 *
	 * @param string $db_string
	 * @param mixed[] $db_values
	 * @return string
	 */
	public function quote($db_string, $db_values);

	/**
	 * Do a query.  Takes care of errors too.
	 *
	 * @param string $identifier
	 * @param string $db_string
	 * @param mixed[]|false $db_values = array()
	 *
	 * @return bool|Result
	 * @throws \ElkArte\Exceptions\Exception
	 */
	public function query($identifier, $db_string, $db_values = array());

	/**
	 * Do a query, and returns the results.
	 *
	 * @param string $db_string
	 * @param mixed[] $db_values = array()
	 * @param mixed[]|null
	 * @return bool|Result
	 */
	public function fetchQuery($db_string, $db_values = array());

	/**
	 * Last insert id
	 *
	 * @param string $table
	 * @return bool|int
	 */
	public function insert_id($table);

	/**
	 * Do a transaction.
	 *
	 * @param string $type - the step to perform (i.e. 'begin', 'commit', 'rollback')
	 *
	 * @return bool|resource
	 */
	public function transaction($type = 'commit');

	/**
	 * Database error.
	 * Backtrace, log, try to fix.
	 *
	 * @param string $db_string
	 */
	public function error($db_string);

	/**
	 * Sets the class not to return the error in case of failures
	 * just for the "next" query.
	 */
	public function skip_next_error();

	/**
	 * Insert data.
	 *
	 * @param string $method - options 'replace', 'ignore', 'insert'
	 * @param string $table
	 * @param mixed[] $columns
	 * @param mixed[] $data
	 * @param string[] $keys
	 * @param bool $disable_trans = false
	 * @return bool|resource
	 */
	public function insert($method = 'replace', $table, $columns, $data, $keys, $disable_trans = false);

	/**
	 * This function tries to work out additional error information from a back trace.
	 *
	 * @param string $error_message
	 * @param string $log_message
	 * @param string|bool $error_type
	 * @param string|null $file
	 * @param int|null $line
	 *
	 * @return array
	 * @throws \ElkArte\Exceptions\Exception
	 */
	public function error_backtrace($error_message, $log_message = '', $error_type = false, $file = null, $line = null);

	/**
	 * Escape string for the database input
	 *
	 * @param string $string
	 * @return string
	 */
	public function escape_string($string);

	/**
	 * Escape the LIKE wildcards so that they match the character and not the wildcard.
	 *
	 * @param string $string
	 * @param bool $translate_human_wildcards = false, if true, turns human readable wildcards into SQL wildcards.
	 *
	 * @return string
	 */
	public function escape_wildcard_string($string, $translate_human_wildcards = false);

	/**
	 * Unescape an escaped string.
	 *
	 * @param string $string
	 * @return string
	 */
	public function unescape_string($string);

	/**
	 * Return last error string from the database server
	 *
	 * @return string
	 */
	public function last_error();

	/**
	 * Returns whether the database system supports ignore.
	 *
	 * @return bool
	 */
	public function support_ignore();

	/**
	 * Get the name (title) of the database system.
	 *
	 * @return string
	 */
	public function title();

	/**
	 * Whether the database system is case sensitive.
	 *
	 * @return bool
	 */
	public function case_sensitive();

	/**
	 * Select database.
	 *
	 * @param string|null $dbName = null
	 */
	public function select_db($dbName = null);

	/**
	 * Return the number of queries executed
	 *
	 * @return int
	 */
	public function num_queries();

	/**
	 * Retrieve the connection object
	 *
	 * @return resource
	 */
	public function connection();

	/**
	 * Return the DB version the system is running under
	 *
	 * @return string - the version as string
	 */
	public function server_version();
}
