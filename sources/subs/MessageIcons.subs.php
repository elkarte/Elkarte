<?php

/**
 * Functions for working with message icons
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

/**
 * Gets a list of all available message icons.
 */
function fetchMessageIconsDetails()
{
	global $settings, $txt;
	static $icons;

	if (isset($icons))
	{
		return $icons;
	}

	$db = database();

	$icons = array();

	$request = $db->query('', '
		SELECT m.id_icon, m.title, m.filename, m.icon_order, m.id_board, b.name AS board_name
		FROM {db_prefix}message_icons AS m
			LEFT JOIN {db_prefix}boards AS b ON (b.id_board = m.id_board)
		WHERE ({query_see_board} OR b.id_board IS NULL)
		ORDER BY m.icon_order',
		array()
	);
	$last_icon = 0;
	$trueOrder = 0;
	while ($row = $db->fetch_assoc($request))
	{
		$icons[$row['id_icon']] = array(
			'id' => $row['id_icon'],
			'title' => $row['title'],
			'filename' => $row['filename'],
			'image_url' => $settings[file_exists($settings['theme_dir'] . '/images/post/' . $row['filename'] . '.png') ? 'actual_images_url' : 'default_images_url'] . '/post/' . $row['filename'] . '.png',
			'board_id' => $row['id_board'],
			'board' => empty($row['board_name']) ? $txt['icons_edit_icons_all_boards'] : $row['board_name'],
			'order' => $row['icon_order'],
			'true_order' => $trueOrder++,
			'after' => $last_icon,
		);
		$last_icon = $row['id_icon'];
	}
	$db->free_result($request);

	return $icons;
}

/**
 * Delete a message icon.
 *
 * @param int[] $icons
 */
function deleteMessageIcons($icons)
{
	$db = database();

	// Do the actual delete!
	$db->query('', '
		DELETE FROM {db_prefix}message_icons
		WHERE id_icon IN ({array_int:icon_list})',
		array(
			'icon_list' => $icons,
		)
	);
}

/**
 * Updates a message icon.
 *
 * @param mixed[] $icon array of values to use in the $db->insert
 */
function updateMessageIcon($icon)
{
	$db = database();

	$db->insert('replace',
		'{db_prefix}message_icons',
		array('id_icon' => 'int', 'id_board' => 'int', 'title' => 'string-80', 'filename' => 'string-80', 'icon_order' => 'int'),
		$icon,
		array('id_icon')
	);
}

/**
 * Adds a new message icon.
 *
 * @param mixed[] $icon associative array to use in the insert
 */
function addMessageIcon($icon)
{
	$db = database();

	$db->insert('',
		'{db_prefix}message_icons',
		array('id_board' => 'int', 'title' => 'string-80', 'filename' => 'string-80', 'icon_order' => 'int'),
		$icon,
		array('id_icon')
	);
}
