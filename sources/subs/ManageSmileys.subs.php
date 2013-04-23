<?php

/**
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * This software is a derived product, based on:
 *
 * Simple Machines Forum (SMF)
 * copyright:	2011 Simple Machines (http://www.simplemachines.org)
 * license:  	BSD, See included LICENSE.TXT for terms and conditions.
 *
 * @version 1.0 Alpha
 *
 */

if (!defined('ELKARTE'))
	die('No access...');

/**
 * Validates, if a smiley already exists
 *
 * @param array $smileys
 * @return array
 */
function smileyExists($smileys)
{
	global $smcFunc;

	$found = array();
	$request = $smcFunc['db_query']('', '
		SELECT filename
		FROM {db_prefix}smileys
		WHERE filename IN ({array_string:smiley_list})',
		array(
			'smiley_list' => $smileys,
		)
	);
	while ($row = $smcFunc['db_fetch_assoc']($request))
		$found[] = $row['filename'];
	$smcFunc['db_free_result']($request);

	return $found;
}

/**
 * Validates duplicate smileys
 *
 * @param string $code
 * @param string $current
 * @return boolean
 */
function validateDuplicateSmiley($code, $current = null)
{
	global $smcFunc;

	$request = $smcFunc['db_query']('', '
		SELECT id_smiley
		FROM {db_prefix}smileys
		WHERE code = {raw:mysql_binary_type} {string:smiley_code}' . (!isset($current) ? '' : '
			AND id_smiley != {int:current_smiley}'),
		array(
			'current_smiley' => $current,
			'mysql_binary_type' => $smcFunc['db_title'] == 'MySQL' ? 'BINARY' : '',
			'smiley_code' => $code,
		)
	);
	if ($smcFunc['db_num_rows']($request) > 0)
		return true;
	$smcFunc['db_free_result']($request);

	return false;
}

/**
 * Request the next location for a new smiley
 *
 * @param type $location
 * @return type
 */
function nextSmileyLocation($location)
{
	global $smcFunc;

	$request = $smcFunc['db_query']('', '
		SELECT MAX(smiley_order) + 1
		FROM {db_prefix}smileys
		WHERE hidden = {int:smiley_location}
			AND smiley_row = {int:first_row}',
		array(
			'smiley_location' => $location,
			'first_row' => 0,
		)
	);
	list ($smiley_order) = $smcFunc['db_fetch_row']($request);
	$smcFunc['db_free_result']($request);

	return $smiley_order;
}

/**
 * Adds a smiley to the database
 *
 * @param array $param
 */
function addSmiley($param)
{
	global $smcFunc;

	$smcFunc['db_insert']('',
		'{db_prefix}smileys',
		array(
			'code' => 'string-30', 'filename' => 'string-48', 'description' => 'string-80', 'hidden' => 'int', 'smiley_order' => 'int',
		),
		$param,
		array('id_smiley')
	);
}

/**
 * Deletes smileys.
 *
 * @param array $smileys
 */
function deleteSmileys($smileys)
{
	global $smcFunc;

	$smcFunc['db_query']('', '
		DELETE FROM {db_prefix}smileys
		WHERE id_smiley IN ({array_int:checked_smileys})',
		array(
			'checked_smileys' => $smileys,
		)
	);
}

/**
 * Changes the display type of given smileys.
 *
 * @param array $smileys
 * @param int $display_type
 */
function updateSmileyDisplayType($smileys, $display_type)
{
	global $smcFunc;

	$smcFunc['db_query']('', '
		UPDATE {db_prefix}smileys
		SET hidden = {int:display_type}
		WHERE id_smiley IN ({array_int:checked_smileys})',
		array(
			'checked_smileys' => $smileys,
			'display_type' => $display_type,
		)
	);
}

/**
 * Updates a smiley..
 *
 * @param array $param
 */
function updateSmiley($param)
{
	global $smcFunc;

	$smcFunc['db_query']('', '
		UPDATE {db_prefix}smileys
		SET
			code = {string:smiley_code},
			filename = {string:smiley_filename},
			description = {string:smiley_description},
			hidden = {int:smiley_location}
		WHERE id_smiley = {int:current_smiley}',
		array(
			'smiley_location' => $param['smiley_location'],
			'current_smiley' => $param['smiley'],
			'smiley_code' => $param['smiley_code'],
			'smiley_filename' => $param['smiley_filename'],
			'smiley_description' => $param['smiley_description'],
		)
	);
}

/**
 * Get detailed smiley information
 *
 * @param int $id
 * @return array
 */
function getSmiley($id)
{
	global $smcFunc;

	$request = $smcFunc['db_query']('', '
		SELECT id_smiley AS id, code, filename, description, hidden AS location, 0 AS is_new
		FROM {db_prefix}smileys
		WHERE id_smiley = {int:current_smiley}',
		array(
			'current_smiley' => $id,
		)
	);
	if ($smcFunc['db_num_rows']($request) != 1)
		fatal_lang_error('smiley_not_found');
	$current_smiley = $smcFunc['db_fetch_assoc']($request);
	$smcFunc['db_free_result']($request);

	return $current_smiley;
}

