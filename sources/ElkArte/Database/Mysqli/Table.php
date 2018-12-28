<?php

/**
 * This class implements functionality related to table structure.
 * Intended in particular for addons to change it to suit their needs.
 *
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * This file contains code covered by:
 * copyright:	2011 Simple Machines (http://www.simplemachines.org)
 * license:  	BSD, See included LICENSE.TXT for terms and conditions.
 *
 * @version 2.0 dev
 *
 */

namespace ElkArte\Database\Mysqli;

/**
 * Adds MySQL table level functionality,
 * Table creation / dropping, column adding / removing
 * Most often used during install and Upgrades of the forum and addons
 */
class Table extends \ElkArte\Database\AbstractTable
{
	/**
	 * Holds this instance of the table interface
	 * @var DbTable_MySQL
	 */
	protected static $_tbl = null;

	/**
	 * {@inheritdoc }
	 */
	protected function _real_prefix()
	{
		return preg_match('~^(`?)(.+?)\\1\\.(.*?)$~', $this->_db_prefix, $match) === 1 ? $match[3] : $this->_db_prefix;
	}

	/**
	 * {@inheritdoc }
	 */
	protected function _close_table_query($temporary)
	{

		$close_string = ') DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci';

		if ($temporary === true)
		{
			$close_string .= ' ENGINE=MEMORY';
		}

		return $close_string;
	}

	/**
	 * {@inheritdoc }
	 */
	protected function _create_query_indexes($indexes, $table_name)
	{
		// Loop through the indexes next...
		$index_query = '';
		foreach ($indexes as $index)
		{
			if (empty($index))
			{
				continue;
			}

			$index['columns'] = $this->_clean_indexes($index['columns']);

			$columns = implode(',', $index['columns']);

			// Primary goes in the table...
			if (isset($index['type']) && $index['type'] == 'primary')
				$index_query .= "\n\t" . 'PRIMARY KEY (' . $columns . '),';
			else
			{
				if (empty($index['name']))
					$index['name'] = implode('_', $index['columns']);
				$index_query .= "\n\t" . (isset($index['type']) && $index['type'] == 'unique' ? 'UNIQUE' : 'KEY') . ' ' . $index['name'] . ' (' . $columns . '),';
			}
		}
		return $index_query;
	}

	/**
	 * {@inheritdoc }
	 */
	public function drop_table($table_name, $force = false)
	{
		// Get some aliases.
		$full_table_name = str_replace('{db_prefix}', $this->_real_prefix(), $table_name);
		$table_name = str_replace('{db_prefix}', $this->_db_prefix, $table_name);

		// God no - dropping one of these = bad.
		if (in_array(strtolower($table_name), $this->_reservedTables))
			return false;

		// Does it exist?
		if ($force === true || $this->table_exists($full_table_name))
		{
			if ($force === true)
			{
				$this->_db->skip_next_error();
			}

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
	 * {@inheritdoc }
	 */
	public function add_column($table_name, $column_info, $parameters = array(), $if_exists = 'update')
	{
		$table_name = str_replace('{db_prefix}', $this->_db_prefix, $table_name);

		// Log that we will want to uninstall this!
		$this->_package_log[] = array('remove_column', $table_name, $column_info['name']);

		// Does it exist - if so don't add it again!
		if ($this->_get_column_info($table_name, $column_info['name']))
		{
			// If we're going to overwrite then use change column.
			if ($if_exists == 'update')
				return $this->change_column($table_name, $column_info['name'], $column_info);
			else
				return false;
		}

		// Now add the thing!
		$this->_alter_table($table_name, '
			ADD ' . $this->_db_create_query_column($column_info, $table_name) . (empty($column_info['auto']) ? '' : ' primary key'));

		return true;
	}

	/**
	 * {@inheritdoc }
	 */
	public function remove_column($table_name, $column_name, $parameters = array())
	{
		$table_name = str_replace('{db_prefix}', $this->_db_prefix, $table_name);

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
	 * {@inheritdoc }
	 */
	public function change_column($table_name, $old_column, $column_info, $parameters = array())
	{
		$table_name = str_replace('{db_prefix}', $this->_db_prefix, $table_name);

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
			CHANGE COLUMN `' . $old_column . '` ' . $this->_db_create_query_column($column_info, $table_name));
	}

	/**
	 * {@inheritdoc }
	 */
	public function add_index($table_name, $index_info, $parameters = array(), $if_exists = 'update')
	{
		$table_name = str_replace('{db_prefix}', $this->_db_prefix, $table_name);

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
		$indexes = $this->list_indexes($table_name, true);

		// Do we already have it?
		foreach ($indexes as $index)
		{
			if ($index['name'] == $index_info['name'] || ($index['type'] == 'primary' && isset($index_info['type']) && $index_info['type'] == 'primary'))
			{
				// If we want to overwrite simply remove the current one then continue.
				if ($if_exists != 'update' || $index['type'] == 'primary')
					return false;
				else
					$this->remove_index($table_name, $index_info['name']);
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
	 * {@inheritdoc }
	 */
	public function remove_index($table_name, $index_name, $parameters = array())
	{
		$table_name = str_replace('{db_prefix}', $this->_db_prefix, $table_name);

		// Better exist!
		$indexes = $this->list_indexes($table_name, true);

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
	 * {@inheritdoc }
	 */
	public function calculate_type($type_name, $type_size = null, $reverse = false)
	{
		// MySQL is actually the generic baseline.
		return array($type_name, $type_size);
	}

	/**
	 * {@inheritdoc }
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
	 * {@inheritdoc }
	 */
	public function list_columns($table_name, $detail = false, $parameters = array())
	{
		$table_name = str_replace('{db_prefix}', $this->_db_prefix, $table_name);

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
	 * {@inheritdoc }
	 */
	public function list_indexes($table_name, $detail = false, $parameters = array())
	{
		$table_name = str_replace('{db_prefix}', $this->_db_prefix, $table_name);

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
	 * @param string $table_name
	 *
	 * @return string
	 */
	protected function _db_create_query_column($column, $table_name)
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
		list ($type, $size) = $this->calculate_type($column['type'], $column['size']);

		// Allow unsigned integers (mysql only)
		$unsigned = in_array($type, array('int', 'tinyint', 'smallint', 'mediumint', 'bigint', 'float')) && !empty($column['unsigned']) ? 'unsigned ' : '';

		if ($size !== null)
			$type = $type . '(' . $size . ')';

		// Now just put it together!
		return '`' . $column['name'] . '` ' . $type . ' ' . (!empty($unsigned) ? $unsigned : '') . (!empty($column['null']) ? '' : 'NOT NULL') . ' ' . $default;
	}

	/**
	 * {@inheritdoc }
	 */
	public function optimize($table)
	{
		$table = str_replace('{db_prefix}', $this->_db_prefix, $table);

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
	 * {@inheritdoc }
	 */
	public function package_log()
	{
		return $this->_package_log;
	}
}
