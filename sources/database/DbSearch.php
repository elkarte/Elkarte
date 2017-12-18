<?php

/**
 * This interface is meant to be implemented by classes which offer database search extra-facilities.
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 2.0-dev
 *
 */

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
	 * @param mixed[] $db_values
	 * @param resource|null $connection
	 * @return resource
	 */
	public function search_query($identifier, $db_string, $db_values = array(), $connection = null);

	/**
	 * This method will tell you whether this database type supports this search type.
	 *
	 * @param string $search_type
	 * @return boolean
	 */
	public function search_support($search_type);

	/**
	 * Method for the custom word index table.
	 *
	 * @param string $size
	 * @return void
	 */
	public function create_word_search($size);

	/**
	 * Sets the class not to return the error in case of failures
	 * just for the "next" query.
	 */
	public function skip_next_error();

	/**
	 * Static method that allows to retrieve or create an instance of this class.
	 */
	public static function db_search();
}