<?php

/**
 * Database and support functions for adding, moving, saving smileys
 *
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * This file contains code covered by:
 * copyright: 2011 Simple Machines (http://www.simplemachines.org)
 *
 * @version 2.0 dev
 *
 */

use ElkArte\Emoji;
use ElkArte\FileFunctions;

/**
 * Validates, if a smiley already exists
 *
 * @param string[] $smileys
 * @return array
 */
function smileyExists($smileys)
{
	$db = database();

	$found = [];

	if (empty($smileys))
	{
		return $found;
	}

	$db->fetchQuery('
		SELECT 
			filename
		FROM {db_prefix}smileys
		WHERE filename IN ({array_string:smiley_list})',
		[
			'smiley_list' => $smileys,
		]
	)->fetch_callback(
		function ($row) use (&$found) {
			$found[] = $row['filename'];
		}
	);

	return $found;
}

/**
 * Validates duplicate smileys
 *
 * @param string $code
 * @param string|null $current
 * @return bool
 */
function validateDuplicateSmiley($code, $current = null)
{
	$db = database();

	return $db->fetchQuery('
		SELECT 
			id_smiley
		FROM {db_prefix}smileys
		WHERE code = {string_case_sensitive:smiley_code}' . (!isset($current) ? '' : '
			AND id_smiley != {int:current_smiley}'),
		[
			'current_smiley' => $current,
			'smiley_code' => $code,
		]
	)->num_rows() > 0;
}

/**
 * Request the next location for a new smiley
 *
 * @param string $location
 *
 * @return int
 */
function nextSmileyLocation($location)
{
	$db = database();

	$request = $db->fetchQuery('
		SELECT 
			MAX(smiley_order) + 1
		FROM {db_prefix}smileys
		WHERE hidden = {int:smiley_location}
			AND smiley_row = {int:first_row}',
		[
			'smiley_location' => $location,
			'first_row' => 0,
		]
	);
	list ($smiley_order) = $request->fetch_row();
	$request->free_result();

	return $smiley_order;
}

/**
 * Adds a smiley to the database
 *
 * @param array $param associative array to use in the insert
 */
function addSmiley($param)
{
	$db = database();

	$db->insert('',
		'{db_prefix}smileys',
		[
			'code' => 'string-30', 'filename' => 'string-48', 'description' => 'string-80',
			'hidden' => 'int', 'smiley_order' => 'int',
		],
		$param,
		['id_smiley']
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
		[
			'checked_smileys' => $smileys,
		]
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
		SET 
			hidden = {int:display_type}
		WHERE id_smiley IN ({array_int:checked_smileys})',
		[
			'checked_smileys' => $smileys,
			'display_type' => $display_type,
		]
	);
}

/**
 * Updates a smiley.
 *
 * @param array $param
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
		[
			'smiley_location' => $param['smiley_location'],
			'current_smiley' => $param['smiley'],
			'smiley_code' => $param['smiley_code'],
			'smiley_filename' => $param['smiley_filename'],
			'smiley_description' => $param['smiley_description'],
		]
	);
}

/**
 * Get detailed smiley information
 *
 * @param int $id
 *
 * @return array
 * @throws \ElkArte\Exceptions\Exception smiley_not_found
 */
function getSmiley($id)
{
	$db = database();

	$current_smiley = [];
	$db->fetchQuery('
		SELECT 
			id_smiley AS id, code, filename, description, hidden AS location, 0 AS is_new, smiley_row AS row
		FROM {db_prefix}smileys
		WHERE id_smiley = {int:current_smiley}',
		[
			'current_smiley' => $id,
		]
	)->fetch_callback(
		function ($row) use (&$current_smiley)
		{
			$current_smiley = [
				'id' => $row['id'],
				'code' => $row['code'],
				'filename' => pathinfo($row['filename'], PATHINFO_FILENAME),
				'description' => $row['description'],
				'row' => $row['row'],
				'is_new' => 0,
				'location' => $row['location']
			];
		}
	);

	if (empty($current_smiley))
	{
		throw new \ElkArte\Exceptions\Exception('smiley_not_found');
	}

	return $current_smiley;
}

/**
 * Get the position from a given smiley
 *
 * @param int $location
 * @param int $id
 *
 * @return array
 */
