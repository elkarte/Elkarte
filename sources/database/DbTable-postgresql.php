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
 * @version 1.1
 *
 */

/**
 * Adds PostgreSQL table level functionality,
 * Table creation / dropping, column adding / removing
 * Most often used during install and Upgrades of the forum and addons
 */
class DbTable_PostgreSQL extends DbTable
{
	/**
	 * Holds this instance of the table interface
	 * @var DbTable_PostgreSQL
	 */
	private static $_tbl = null;

	/**
	 * DbTable_PostgreSQL::construct
	 *
	 * @param object $db - A Database_PostgreSQL object
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
		$real_prefix = preg_match('~^("?)(.+?)\\1\\.(.*?)$~', $db_prefix, $match) === 1 ? $match[3] : $db_prefix;

		// With or without the database name, the fullname looks like this.
		$full_table_name = str_replace('{db_prefix}', $real_prefix, $table_name);
		$table_name = str_replace('{db_prefix}', $db_prefix, $table_name);

		// First - no way do we touch our tables.
		if (in_array(strtolower($table_name), $this->_reservedTables))
			return false;

		// Log that we'll want to remove this on uninstall.
		$this->_package_log[] = array('remove_table', $table_name);

		// This... my friends... is a function in a half - let's start by checking if the table exists!
		if ($this->table_exists($full_table_name))
		{
			// This is a sad day... drop the table? If not, return false (error) by default.
			if ($if_exists == 'overwrite')
				$this->db_drop_table($table_name);
			else
				return $if_exists == 'ignore';
		}

		// If we've got this far - good news - no table exists. We can build our own!
		$this->_db->db_transaction('begin');
		$table_query = 'CREATE TABLE ' . $table_name . "\n" . '(';
		foreach ($columns as $column)
		{
			// If we have an auto increment do it!
			if (!empty($column['auto']))
			{
				$this->_db->query('', '
					CREATE SEQUENCE ' . $table_name . '_seq',
					array(
						'security_override' => true,
					)
				);
				$default = 'default nextval(\'' . $table_name . '_seq\')';
			}
			elseif (isset($column['default']) && $column['default'] !== null)
				$default = 'default \'' . $this->_db->escape_string($column['default']) . '\'';
			else
				$default = '';

			// Sort out the size...
			$column['size'] = isset($column['size']) && is_numeric($column['size']) ? $column['size'] : null;
			list ($type, $size) = $this->db_calculate_type($column['type'], $column['size']);
			if ($size !== null)
				$type = $type . '(' . $size . ')';

			// Now just put it together!
			$table_query .= "\n\t\"" . $column['name'] . '" ' . $type . ' ' . (!empty($column['null']) ? '' : 'NOT NULL') . ' ' . $default . ',';
		}

		// Loop through the indexes a sec...
		$index_queries = array();
		foreach ($indexes as $index)
		{
			// MySQL supports a length argument, postgre no
			foreach ($index['columns'] as $id => $col)
				if (strpos($col, '(') !== false)
					$index['columns'][$id] = substr($col, 0, strpos($col, '('));

			$columns = implode(',', $index['columns']);

			// Primary goes in the table...
			if (isset($index['type']) && $index['type'] == 'primary')
				$table_query .= "\n\t" . 'PRIMARY KEY (' . $columns . '),';
			else
			{
				if (empty($index['name']))
					$index['name'] = implode('_', $index['columns']);
				$index_queries[] = 'CREATE ' . (isset($index['type']) && $index['type'] == 'unique' ? 'UNIQUE' : '') . ' INDEX ' . $table_name . '_' . $index['name'] . ' ON ' . $table_name . ' (' . $columns . ')';
			}
		}

		// No trailing commas!
		if (substr($table_query, -1) == ',')
			$table_query = substr($table_query, 0, -1);

		$table_query .= ')';

		// Create the table!
		$this->_db->query('', $table_query,
			array(
				'security_override' => true,
			)
		);
		// And the indexes...
		foreach ($index_queries as $query)
		{
			$this->_db->query('', $query,
				array(
					'security_override' => true,
				)
			);
		}

		// Go, go power rangers!
		$this->_db->db_transaction('commit');

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
		$real_prefix = preg_match('~^("?)(.+?)\\1\\.(.*?)$~', $db_prefix, $match) === 1 ? $match[3] : $db_prefix;

		// Get some aliases.
		$full_table_name = str_replace('{db_prefix}', $real_prefix, $table_name);
		$table_name = str_replace('{db_prefix}', $db_prefix, $table_name);

		// God no - dropping one of these = bad.
		if (in_array(strtolower($table_name), $this->_reservedTables))
			return false;

		// Does it exist?
		if ($this->table_exists($full_table_name))
		{
			// We can then drop the table.
			$this->_db->db_transaction('begin');

			// the table
			$table_query = 'DROP TABLE ' . $table_name;

			// and the associated sequence, if any
			$sequence_query = 'DROP SEQUENCE IF EXISTS ' . $table_name . '_seq';

			// drop them
			$this->_db->query('',
				$table_query,
				array(
					'security_override' => true,
				)
			);
			$this->_db->query('',
				$sequence_query,
				array(
					'security_override' => true,
				)
			);

			$this->_db->db_transaction('commit');

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

		// Get the specifics...
		$column_info['size'] = isset($column_info['size']) && is_numeric($column_info['size']) ? $column_info['size'] : null;
		list ($type, $size) = $this->db_calculate_type($column_info['type'], $column_info['size']);
		if ($size !== null)
			$type = $type . '(' . $size . ')';

		// Now add the thing!
		$this->_alter_table($table_name, '
			ADD COLUMN ' . $column_info['name'] . ' ' . $type);

		// If there's more attributes they need to be done via a change on PostgreSQL.
		unset($column_info['type'], $column_info['size']);

		if (count($column_info) != 1)
			return $this->db_change_column($table_name, $column_info['name'], $column_info);
		else
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
			// If there is an auto we need remove it!
			if ($column['auto'])
				$this->_db->query('',
					'DROP SEQUENCE ' . $table_name . '_seq',
					array(
						'security_override' => true,
					)
				);

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

		// Now we check each bit individually and ALTER as required.
		if (isset($column_info['name']) && $column_info['name'] != $old_column)
		{
			$this->_alter_table($table_name, '
				RENAME COLUMN ' . $old_column . ' TO ' . $column_info['name']);
		}

		// Different default?
		if (isset($column_info['default']) && $column_info['default'] != $old_info['default'])
		{
			$action = $column_info['default'] !== null ? 'SET DEFAULT \'' . $this->_db->escape_string($column_info['default']) . '\'' : 'DROP DEFAULT';
			$this->_alter_table($table_name, '
				ALTER COLUMN ' . $column_info['name'] . ' ' . $action);
		}

		// Is it null - or otherwise?
		if (isset($column_info['null']) && $column_info['null'] != $old_info['null'])
		{
			$action = $column_info['null'] ? 'DROP' : 'SET';
			$this->_db->db_transaction('begin');
			if (!$column_info['null'])
			{
				// We have to set it to something if we are making it NOT NULL. And we must comply with the current column format.
				$setTo = isset($column_info['default']) ? $column_info['default'] : (strpos($old_info['type'], 'int') !== false ? 0 : '');
				$this->_db->query('', '
					UPDATE ' . $table_name . '
					SET ' . $column_info['name'] . ' = \'' . $setTo . '\'
					WHERE ' . $column_info['name'] . ' IS NULL',
					array(
						'security_override' => true,
					)
				);
			}
			$this->_alter_table($table_name, '
				ALTER COLUMN ' . $column_info['name'] . ' ' . $action . ' NOT NULL');
			$this->_db->db_transaction('commit');
		}

		// What about a change in type?
		if (isset($column_info['type']) && ($column_info['type'] != $old_info['type'] || (isset($column_info['size']) && $column_info['size'] != $old_info['size'])))
		{
			$column_info['size'] = isset($column_info['size']) && is_numeric($column_info['size']) ? $column_info['size'] : null;
			list ($type, $size) = $this->db_calculate_type($column_info['type'], $column_info['size']);
			if ($size !== null)
				$type = $type . '(' . $size . ')';

			// The alter is a pain.
			$this->_db->db_transaction('begin');
			$this->_alter_table($table_name, '
				ADD COLUMN ' . $column_info['name'] . '_tempxx ' . $type);
			$this->_db->query('', '
				UPDATE ' . $table_name . '
				SET ' . $column_info['name'] . '_tempxx = CAST(' . $column_info['name'] . ' AS ' . $type . ')',
				array(
					'security_override' => true,
				)
			);
			$this->_alter_table($table_name, '
				DROP COLUMN ' . $column_info['name']);
			$this->_alter_table($table_name, '
				RENAME COLUMN ' . $column_info['name'] . '_tempxx TO ' . $column_info['name']);
			$this->_db->db_transaction('commit');
		}

		// Finally - auto increment?!
		if (isset($column_info['auto']) && $column_info['auto'] != $old_info['auto'])
		{
			// Are we removing an old one?
			if ($old_info['auto'])
			{
				// Alter the table first - then drop the sequence.
				$this->_alter_table($table_name, '
					ALTER COLUMN ' . $column_info['name'] . ' SET DEFAULT \'0\'');
				$this->_db->query('', '
					DROP SEQUENCE ' . $table_name . '_seq',
					array(
						'security_override' => true,
					)
				);
			}
			// Otherwise add it!
			else
			{
				$this->_db->query('', '
					CREATE SEQUENCE ' . $table_name . '_seq',
					array(
						'security_override' => true,
					)
				);
				$this->_alter_table($table_name, '
					ALTER COLUMN ' . $column_info['name'] . ' SET DEFAULT nextval(\'' . $table_name . '_seq\')');
			}
		}
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

		// MySQL supports a length argument, postgre no
		foreach ($index_info['columns'] as $id => $col)
			if (strpos($col, '(') !== false)
				$index_info['columns'][$id] = substr($col, 0, strpos($col, '('));

		$columns = implode(',', $index_info['columns']);

		// No name - make it up!
		if (empty($index_info['name']))
		{
			// No need for primary.
			if (isset($index_info['type']) && $index_info['type'] == 'primary')
				$index_info['name'] = '';
			else
				$index_info['name'] = $table_name . implode('_', $index_info['columns']);
		}
		else
			$index_info['name'] = $table_name . $index_info['name'];

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
			$this->_db->query('', '
				CREATE ' . (isset($index_info['type']) && $index_info['type'] == 'unique' ? 'UNIQUE' : '') . ' INDEX ' . $index_info['name'] . ' ON ' . $table_name . ' (' . $columns . ')',
				array(
					'security_override' => true,
				)
			);
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
		if ($index_name != 'primary')
			$index_name = $table_name . '_' . $index_name;

		foreach ($indexes as $index)
		{
			// If the name is primary we want the primary key!
			if ($index['type'] == 'primary' && $index_name == 'primary')
			{
				// Dropping primary key is odd...
				$this->_alter_table($table_name, '
					DROP CONSTRAINT ' . $index['name']);

				return true;
			}

			if ($index['name'] == $index_name)
			{
				// Drop the bugger...
				$this->_db->query('', '
					DROP INDEX ' . $index_name,
					array(
						'security_override' => true,
					)
				);

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
		// Let's be sure it's lowercase MySQL likes both, others no.
		$type_name = strtolower($type_name);

		// Generic => Specific.
		if (!$reverse)
		{
			$types = array(
				'varchar' => 'character varying',
				'char' => 'character',
				'mediumint' => 'int',
				'tinyint' => 'smallint',
				'tinytext' => 'character varying',
				'mediumtext' => 'text',
				'largetext' => 'text',
			);
		}
		else
		{
			$types = array(
				'character varying' => 'varchar',
				'character' => 'char',
				'integer' => 'int',
			);
		}

		// Got it? Change it!
		if (isset($types[$type_name]))
		{
			if ($type_name == 'tinytext')
				$type_size = 255;
			$type_name = $types[$type_name];
		}

		// Numbers don't have a size.
		if (strpos($type_name, 'int') !== false)
				$type_size = null;

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
			SELECT column_name, column_default, is_nullable, data_type, character_maximum_length
			FROM information_schema.columns
			WHERE table_name = \'' . $table_name . '\'
			ORDER BY ordinal_position',
			array(
				'security_override' => true,
			)
		);
		$columns = array();
		while ($row = $this->_db->fetch_assoc($result))
		{
			if (!$detail)
			{
				$columns[] = $row['column_name'];
			}
			else
			{
				$auto = false;

				// What is the default?
				if (preg_match('~nextval\(\'(.+?)\'(.+?)*\)~i', $row['column_default'], $matches) != 0)
				{
					$default = null;
					$auto = true;
				}
				elseif (trim($row['column_default']) != '')
					$default = strpos($row['column_default'], '::') === false ? $row['column_default'] : substr($row['column_default'], 0, strpos($row['column_default'], '::'));
				else
					$default = null;

				// Make the type generic.
				list ($type, $size) = $this->db_calculate_type($row['data_type'], $row['character_maximum_length'], true);

				$columns[$row['column_name']] = array(
					'name' => $row['column_name'],
					'null' => $row['is_nullable'] ? true : false,
					'default' => $default,
					'type' => $type,
					'size' => $size,
					'auto' => $auto,
				);
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
			SELECT CASE WHEN i.indisprimary THEN 1 ELSE 0 END AS is_primary,
				CASE WHEN i.indisunique THEN 1 ELSE 0 END AS is_unique,
				c2.relname AS name,
				pg_get_indexdef(i.indexrelid) AS inddef
			FROM pg_class AS c, pg_class AS c2, pg_index AS i
			WHERE c.relname = \'' . $table_name . '\'
				AND c.oid = i.indrelid
				AND i.indexrelid = c2.oid',
			array(
				'security_override' => true,
			)
		);
		$indexes = array();
		while ($row = $this->_db->fetch_assoc($result))
		{
			// Try get the columns that make it up.
			if (preg_match('~\(([^\)]+?)\)~i', $row['inddef'], $matches) == 0)
				continue;

			$columns = explode(',', $matches[1]);

			if (empty($columns))
				continue;

			foreach ($columns as $k => $v)
				$columns[$k] = trim($v);

			// Fix up the name to be consistent cross databases
			if (substr($row['name'], -5) == '_pkey' && $row['is_primary'] == 1)
				$row['name'] = 'PRIMARY';
			else
				$row['name'] = str_replace($table_name . '_', '', $row['name']);

			if (!$detail)
				$indexes[] = $row['name'];
			else
			{
				$indexes[$row['name']] = array(
					'name' => $row['name'],
					'type' => $row['is_primary'] ? 'primary' : ($row['is_unique'] ? 'unique' : 'index'),
					'columns' => $columns,
				);
			}
		}
		$this->_db->free_result($result);

		return $indexes;
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

		$request = $this->_db->query('', '
			VACUUM ANALYZE {raw:table}',
			array(
				'table' => $table,
			)
		);
		if (!$request)
			return -1;

		$row = $this->_db->fetch_assoc($request);
		$this->_db->free_result($request);

		if (isset($row['Data_free']))
			return $row['Data_free'] / 1024;
		else
			return 0;
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
	 * @param object $db - A Database_PostgreSQL object
	 * @return DbTable_PostgreSQL - A DbTable_PostgreSQL object
	 */
	public static function db_table($db)
	{
		if (is_null(self::$_tbl))
			self::$_tbl = new DbTable_PostgreSQL($db);
		return self::$_tbl;
	}
}