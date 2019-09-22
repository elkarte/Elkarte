<?php

/**
 * This file has all the main functions in it that relate to the mysql database.
 *
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * This file contains code covered by:
 * copyright:	2011 Simple Machines (http://www.simplemachines.org)
 *
 * @version 2.0 dev
 *
 */

namespace ElkArte\Database\Mysqli;

/**
 * SQL database class, implements database class to control mysql functions
 */
	class Dump extends \ElkArte\Database\AbstractDump
{
	/**
	 * {@inheritDoc}
	 */
	public function table_sql($tableName)
	{
		$tableName = str_replace('{db_prefix}', $this->_db_prefix, $tableName);

		// This will be needed...
		$crlf = "\r\n";

		// Drop it if it exists.
		$schema_create = 'DROP TABLE IF EXISTS `' . $tableName . '`;' . $crlf . $crlf;

		// Start the create table...
		$schema_create .= 'CREATE TABLE `' . $tableName . '` (' . $crlf;

		// Find all the fields.
		$result = $this->_db->query('', '
			SHOW FIELDS
			FROM `{raw:table}`',
			array(
				'table' => $tableName,
			)
		);
		while ($row = $result->fetch_assoc())
		{
			// Make the CREATE for this column.
			$schema_create .= ' `' . $row['Field'] . '` ' . $row['Type'] . ($row['Null'] != 'YES' ? ' NOT NULL' : '');

			// Add a default...?
			if (!empty($row['Default']) || $row['Null'] !== 'YES')
			{
				// Make a special case of auto-timestamp.
				if ($row['Default'] == 'CURRENT_TIMESTAMP')
					$schema_create .= ' /*!40102 NOT NULL default CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP */';
				// Text shouldn't have a default.
				elseif ($row['Default'] !== null)
				{
					// If this field is numeric the default needs no escaping.
					$type = strtolower($row['Type']);
					$isNumericColumn = strpos($type, 'int') !== false || strpos($type, 'bool') !== false || strpos($type, 'bit') !== false || strpos($type, 'float') !== false || strpos($type, 'double') !== false || strpos($type, 'decimal') !== false;

					$schema_create .= ' default ' . ($isNumericColumn ? $row['Default'] : '\'' . $this->_db->escape_string($row['Default']) . '\'');
				}
			}

			// And now any extra information. (such as auto_increment.)
			$schema_create .= ($row['Extra'] != '' ? ' ' . $row['Extra'] : '') . ',' . $crlf;
		}
		$result->free_result();

		// Take off the last comma.
		$schema_create = substr($schema_create, 0, -strlen($crlf) - 1);

		// Find the keys.
		$result = $this->_db->query('', '
			SHOW KEYS
			FROM `{raw:table}`',
			array(
				'table' => $tableName,
			)
		);
		$indexes = array();
		while ($row = $result->fetch_assoc())
		{
			// Is this a primary key, unique index, or regular index?
			$row['Key_name'] = $row['Key_name'] == 'PRIMARY' ? 'PRIMARY KEY' : (empty($row['Non_unique']) ? 'UNIQUE ' : ($row['Comment'] == 'FULLTEXT' || (isset($row['Index_type']) && $row['Index_type'] == 'FULLTEXT') ? 'FULLTEXT ' : 'KEY ')) . '`' . $row['Key_name'] . '`';

			// Is this the first column in the index?
			if (empty($indexes[$row['Key_name']]))
				$indexes[$row['Key_name']] = array();

			// A sub part, like only indexing 15 characters of a varchar.
			if (!empty($row['Sub_part']))
				$indexes[$row['Key_name']][$row['Seq_in_index']] = '`' . $row['Column_name'] . '`(' . $row['Sub_part'] . ')';
			else
				$indexes[$row['Key_name']][$row['Seq_in_index']] = '`' . $row['Column_name'] . '`';
		}
		$result->free_result();

		// Build the CREATEs for the keys.
		foreach ($indexes as $keyname => $columns)
		{
			// Ensure the columns are in proper order.
			ksort($columns);

			$schema_create .= ',' . $crlf . ' ' . $keyname . ' (' . implode(', ', $columns) . ')';
		}

		// Now just get the comment and type... (MyISAM, etc.)
		$result = $this->_db->query('', '
			SHOW TABLE STATUS
			LIKE {string:table}',
			array(
				'table' => strtr($tableName, array('_' => '\\_', '%' => '\\%')),
			)
		);
		$row = $result->fetch_assoc();
		$result->free_result();

		// Probably MyISAM.... and it might have a comment.
		$schema_create .= $crlf . ') ENGINE=' . (isset($row['Type']) ? $row['Type'] : $row['Engine']) . ($row['Comment'] != '' ? ' COMMENT="' . $row['Comment'] . '"' : '');

		return $schema_create;
	}

	/**
	 * {@inheritdoc}
	 */
	public function list_tables($db_name_str = false, $filter = false)
	{
		global $db_name;

		$db_name_str = $db_name_str === false ? $db_name : $db_name_str;
		$db_name_str = trim($db_name_str);
		$filter = $filter === false ? '' : ' LIKE \'' . $filter . '\'';

		$request = $this->_db->query('', '
			SHOW TABLES
			FROM `{raw:db_name_str}`
			{raw:filter}',
			array(
				'db_name_str' => $db_name_str[0] === '`' ? strtr($db_name_str, array('`' => '')) : $db_name_str,
				'filter' => $filter,
			)
		);
		$tables = array();
		while ($row = $request->fetch_row())
		{
			$tables[] = $row[0];
		}
		$request->free_result();

		return $tables;
	}

