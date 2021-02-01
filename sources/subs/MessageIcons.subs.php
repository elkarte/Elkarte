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

use ElkArte\Cache\Cache;

/**
 * Gets a list of all available message icons.
 */
function fetchMessageIconsDetails()
{
	static $icons;

	if (isset($icons))
	{
		return $icons;
	}

	$db = database();

	$icons = array();
	$last_icon = 0;
	$trueOrder = 0;
	$db->fetchQuery('
		SELECT 
			m.id_icon, m.title, m.filename, m.icon_order, m.id_board, b.name AS board_name
		FROM {db_prefix}message_icons AS m
			LEFT JOIN {db_prefix}boards AS b ON (b.id_board = m.id_board)
		WHERE ({query_see_board} OR b.id_board IS NULL)
		ORDER BY m.icon_order',
		array()
	)->fetch_callback(
		function ($row) use (&$icons, &$last_icon, &$trueOrder) {
			global $settings, $txt;

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
	);

	return $icons;
}

/**
 * Delete a message icon.
 *
 * @param int[] $icons
 * @throws \ElkArte\Exceptions\Exception
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
 * @throws \Exception
 */
function updateMessageIcon($icon)
{
	$db = database();

	$db->replace(
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
 * @throws \Exception
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

/**
 * Retrieves a list of message icons.
 * - Based on the settings, the array will either contain a list of default
 *   message icons or a list of custom message icons retrieved from the database.
 * - The board_id is needed for the custom message icons (which can be set for
 *   each board individually).
 *
 * @param int $board_id
 * @return array
 * @throws \Exception
 */
function getMessageIcons($board_id)
{
	global $modSettings, $txt, $settings;

	$db = database();

	if (empty($modSettings['messageIcons_enable']))
	{
		theme()->getTemplates()->loadLanguageFile('Post');

		$icons = array(
			array('value' => 'xx', 'name' => $txt['standard']),
			array('value' => 'thumbup', 'name' => $txt['thumbs_up']),
			array('value' => 'thumbdown', 'name' => $txt['thumbs_down']),
			array('value' => 'exclamation', 'name' => $txt['exclamation_point']),
			array('value' => 'question', 'name' => $txt['question_mark']),
			array('value' => 'lamp', 'name' => $txt['lamp']),
			array('value' => 'smiley', 'name' => $txt['icon_smiley']),
			array('value' => 'angry', 'name' => $txt['icon_angry']),
			array('value' => 'cheesy', 'name' => $txt['icon_cheesy']),
			array('value' => 'grin', 'name' => $txt['icon_grin']),
			array('value' => 'sad', 'name' => $txt['icon_sad']),
			array('value' => 'wink', 'name' => $txt['icon_wink']),
			array('value' => 'poll', 'name' => $txt['icon_poll']),
		);

		foreach ($icons as $k => $dummy)
		{
			$icons[$k]['url'] = $settings['images_url'] . '/post/' . $dummy['value'] . '.png';
			$icons[$k]['is_last'] = false;
		}
	}
	// Otherwise load the icons, and check we give the right image too...
	else
	{
		$icons = array();
		if (!Cache::instance()->getVar($icons, 'posting_icons-' . $board_id, 480))
		{
			$icon_data = $db->fetchQuery('
				SELECT 
					title, filename
				FROM {db_prefix}message_icons
				WHERE id_board IN (0, {int:board_id})
				ORDER BY icon_order',
				array(
					'board_id' => $board_id,
				)
			);
			$icons = array();
			foreach ($icon_data->fetch_all() as $icon)
			{
				$icons[$icon['filename']] = array(
					'value' => $icon['filename'],
					'name' => $icon['title'],
					'url' => $settings[file_exists($settings['theme_dir'] . '/images/post/' . $icon['filename'] . '.png') ? 'images_url' : 'default_images_url'] . '/post/' . $icon['filename'] . '.png',
					'is_last' => false,
				);
			}

			Cache::instance()->put('posting_icons-' . $board_id, $icons, 480);
		}
	}

	return array_values($icons);
}
