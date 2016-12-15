<?php

/**
 * Database and support functions for adding, moving, saving smileys
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
 * Validates, if a smiley already exists
 *
 * @param string[] $smileys
 * @return array
 */
function smileyExists($smileys)
{
	$db = database();

	$found = array();
	$request = $db->query('', '
		SELECT filename
		FROM {db_prefix}smileys
		WHERE filename IN ({array_string:smiley_list})',
		array(
			'smiley_list' => $smileys,
		)
	);
	while ($row = $db->fetch_assoc($request))
		$found[] = $row['filename'];
	$db->free_result($request);

	return $found;
}

/**
 * Validates duplicate smileys
 *
 * @param string $code
 * @param string|null $current
 * @return boolean
 */
function validateDuplicateSmiley($code, $current = null)
{
	$db = database();

	$request = $db->query('', '
		SELECT id_smiley
		FROM {db_prefix}smileys
		WHERE code = {raw:mysql_binary_type} {string:smiley_code}' . (!isset($current) ? '' : '
			AND id_smiley != {int:current_smiley}'),
		array(
			'current_smiley' => $current,
			'mysql_binary_type' => $db->db_title() == 'MySQL' ? 'BINARY' : '',
			'smiley_code' => $code,
		)
	);
	if ($db->num_rows($request) > 0)
		return true;
	$db->free_result($request);

	return false;
}

/**
 * Request the next location for a new smiley
 *
 * @param string $location
 */
function nextSmileyLocation($location)
{
	$db = database();

	$request = $db->query('', '
		SELECT MAX(smiley_order) + 1
		FROM {db_prefix}smileys
		WHERE hidden = {int:smiley_location}
			AND smiley_row = {int:first_row}',
		array(
			'smiley_location' => $location,
			'first_row' => 0,
		)
	);
	list ($smiley_order) = $db->fetch_row($request);
	$db->free_result($request);

	return $smiley_order;
}

/**
 * Adds a smiley to the database
 *
 * @param mixed[] $param associative array to use in the insert
 */
