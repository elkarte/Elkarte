<?php

/**
 * This is the base class for DbTable functionality.
 * It contains abstract methods to be implemented for the specific database system,
 * related to a table structure.
 * Add-ons will need this, to change the database for their needs.
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
 * This is used to create a table without worrying about schema compatabilities
 * across supported database systems.
 */
abstract class DbTable
{
	/**
	 * This function can be used to create a table without worrying about schema
	 *  compatabilities across supported database systems.
	 *  - If the table exists will, by default, do nothing.
	 *  - Builds table with columns as passed to it - at least one column must be sent.
	 *  The columns array should have one sub-array for each column - these sub arrays contain:
	 *    'name' = Column name
	 *    'type' = Type of column - values from (smallint, mediumint, int, text, varchar, char, tinytext, mediumtext, largetext)
	 *    'size' => Size of column (If applicable) - for example 255 for a large varchar, 10 for an int etc.
	 *      If not set it will pick a size.
	 *    - 'default' = Default value - do not set if no default required.
	 *    - 'null' => Can it be null (true or false) - if not set default will be false.
	 *    - 'auto' => Set to true to make it an auto incrementing column. Set to a numerical value to set from what
	 *      it should begin counting.
	 *  - Adds indexes as specified within indexes parameter. Each index should be a member of $indexes. Values are:
	 *    - 'name' => Index name (If left empty it will be generated).
	 *    - 'type' => Type of index. Choose from 'primary', 'unique' or 'index'. If not set will default to 'index'.
	 *    - 'columns' => Array containing columns that form part of key - in the order the index is to be created.
	 *  - parameters: (None yet)
	 *  - if_exists values:
	 *    - 'ignore' will do nothing if the table exists. (And will return true)
	 *    - 'overwrite' will drop any existing table of the same name.
	 *    - 'error' will return false if the table already exists.
	 *
	 * @param string $table_name
	 * @param array $columns in the format specified.
	 * @param array $indexes default array(), in the format specified.
	 * @param array $parameters default array()
	 * @param string $if_exists default 'ignore'
	 * @param string $error default 'fatal'
	 */
	abstract function db_create_table($table_name, $columns, $indexes = array(), $parameters = array(), $if_exists = 'ignore', $error = 'fatal');

	/**
	 * Drop a table.
	 *
	 * @param string $table_name
	 * @param array $parameters default array()
	 * @param string $error default 'fatal'
	 */
	abstract function db_drop_table($table_name, $parameters = array(), $error = 'fatal');

	/**
	 * This function adds a column.
	 *
	 * @param string $table_name the name of the table
	 * @param array $column_info with column information
	 * @param array $parameters default array()
	 * @param string $if_exists default 'update'
	 * @param string $error default 'fatal'
	 */
	abstract function db_add_column($table_name, $column_info, $parameters = array(), $if_exists = 'update', $error = 'fatal');

	/**
	 * Removes a column.
	 *
	 * @param string $table_name
	 * @param string $column_name
	 * @param array $parameters default array()
	 * @param string $error default 'fatal'
	 */
	abstract function db_remove_column($table_name, $column_name, $parameters = array(), $error = 'fatal');

	/**
	 * Change a column.
	 *
	 * @param string $table_name
	 * @param string $old_column
	 * @param $column_info
	 * @param array $parameters default array()
	 * @param string $error default 'fatal'
	 */
	abstract function db_change_column($table_name, $old_column, $column_info, $parameters = array(), $error = 'fatal');

	/**
	 * Add an index.
	 *
	 * @param string $table_name
	 * @param array $index_info
	 * @param array $parameters default array()
	 * @param string $if_exists default 'update'
	 * @param string $error default 'fatal'
	 */
	abstract function db_add_index($table_name, $index_info, $parameters = array(), $if_exists = 'update', $error = 'fatal');

	/**
	 * Remove an index.
	 *
	 * @param string $table_name
	 * @param string $index_name
	 * @param array $parameters default array()
	 * @param string $error default 'fatal'
	 */
	abstract function db_remove_index($table_name, $index_name, $parameters = array(), $error = 'fatal');

	/**
	 * Get the schema formatted name for a type.
	 *
	 * @param string $type_name
	 * @param $type_size
	 * @param $reverse
	 */
	abstract function db_calculate_type($type_name, $type_size = null, $reverse = false);

	/**
	 * Get table structure.
	 *
	 * @param string $table_name
	 * @param array $parameters default array()
	 */
	abstract function db_table_structure($table_name, $parameters = array());

	/**
	 * Return column information for a table.
	 *
	 * @param string $table_name
	 * @param bool $detail
	 * @param array $parameters default array()
	 * @return mixed
	 */
	abstract function db_list_columns($table_name, $detail = false, $parameters = array());

	/**
	 * Get index information.
	 *
	 * @param string $table_name
	 * @param bool $detail
	 * @param array $parameters
	 * @return mixed
	 */
	abstract function db_list_indexes($table_name, $detail = false, $parameters = array());

	/**
	 * Alter table.
	 *
	 * @param string $table_name
	 * @param array $columns
	 */
	function db_alter_table($table_name, $columns)
	{
		// Not implemented by default.
		// Only SQLite needed it.
		return;
	}
}