<?php

/**
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * This file contains code covered by:
 * copyright:	2011 Simple Machines (http://www.simplemachines.org)
 * license:  	BSD, See included LICENSE.TXT for terms and conditions.
 *
 * @version 1.1 Release Candidate 1
 *
 */

/**
 * Alter a text column definition preserving its character set.
 *
 * @param mixed $change
 * @param string $substep
 */
function textfield_alter($change, $substep)
{
	global $db_prefix;

	$db = database();

	$db->skip_next_error();
	$request = $db->query('', '
		SHOW FULL COLUMNS
		FROM {db_prefix}' . $change['table'] . '
		LIKE {string:column}',
		array(
			'column' => $change['column'],
		)
	);
	if ($db->num_rows($request) === 0)
	{
		die('Unable to find column ' . $change['column'] . ' inside table ' . $db_prefix . $change['table']);
	}
	$table_row = $db->fetch_assoc($request);
	$db->free_result($request);

	// If something of the current column definition is different, fix it.
	$column_fix = $table_row['Type'] !== $change['type'] || (strtolower($table_row['Null']) === 'yes') !== $change['null_allowed'] || ($table_row['Default'] === null) !== !isset($change['default']) || (isset($change['default']) && $change['default'] !== $table_row['Default']);

	// Columns that previously allowed null, need to be converted first.
	$null_fix = strtolower($table_row['Null']) === 'yes' && !$change['null_allowed'];

	// Get the character set that goes with the collation of the column.
	if ($column_fix && !empty($table_row['Collation']))
	{
		$db->skip_next_error();
		$request = $db->query('', '
			SHOW COLLATION
			LIKE {string:collation}',
			array(
				'collation' => $table_row['Collation'],
			)
		);

		// No results? Just forget it all together.
		if ($db->num_rows($request) === 0)
		{
			unset($table_row['Collation']);
		}
		else
		{
			$collation_info = $db->fetch_assoc($request);
		}
		$db->free_result($request);
	}

	if ($column_fix)
	{
		// Make sure there are no NULL's left.
		$db->skip_next_error();
		if ($null_fix)
		{
			$db->query('', '
				UPDATE {db_prefix}' . $change['table'] . '
				SET ' . $change['column'] . ' = {string:default}
				WHERE ' . $change['column'] . ' IS NULL',
				array(
					'default' => isset($change['default']) ? $change['default'] : '',
				)
			);
		}

		// Do the actual alteration.
		$db->skip_next_error();
		$db->query('', '
			ALTER TABLE {db_prefix}' . $change['table'] . '
			CHANGE COLUMN ' . $change['column'] . ' ' . $change['column'] . ' ' . $change['type'] . (isset($collation_info['Charset']) ? ' CHARACTER SET ' . $collation_info['Charset'] . ' COLLATE ' . $collation_info['Collation'] : '') . ($change['null_allowed'] ? '' : ' NOT NULL') . (isset($change['default']) ? ' default {string:default}' : ''),
			array(
				'default' => isset($change['default']) ? $change['default'] : '',
			)
		);
	}

	nextSubstep($substep);
}

/**
 * Check if we need to alter this query.
 *
 * @param array $change
 */
function checkChange(&$change)
{
	global $db_type, $databases, $db_connection;
	static $database_version, $where_field_support;

	$db = database();

	// Attempt to find a database_version.
	if (empty($database_version))
	{
		$database_version = $databases[$db_type]['version_check']($db_connection);
		$where_field_support = $db_type == 'mysql' && version_compare('5.0', $database_version, '<=');
	}

	// Not a column we need to check on?
	if (!in_array($change['name'], array('memberGroups', 'passwordSalt')))
	{
		return;
	}

	// Break it up you (six|seven).
	$temp = explode(' ', str_replace('NOT NULL', 'NOT_NULL', $change['text']));

	// Can we support a shortcut method?
	if ($where_field_support)
	{
		// Get the details about this change.
		$request = $db->query('', '
			SHOW FIELDS
			FROM {db_prefix}{raw:table}
			WHERE Field = {string:old_name} OR Field = {string:new_name}',
			array(
				'table' => $change['table'],
				'old_name' => $temp[1],
				'new_name' => $temp[2],
		));
		if ($db->num_rows != 1)
		{
			return;
		}
		list (, $current_type) = $db->fetch_assoc($request);
		$db->free_result($request);
	}
	else
	{
		// Do this the old fashion, sure method way.
		$request = $db->query('', '
			SHOW FIELDS
			FROM {db_prefix}{raw:table}',
			array(
				'table' => $change['table'],
			)
		);
		// Mayday!
		if ($db->num_rows($request) == 0)
		{
			return;
		}
		// Oh where, oh where has my little field gone. Oh where can it be...
		while ($row = $db->fetch_assoc($request))
		{
			if ($row['Field'] == $temp[1] || $row['Field'] == $temp[2])
			{
				$current_type = $row['Type'];
				break;
			}
		}
	}

	// If this doesn't match, the column may of been altered for a reason.
	if (trim($current_type) != trim($temp[3]))
	{
		$temp[3] = $current_type;
	}

	// Piece this back together.
	$change['text'] = str_replace('NOT_NULL', 'NOT NULL', implode(' ', $temp));
}
