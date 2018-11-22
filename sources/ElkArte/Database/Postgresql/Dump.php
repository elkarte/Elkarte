<?php

/**
 * This file has all the main functions in it that relate to the Postgre database.
 *
 * @name      ElkArte Forum
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

namespace ElkArte\Database\Postgresql;

/**
 * PostgreSQL database class, implements database class to control postgre functions
 */
class Dump extends \ElkArte\Database\AbstractDump
{
	/**
	 * {@inheritDoc}
	 */
	public function db_table_sql($tableName)
	{
		$tableName = str_replace('{db_prefix}', $this->_db_prefix, $tableName);

		// This will be needed...
		$crlf = "\r\n";

		// Start the create table...
		$schema_create = 'CREATE TABLE ' . $tableName . ' (' . $crlf;
		$index_create = '';
		$seq_create = '';

		// Find all the fields.
		$result = $this->_db->query('', '
			SELECT column_name, column_default, is_nullable, data_type, character_maximum_length
			FROM information_schema.columns
			WHERE table_name = {string:table}
			ORDER BY ordinal_position',
			array(
				'table' => $tableName,
			)
		);
		while ($row = $result->fetch_assoc())
		{
			if ($row['data_type'] == 'character varying')
				$row['data_type'] = 'varchar';
			elseif ($row['data_type'] == 'character')
				$row['data_type'] = 'char';

			if ($row['character_maximum_length'])
				$row['data_type'] .= '(' . $row['character_maximum_length'] . ')';

			// Make the CREATE for this column.
			$schema_create .= ' "' . $row['column_name'] . '" ' . $row['data_type'] . ($row['is_nullable'] != 'YES' ? ' NOT NULL' : '');

			// Add a default...?
			if (trim($row['column_default']) != '')
			{
				$schema_create .= ' default ' . $row['column_default'] . '';

				// Auto increment?
				if (preg_match('~nextval\(\'(.+?)\'(.+?)*\)~i', $row['column_default'], $matches) != 0)
				{
					// Get to find the next variable first!
					$count_req = $this->_db->query('', '
						SELECT MAX("{raw:column}")
						FROM {raw:table}',
						array(
							'column' => $row['column_name'],
							'table' => $tableName,
						)
					);
					list ($max_ind) = $count_req->fetch_row();
					$count_req->free_result();

					// Get the right bloody start!
					$seq_create .= 'CREATE SEQUENCE ' . $matches[1] . ' START WITH ' . ($max_ind + 1) . ';' . $crlf . $crlf;
				}
			}

			$schema_create .= ',' . $crlf;
		}
		$result->free_result();

		// Take off the last comma.
		$schema_create = substr($schema_create, 0, -strlen($crlf) - 1);

		$result = $this->_db->query('', '
			SELECT CASE WHEN i.indisprimary THEN 1 ELSE 0 END AS is_primary, pg_get_indexdef(i.indexrelid) AS inddef
			FROM pg_class AS c
				INNER JOIN pg_index AS i ON (i.indrelid = c.oid)
				INNER JOIN pg_class AS c2 ON (c2.oid = i.indexrelid)
			WHERE c.relname = {string:table}',
			array(
				'table' => $tableName,
			)
		);

		while ($row = $result->fetch_assoc())
		{
			if ($row['is_primary'])
			{
				if (preg_match('~\(([^\)]+?)\)~i', $row['inddef'], $matches) == 0)
					continue;

				$index_create .= $crlf . 'ALTER TABLE ' . $tableName . ' ADD PRIMARY KEY ("' . $matches[1] . '");';
			}
			else
				$index_create .= $crlf . $row['inddef'] . ';';
		}
		$result->free_result();

		// Finish it off!
		$schema_create .= $crlf . ');';

		return $seq_create . $schema_create . $index_create;
	}

	/**
	 * {@inheritdoc}
	 */
	public function list_tables($db_name_str = false, $filter = false)
	{
		$request = $this->_db->query('', '
			SELECT tablename
			FROM pg_tables
			WHERE schemaname = {string:schema_public}' . ($filter === false ? '' : '
				AND tablename LIKE {string:filter}') . '
			ORDER BY tablename',
			array(
				'schema_public' => 'public',
				'filter' => $filter,
			)
		);
		$tables = array();
		while ($row = $request->fetch_row())
			$tables[] = $row[0];
		$request->free_result();

		return $tables;
	}

	/**
	 * {@inheritDoc}
	 */
	public function backup_table($table, $backup_table)
	{
		$table = str_replace('{db_prefix}', $this->_db_prefix, $table);

		// Do we need to drop it first?
		$this->_db_table->db_drop_table($backup_table);

		// @todo Should we create backups of sequences as well?
		$this->_db->query('', '
			CREATE TABLE {raw:backup_table}
			(
				LIKE {raw:table}
				INCLUDING DEFAULTS
			)',
			array(
				'backup_table' => $backup_table,
				'table' => $table,
			)
		);

		$this->_db->query('', '
			INSERT INTO {raw:backup_table}
			SELECT * FROM {raw:table}',
			array(
				'backup_table' => $backup_table,
				'table' => $table,
			)
		);
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

		$data = '';
		$tableName = str_replace('{db_prefix}', $this->_db_prefix, $tableName);

		// This will be handy...
		$crlf = "\r\n";

		$result = $this->_db->query('', '
			SELECT *
			FROM ' . $tableName . '
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
		$insert_msg = 'INSERT INTO ' . $tableName . $crlf . "\t" . '(' . implode(', ', $fields) . ')' . $crlf . 'VALUES ' . $crlf . "\t";

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
					$field_list[] = '\'' . $this->_db->escape_string($item) . '\'';
			}

			// 'Insert' the data.
			$data .= $insert_msg . '(' . implode(', ', $field_list) . ');' . $crlf;
		}
		$result->free_result();

		$data .= $crlf;

		$start += $limit;

		return $data;
	}
}
