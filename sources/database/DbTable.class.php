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
 * @version 1.1 beta 3
 *
 */

/**
 * This is used to create a table without worrying about schema compatibilities
 * across supported database systems.
 */
abstract class DbTable
{
	/**
	 * We need a way to interact with the database
	 * @var Database
	 */
	protected $_db = null;

	/**
	 * Array of table names we don't allow to be removed by addons.
	 * @var array
	 */
	protected $_reservedTables = null;

	/**
	 * Keeps a (reverse) log of changes to the table structure, to be undone.
	 * This is used by Packages admin installation/uninstallation/upgrade.
	 *
	 * @var array
	 */
	protected $_package_log = null;

	/**
	 * This function can be used to create a table without worrying about schema
	 *  compatibilities across supported database systems.
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
	 * @param mixed[] $columns in the format specified.
	 * @param mixed[] $indexes default array(), in the format specified.
	 * @param mixed[] $parameters default array()
	 * @param string $if_exists default 'ignore'
	 * @param string $error default 'fatal'
	 */
	abstract public function db_create_table($table_name, $columns, $indexes = array(), $parameters = array(), $if_exists = 'ignore', $error = 'fatal');

	/**
	 * Drop a table.
	 *
	 * @param string $table_name
	 * @param mixed[] $parameters default array()
	 * @param string $error default 'fatal'
	 */
	abstract public function db_drop_table($table_name, $parameters = array(), $error = 'fatal');

	/**
	 * This function adds a column.
	 *
	 * @param string $table_name the name of the table
	 * @param mixed[] $column_info with column information
	 * @param mixed[] $parameters default array()
	 * @param string $if_exists default 'update'
	 * @param string $error default 'fatal'
	 */
	abstract public function db_add_column($table_name, $column_info, $parameters = array(), $if_exists = 'update', $error = 'fatal');

	/**
	 * Removes a column.
	 *
	 * @param string $table_name
	 * @param string $column_name
	 * @param mixed[] $parameters default array()
	 * @param string $error default 'fatal'
	 */
	abstract public function db_remove_column($table_name, $column_name, $parameters = array(), $error = 'fatal');

	/**
	 * Change a column.
	 *
	 * @param string $table_name
	 * @param string $old_column
	 * @param mixed[] $column_info
	 * @param mixed[] $parameters default array()
	 * @param string $error default 'fatal'
	 */
	abstract public function db_change_column($table_name, $old_column, $column_info, $parameters = array(), $error = 'fatal');

	/**
	 * Add an index.
	 *
	 * @param string $table_name
	 * @param mixed[] $index_info
	 * @param mixed[] $parameters default array()
	 * @param string $if_exists default 'update'
	 * @param string $error default 'fatal'
	 */
	abstract public function db_add_index($table_name, $index_info, $parameters = array(), $if_exists = 'update', $error = 'fatal');

	/**
	 * Remove an index.
	 *
	 * @param string $table_name
	 * @param string $index_name
	 * @param mixed[] $parameters default array()
	 * @param string $error default 'fatal'
	 */
	abstract public function db_remove_index($table_name, $index_name, $parameters = array(), $error = 'fatal');

	/**
	 * Get the schema formatted name for a type.
	 *
	 * @param string $type_name
	 * @param int|null $type_size
	 * @param boolean $reverse
	 */
	abstract public function db_calculate_type($type_name, $type_size = null, $reverse = false);

	/**
	 * Get table structure.
	 *
	 * @param string $table_name
	 * @param mixed[] $parameters default array()
	 */
	abstract public function db_table_structure($table_name, $parameters = array());

	/**
	 * Return column information for a table.
	 *
	 * @param string $table_name
	 * @param bool $detail
	 * @param mixed[] $parameters default array()
	 * @return mixed
	 */
	abstract public function db_list_columns($table_name, $detail = false, $parameters = array());

	/**
	 * Get index information.
	 *
	 * @param string $table_name
	 * @param bool $detail
	 * @param mixed[] $parameters
	 * @return mixed
	 */
	abstract public function db_list_indexes($table_name, $detail = false, $parameters = array());

	/**
	 * Optimize a table
	 *
	 * @param string $table - the table to be optimized
	 * @return int
	 */
	abstract public function optimize($table);

	/**
	 * A very simple wrapper around the ALTER TABLE SQL statement.
	 *
	 * @param string $table_name
	 * @param string $statement
	 */
	protected function _alter_table($table_name, $statement)
	{
		return $this->_db->query('', '
			ALTER TABLE ' . $table_name . '
			' . $statement,
			array(
				'security_override' => true,
			)
		);
	}

	/**
	 * Finds a column by name in a table and returns some info.
	 *
	 * @param string $table_name
	 * @param string $column_name
	 * @return mixed[]|false
	 */
	protected function _get_column_info($table_name, $column_name)
	{
		$columns = $this->db_list_columns($table_name, true);

		foreach ($columns as $column)
		{
			if ($column_name == $column['name'])
			{
				return $column;
			}
		}

		return false;
	}

	/**
	 * Return a copy of this instance package log
	 */
	public function package_log()
	{
		return $this->_package_log;
	}

	/**
	 * Checks if a table exists
	 *
	 * @param string $table_name
	 * @return bool
	 */
	public function table_exists($table_name)
	{
		// Grab ourselves one o'these.
		$db = database();

		$filter = $db->db_list_tables(false, $table_name);
		return !empty($filter);
	}

	/**
	 * Checks if a column exists in a table
	 *
	 * @param string $table_name
	 * @param string $column_name
	 * @return bool
	 */
	public function column_exists($table_name, $column_name)
	{
		return $this->_get_column_info($table_name, $column_name) !== false;
	}
}