function getSmileyPosition($location, $id)
{
	$db = database();

	$smiley = [];

	$request = $db->query('', '
		SELECT 
			smiley_row, smiley_order, hidden
		FROM {db_prefix}smileys
		WHERE hidden = {int:location}
			AND id_smiley = {int:id_smiley}',
		[
			'location' => $location,
			'id_smiley' => $id,
		]
	);
	list ($smiley['row'], $smiley['order'], $smiley['location']) = $request->fetch_row();
	$request->free_result();

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
		SET 
			smiley_order = smiley_order + 1
		WHERE hidden = {int:new_location}
			AND smiley_row = {int:smiley_row}
			AND smiley_order > {int:smiley_order}',
		[
			'new_location' => $smiley['location'],
			'smiley_row' => $smiley['row'],
			'smiley_order' => $smiley['order'],
		]
	);

	$db->query('', '
		UPDATE {db_prefix}smileys
		SET
			smiley_order = {int:smiley_order} + 1,
			smiley_row = {int:smiley_row},
			hidden = {int:new_location}
		WHERE id_smiley = {int:current_smiley}',
		[
			'smiley_order' => $smiley['order'],
			'smiley_row' => $smiley['row'],
			'new_location' => $smiley['location'],
			'current_smiley' => $source,
		]
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
		SET 
			smiley_row = {int:new_row}
		WHERE smiley_row = {int:current_row}
			AND hidden = {int:location}',
		[
			'new_row' => $id,
			'current_row' => $row,
			'location' => $location === 'postform' ? '0' : '2',
		]
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
		SET 
			smiley_order = {int:new_order}
		WHERE id_smiley = {int:current_smiley}',
		[
			'new_order' => $order,
			'current_smiley' => $id,
		]
	);
}

/**
 * Get a list of all visible smileys.
 *
 * hidden = 0 is post form, 1 is hidden, 2 is popup,
 */
function getSmileys()
{
	$db = database();

	$smileys = [
		'postform' => [
			'rows' => [],
		],
		'popup' => [
			'rows' => [],
		],
	];

	$db->fetchQuery('
		SELECT 
			id_smiley, code, filename, description, smiley_row, smiley_order, hidden
		FROM {db_prefix}smileys
		WHERE hidden != {int:hidden}
		ORDER BY smiley_order, smiley_row',
		[
			'hidden' => 1,
		]
	)->fetch_callback(
		function ($row) use (&$smileys) {
			global $context;

			$location = empty($row['hidden']) ? 'postform' : 'popup';
			$filename = pathinfo($row['filename'], PATHINFO_FILENAME) . '.' . $context['smiley_extension'];
			if (possibleSmileEmoji($row))
			{
				$filename = $row['emoji'] . '.svg';
			}

			$smileys[$location]['rows'][$row['smiley_row']][] = [
				'id' => $row['id_smiley'],
				'code' => htmlspecialchars($row['code'], ENT_COMPAT, 'UTF-8'),
				'filename' => htmlspecialchars($filename, ENT_COMPAT, 'UTF-8'),
				'description' => htmlspecialchars($row['description'], ENT_COMPAT, 'UTF-8'),
				'row' => $row['smiley_row'],
				'order' => $row['smiley_order'],
				'selected' => !empty($_REQUEST['move']) && $_REQUEST['move'] == $row['id_smiley'],
				'emoji' => $row['emoji'] ?? null,
			];
		}
	);

	return $smileys;
}

/**
 * Validates, if a smiley set was properly installed.
 *
 * @param string $set name of smiley set to check
 * @return bool
 */
function isSmileySetInstalled($set)
{
	$db = database();

	return $db->fetchQuery('
		SELECT 
			version, themes_installed, db_changes
		FROM {db_prefix}log_packages
		WHERE package_id = {string:current_package}
			AND install_state != {int:not_installed}
		ORDER BY time_installed DESC
		LIMIT 1',
		[
			'not_installed' => 0,
			'current_package' => $set,
		]
	)->num_rows() <= 0;
}

/**
 * Logs the installation of a new smiley set.
 *
 * @param array $param
 */
function logPackageInstall($param)
{
	$db = database();

	$db->insert('',
		'{db_prefix}log_packages',
		[
			'filename' => 'string', 'name' => 'string', 'package_id' => 'string', 'version' => 'string',
			'id_member_installed' => 'int', 'member_installed' => 'string', 'time_installed' => 'int',
			'install_state' => 'int', 'failed_steps' => 'string', 'themes_installed' => 'string',
			'member_removed' => 'int', 'db_changes' => 'string', 'credits' => 'string',
		],
		[
			$param['filename'], $param['name'], $param['package_id'], $param['version'],
			$param['id_member'], $param['member_name'], time(),
			1, '', '',
			0, '', $param['credits_tag'],
		],
		['id_install']
	);
}