/**
 * Get the position from a given smiley
 *
 * @param int $location
 * @param int $id
 * @return array
 */
function getSmileyPosition($location, $id)
{
	global $smcFunc;

	$smiley = array();

	$request = $smcFunc['db_query']('', '
		SELECT smiley_row, smiley_order, hidden
		FROM {db_prefix}smileys
		WHERE hidden = {int:location}
			AND id_smiley = {int:id_smiley}',
		array(
			'location' => $location,
			'id_smiley' => $id,
		)
	);
	list ($smiley['row'], $smiley['order'], $smiley['location']) = $smcFunc['db_fetch_row']($request);

	$smcFunc['db_free_result']($request);

	return $smiley;
}

/**
 * Move a smiley to their new position.
 *
 * @param int $smiley
 * @param int $source
 */
function moveSmileyPosition($smiley, $source)
{
	global $smcFunc;

	$smcFunc['db_query']('', '
		UPDATE {db_prefix}smileys
		SET smiley_order = smiley_order + 1
		WHERE hidden = {int:new_location}
			AND smiley_row = {int:smiley_row}
			AND smiley_order > {int:smiley_order}',
		array(
			'new_location' => $smiley['location'],
			'smiley_row' => $smiley['row'],
			'smiley_order' => $smiley['order'],
		)
	);

	$smcFunc['db_query']('', '
		UPDATE {db_prefix}smileys
		SET
			smiley_order = {int:smiley_order} + 1,
			smiley_row = {int:smiley_row},
			hidden = {int:new_location}
		WHERE id_smiley = {int:current_smiley}',
		array(
			'smiley_order' => $smiley['order'],
			'smiley_row' => $smiley['row'],
			'new_location' => $smiley['location'],
			'current_smiley' => $source,
		)
	);
}

/**
 * Change the row of a given smiley.
 *
 * @param int $id
 * @param int $row
 * @param int $location
 */
function updateSmileyRow($id, $row, $location)
{
	global $smcFunc;

	$smcFunc['db_query']('', '
		UPDATE {db_prefix}smileys
		SET smiley_row = {int:new_row}
		WHERE smiley_row = {int:current_row}
			AND hidden = {int:location}',
		array(
			'new_row' => $id,
			'current_row' => $row,
			'location' => $location == 'postform' ? '0' : '2',
		)
	);
}

/**
 * Set an new order for the given smiley.
 *
 * @param type $id
 * @param type $order
 */
function updateSmileyOrder($id, $order)
{
	global $smcFunc;

	$smcFunc['db_query']('', '
		UPDATE {db_prefix}smileys
		SET smiley_order = {int:new_order}
		WHERE id_smiley = {int:current_smiley}',
		array(
			'new_order' => $order,
			'current_smiley' => $id,
		)
	);
}

/**
 * Get a list of all visible smileys.
 *
 * @return type
 */
function getSmileys()
{
	global $smcFunc;

	$request = $smcFunc['db_query']('', '
		SELECT id_smiley, code, filename, description, smiley_row, smiley_order, hidden
		FROM {db_prefix}smileys
		WHERE hidden != {int:popup}
		ORDER BY smiley_order, smiley_row',
		array(
			'popup' => 1,
		)
	);
	$smileys = array(
		'postform' => array(
			'rows' => array(),
		),
		'popup' => array(
			'rows' => array(),
		),
	);
	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		$location = empty($row['hidden']) ? 'postform' : 'popup';
		$smileys[$location]['rows'][$row['smiley_row']][] = array(
			'id' => $row['id_smiley'],
			'code' => htmlspecialchars($row['code']),
			'filename' => htmlspecialchars($row['filename']),
			'description' => htmlspecialchars($row['description']),
			'row' => $row['smiley_row'],
			'order' => $row['smiley_order'],
			'selected' => !empty($_REQUEST['move']) && $_REQUEST['move'] == $row['id_smiley'],
		);
	}
	$smcFunc['db_free_result']($request);

	return $smileys;
}

/**
 * Validates, if a smiley set was properly installed.
 *
 * @param type $set
 * @return boolean
 */
function isSmileySetInstalled($set)
{
	global $smcFunc;

	$request = $smcFunc['db_query']('', '
		SELECT version, themes_installed, db_changes
		FROM {db_prefix}log_packages
		WHERE package_id = {string:current_package}
			AND install_state != {int:not_installed}
		ORDER BY time_installed DESC
		LIMIT 1',
		array(
			'not_installed'	=> 0,
			'current_package' => $set,
		)
	);

	if ($smcFunc['db_num_rows']($request) > 0)
		return false;

	return true;
}

/**
 * Logs the installation of a new smiley set.
 *
 * @param array $param
 */
