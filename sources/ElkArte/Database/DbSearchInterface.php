<?php

/**
 * This interface is meant to be implemented by classes which offer database search extra-facilities.
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 2.0 dev
 *
 */

namespace ElkArte\Database;

/**
 * Interface methods for database searches
 */
interface DbSearchInterface
{
	/**
	 * Execute the appropriate query for the search.
	 *
	 * @param string $identifier
	 * @param string $db_string
	 * @param mixed[] $db_values
	 *
	 * @return resource
	 */
	public function search_query($identifier, $db_string, $db_values = array());

	/**
	 * This method will tell you whether this database type supports this search type.
	 *
	 * @param string $search_type
	 * @return boolean
	 */
	public function search_support($search_type);

	/**
	 * Returns some basic info about the {db_prefix}messages table
	 * Used in ManageSearch.controller.php in the page to select the index method
	 */
	public function membersTableInfo();

	/**
	 * Method for the custom word index table.
	 *
	 * @param string $type
	 * @param int $size
	 * @return void
	 */
	public function create_word_search($type, $size = 10);

	/**
	 * Sets the class not to return the error in case of failures
	 * just for the "next" query.
	 */
	public function skip_next_error();

	/**
	 * Create a temporary table.
	 * A wrapper around DbTable::db_create_table setting the 'temporary' parameter.
	 *
	 * @param string $name
	 * @param mixed[] $columns in the format specified.
	 * @param mixed[] $indexes default array(), in the format specified.
	 */
	public function createTemporaryTable($name, $columns, $indexes);

	/**
	 * Static method that allows to retrieve or create an instance of this class.
	 */
	public static function db_search();
}