/**
 * Get the last smiley_order from the first smileys row.
 *
 * @return string
 */
function getMaxSmileyOrder()
{
	$db = database();

	$request = $db->query('', '
		SELECT 
			MAX(smiley_order)
		FROM {db_prefix}smileys
		WHERE hidden = {int:postform}
			AND smiley_row = {int:first_row}',
		[
			'postform' => 0,
			'first_row' => 0,
		]
	);
	list ($smiley_order) = $request->fetch_row();
	$request->free_result();

	return $smiley_order;
}

/**
 * Callback function for createList().
 * Lists all smiley sets.
 *
 * @param int $start The item to start with (for pagination purposes)
 * @param int $items_per_page The number of items to show per page
 * @param string $sort A string indicating how to sort the results
 *
 * @return array
 */
function list_getSmileySets($start, $items_per_page, $sort)
{
	global $modSettings;

	$known_sets = explode(',', $modSettings['smiley_sets_known']);
	$set_names = explode("\n", $modSettings['smiley_sets_names']);
	$set_exts = explode(',', $modSettings['smiley_sets_extensions']);

	$cols = [
		'id' => [],
		'selected' => [],
		'path' => [],
		'name' => [],
	];

	foreach ($known_sets as $i => $set)
	{
		$cols['id'][] = $i;
		$cols['selected'][] = $i;
		$cols['path'][] = $set;
		$cols['name'][] = stripslashes($set_names[$i]);
		$cols['ext'][] = $set_exts[$i];
	}

	$sort_flag = strpos($sort, 'DESC') === false ? SORT_ASC : SORT_DESC;

	if (strpos($sort, 'name') === 0)
	{
		array_multisort($cols['name'], $sort_flag, SORT_REGULAR, $cols['path'], $cols['selected'], $cols['id'], $cols['ext']);
	}
	elseif (strpos($sort, 'ext') === 0)
	{
		array_multisort($cols['ext'], $sort_flag, SORT_REGULAR, $cols['name'], $cols['selected'], $cols['id'], $cols['path']);
	}
	elseif (strpos($sort, 'path') === 0)
	{
		array_multisort($cols['path'], $sort_flag, SORT_REGULAR, $cols['name'], $cols['selected'], $cols['id'], $cols['ext']);
	}
	else
	{
		array_multisort($cols['selected'], $sort_flag, SORT_REGULAR, $cols['path'], $cols['name'], $cols['id'], $cols['ext']);
	}

	$smiley_sets = [];
	foreach ($cols['id'] as $i => $id)
	{
		$smiley_sets[] = [
			'id' => $id,
			'path' => $cols['path'][$i],
			'name' => $cols['name'][$i],
			'ext' => $cols['ext'][$i],
			'selected' => $cols['path'][$i] === $modSettings['smiley_sets_default']
		];
	}

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
 * @param int $items_per_page The number of items to show per page
 * @param string $sort A string indicating how to sort the results
 *
 * @return array
 */
function list_getSmileys($start, $items_per_page, $sort)
{
	$db = database();

	$result = [];
	$db->fetchQuery('
		SELECT 
			id_smiley, code, filename, description, smiley_row, smiley_order, hidden
		FROM {db_prefix}smileys
		ORDER BY ' . $sort . '
		LIMIT ' . $items_per_page . '  OFFSET ' . $start,
		[]
	)->fetch_callback(
		function($row) use(&$result) {
			$result[] = [
				'id_smiley' => $row['id_smiley'],
				'code' => $row['code'],
				'filename' => pathinfo($row['filename'], PATHINFO_FILENAME),
				'description' => $row['description'],
				'smiley_row' => $row['smiley_row'],
				'smiley_order' => $row['smiley_order'],
				'hidden' => $row['hidden'],
			];
		}
	);

	return $result;
}

/**
 * Callback function for createList().
 */
function list_getNumSmileys()
{
	$db = database();

	$request = $db->query('', '
		SELECT 
			COUNT(*)
		FROM {db_prefix}smileys',
		[]
	);
	list ($numSmileys) = $request->fetch_row();
	$request->free_result();

	return $numSmileys;
}

/**
 * Reads all smiley directories, and sets the image type for the set(s).  Saves this information
 * in modSettings.
 *
 * @return string a csv string in the same order as smiley_sets_known
 */
function setSmileyExtensionArray()
{
	global $modSettings;

	$smiley_types =  ['jpg', 'gif', 'jpeg', 'png', 'webp', 'svg'];
	$smileys_dir = empty($modSettings['smileys_dir']) ? BOARDDIR . '/smileys' : $modSettings['smileys_dir'];
	$fileFunc = FileFunctions::instance();
	$extensionTypes = [];

	$smiley_sets_known = explode(',', $modSettings['smiley_sets_known']);
	foreach ($smiley_sets_known as $set)
	{
		$smiles = $fileFunc->listTree($smileys_dir . '/' . $set);

		// What type of set is this, svg, gif, png
		foreach ($smiles as $smile)
		{
			$temp = pathinfo($smile['filename'], PATHINFO_EXTENSION);
			if (in_array($temp, $smiley_types, true))
			{
				$extensionTypes[] = $temp;
				break;
			}
		}
	}

	$extensionTypes = implode(',', $extensionTypes);
	updateSettings(['smiley_sets_extensions' => $extensionTypes]);

	return $extensionTypes;
}

/**
 * Fetch and prepare the smileys for use in the post editor
 *
 * What it does:
 * - Old smiles as :) are processed as normal, requiring an image file in its smile set directory
 * - Emoji :smile:
 *   - first checked if the image file exists in the smile set directory
 *   - if not found it will use the emoji class to check if the code exists in the emoji code list and if found
 * the image will be set appropriately and a flag set to indicate an emoji, not smile, image
 *   - if not found treated as a missing image
 *   - Does not process any defined with hidden = 1 (hidden / custom)
 *
 * @return array composed of smiley location and row in that location
 */
function getEditorSmileys()
{
	global $context;

	$db = database();

	$smileys = [];

	$db->fetchQuery('
		SELECT 
			code, filename, description, smiley_row, hidden
		FROM {db_prefix}smileys
		WHERE hidden IN (0, 2)
		ORDER BY smiley_row, smiley_order',
		[]
	)->fetch_callback(
		function ($row) use (&$smileys, $context) {
			$filename = $row['filename'] . '.' . $context['smiley_extension'];
			if (possibleSmileEmoji($row))
			{
				$filename = $row['emoji'] . '.svg';
			}

			$row['description'] = htmlspecialchars($row['description'], ENT_COMPAT, 'UTF-8');
			$row['filename'] = htmlspecialchars($filename, ENT_COMPAT, 'UTF-8');

			$smileys[empty($row['hidden']) ? 'postform' : 'popup'][$row['smiley_row']]['smileys'][] = $row;
		}
	);

	return $smileys;
}

/**
 * Checks if a defined smiley code as :smile: exists
 *
 * What it does:
 * - Looks in the smile directory for example, smile.png.
 * - If not found, checks if :smile: is a legitimate emoji short code.
 * - If so, sets an ['emoji'] row to the proper utf8 value.
 *
 * @param array $row
 * @param string $path
 * @param string $ext
 * @return bool if the code is a legitimate emoji short code and no image exists in the smile/smile_set directory
 */
function possibleSmileEmoji(&$row, $path = null, $ext = null)
{
	global $context;

	$ext = $ext ?? $context['smiley_extension'];
	$path = $path ?? $context['smiley_dir'];
	$path = rtrim($path, '/\\') . DIRECTORY_SEPARATOR;

	// At least 4 characters long, starts and ends with :  -- Marginally faster than preg_match
	$possibleEmoji = isset($row['code'][3]) && $row['code'][0] === ':' && substr($row['code'], -1, 1) === ':';

	// If this is possibly an emoji and the image does not exist in the smile set
	if ($possibleEmoji && !FileFunctions::instance()->fileExists($path . $row['filename'] . '.' . $ext))
	{
		$emoji = Emoji::instance();

		// Check if we have an emoji image for this smiley code
		$test = preg_replace_callback('~(:([-+\w]+):)~u', [$emoji, 'emojiToImage'], $row['code']);
		if ($test !== $row['filename'] && preg_match('~data-emoji-code=["\'](.*?)["\']~', $test, $result))
		{
			// Valid emoji, set the filename to the proper emoji file and type
			$row['emoji'] =  $result[1];
			return true;
		}
	}

	return false;
}
