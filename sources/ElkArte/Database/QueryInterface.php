<?php

/**
 * This class is the base class for database drivers implementations.
 *
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * @version 2.0 Beta 1
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
	 * @param array $matches
	 */
	public function replacement__callback($matches);

	/**
	 * This function works like $db->query(), escapes and quotes a string,
	 * but it doesn't execute the query.
	 *
	 * @param string $db_string
	 * @param array $db_values
	 * @return string
	 */
	public function quote($db_string, $db_values);

	/**
	 * Executes a database query using the provided identifier, query string, and values.
	 *
	 * @param string $identifier A unique identifier for the query.
	 * @param string $db_string The SQL query string to be executed.
	 * @param array $db_values An associative array of values to bind to the query. Default is an empty array.
	 * @return AbstractResult
	 */
	public function query($identifier, $db_string, $db_values = []);

	/**
	 * Executes a database fetch operation using the provided query string and values.
	 *
	 * @param string $db_string The SQL query string to be executed.
	 * @param array $db_values An associative array of values to bind to the query. Default is an empty array.
	 * @return AbstractResult
	 */
	public function fetchQuery($db_string, $db_values = []);

	/**
	 * Last insert id
	 *
	 * @param string $table
	 * @return bool|int
	 */
	public function insert_id($table);

	/**
	 * Deletes all the data from a table
	 *
	 * @param string $table
	 */
	public function truncate($table);

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
	 * Insert data into a database table using the specified method.
	 *
	 * @param string $method The method of insertion (options 'replace', 'ignore', 'insert').
	 * @param string $table The name of the database table.
	 * @param array $columns The list of columns to insert data into.
	 * @param array $data The data to be inserted, structured as an array of rows.
	 * @param array $keys The key columns to be used for conflicts or duplicates.
	 * @param bool $disable_trans Whether to disable transactions during insertion.
	 *
	 * @return object The result of the insertion query, including details about the operation.
	 */
	public function insert($method, $table, $columns, $data, $keys, $disable_trans = false);

	/**
	 * Replace or insert data into a specified table based on unique keys.
	 *
	 * @param string $table The name of the table where the data should be replaced.
	 * @param array $columns An array of column names for the table.
	 * @param array $data An array of data to insert or replace in the table.
	 * @param array $keys An array of unique keys used to determine if a row should be replaced or inserted.
	 * @param bool $disable_trans Optional. Whether to disable transactions for the operation. Default is false.
	 *
	 * @return bool True on success, false on failure.
	 */
	public function replace($table, $columns, $data, $keys, $disable_trans = false);

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
