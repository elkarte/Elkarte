<?php

/**
 * This class implements functionality related to table structure.
 * Intended in particular for addons to change it to suit their needs.
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * This file contains code covered by:
 * copyright:	2011 Simple Machines (http://www.simplemachines.org)
 * license:  	BSD, See included LICENSE.TXT for terms and conditions.
 *
 * @version 1.1 beta 4
 *
 */

/**
 * Adds MySQL table level functionality,
 * Table creation / dropping, column adding / removing
 * Most often used during install and Upgrades of the forum and addons
 */
class DbTable_MySQL extends DbTable
{
	/**
	 * Holds this instance of the table interface
	 * @var DbTable_MySQL
	 */
	private static $_tbl = null;

	/**
	 * DbTable_MySQL::construct
	 *
	 * @param object $db - A Database_MySQL object
	 */
	private function __construct($db)
	{
		global $db_prefix;

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
			$this->_reservedTables[$k] = strtolower($db_prefix . $table_name);

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
	 * @param mixed[] $parameters default array()
	 * @param string $if_exists default 'ignore'
	 * @param string $error default 'fatal'
	 */
	public function db_create_table($table_name, $columns, $indexes = array(), $parameters = array(), $if_exists = 'ignore', $error = 'fatal')
	{
		global $db_prefix;

		// Strip out the table name, we might not need it in some cases
		$real_prefix = preg_match('~^(`?)(.+?)\\1\\.(.*?)$~', $db_prefix, $match) === 1 ? $match[3] : $db_prefix;

		// With or without the database name, the fullname looks like this.
		$full_table_name = str_replace('{db_prefix}', $real_prefix, $table_name);
		$table_name = str_replace('{db_prefix}', $db_prefix, $table_name);

		// First - no way do we touch our tables.
		if (in_array(strtolower($table_name), $this->_reservedTables))
			return false;

		// Log that we'll want to remove this on uninstall.
		$this->_package_log[] = array('remove_table', $table_name);

		// Slightly easier on MySQL than the others...
		if ($this->table_exists($full_table_name))
		{
			// This is a sad day... drop the table? If not, return false (error) by default.
			if ($if_exists == 'overwrite')
				$this->db_drop_table($table_name);
			else
				return $if_exists == 'ignore';
		}

		// Righty - let's do the damn thing!
		$table_query = 'CREATE TABLE ' . $table_name . "\n" . '(';
		foreach ($columns as $column)
			$table_query .= "\n\t" . $this->_db_create_query_column($column) . ',';

		// Loop through the indexes next...
		foreach ($indexes as $index)
		{
			$columns = implode(',', $index['columns']);

			// Is it the primary?
			if (isset($index['type']) && $index['type'] == 'primary')
				$table_query .= "\n\t" . 'PRIMARY KEY (' . $columns . '),';
			else
			{
				if (empty($index['name']))
					$index['name'] = implode('_', $index['columns']);
				$table_query .= "\n\t" . (isset($index['type']) && $index['type'] == 'unique' ? 'UNIQUE' : 'KEY') . ' ' . $index['name'] . ' (' . $columns . '),';
			}
		}

		// No trailing commas!
		if (substr($table_query, -1) == ',')
			$table_query = substr($table_query, 0, -1);

		$table_query .= ') DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci';

		// Create the table!
		$this->_db->query('', $table_query,
			array(
				'security_override' => true,
			)
		);

		return true;
	}

	/**
	 * Drop a table.
	 *
	 * @param string $table_name
	 * @param mixed[] $parameters default array()
	 * @param string $error default 'fatal'
	 */
	public function db_drop_table($table_name, $parameters = array(), $error = 'fatal')
	{
		global $db_prefix;

		// After stripping away the database name, this is what's left.
		$real_prefix = preg_match('~^(`?)(.+?)\\1\\.(.*?)$~', $db_prefix, $match) === 1 ? $match[3] : $db_prefix;

		// Get some aliases.
		$full_table_name = str_replace('{db_prefix}', $real_prefix, $table_name);
		$table_name = str_replace('{db_prefix}', $db_prefix, $table_name);

		// God no - dropping one of these = bad.
		if (in_array(strtolower($table_name), $this->_reservedTables))
			return false;

		// Does it exist?
		if ($this->table_exists($full_table_name))
		{
			$query = 'DROP TABLE ' . $table_name;
			$this->_db->query('',
				$query,
				array(
					'security_override' => true,
				)
			);

			return true;
		}

		// Otherwise do 'nout.
		return false;
	}

	/**
	 * This function adds a column.
	 *
	 * @param string $table_name the name of the table
	 * @param mixed[] $column_info with column information
	 * @param mixed[] $parameters default array()
	 * @param string $if_exists default 'update'
	 * @param string $error default 'fatal'
	 */
	public function db_add_column($table_name, $column_info, $parameters = array(), $if_exists = 'update', $error = 'fatal')
	{
		global $db_prefix;

		$table_name = str_replace('{db_prefix}', $db_prefix, $table_name);

		// Log that we will want to uninstall this!
		$this->_package_log[] = array('remove_column', $table_name, $column_info['name']);

		// Does it exist - if so don't add it again!
		if ($this->_get_column_info($table_name, $column_info['name']))
		{
			// If we're going to overwrite then use change column.
			if ($if_exists == 'update')
				return $this->db_change_column($table_name, $column_info['name'], $column_info);
			else
				return false;
		}

		// Now add the thing!
		$this->_alter_table($table_name, '
			ADD ' . $this->_db_create_query_column($column_info) . (empty($column_info['auto']) ? '' : ' primary key'));

		return true;
	}

	/**
	 * Removes a column.
	 *
	 * @param string $table_name
	 * @param string $column_name
	 * @param mixed[] $parameters default array()
	 * @param string $error default 'fatal'
	 */
	public function db_remove_column($table_name, $column_name, $parameters = array(), $error = 'fatal')
	{
		global $db_prefix;

		$table_name = str_replace('{db_prefix}', $db_prefix, $table_name);

		// Does it exist?
		$column = $this->_get_column_info($table_name, $column_name);
		if ($column !== false)
		{
			$this->_alter_table($table_name, '
				DROP COLUMN ' . $column_name);

			return true;
		}

		// If here we didn't have to work - joy!
		return false;
	}

	/**
	 * Change a column.
	 *
	 * @param string $table_name
	 * @param string $old_column
	 * @param mixed[] $column_info
	 * @param mixed[] $parameters default array()
	 * @param string $error default 'fatal'
	 */
	public function db_change_column($table_name, $old_column, $column_info, $parameters = array(), $error = 'fatal')
	{
		global $db_prefix;

		$table_name = str_replace('{db_prefix}', $db_prefix, $table_name);

		// Check it does exist!
		$old_info = $this->_get_column_info($table_name, $old_column);

		// Nothing?
		if ($old_info === false)
			return false;

		// Get the right bits.
		if (!isset($column_info['name']))
			$column_info['name'] = $old_column;
		if (!isset($column_info['default']))
			$column_info['default'] = $old_info['default'];
		if (!isset($column_info['null']))
			$column_info['null'] = $old_info['null'];
		if (!isset($column_info['auto']))
			$column_info['auto'] = $old_info['auto'];
		if (!isset($column_info['type']))
			$column_info['type'] = $old_info['type'];
		if (!isset($column_info['size']) || !is_numeric($column_info['size']))
			$column_info['size'] = $old_info['size'];
		if (!isset($column_info['unsigned']) || !in_array($column_info['type'], array('int', 'tinyint', 'smallint', 'mediumint', 'bigint')))
			$column_info['unsigned'] = '';

		$this->_alter_table($table_name, '
			CHANGE COLUMN `' . $old_column . '` ' . $this->_db_create_query_column($column_info));
	}

	/**
	 * Add an index.
	 *
	 * @param string $table_name
	 * @param mixed[] $index_info
	 * @param mixed[] $parameters default array()
	 * @param string $if_exists default 'update'
	 * @param string $error default 'fatal'
	 */
	public function db_add_index($table_name, $index_info, $parameters = array(), $if_exists = 'update', $error = 'fatal')
	{
		global $db_prefix;

		$table_name = str_replace('{db_prefix}', $db_prefix, $table_name);

		// No columns = no index.
		if (empty($index_info['columns']))
			return false;
		$columns = implode(',', $index_info['columns']);

		// No name - make it up!
		if (empty($index_info['name']))
		{
			// No need for primary.
			if (isset($index_info['type']) && $index_info['type'] == 'primary')
				$index_info['name'] = '';
			else
				$index_info['name'] = implode('_', $index_info['columns']);
		}

		// Log that we are going to want to remove this!
		$this->_package_log[] = array('remove_index', $table_name, $index_info['name']);

		// Let's get all our indexes.
		$indexes = $this->db_list_indexes($table_name, true);

		// Do we already have it?
		foreach ($indexes as $index)
		{
			if ($index['name'] == $index_info['name'] || ($index['type'] == 'primary' && isset($index_info['type']) && $index_info['type'] == 'primary'))
			{
				// If we want to overwrite simply remove the current one then continue.
				if ($if_exists != 'update' || $index['type'] == 'primary')
					return false;
				else
					$this->db_remove_index($table_name, $index_info['name']);
			}
		}

		// If we're here we know we don't have the index - so just add it.
		if (!empty($index_info['type']) && $index_info['type'] == 'primary')
		{
			$this->_alter_table($table_name, '
				ADD PRIMARY KEY (' . $columns . ')');
		}
		else
		{
			$this->_alter_table($table_name, '
				ADD ' . (isset($index_info['type']) && $index_info['type'] == 'unique' ? 'UNIQUE' : 'INDEX') . ' ' . $index_info['name'] . ' (' . $columns . ')');
		}
	}

	/**
	 * Remove an index.
	 *
	 * @param string $table_name
	 * @param string $index_name
	 * @param mixed[] $parameters default array()
	 * @param string $error default 'fatal'
	 */
	public function db_remove_index($table_name, $index_name, $parameters = array(), $error = 'fatal')
	{
		global $db_prefix;

		$table_name = str_replace('{db_prefix}', $db_prefix, $table_name);

		// Better exist!
		$indexes = $this->db_list_indexes($table_name, true);

		foreach ($indexes as $index)
		{
			// If the name is primary we want the primary key!
			if ($index['type'] == 'primary' && $index_name == 'primary')
			{
				// Dropping primary key?
				$this->_alter_table($table_name, '
					DROP PRIMARY KEY');

				return true;
			}

			if ($index['name'] == $index_name)
			{
				// Drop the bugger...
				$this->_alter_table($table_name, '
					DROP INDEX ' . $index_name);

				return true;
			}
		}

		// Not to be found ;(
		return false;
	}

	/**
	 * Get the schema formatted name for a type.
	 *
	 * @param string $type_name
	 * @param int|null $type_size
	 * @param boolean $reverse
	 */
	public function db_calculate_type($type_name, $type_size = null, $reverse = false)
	{
		// MySQL is actually the generic baseline.
		return array($type_name, $type_size);
	}

	/**
	 * Get table structure.
	 *
	 * @param string $table_name
	 * @param mixed[] $parameters default array()
	 */
	public function db_table_structure($table_name, $parameters = array())
	{
		global $db_prefix;

		$table_name = str_replace('{db_prefix}', $db_prefix, $table_name);

		return array(
			'name' => $table_name,
			'columns' => $this->db_list_columns($table_name, true),
			'indexes' => $this->db_list_indexes($table_name, true),
		);
	}

	/**
	 * Return column information for a table.
	 *
	 * @param string $table_name
	 * @param bool $detail
	 * @param mixed[] $parameters default array()
	 * @return mixed
	 */
	public function db_list_columns($table_name, $detail = false, $parameters = array())
	{
		global $db_prefix;

		$table_name = str_replace('{db_prefix}', $db_prefix, $table_name);

		$result = $this->_db->query('', '
			SHOW FIELDS
			FROM {raw:table_name}',
			array(
				'table_name' => substr($table_name, 0, 1) == '`' ? $table_name : '`' . $table_name . '`',
			)
		);
		$columns = array();
		while ($row = $this->_db->fetch_assoc($result))
		{
			if (!$detail)
			{
				$columns[] = $row['Field'];
			}
			else
			{
				// Is there an auto_increment?
				$auto = strpos($row['Extra'], 'auto_increment') !== false ? true : false;

				// Can we split out the size?
				if (preg_match('~(.+?)\s*\((\d+)\)(?:(?:\s*)?(unsigned))?~i', $row['Type'], $matches) === 1)
				{
					$type = $matches[1];
					$size = $matches[2];
					if (!empty($matches[3]) && $matches[3] == 'unsigned')
						$unsigned = true;
				}
				else
				{
					$type = $row['Type'];
					$size = null;
				}

				$columns[$row['Field']] = array(
					'name' => $row['Field'],
					'null' => $row['Null'] != 'YES' ? false : true,
					'default' => isset($row['Default']) ? $row['Default'] : null,
					'type' => $type,
					'size' => $size,
					'auto' => $auto,
				);

				if (isset($unsigned))
				{
					$columns[$row['Field']]['unsigned'] = $unsigned;
					unset($unsigned);
				}
			}
		}
		$this->_db->free_result($result);

		return $columns;
	}

	/**
	 * Get index information.
	 *
	 * @param string $table_name
	 * @param bool $detail
	 * @param mixed[] $parameters
	 * @return mixed
	 */
	public function db_list_indexes($table_name, $detail = false, $parameters = array())
	{
		global $db_prefix;

		$table_name = str_replace('{db_prefix}', $db_prefix, $table_name);

		$result = $this->_db->query('', '
			SHOW KEYS
			FROM {raw:table_name}',
			array(
				'table_name' => substr($table_name, 0, 1) == '`' ? $table_name : '`' . $table_name . '`',
			)
		);
		$indexes = array();
		while ($row = $this->_db->fetch_assoc($result))
		{
			if (!$detail)
				$indexes[] = $row['Key_name'];
			else
			{
				// What is the type?
				if ($row['Key_name'] == 'PRIMARY')
					$type = 'primary';
				elseif (empty($row['Non_unique']))
					$type = 'unique';
				elseif (isset($row['Index_type']) && $row['Index_type'] == 'FULLTEXT')
					$type = 'fulltext';
				else
					$type = 'index';

				// This is the first column we've seen?
				if (empty($indexes[$row['Key_name']]))
				{
					$indexes[$row['Key_name']] = array(
						'name' => $row['Key_name'],
						'type' => $type,
						'columns' => array(),
					);
				}

				// Is it a partial index?
				if (!empty($row['Sub_part']))
					$indexes[$row['Key_name']]['columns'][] = $row['Column_name'] . '(' . $row['Sub_part'] . ')';
				else
					$indexes[$row['Key_name']]['columns'][] = $row['Column_name'];
			}
		}
		$this->_db->free_result($result);

		return $indexes;
	}

	/**
	 * Creates a query for a column
	 *
	 * @param mixed[] $column
	 */
	private function _db_create_query_column($column)
	{
		// Auto increment is easy here!
		if (!empty($column['auto']))
		{
			$default = 'auto_increment';
		}
		elseif (isset($column['default']) && $column['default'] !== null)
			$default = 'default \'' . $this->_db->escape_string($column['default']) . '\'';
		else
			$default = '';

		// Sort out the size... and stuff...
		$column['size'] = isset($column['size']) && is_numeric($column['size']) ? $column['size'] : null;
		list ($type, $size) = $this->db_calculate_type($column['type'], $column['size']);

		// Allow unsigned integers (mysql only)
		$unsigned = in_array($type, array('int', 'tinyint', 'smallint', 'mediumint', 'bigint', 'float')) && !empty($column['unsigned']) ? 'unsigned ' : '';

		if ($size !== null)
			$type = $type . '(' . $size . ')';

		// Now just put it together!
		return '`' . $column['name'] . '` ' . $type . ' ' . (!empty($unsigned) ? $unsigned : '') . (!empty($column['null']) ? '' : 'NOT NULL') . ' ' . $default;
	}

	/**
	 * This function optimizes a table.
	 *
	 * @param string $table - the table to be optimized
	 *
	 * @return int how much it was gained
	 */
	public function optimize($table)
	{
		global $db_prefix;

		$table = str_replace('{db_prefix}', $db_prefix, $table);

		// Get how much overhead there is.
		$request = $this->_db->query('', '
			SHOW TABLE STATUS LIKE {string:table_name}',
			array(
				'table_name' => str_replace('_', '\_', $table),
			)
		);
		$row = $this->_db->fetch_assoc($request);
		$this->_db->free_result($request);

		$data_before = isset($row['Data_free']) ? $row['Data_free'] : 0;
		$request = $this->_db->query('', '
			OPTIMIZE TABLE `{raw:table}`',
			array(
				'table' => $table,
			)
		);
		if (!$request)
			return -1;

		// How much left?
		$request = $this->_db->query('', '
			SHOW TABLE STATUS LIKE {string:table}',
			array(
				'table' => str_replace('_', '\_', $table),
			)
		);
		$row = $this->_db->fetch_assoc($request);
		$this->_db->free_result($request);

		$total_change = isset($row['Data_free']) && $data_before > $row['Data_free'] ? $data_before / 1024 : 0;

		return $total_change;
	}

	/**
	 * Return a copy of this instance package log
	 */
	public function package_log()
	{
		return $this->_package_log;
	}

	/**
	 * Static method that allows to retrieve or create an instance of this class.
	 *
	 * @param object $db - A Database_MySQL object
	 * @return DbTable_MySQL - A DbTable_MySQL object
	 */
	public static function db_table($db)
	{
		if (is_null(self::$_tbl))
			self::$_tbl = new DbTable_MySQL($db);
		return self::$_tbl;
	}
}