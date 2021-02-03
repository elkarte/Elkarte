<?php

/**
 * This is the base class for DbTable functionality.
 * It contains abstract methods to be implemented for the specific database system,
 * related to a table structure.
 * Add-ons will need this, to change the database for their needs.
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
 * This is used to create a table without worrying about schema compatibilities
 * across supported database systems.
 */
abstract class AbstractTable
{
	/**
	 * We need a way to interact with the database
	 *
	 * @var \ElkArte\Database\QueryInterface
	 */
	protected $_db = null;

	/**
	 * The forum tables prefix
	 *
	 * @var string
	 */
	protected $_db_prefix = null;

	/**
	 * Array of table names we don't allow to be removed by addons.
	 *
	 * @var array
	 */
	protected $_reservedTables = null;

	/**
	 * Keeps a (reverse) log of changes to the table structure, to be undone.
	 * This is used by Packages admin installation/uninstalling/upgrade.
	 *
	 * @var array
	 */
	protected $_package_log = null;

	/**
	 * DbTable::construct
	 *
	 * @param object $db - An implementation of the abstract DbTable
	 * @param string $db_prefix - Database tables prefix
	 */
	public function __construct($db, $db_prefix)
	{
		$this->_db_prefix = $db_prefix;

		// We won't do any remove on these
		$this->_reservedTables = array('admin_info_files', 'approval_queue', 'attachments', 'ban_groups', 'ban_items',
									   'board_permissions', 'boards', 'calendar', 'calendar_holidays', 'categories', 'collapsed_categories',
									   'custom_fields', 'group_moderators', 'log_actions', 'log_activity', 'log_banned', 'log_boards',
									   'log_digest', 'log_errors', 'log_floodcontrol', 'log_group_requests', 'log_karma', 'log_mark_read',
									   'log_notify', 'log_online', 'log_packages', 'log_polls', 'log_reported', 'log_reported_comments',
									   'log_scheduled_tasks', 'log_search_messages', 'log_search_results', 'log_search_subjects',
									   'log_search_topics', 'log_topics', 'mail_queue', 'membergroups', 'members', 'message_icons',
									   'messages', 'moderators', 'package_servers', 'permission_profiles', 'permissions', 'personal_messages',
									   'pm_recipients', 'poll_choices', 'polls', 'scheduled_tasks', 'sessions', 'settings', 'smileys',
									   'themes', 'topics');

		foreach ($this->_reservedTables as $k => $table_name)
		{
			$this->_reservedTables[$k] = strtolower($this->_db_prefix . $table_name);
		}

		// let's be sure.
		$this->_package_log = array();

		// This executes queries and things
		$this->_db = $db;
	}

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
	 * @param mixed[] $parameters default array(
	 *                  'if_exists' => 'ignore',
	 *                  'temporary' => false,
	 *                )
	 * @return bool
	 * @throws \ElkArte\Exceptions\Exception
	 */
	public function create_table($table_name, $columns, $indexes = array(), $parameters = array())
	{
		$parameters = array_merge(array(
			'if_exists' => 'ignore',
			'temporary' => false,
		), $parameters);

		// With or without the database name, the fullname looks like this.
		$full_table_name = str_replace('{db_prefix}', $this->_real_prefix(), $table_name);
		$table_name = str_replace('{db_prefix}', $this->_db_prefix, $table_name);

		// First - no way do we touch our tables.
		if (in_array(strtolower($table_name), $this->_reservedTables))
		{
			return false;
		}

		// Log that we'll want to remove this on uninstall.
		$this->_package_log[] = array('remove_table', $table_name);

		// This... my friends... is a function in a half - let's start by checking if the table exists!
		if ($parameters['if_exists'] === 'force_drop')
		{
			$this->drop_table($table_name, true);
		}
		elseif ($this->table_exists($full_table_name))
		{
			// This is a sad day... drop the table? If not, return false (error) by default.
			if ($parameters['if_exists'] === 'overwrite')
			{
				$this->drop_table($table_name);
			}
			else
			{
				return $parameters['if_exists'] === 'ignore';
			}
		}

		// If we've got this far - good news - no table exists. We can build our own!
		$this->_db->transaction('begin');

		if ($parameters['temporary'] !== true)
		{
			$table_query = 'CREATE TABLE ' . $table_name . "\n" . '(';
		}
		else
		{
			$table_query = 'CREATE TEMPORARY TABLE ' . $table_name . "\n" . '(';
		}
		foreach ($columns as $column)
		{
			$table_query .= "\n\t" . $this->_db_create_query_column($column, $table_name) . ',';
		}

		$table_query .= $this->_create_query_indexes($indexes, $table_name);

		// No trailing commas!
		if (substr($table_query, -1) === ',')
		{
			$table_query = substr($table_query, 0, -1);
		}

		$table_query .= $this->_close_table_query($parameters['temporary']);

		// Create the table!
		$this->_db->query('', $table_query,
			array(
				'security_override' => true,
			)
		);

		// And the indexes... if any
		$this->_build_indexes();

		// Go, go power rangers!
		$this->_db->transaction('commit');

		return true;
	}

