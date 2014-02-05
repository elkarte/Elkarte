<?php

/**
 * This interface is meant to be implemented by classes which offer database search extra-facilities.
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 1.0 Beta 2
 *
 */

if (!defined('ELK'))
	die('No access...');

/**
 * Interface methods for database searches
 */
interface DbSearch
{
	/**
	 * Execute the appropriate query for the search.
	 *
	 * @param string $identifier
	 * @param string $db_string
	 * @param array $db_values
	 * @param resource $connection
	 */
	function search_query($identifier, $db_string, $db_values = array(), $connection = null);

	/**
	 * This method will tell you whether this database type supports this search type.
	 *
	 * @param string $search_type
	 */
	function search_support($search_type);

	/**
	 * Method for the custom word index table.
	 *
	 * @param string $size
	 */
	function create_word_search($size);

	/**
	 * Static method that allows to retrieve or create an instance of this class.
	 */
	public static function db_search();
}