function addSmiley($param)
{
	$db = database();

	$db->insert('',
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
 * @param int[] $smileys
 */
function deleteSmileys($smileys)
{
	$db = database();

	$db->query('', '
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
 * @param int[] $smileys
 * @param int $display_type
 */
function updateSmileyDisplayType($smileys, $display_type)
{
	$db = database();

	$db->query('', '
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
 * Updates a smiley.
 *
 * @param mixed[] $param
 */
function updateSmiley($param)
{
	$db = database();

	$db->query('', '
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
 *
 * @return array
 * @throws Elk_Exception smiley_not_found
 */
function getSmiley($id)
{
	$db = database();

	$request = $db->query('', '
		SELECT id_smiley AS id, code, filename, description, hidden AS location, 0 AS is_new, smiley_row AS row
		FROM {db_prefix}smileys
		WHERE id_smiley = {int:current_smiley}',
		array(
			'current_smiley' => $id,
		)
	);
	if ($db->num_rows($request) != 1)
		throw new Elk_Exception('smiley_not_found');
	$current_smiley = $db->fetch_assoc($request);
	$db->free_result($request);

	return $current_smiley;
}

/**
 * Get the position from a given smiley
 *
 * @param int $location
 * @param int $id
 * @return integer
 */
function getSmileyPosition($location, $id)
{
	$db = database();

	$smiley = array();

	$request = $db->query('', '
		SELECT smiley_row, smiley_order, hidden
		FROM {db_prefix}smileys
		WHERE hidden = {int:location}
			AND id_smiley = {int:id_smiley}',
		array(
			'location' => $location,
			'id_smiley' => $id,
		)
	);
	list ($smiley['row'], $smiley['order'], $smiley['location']) = $db->fetch_row($request);

	$db->free_result($request);

	return $smiley;
}

/**
 * Move a smiley to their new position.
 *
 * @param int[] $smiley
 * @param int $source
 */
function moveSmileyPosition($smiley, $source)
{
	$db = database();

	$db->query('', '
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

	$db->query('', '
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
	$db = database();

	$db->query('', '
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
 * @param int $id
 * @param int $order
 */
function updateSmileyOrder($id, $order)
{
	$db = database();

	$db->query('', '
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
 */
function getSmileys()
{
	$db = database();

	$request = $db->query('', '
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
	while ($row = $db->fetch_assoc($request))
	{
		$location = empty($row['hidden']) ? 'postform' : 'popup';
		$smileys[$location]['rows'][$row['smiley_row']][] = array(
			'id' => $row['id_smiley'],
			'code' => htmlspecialchars($row['code'], ENT_COMPAT, 'UTF-8'),
			'filename' => htmlspecialchars($row['filename'], ENT_COMPAT, 'UTF-8'),
			'description' => htmlspecialchars($row['description'], ENT_COMPAT, 'UTF-8'),
			'row' => $row['smiley_row'],
			'order' => $row['smiley_order'],
			'selected' => !empty($_REQUEST['move']) && $_REQUEST['move'] == $row['id_smiley'],
		);
	}
	$db->free_result($request);

	return $smileys;
}

/**
 * Validates, if a smiley set was properly installed.
 *
 * @param string $set name of smiley set to check
 * @return boolean
 */
function isSmileySetInstalled($set)
{
	$db = database();

	$request = $db->query('', '
		SELECT version, themes_installed, db_changes
		FROM {db_prefix}log_packages
		WHERE package_id = {string:current_package}
			AND install_state != {int:not_installed}
		ORDER BY time_installed DESC
		LIMIT 1',
		array(
			'not_installed' => 0,
			'current_package' => $set,
		)
	);
	return !($db->num_rows($request) > 0);
}

/**
 * Logs the installation of a new smiley set.
 *
 * @param mixed[] $param
 */
function logPackageInstall($param)
{
	$db = database();

	$db->insert('',
		'{db_prefix}log_packages',
		array(
			'filename' => 'string', 'name' => 'string', 'package_id' => 'string', 'version' => 'string',
			'id_member_installed' => 'int', 'member_installed' => 'string', 'time_installed' => 'int',
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
 * @return string
 */
function getMaxSmileyOrder()
{
	$db = database();

	$request = $db->query('', '
		SELECT MAX(smiley_order)
		FROM {db_prefix}smileys
		WHERE hidden = {int:postform}
			AND smiley_row = {int:first_row}',
		array(
			'postform' => 0,
			'first_row' => 0,
		)
	);
	list ($smiley_order) = $db->fetch_row($request);
	$db->free_result($request);

	return $smiley_order;
}

/**
 * This function sorts the smiley table by code length,
 * it is needed as MySQL withdrew support for functions in order by.
 *
 * @deprecated since 1.0 - the ordering is done in the query, probably not needed
 */
function sortSmileyTable()
{
	$db = database();

	// Order the table by code length.
	$db->query('alter_table', '
		ALTER TABLE {db_prefix}smileys
		ORDER BY LENGTH(code) DESC',
		array(
			'db_error_skip' => true,
		)
	);
}

/**
 * Callback function for createList().
 * Lists all smiley sets.
 *
 * @param int $start The item to start with (for pagination purposes)
 * @param int $items_per_page  The number of items to show per page
 * @param string $sort A string indicating how to sort the results
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
 * @param int $start The item to start with (for pagination purposes)
 * @param int $items_per_page  The number of items to show per page
 * @param string $sort A string indicating how to sort the results
 */
function list_getSmileys($start, $items_per_page, $sort)
{
	$db = database();

	$request = $db->query('', '
		SELECT id_smiley, code, filename, description, smiley_row, smiley_order, hidden
		FROM {db_prefix}smileys
		ORDER BY ' . $sort . '
		LIMIT ' . $start . ', ' . $items_per_page,
		array(
		)
	);
	$smileys = array();
	while ($row = $db->fetch_assoc($request))
		$smileys[] = $row;
	$db->free_result($request);

	return $smileys;
}

/**
 * Callback function for createList().
 */
function list_getNumSmileys()
{
	$db = database();

	$request = $db->query('', '
		SELECT COUNT(*)
		FROM {db_prefix}smileys',
		array(
		)
	);
	list ($numSmileys) = $db->fetch_row($request);
	$db->free_result($request);

	return $numSmileys;
}