function logPackageInstall($param)
{
	global $smcFunc;

	$smcFunc['db_insert']('',
		'{db_prefix}log_packages',
		array(
			'filename' => 'string', 'name' => 'string', 'package_id' => 'string', 'version' => 'string',
			'id_member_installed' => 'int', 'member_installed' => 'string','time_installed' => 'int',
			'install_state' => 'int', 'failed_steps' => 'string', 'themes_installed' => 'string',
			'member_removed' => 'int', 'db_changes' => 'string', 'credits' => 'string',
		),
		array(
			$param['filename'], $param['name'], $param['package_id'], $param['version'],
			$param['id_member'], $param['member_name'], time(),
			1, '', '',
			0, '', $param['credits_tag'],
		),
		array('id_install')
	);
}

/**
 * Get the last smiley_order from the first smileys row. 
 *
 * @return type
 */
function getMaxSmileyOrder()
{
	global $smcFunc;

	$request = $smcFunc['db_query']('', '
		SELECT MAX(smiley_order)
		FROM {db_prefix}smileys
		WHERE hidden = {int:postform}
			AND smiley_row = {int:first_row}',
		array(
			'postform' => 0,
			'first_row' => 0,
		)
	);
	list ($smiley_order) = $smcFunc['db_fetch_row']($request);
	$smcFunc['db_free_result']($request);

	return $smiley_order;
}

/**
 * This function sorts the smiley table by code length,
 * it is needed as MySQL withdrew support for functions in order by.
 * @todo is this ordering itself needed?
 */
function sortSmileyTable()
{
	global $smcFunc;

	db_extend('packages');

	// Add a sorting column.
	$smcFunc['db_add_column']('{db_prefix}smileys', array('name' => 'temp_order', 'size' => 8, 'type' => 'mediumint', 'null' => false));

	// Set the contents of this column.
	$smcFunc['db_query']('set_smiley_order', '
		UPDATE {db_prefix}smileys
		SET temp_order = LENGTH(code)',
		array(
		)
	);

	// Order the table by this column.
	$smcFunc['db_query']('alter_table_smileys', '
		ALTER TABLE {db_prefix}smileys
		ORDER BY temp_order DESC',
		array(
			'db_error_skip' => true,
		)
	);

	// Remove the sorting column.
	$smcFunc['db_remove_column']('{db_prefix}smileys', 'temp_order');
}

/**
 * Callback function for createList().
 * Lists all smiley sets.
 *
 * @param $start
 * @param $items_per_page
 * @param $sort
 */
function list_getSmileySets($start, $items_per_page, $sort)
{
	global $modSettings;

	$known_sets = explode(',', $modSettings['smiley_sets_known']);
	$set_names = explode("\n", $modSettings['smiley_sets_names']);
	$cols = array(
		'id' => array(),
		'selected' => array(),
		'path' => array(),
		'name' => array(),
	);
	foreach ($known_sets as $i => $set)
	{
		$cols['id'][] = $i;
		$cols['selected'][] = $i;
		$cols['path'][] = $set;
		$cols['name'][] = $set_names[$i];
	}
	$sort_flag = strpos($sort, 'DESC') === false ? SORT_ASC : SORT_DESC;
	if (substr($sort, 0, 4) === 'name')
		array_multisort($cols['name'], $sort_flag, SORT_REGULAR, $cols['path'], $cols['selected'], $cols['id']);
	elseif (substr($sort, 0, 4) === 'path')
		array_multisort($cols['path'], $sort_flag, SORT_REGULAR, $cols['name'], $cols['selected'], $cols['id']);
	else
		array_multisort($cols['selected'], $sort_flag, SORT_REGULAR, $cols['path'], $cols['name'], $cols['id']);

	$smiley_sets = array();
	foreach ($cols['id'] as $i => $id)
		$smiley_sets[] = array(
			'id' => $id,
			'path' => $cols['path'][$i],
			'name' => $cols['name'][$i],
			'selected' => $cols['path'][$i] == $modSettings['smiley_sets_default']
		);

	return $smiley_sets;
}

/**
 * Callback function for createList().
 */
function list_getNumSmileySets()
{
	global $modSettings;

	return count(explode(',', $modSettings['smiley_sets_known']));
}

/**
 * Callback function for createList().
 *
 * @param int $start
 * @param int $items_per_page
 * @param string $sort
 */
function list_getSmileys($start, $items_per_page, $sort)
{
	global $smcFunc;

	$request = $smcFunc['db_query']('', '
		SELECT id_smiley, code, filename, description, smiley_row, smiley_order, hidden
		FROM {db_prefix}smileys
		ORDER BY ' . $sort . '
		LIMIT ' . $start . ', ' . $items_per_page,
		array(
		)
	);
	$smileys = array();
	while ($row = $smcFunc['db_fetch_assoc']($request))
		$smileys[] = $row;
	$smcFunc['db_free_result']($request);

	return $smileys;
}

/**
 * Callback function for createList().
 */
function list_getNumSmileys()
{
	global $smcFunc;

	$request = $smcFunc['db_query']('', '
		SELECT COUNT(*)
		FROM {db_prefix}smileys',
		array(
		)
	);
	list($numSmileys) = $smcFunc['db_fetch_row'];
	$smcFunc['db_free_result']($request);

	return $numSmileys;
}