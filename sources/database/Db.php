<?php

/**
 * This class is the base class for database drivers implementations.
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 1.0 Release Candidate 1
 *
 */

if (!defined('ELK'))
	die('No access...');

/**
 * Database driver interface
 */
interface Database
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
	 * their current values from $user_info.
	 * In addition, it performs checks and sanitization on the values sent to the database.
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
	 * @param resource|null $connection = null
	 * @return string
	 */
	public function quote($db_string, $db_values, $connection = null);

	/**
	 * Do a query.  Takes care of errors too.
	 *
	 * @param string $identifier
	 * @param string $db_string
	 * @param mixed[] $db_values = array()
	 * @param resource|null $connection = null
	 */
	public function query($identifier, $db_string, $db_values = array(), $connection = null);

	/**
	 * Fetch next result as association.
	 *
	 * @param resource $request
	 * @param int|boolean $counter = false
	 */
	public function fetch_assoc($request, $counter = false);

	/**
	 * Fetch a row from the resultset given as parameter.
	 *
	 * @param resource $result
	 * @param int|boolean $counter = false
	 */
	public function fetch_row($result, $counter = false);

	/**
	 * Free the resultset.
	 *
	 * @param resource $result
	 * @return void
	 */
	public function free_result($result);

	/**
	 * Get the number of rows in the result.
	 *
	 * @param resource $result
	 */
	public function num_rows($result);

	/**
	 * Get the number of fields in the resultset.
	 *
	 * @param resource $request
	 */
	public function num_fields($request);

	/**
	 * Reset the internal result pointer.
	 *
	 * @param resource $request
	 * @param int $counter
	 */
	public function data_seek($request, $counter);

	/**
	 * Returns count of affected rows from the last transaction.
	 */
	public function affected_rows();

	/**
	 * Last insert id
	 *
	 * @param string $table
	 * @param string|null $field = null
	 * @param resource|null $connection = null
	 */
	public function insert_id($table, $field = null, $connection = null);

	/**
	 * Do a transaction.
	 *
	 * @param string $type - the step to perform (i.e. 'begin', 'commit', 'rollback')
	 * @param resource|null $connection = null
	 */
	public function db_transaction($type = 'commit', $connection = null);

	/**
	 * Database error.
	 * Backtrace, log, try to fix.
	 *
	 * @param string $db_string
	 * @param resource|null $connection = null
	 */
	public function error($db_string, $connection = null);

	/**
	 * Insert data.
	 *
	 * @param string $method - options 'replace', 'ignore', 'insert'
	 * @param string $table
	 * @param mixed[] $columns
	 * @param mixed[] $data
	 * @param mixed[] $keys
	 * @param bool $disable_trans = false
	 * @param resource|null $connection = null
	 * @return void
	 */
	public function insert($method = 'replace', $table, $columns, $data, $keys, $disable_trans = false, $connection = null);

	/**
	 * This function tries to work out additional error information from a back trace.
	 *
	 * @param string $error_message
	 * @param string $log_message
	 * @param string|false $error_type
	 * @param string|null $file
	 * @param int|null $line
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
	 * @param resource|null $connection = null
	 * @return string
	 */
	public function last_error($connection = null);

	/**
	 * Returns whether the database system supports ignore.
	 *
	 * @return bool
	 */
	public function support_ignore();

	/**
	 * Get the name (title) of the database system.
	 * @return string
	 */
	public function db_title();

	/**
	 * Whether the database system is case sensitive.
	 *
	 * @return bool
	 */
	public function db_case_sensitive();

	/**
	 * Gets all the necessary INSERTs for the table named table_name.
	 * It goes in 250 row segments.
	 *
	 * @param string $tableName - the table to create the inserts for.
	 * @param bool $new_table
	 * @return string the query to insert the data back in, or an empty string if the table was empty.
	 */
	public function insert_sql($tableName, $new_table = false);

	/**
	 * Select database.
	 *
	 * @param string|null $dbName = null
	 * @param resource|null $connection = null
	 */
	public function select_db($dbName = null, $connection = null);
}