	/**
	 * {@inheritDoc}
	 */
	public function backup_table($table_name, $backup_table)
	{
		$table = str_replace('{db_prefix}', $this->_db_prefix, $table_name);

		// First, get rid of the old table.
		$this->_db_table->drop_table($backup_table);

		// Can we do this the quick way?
		$result = $this->_db->query('', '
			CREATE TABLE {raw:backup_table} LIKE {raw:table}',
			array(
				'backup_table' => $backup_table,
				'table' => $table
		));
		// If this failed, we go old school.
		if ($result->hasResults())
		{
			$request = $this->_db->query('', '
				INSERT INTO {raw:backup_table}
				SELECT *
				FROM {raw:table}',
				array(
					'backup_table' => $backup_table,
					'table' => $table
				));

			// Old school or no school?
			if ($request)
				return $request;
		}

		// At this point, the quick method failed.
		$result = $this->_db->query('', '
			SHOW CREATE TABLE {raw:table}',
			array(
				'table' => $table,
			)
		);
		list (, $create) = $result->fetch_row();
		$result->free_result();

		$create = preg_split('/[\n\r]/', $create);

		$auto_inc = '';

		// Default engine type.
		$engine = 'MyISAM';
		$charset = '';
		$collate = '';

		foreach ($create as $k => $l)
		{
			// Get the name of the auto_increment column.
			if (strpos($l, 'auto_increment'))
				$auto_inc = trim($l);

			// For the engine type, see if we can work out what it is.
			if (strpos($l, 'ENGINE') !== false || strpos($l, 'TYPE') !== false)
			{
				// Extract the engine type.
				preg_match('~(ENGINE|TYPE)=(\w+)(\sDEFAULT)?(\sCHARSET=(\w+))?(\sCOLLATE=(\w+))?~', $l, $match);

				if (!empty($match[1]))
					$engine = $match[1];

				if (!empty($match[2]))
					$engine = $match[2];

				if (!empty($match[5]))
					$charset = $match[5];

				if (!empty($match[7]))
					$collate = $match[7];
			}

			// Skip everything but keys...
			if (strpos($l, 'KEY') === false)
				unset($create[$k]);
		}

		$create = !empty($create) ? '(
				' . implode('
				', $create) . ')' : '';

		$request = $this->_db->query('', '
			CREATE TABLE {raw:backup_table} {raw:create}
			ENGINE={raw:engine}' . (empty($charset) ? '' : ' CHARACTER SET {raw:charset}' . (empty($collate) ? '' : ' COLLATE {raw:collate}')) . '
			SELECT *
			FROM {raw:table}',
			array(
				'backup_table' => $backup_table,
				'table' => $table,
				'create' => $create,
				'engine' => $engine,
				'charset' => empty($charset) ? '' : $charset,
				'collate' => empty($collate) ? '' : $collate,
			)
		);

		if ($auto_inc !== '')
		{
			if (preg_match('~\`(.+?)\`\s~', $auto_inc, $match) != 0 && substr($auto_inc, -1, 1) === ',')
				$auto_inc = substr($auto_inc, 0, -1);

			$this->_db->query('', '
				ALTER TABLE {raw:backup_table}
				CHANGE COLUMN {raw:column_detail} {raw:auto_inc}',
				array(
					'backup_table' => $backup_table,
					'column_detail' => $match[1],
					'auto_inc' => $auto_inc,
				)
			);
		}

		return $request;
	}

	/**
	 * {@inheritDoc}
	 */
	public function insert_sql($tableName, $new_table = false)
	{
		static $start = 0, $num_rows, $fields, $limit;

		if ($new_table)
		{
			$limit = strstr($tableName, 'log_') !== false ? 500 : 250;
			$start = 0;
		}

		$tableName = str_replace('{db_prefix}', $this->_db_prefix, $tableName);

		// This will be handy...
		$crlf = "\r\n";

		$result = $this->_db->query('', '
			SELECT /*!40001 SQL_NO_CACHE */ *
			FROM `' . $tableName . '`
			LIMIT ' . $start . ', ' . $limit,
			array(
				'security_override' => true,
			)
		);

		// The number of rows, just for record keeping and breaking INSERTs up.
		$num_rows = $result->num_rows();

		if ($num_rows == 0)
			return '';

		if ($new_table)
		{
			$fields = array_keys($result->fetch_assoc());
			$result->data_seek(0);
		}

		// Start it off with the basic INSERT INTO.
		$data = 'INSERT INTO `' . $tableName . '`' . $crlf . "\t" . '(`' . implode('`, `', $fields) . '`)' . $crlf . 'VALUES ';

		// Loop through each row.
		while ($row = $result->fetch_assoc())
		{
			// Get the fields in this row...
			$field_list = array();

			foreach ($row as $key => $item)
			{
				// Try to figure out the type of each field. (NULL, number, or 'string'.)
				if (!isset($item))
					$field_list[] = 'NULL';
				elseif (is_numeric($item) && (int) $item == $item)
					$field_list[] = $item;
				else
					$field_list[] = '\'' . $this->escape_string($item) . '\'';
			}

			$data .= '(' . implode(', ', $field_list) . '),' . $crlf . "\t";
		}

		$result->free_result();
		$data = substr(trim($data), 0, -1) . ';' . $crlf . $crlf;

		$start += $limit;

		return $data;
	}
}