	/**
	 * Strips out the table name, we might not need it in some cases
	 */
	abstract protected function _real_prefix();

	/**
	 * Drop a table.
	 *
	 * @param string $table_name
	 * @param bool $force If forcing the drop or not. Useful in case of temporary
	 *                    tables that may not be detected as existing.
	 */
	abstract public function drop_table($table_name, $force = false);

	/**
	 * Checks if a table exists
	 *
	 * @param string $table_name
	 * @return bool
	 */
	public function table_exists($table_name)
	{
		$filter = $this->_db->list_tables(false, $table_name);

		return !empty($filter);
	}

	/**
	 * It is mean to parse the indexes array of a create_table function
	 * to prepare for the indexes creation
	 *
	 * @param string[] $indexes
	 * @param string $table_name
	 * @return string
	 */
	abstract protected function _create_query_indexes($indexes, $table_name);

	/**
	 * Adds the closing "touch" to the CREATE TABLE query
	 *
	 * @param bool $temporary - If the table is temporary
	 * @return string
	 */
	abstract protected function _close_table_query($temporary);

	/**
	 * In certain cases it is necessary to create the indexes of a
	 * newly created table with new queries after the table has been created.
	 *
	 * @param string[] $indexes
	 * @return string
	 */
	protected function _build_indexes()
	{
		return;
	}

	/**
	 * This function adds a column.
	 *
	 * @param string $table_name the name of the table
	 * @param mixed[] $column_info with column information
	 * @param mixed[] $parameters default array()
	 * @param string $if_exists default 'update'
	 */
	abstract public function add_column($table_name, $column_info, $parameters = array(), $if_exists = 'update');

	/**
	 * Removes a column.
	 *
	 * @param string $table_name
	 * @param string $column_name
	 * @param mixed[] $parameters default array()
	 */
	abstract public function remove_column($table_name, $column_name, $parameters = array());

	/**
	 * Change a column.
	 *
	 * @param string $table_name
	 * @param string $old_column
	 * @param mixed[] $column_info
	 * @param mixed[] $parameters default array()
	 */
	abstract public function change_column($table_name, $old_column, $column_info, $parameters = array());

	/**
	 * Add an index.
	 *
	 * @param string $table_name
	 * @param mixed[] $index_info
	 * @param mixed[] $parameters default array()
	 * @param string $if_exists default 'update'
	 */
	abstract public function add_index($table_name, $index_info, $parameters = array(), $if_exists = 'update');

	/**
	 * Remove an index.
	 *
	 * @param string $table_name
	 * @param string $index_name
	 * @param mixed[] $parameters default array()
	 */
	abstract public function remove_index($table_name, $index_name, $parameters = array());

	/**
	 * Get the schema formatted name for a type.
	 *
	 * @param string $type_name
	 * @param int|null $type_size
	 * @param bool $reverse
	 */
	abstract public function calculate_type($type_name, $type_size = null, $reverse = false);

	/**
	 * Optimize a table
	 *
	 * @param string $table - the table to be optimized
	 * @return int - how much it was gained
	 */
	abstract public function optimize($table);

	/**
	 * Return a copy of this instance package log
	 */
	public function package_log()
	{
		return $this->_package_log;
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

	/**
	 * Finds a column by name in a table and returns some info.
	 *
	 * @param string $table_name
	 * @param string $column_name
	 * @return mixed[]|false
	 */
	protected function _get_column_info($table_name, $column_name)
	{
		$columns = $this->list_columns($table_name, true);

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
	 * Return column information for a table.
	 *
	 * @param string $table_name
	 * @param bool $detail
	 * @param mixed[] $parameters default array()
	 * @return mixed
	 */
	abstract public function list_columns($table_name, $detail = false, $parameters = array());

	/**
	 * Returns name, columns and indexes of a table
	 *
	 * @param string $table_name
	 * @return mixed[]
	 */
	public function table_structure($table_name)
	{
		$table_name = str_replace('{db_prefix}', $this->_db_prefix, $table_name);

		return array(
			'name' => $table_name,
			'columns' => $this->list_columns($table_name, true),
			'indexes' => $this->list_indexes($table_name, true),
		);
	}

	/**
	 * Get index information.
	 *
	 * @param string $table_name
	 * @param bool $detail
	 * @param mixed[] $parameters
	 * @return mixed
	 */
	abstract public function list_indexes($table_name, $detail = false, $parameters = array());

	/**
	 * Clean the indexes strings (e.g. PostgreSQL doesn't support max length)
	 *
	 * @param string[] $columns
	 * @return string
	 */
	protected function _clean_indexes($columns)
	{
		return $columns;
	}

	/**
	 * A very simple wrapper around the ALTER TABLE SQL statement.
	 *
	 * @param string $table_name
	 * @param string $statement
	 * @return bool|\ElkArte\Database\AbstractResult
	 * @throws \ElkArte\Exceptions\Exception
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
}
