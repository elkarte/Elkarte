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
 * This file is mainly concerned with minor tasks relating to boards, such as
 * marking them read, collapsing categories, or quick moderation.
 *
 */

if (!defined('ELKARTE'))
	die('No access...');

/**
 * Mark a board or multiple boards read.
 *
 * @param array $boards
 * @param bool $unread = false
 */
function markBoardsRead($boards, $unread = false, $resetTopics = false)
{
	global $user_info, $modSettings;

	$db = database();

	// Force $boards to be an array.
	if (!is_array($boards))
		$boards = array($boards);
	else
		$boards = array_unique($boards);

	// No boards, nothing to mark as read.
	if (empty($boards))
		return;

	// Allow the user to mark a board as unread.
	if ($unread)
	{
		// Clear out all the places where this lovely info is stored.
		// @todo Maybe not log_mark_read?
		$db->query('', '
			DELETE FROM {db_prefix}log_mark_read
			WHERE id_board IN ({array_int:board_list})
				AND id_member = {int:current_member}',
			array(
				'current_member' => $user_info['id'],
				'board_list' => $boards,
			)
		);
		$db->query('', '
			DELETE FROM {db_prefix}log_boards
			WHERE id_board IN ({array_int:board_list})
				AND id_member = {int:current_member}',
			array(
				'current_member' => $user_info['id'],
				'board_list' => $boards,
			)
		);
	}
	// Otherwise mark the board as read.
	else
	{
		$markRead = array();
		foreach ($boards as $board)
			$markRead[] = array($modSettings['maxMsgID'], $user_info['id'], $board);

		// Update log_mark_read and log_boards.
		$db->insert('replace',
			'{db_prefix}log_mark_read',
			array('id_msg' => 'int', 'id_member' => 'int', 'id_board' => 'int'),
			$markRead,
			array('id_board', 'id_member')
		);

		$db->insert('replace',
			'{db_prefix}log_boards',
			array('id_msg' => 'int', 'id_member' => 'int', 'id_board' => 'int'),
			$markRead,
			array('id_board', 'id_member')
		);
	}

	// Get rid of useless log_topics data, because log_mark_read is better for it - even if marking unread - I think so...
	// @todo look at this...
	// The call to markBoardsRead() in Display() used to be simply
	// marking log_boards (the previous query only)
	// I'm adding a bool to control the processing of log_topics. We might want to just disociate it from boards,
	// and call the log_topics clear-up only from the controller that needs it..

	// Notes (for read/unread rework)
	// MessageIndex::action_messageindex() does not update log_topics at all (only the above).
	// Display controller needed only to update log_boards.

	if ($resetTopics)
	{
		$result = $db->query('', '
			SELECT MIN(id_topic)
			FROM {db_prefix}log_topics
			WHERE id_member = {int:current_member}',
			array(
				'current_member' => $user_info['id'],
			)
		);
		list ($lowest_topic) = $db->fetch_row($result);
		$db->free_result($result);

		if (empty($lowest_topic))
			return;

		// @todo SLOW This query seems to eat it sometimes.
		$result = $db->query('', '
			SELECT lt.id_topic
			FROM {db_prefix}log_topics AS lt
				INNER JOIN {db_prefix}topics AS t /*!40000 USE INDEX (PRIMARY) */ ON (t.id_topic = lt.id_topic
					AND t.id_board IN ({array_int:board_list}))
			WHERE lt.id_member = {int:current_member}
				AND lt.id_topic >= {int:lowest_topic}
				AND lt.disregarded != 1',
			array(
				'current_member' => $user_info['id'],
				'board_list' => $boards,
				'lowest_topic' => $lowest_topic,
			)
		);
		$topics = array();
		while ($row = $db->fetch_assoc($result))
			$topics[] = $row['id_topic'];
		$db->free_result($result);

		if (!empty($topics))
			$db->query('', '
				DELETE FROM {db_prefix}log_topics
				WHERE id_member = {int:current_member}
					AND id_topic IN ({array_int:topic_list})',
				array(
					'current_member' => $user_info['id'],
					'topic_list' => $topics,
				)
			);
	}
}

/**
 * Get the id_member associated with the specified message ID.
 *
 * @param int $messageID message ID
 * @return int the member id
 */
function getMsgMemberID($messageID)
{
	require_once(SUBSDIR . '/Messages.subs.php');
	$message_info = getMessageInfo((int) $messageID, true);

	return empty($message_info['id_member']) ? 0 : (int) $message_info['id_member'];
}

/**
 * Modify the settings and position of a board.
 * Used by ManageBoards.php to change the settings of a board.
 *
 * @param int $board_id
 * @param array &$boardOptions
 */
function modifyBoard($board_id, &$boardOptions)
{
	global $cat_tree, $boards;

	$db = database();

	// Get some basic information about all boards and categories.
	getBoardTree();

	// Make sure given boards and categories exist.
	if (!isset($boards[$board_id]) || (isset($boardOptions['target_board']) && !isset($boards[$boardOptions['target_board']])) || (isset($boardOptions['target_category']) && !isset($cat_tree[$boardOptions['target_category']])))
		fatal_lang_error('no_board');

	$id = $board_id;
	call_integration_hook('integrate_pre_modify_board', array($id, &$boardOptions));

	// All things that will be updated in the database will be in $boardUpdates.
	$boardUpdates = array();
	$boardUpdateParameters = array();

	// In case the board has to be moved
	if (isset($boardOptions['move_to']))
	{
		// Move the board to the top of a given category.
		if ($boardOptions['move_to'] == 'top')
		{
			$id_cat = $boardOptions['target_category'];
			$child_level = 0;
			$id_parent = 0;
			$after = $cat_tree[$id_cat]['last_board_order'];
		}

		// Move the board to the bottom of a given category.
		elseif ($boardOptions['move_to'] == 'bottom')
		{
			$id_cat = $boardOptions['target_category'];
			$child_level = 0;
			$id_parent = 0;
			$after = 0;
			foreach ($cat_tree[$id_cat]['children'] as $id_board => $dummy)
				$after = max($after, $boards[$id_board]['order']);
		}

		// Make the board a child of a given board.
		elseif ($boardOptions['move_to'] == 'child')
		{
			$id_cat = $boards[$boardOptions['target_board']]['category'];
			$child_level = $boards[$boardOptions['target_board']]['level'] + 1;
			$id_parent = $boardOptions['target_board'];

			// People can be creative, in many ways...
			if (isChildOf($id_parent, $board_id))
				fatal_lang_error('mboards_parent_own_child_error', false);
			elseif ($id_parent == $board_id)
				fatal_lang_error('mboards_board_own_child_error', false);

			$after = $boards[$boardOptions['target_board']]['order'];

			// Check if there are already children and (if so) get the max board order.
			if (!empty($boards[$id_parent]['tree']['children']) && empty($boardOptions['move_first_child']))
				foreach ($boards[$id_parent]['tree']['children'] as $childBoard_id => $dummy)
					$after = max($after, $boards[$childBoard_id]['order']);
		}

		// Place a board before or after another board, on the same child level.
		elseif (in_array($boardOptions['move_to'], array('before', 'after')))
		{
			$id_cat = $boards[$boardOptions['target_board']]['category'];
			$child_level = $boards[$boardOptions['target_board']]['level'];
			$id_parent = $boards[$boardOptions['target_board']]['parent'];
			$after = $boards[$boardOptions['target_board']]['order'] - ($boardOptions['move_to'] == 'before' ? 1 : 0);
		}

		// Oops...?
		else
			trigger_error('modifyBoard(): The move_to value \'' . $boardOptions['move_to'] . '\' is incorrect', E_USER_ERROR);

		// Get a list of children of this board.
		$childList = array();
		recursiveBoards($childList, $boards[$board_id]['tree']);

		// See if there are changes that affect children.
		$childUpdates = array();
		$levelDiff = $child_level - $boards[$board_id]['level'];
		if ($levelDiff != 0)
			$childUpdates[] = 'child_level = child_level ' . ($levelDiff > 0 ? '+ ' : '') . '{int:level_diff}';
		if ($id_cat != $boards[$board_id]['category'])
			$childUpdates[] = 'id_cat = {int:category}';

		// Fix the children of this board.
		if (!empty($childList) && !empty($childUpdates))
			$db->query('', '
				UPDATE {db_prefix}boards
				SET ' . implode(',
					', $childUpdates) . '
				WHERE id_board IN ({array_int:board_list})',
				array(
					'board_list' => $childList,
					'category' => $id_cat,
					'level_diff' => $levelDiff,
				)
			);

		// Make some room for this spot.
		$db->query('', '
			UPDATE {db_prefix}boards
			SET board_order = board_order + {int:new_order}
			WHERE board_order > {int:insert_after}
				AND id_board != {int:selected_board}',
			array(
				'insert_after' => $after,
				'selected_board' => $board_id,
				'new_order' => 1 + count($childList),
			)
		);

		$boardUpdates[] = 'id_cat = {int:id_cat}';
		$boardUpdates[] = 'id_parent = {int:id_parent}';
		$boardUpdates[] = 'child_level = {int:child_level}';
		$boardUpdates[] = 'board_order = {int:board_order}';
		$boardUpdateParameters += array(
			'id_cat' => $id_cat,
			'id_parent' => $id_parent,
			'child_level' => $child_level,
			'board_order' => $after + 1,
		);
	}

	// This setting is a little twisted in the database...
	if (isset($boardOptions['posts_count']))
	{
		$boardUpdates[] = 'count_posts = {int:count_posts}';
		$boardUpdateParameters['count_posts'] = $boardOptions['posts_count'] ? 0 : 1;
	}

	// Set the theme for this board.
	if (isset($boardOptions['board_theme']))
	{
		$boardUpdates[] = 'id_theme = {int:id_theme}';
		$boardUpdateParameters['id_theme'] = (int) $boardOptions['board_theme'];
	}

	// Should the board theme override the user preferred theme?
	if (isset($boardOptions['override_theme']))
	{
		$boardUpdates[] = 'override_theme = {int:override_theme}';
		$boardUpdateParameters['override_theme'] = $boardOptions['override_theme'] ? 1 : 0;
	}

	// Who's allowed to access this board.
	if (isset($boardOptions['access_groups']))
	{
		$boardUpdates[] = 'member_groups = {string:member_groups}';
		$boardUpdateParameters['member_groups'] = implode(',', $boardOptions['access_groups']);
	}

	// And who isn't.
	if (isset($boardOptions['deny_groups']))
	{
		$boardUpdates[] = 'deny_member_groups = {string:deny_groups}';
		$boardUpdateParameters['deny_groups'] = implode(',', $boardOptions['deny_groups']);
	}

	if (isset($boardOptions['board_name']))
	{
		$boardUpdates[] = 'name = {string:board_name}';
		$boardUpdateParameters['board_name'] = $boardOptions['board_name'];
	}

	if (isset($boardOptions['board_description']))
	{
		$boardUpdates[] = 'description = {string:board_description}';
		$boardUpdateParameters['board_description'] = $boardOptions['board_description'];
	}

	if (isset($boardOptions['profile']))
	{
		$boardUpdates[] = 'id_profile = {int:profile}';
		$boardUpdateParameters['profile'] = (int) $boardOptions['profile'];
	}

	if (isset($boardOptions['redirect']))
	{
		$boardUpdates[] = 'redirect = {string:redirect}';
		$boardUpdateParameters['redirect'] = $boardOptions['redirect'];
	}

	if (isset($boardOptions['num_posts']))
	{
		$boardUpdates[] = 'num_posts = {int:num_posts}';
		$boardUpdateParameters['num_posts'] = (int) $boardOptions['num_posts'];
	}

	$id = $board_id;
	call_integration_hook('integrate_modify_board', array($id, &$boardUpdates, &$boardUpdateParameters));

	// Do the updates (if any).
	if (!empty($boardUpdates))
		$request = $db->query('', '
			UPDATE {db_prefix}boards
			SET
				' . implode(',
				', $boardUpdates) . '
			WHERE id_board = {int:selected_board}',
			array_merge($boardUpdateParameters, array(
				'selected_board' => $board_id,
			))
		);

	// Set moderators of this board.
	if (isset($boardOptions['moderators']) || isset($boardOptions['moderator_string']))
	{
		// Reset current moderators for this board - if there are any!
		$db->query('', '
			DELETE FROM {db_prefix}moderators
			WHERE id_board = {int:board_list}',
			array(
				'board_list' => $board_id,
			)
		);

		// Validate and get the IDs of the new moderators.
		if (isset($boardOptions['moderator_string']) && trim($boardOptions['moderator_string']) != '')
		{
			// Divvy out the usernames, remove extra space.
			$moderator_string = strtr(Util::htmlspecialchars($boardOptions['moderator_string'], ENT_QUOTES), array('&quot;' => '"'));
			preg_match_all('~"([^"]+)"~', $moderator_string, $matches);
			$moderators = array_merge($matches[1], explode(',', preg_replace('~"[^"]+"~', '', $moderator_string)));
			for ($k = 0, $n = count($moderators); $k < $n; $k++)
			{
				$moderators[$k] = trim($moderators[$k]);

				if (strlen($moderators[$k]) == 0)
					unset($moderators[$k]);
			}

			// Find all the id_member's for the member_name's in the list.
			if (empty($boardOptions['moderators']))
				$boardOptions['moderators'] = array();
			if (!empty($moderators))
			{
				$request = $db->query('', '
					SELECT id_member
					FROM {db_prefix}members
					WHERE member_name IN ({array_string:moderator_list}) OR real_name IN ({array_string:moderator_list})
					LIMIT ' . count($moderators),
					array(
						'moderator_list' => $moderators,
					)
				);
				while ($row = $db->fetch_assoc($request))
					$boardOptions['moderators'][] = $row['id_member'];
				$db->free_result($request);
			}
		}

		// Add the moderators to the board.
		if (!empty($boardOptions['moderators']))
		{
			$inserts = array();
			foreach ($boardOptions['moderators'] as $moderator)
				$inserts[] = array($board_id, $moderator);

			$db->insert('insert',
				'{db_prefix}moderators',
				array('id_board' => 'int', 'id_member' => 'int'),
				$inserts,
				array('id_board', 'id_member')
			);
		}

		// Note that caches can now be wrong!
		updateSettings(array('settings_updated' => time()));
	}

	if (isset($boardOptions['move_to']))
		reorderBoards();

	clean_cache('data');

	if (empty($boardOptions['dont_log']))
		logAction('edit_board', array('board' => $board_id), 'admin');
}

/**
 * Create a new board and set its properties and position.
 * Allows (almost) the same options as the modifyBoard() function.
 * With the option inherit_permissions set, the parent board permissions
 * will be inherited.
 *
 * @param array $boardOptions
 * @return int The new board id
 */
function createBoard($boardOptions)
{
	global $boards;

	$db = database();

	// Trigger an error if one of the required values is not set.
	if (!isset($boardOptions['board_name']) || trim($boardOptions['board_name']) == '' || !isset($boardOptions['move_to']) || !isset($boardOptions['target_category']))
		trigger_error('createBoard(): One or more of the required options is not set', E_USER_ERROR);

	if (in_array($boardOptions['move_to'], array('child', 'before', 'after')) && !isset($boardOptions['target_board']))
		trigger_error('createBoard(): Target board is not set', E_USER_ERROR);

	// Set every optional value to its default value.
	$boardOptions += array(
		'posts_count' => true,
		'override_theme' => false,
		'board_theme' => 0,
		'access_groups' => array(),
		'board_description' => '',
		'profile' => 1,
		'moderators' => '',
		'inherit_permissions' => true,
		'dont_log' => true,
	);
	$board_columns = array(
		'id_cat' => 'int', 'name' => 'string-255', 'description' => 'string', 'board_order' => 'int',
		'member_groups' => 'string', 'redirect' => 'string',
	);
	$board_parameters = array(
		$boardOptions['target_category'], $boardOptions['board_name'] , '', 0,
		'-1,0', '',
	);

	call_integration_hook('integrate_create_board', array(&$boardOptions, &$board_columns, &$board_parameters));

	// Insert a board, the settings are dealt with later.
	$db->insert('',
		'{db_prefix}boards',
		$board_columns,
		$board_parameters,
		array('id_board')
	);
	$board_id = $db->insert_id('{db_prefix}boards', 'id_board');

	if (empty($board_id))
		return 0;

	// Change the board according to the given specifications.
	modifyBoard($board_id, $boardOptions);

	// Do we want the parent permissions to be inherited?
	if ($boardOptions['inherit_permissions'])
	{
		getBoardTree();

		if (!empty($boards[$board_id]['parent']))
		{
			$board_data = fetchBoardsInfo(array('boards' => $boards[$board_id]['parent']), array('selects' => 'permissions'));

			$db->query('', '
				UPDATE {db_prefix}boards
				SET id_profile = {int:new_profile}
				WHERE id_board = {int:current_board}',
				array(
					'new_profile' => $board_data['id_profile'],
					'current_board' => $board_id,
				)
			);
		}
	}

	// Clean the data cache.
	clean_cache('data');

	// Created it.
	logAction('add_board', array('board' => $board_id), 'admin');

	// Here you are, a new board, ready to be spammed.
	return $board_id;
}

/**
 * Remove one or more boards.
 * Allows to move the children of the board before deleting it
 * if moveChildrenTo is set to null, the child boards will be deleted.
 * Deletes:
 *   - all topics that are on the given boards;
 *   - all information that's associated with the given boards;
 * updates the statistics to reflect the new situation.
 *
 * @param array $boards_to_remove
 * @param array $moveChildrenTo = null
 */
function deleteBoards($boards_to_remove, $moveChildrenTo = null)
{
	global $boards;

	$db = database();

	// No boards to delete? Return!
	if (empty($boards_to_remove))
		return;

	getBoardTree();

	call_integration_hook('integrate_delete_board', array($boards_to_remove, &$moveChildrenTo));

	// If $moveChildrenTo is set to null, include the children in the removal.
	if ($moveChildrenTo === null)
	{
		// Get a list of the child boards that will also be removed.
		$child_boards_to_remove = array();
		foreach ($boards_to_remove as $board_to_remove)
			recursiveBoards($child_boards_to_remove, $boards[$board_to_remove]['tree']);

		// Merge the children with their parents.
		if (!empty($child_boards_to_remove))
			$boards_to_remove = array_unique(array_merge($boards_to_remove, $child_boards_to_remove));
	}
	// Move the children to a safe home.
	else
	{
		foreach ($boards_to_remove as $id_board)
		{
			// @todo Separate category?
			if ($moveChildrenTo === 0)
				fixChildren($id_board, 0, 0);
			else
				fixChildren($id_board, $boards[$moveChildrenTo]['level'] + 1, $moveChildrenTo);
		}
	}

	// Delete ALL topics in the selected boards (done first so topics can't be marooned.)
	$request = $db->query('', '
		SELECT id_topic
		FROM {db_prefix}topics
		WHERE id_board IN ({array_int:boards_to_remove})',
		array(
			'boards_to_remove' => $boards_to_remove,
		)
	);
	$topics = array();
	while ($row = $db->fetch_assoc($request))
		$topics[] = $row['id_topic'];
	$db->free_result($request);

	require_once(SUBSDIR . '/Topic.subs.php');
	removeTopics($topics, false);

	// Delete the board's logs.
	$db->query('', '
		DELETE FROM {db_prefix}log_mark_read
		WHERE id_board IN ({array_int:boards_to_remove})',
		array(
			'boards_to_remove' => $boards_to_remove,
		)
	);
	$db->query('', '
		DELETE FROM {db_prefix}log_boards
		WHERE id_board IN ({array_int:boards_to_remove})',
		array(
			'boards_to_remove' => $boards_to_remove,
		)
	);
	$db->query('', '
		DELETE FROM {db_prefix}log_notify
		WHERE id_board IN ({array_int:boards_to_remove})',
		array(
			'boards_to_remove' => $boards_to_remove,
		)
	);

	// Delete this board's moderators.
	$db->query('', '
		DELETE FROM {db_prefix}moderators
		WHERE id_board IN ({array_int:boards_to_remove})',
		array(
			'boards_to_remove' => $boards_to_remove,
		)
	);

	// Delete any extra events in the calendar.
	$db->query('', '
		DELETE FROM {db_prefix}calendar
		WHERE id_board IN ({array_int:boards_to_remove})',
		array(
			'boards_to_remove' => $boards_to_remove,
		)
	);

	// Delete any message icons that only appear on these boards.
	$db->query('', '
		DELETE FROM {db_prefix}message_icons
		WHERE id_board IN ({array_int:boards_to_remove})',
		array(
			'boards_to_remove' => $boards_to_remove,
		)
	);

	// Delete the boards.
	$db->query('', '
		DELETE FROM {db_prefix}boards
		WHERE id_board IN ({array_int:boards_to_remove})',
		array(
			'boards_to_remove' => $boards_to_remove,
		)
	);

	// Latest message/topic might not be there anymore.
	updateStats('message');
	updateStats('topic');
	updateSettings(array(
		'calendar_updated' => time(),
	));

	// Plus reset the cache to stop people getting odd results.
	updateSettings(array('settings_updated' => time()));

	// Clean the cache as well.
	clean_cache('data');

	// Let's do some serious logging.
	foreach ($boards_to_remove as $id_board)
		logAction('delete_board', array('boardname' => $boards[$id_board]['name']), 'admin');

	reorderBoards();
}

/**
 * Put all boards in the right order and sorts the records of the boards table.
 * Used by modifyBoard(), deleteBoards(), modifyCategory(), and deleteCategories() functions
 */
function reorderBoards()
{
	global $cat_tree, $boardList, $boards;

	$db = database();

	getBoardTree();

	// Set the board order for each category.
	$board_order = 0;
	foreach ($cat_tree as $catID => $dummy)
	{
		foreach ($boardList[$catID] as $boardID)
			if ($boards[$boardID]['order'] != ++$board_order)
				$db->query('', '
					UPDATE {db_prefix}boards
					SET board_order = {int:new_order}
					WHERE id_board = {int:selected_board}',
					array(
						'new_order' => $board_order,
						'selected_board' => $boardID,
					)
				);
	}

	// Sort the records of the boards table on the board_order value.
	$db->query('alter_table_boards', '
		ALTER TABLE {db_prefix}boards
		ORDER BY board_order',
		array(
			'db_error_skip' => true,
		)
	);
}

/**
 * Fixes the children of a board by setting their child_levels to new values.
 * Used when a board is deleted or moved, to affect its children.
 *
 * @param int $parent
 * @param int $newLevel
 * @param int $newParent
 */
function fixChildren($parent, $newLevel, $newParent)
{
	$db = database();

	// Grab all children of $parent...
	$result = $db->query('', '
		SELECT id_board
		FROM {db_prefix}boards
		WHERE id_parent = {int:parent_board}',
		array(
			'parent_board' => $parent,
		)
	);
	$children = array();
	while ($row = $db->fetch_assoc($result))
		$children[] = $row['id_board'];
	$db->free_result($result);

	// ...and set it to a new parent and child_level.
	$db->query('', '
		UPDATE {db_prefix}boards
		SET id_parent = {int:new_parent}, child_level = {int:new_child_level}
		WHERE id_parent = {int:parent_board}',
		array(
			'new_parent' => $newParent,
			'new_child_level' => $newLevel,
			'parent_board' => $parent,
		)
	);

	// Recursively fix the children of the children.
	foreach ($children as $child)
		fixChildren($child, $newLevel + 1, $child);
}

/**
 * Load a lot of useful information regarding the boards and categories.
 * The information retrieved is stored in globals:
 *  $boards		properties of each board.
 *  $boardList	a list of boards grouped by category ID.
 *  $cat_tree	properties of each category.
 */
function getBoardTree()
{
	global $cat_tree, $boards, $boardList;

	$db = database();

	// Getting all the board and category information you'd ever wanted.
	$request = $db->query('', '
		SELECT
			IFNULL(b.id_board, 0) AS id_board, b.id_parent, b.name AS board_name, b.description, b.child_level,
			b.board_order, b.count_posts, b.member_groups, b.id_theme, b.override_theme, b.id_profile, b.redirect,
			b.num_posts, b.num_topics, b.deny_member_groups, c.id_cat, c.name AS cat_name, c.cat_order, c.can_collapse
		FROM {db_prefix}categories AS c
			LEFT JOIN {db_prefix}boards AS b ON (b.id_cat = c.id_cat)
		ORDER BY c.cat_order, b.child_level, b.board_order',
		array(
		)
	);
	$cat_tree = array();
	$boards = array();
	$last_board_order = 0;
	while ($row = $db->fetch_assoc($request))
	{
		if (!isset($cat_tree[$row['id_cat']]))
		{
			$cat_tree[$row['id_cat']] = array(
				'node' => array(
					'id' => $row['id_cat'],
					'name' => $row['cat_name'],
					'order' => $row['cat_order'],
					'can_collapse' => $row['can_collapse']
				),
				'is_first' => empty($cat_tree),
				'last_board_order' => $last_board_order,
				'children' => array()
			);
			$prevBoard = 0;
			$curLevel = 0;
		}

		if (!empty($row['id_board']))
		{
			if ($row['child_level'] != $curLevel)
				$prevBoard = 0;

			$boards[$row['id_board']] = array(
				'id' => $row['id_board'],
				'category' => $row['id_cat'],
				'parent' => $row['id_parent'],
				'level' => $row['child_level'],
				'order' => $row['board_order'],
				'name' => $row['board_name'],
				'member_groups' => explode(',', $row['member_groups']),
				'deny_groups' => explode(',', $row['deny_member_groups']),
				'description' => $row['description'],
				'count_posts' => empty($row['count_posts']),
				'posts' => $row['num_posts'],
				'topics' => $row['num_topics'],
				'theme' => $row['id_theme'],
				'override_theme' => $row['override_theme'],
				'profile' => $row['id_profile'],
				'redirect' => $row['redirect'],
				'prev_board' => $prevBoard
			);
			$prevBoard = $row['id_board'];
			$last_board_order = $row['board_order'];

			if (empty($row['child_level']))
			{
				$cat_tree[$row['id_cat']]['children'][$row['id_board']] = array(
					'node' => &$boards[$row['id_board']],
					'is_first' => empty($cat_tree[$row['id_cat']]['children']),
					'children' => array()
				);
				$boards[$row['id_board']]['tree'] = &$cat_tree[$row['id_cat']]['children'][$row['id_board']];
			}
			else
			{
				// Parent doesn't exist!
				if (!isset($boards[$row['id_parent']]['tree']))
					fatal_lang_error('no_valid_parent', false, array($row['board_name']));

				// Wrong childlevel...we can silently fix this...
				if ($boards[$row['id_parent']]['tree']['node']['level'] != $row['child_level'] - 1)
					$db->query('', '
						UPDATE {db_prefix}boards
						SET child_level = {int:new_child_level}
						WHERE id_board = {int:selected_board}',
						array(
							'new_child_level' => $boards[$row['id_parent']]['tree']['node']['level'] + 1,
							'selected_board' => $row['id_board'],
						)
					);

				$boards[$row['id_parent']]['tree']['children'][$row['id_board']] = array(
					'node' => &$boards[$row['id_board']],
					'is_first' => empty($boards[$row['id_parent']]['tree']['children']),
					'children' => array()
				);
				$boards[$row['id_board']]['tree'] = &$boards[$row['id_parent']]['tree']['children'][$row['id_board']];
			}
		}
	}
	$db->free_result($request);

	// Get a list of all the boards in each category (using recursion).
	$boardList = array();
	foreach ($cat_tree as $catID => $node)
	{
		$boardList[$catID] = array();
		recursiveBoards($boardList[$catID], $node);
	}
}

/**
 * Generates the query to determine the list of available boards for a user
 * Executes the query and returns the list
 *
 * @param array $boardListOptions
 * @param boolean $simple if true a simple array is returned containing some basic
 *                informations regarding the board (id_board, board_name, child_level, id_cat, cat_name)
 *                if false the boards are returned in an array subdivided by categories including also
 *                additional data like the number of boards
 * @return array
 */
function getBoardList($boardListOptions = array(), $simple = false)
{
	global $user_info;

	$db = database();

	if ((isset($boardListOptions['excluded_boards']) || isset($boardListOptions['allowed_to'])) && isset($boardListOptions['included_boards']))
		trigger_error('getBoardList(): Setting both excluded_boards and included_boards is not allowed.', E_USER_ERROR);

	$where = array();
	$select = '';
	$where_parameters = array();
	if (isset($boardListOptions['excluded_boards']))
	{
		$where[] = 'b.id_board NOT IN ({array_int:excluded_boards})';
		$where_parameters['excluded_boards'] = $boardListOptions['excluded_boards'];
	}

	if (isset($boardListOptions['allowed_to']))
	{
		$boardListOptions['included_boards'] = boardsAllowedTo($boardListOptions['allowed_to']);
		if (in_array(0, $boardListOptions['included_boards']))
			unset($boardListOptions['included_boards']);
	}
	if (isset($boardListOptions['included_boards']))
	{
		$where[] = 'b.id_board IN ({array_int:included_boards})';
		$where_parameters['included_boards'] = $boardListOptions['included_boards'];
	}

	if (isset($boardListOptions['access']))
	{
		$select .= ',
			FIND_IN_SET({string:current_group}, b.member_groups) != 0 AS can_access,
			FIND_IN_SET({string:current_group}, b.deny_member_groups) != 0 AS cannot_access';
		$where_parameters['current_group'] = $boardListOptions['access'];
	}

	if (isset($boardListOptions['ignore']))
	{
		$select .= ',' . (!empty($boardListOptions['ignore']) ? 'b.id_board IN ({array_int:ignore_boards})' : '0') . ' AS is_ignored';
		$where_parameters['included_boards'] = $boardListOptions['ignore'];
	}

	if (!empty($boardListOptions['ignore_boards']))
		$where[] = '{query_wanna_see_board}';

	elseif (!empty($boardListOptions['use_permissions']))
		$where[] = '{query_see_board}';

	if (!empty($boardListOptions['not_redirection']))
	{
		$where[] = 'b.redirect = {string:blank_redirect}';
		$where_parameters['blank_redirect'] = '';
	}

	$request = $db->query('messageindex_fetch_boards', '
		SELECT c.name AS cat_name, c.id_cat, b.id_board, b.name AS board_name, b.child_level' . $select . '
		FROM {db_prefix}boards AS b
			LEFT JOIN {db_prefix}categories AS c ON (c.id_cat = b.id_cat)' . (empty($where) ? '' : '
		WHERE ' . implode('
			AND ', $where)),
		$where_parameters
	);

	if ($simple)
	{
		$return_value = array();
		while ($row = $db->fetch_assoc($request))
		{
			$return_value[$row['id_board']] = array(
				'id_cat' => $row['id_cat'],
				'cat_name' => $row['cat_name'],
				'id_board' => $row['id_board'],
				'board_name' => $row['board_name'],
				'child_level' => $row['child_level'],
			);
		}
	}
	else
	{
		$return_value = array(
			'num_boards' => $db->num_rows($request),
			'boards_check_all' => true,
			'boards_current_disabled' => true,
			'categories' => array(),
		);
		while ($row = $db->fetch_assoc($request))
		{
			// This category hasn't been set up yet..
			if (!isset($return_value['categories'][$row['id_cat']]))
				$return_value['categories'][$row['id_cat']] = array(
					'id' => $row['id_cat'],
					'name' => $row['cat_name'],
					'boards' => array(),
				);

			$return_value['categories'][$row['id_cat']]['boards'][$row['id_board']] = array(
				'id' => $row['id_board'],
				'name' => $row['board_name'],
				'child_level' => $row['child_level'],
				'allow' => false,
				'deny' => false,
				'selected' => isset($boardListOptions['selected_board']) && $boardListOptions['selected_board'] == $row['id_board'],
			);

			// Do we want access informations?
			if (!empty($boardListOptions['access']))
				$return_value['categories'][$row['id_cat']]['boards'][$row['id_board']] += array(
					'allow' => !(empty($row['can_access']) || $row['can_access'] == 'f'),
					'deny' => !(empty($row['cannot_access']) || $row['cannot_access'] == 'f'),
				);

			// If is_ignored is set, it means we could have to deselect a board
			if (isset($row['is_ignored']))
			{
				$return_value['categories'][$row['id_cat']]['boards'][$row['id_board']]['selected'] = $row['is_ignored'];

				// If a board wasn't checked that probably should have been ensure the board selection is selected, yo!
				if (!empty($return_value['categories'][$row['id_cat']]['boards'][$row['id_board']]['selected']) && (empty($modSettings['recycle_enable']) || $row['id_board'] != $modSettings['recycle_board']))
					$return_value['boards_check_all'] = false;
			}
		}
	}

	$db->free_result($request);

	return $return_value;
}

/**
 * Recursively get a list of boards.
 * Used by getBoardTree
 *
 * @param array &$_boardList
 * @param array &$_tree
 */
function recursiveBoards(&$_boardList, &$_tree)
{
	if (empty($_tree['children']))
		return;

	foreach ($_tree['children'] as $id => $node)
	{
		$_boardList[] = $id;
		recursiveBoards($_boardList, $node);
	}
}

/**
 * Returns whether the child board id is actually a child of the parent (recursive).
 * @param int $child
 * @param int $parent
 * @return boolean
 */
function isChildOf($child, $parent)
{
	global $boards;

	if (empty($boards[$child]['parent']))
		return false;

	if ($boards[$child]['parent'] == $parent)
		return true;

	return isChildOf($boards[$child]['parent'], $parent);
}

/**
 * Returns whether this member has notification turned on for the specified board.
 *
 * @param int $id_member
 * @param int $id_board
 * @return bool
 */
function hasBoardNotification($id_member, $id_board)
{
	$db = database();

	// Find out if they have notification set for this board already.
	$request = $db->query('', '
		SELECT id_member
		FROM {db_prefix}log_notify
		WHERE id_member = {int:current_member}
			AND id_board = {int:current_board}
		LIMIT 1',
		array(
			'current_board' => $id_board,
			'current_member' => $id_member,
		)
	);
	$hasNotification = $db->num_rows($request) != 0;
	$db->free_result($request);

	return $hasNotification;
}

/**
 * Set board notification on or off for the given member.
 *
 * @param int $id_member
 * @param int $id_board
 * @param bool $on = false
 */
function setBoardNotification($id_member, $id_board, $on = false)
{
	$db = database();

	if ($on)
	{
		// Turn notification on.  (note this just blows smoke if it's already on.)
		$db->insert('ignore',
			'{db_prefix}log_notify',
			array('id_member' => 'int', 'id_board' => 'int'),
			array($id_member, $id_board),
			array('id_member', 'id_board')
		);
	}
	else
	{
		// Turn notification off for this board.
		$db->query('', '
			DELETE FROM {db_prefix}log_notify
			WHERE id_member = {int:current_member}
				AND id_board = {int:current_board}',
			array(
				'current_board' => $id_board,
				'current_member' => $id_member,
			)
		);
	}
}

/**
 * Reset sent status for board notifications.
 *
 * @param int $id_member
 * @param int $id_board
 * @param bool $check = true check if the user has notifications enabled for the board
 *
 * @return bool if the board was marked for notifications
 */
function resetSentBoardNotification($id_member, $id_board, $check = true)
{
	// This function returns a boolean equivalent with hasBoardNotification().
	// This is unexpected, but it's done this way to avoid any extra-query is executed on MessageIndex::action_messageindex().
	// Just ignore the return value for normal use.

	$db = database();

	// Check if notifications are enabled for this user on the board?
	if ($check)
	{
		// check if the member has notifications enabled for this board
		$request = $db->query('', '
			SELECT sent
			FROM {db_prefix}log_notify
			WHERE id_board = {int:current_board}
				AND id_member = {int:current_member}
			LIMIT 1',
			array(
				'current_board' => $id_board,
				'current_member' => $id_member,
			)
		);
		if ($db->num_rows($request) == 0)
			// nothing to do
			return false;
		$sent = $db->fetch_row($request);
		$db->free_result($request);
		if (empty($sent))
			// not sent already? No need to stay around then
			return true;
	}

	// Reset 'sent' status.
	$db->query('', '
		UPDATE {db_prefix}log_notify
		SET sent = {int:is_sent}
		WHERE id_board = {int:current_board}
			AND id_member = {int:current_member}',
		array(
			'current_board' => $id_board,
			'current_member' => $id_member,
			'is_sent' => 0,
		)
	);
	return true;
}

/**
 * Returns all the boards accessible to the current user.
 * If $id_parents is given, return only the child boards of those boards.
 *
 * @param @id_parents
 */
function accessibleBoards($id_parents = null)
{
	$db = database();

	$boards = array();
	if (empty($id_parents))
	{
		// Find all the boards this user can see.
		$request = $db->query('', '
			SELECT b.id_board
			FROM {db_prefix}boards AS b
			WHERE {query_see_board}',
			array(
			)
		);
	}
	else
	{
		// Find all boards down from $id_parent
		$request = $db->query('', '
			SELECT b.id_board
			FROM {db_prefix}boards AS b
			WHERE b.id_parent IN ({array_int:parent_list})
				AND {query_see_board}',
			array(
				'parent_list' => $id_parents,
			)
		);
	}

	while ($row = $db->fetch_assoc($request))
		$boards[] = $row['id_board'];
	$db->free_result($request);

	return $boards;
}

/**
 * Returns the post count and name of a board
 *  - if supplied a topic id will also return the message subject
 *  - honors query_see_board to ensure a user can see the information
 *
 * @param type $board_id
 * @param type $topic_id
 */
function boardInfo($board_id, $topic_id = null)
{
	$db = database();

	if (!empty($topic_id))
	{
		$request = $db->query('', '
			SELECT b.count_posts, b.name, m.subject
			FROM {db_prefix}boards AS b
				INNER JOIN {db_prefix}topics AS t ON (t.id_topic = {int:current_topic})
				INNER JOIN {db_prefix}messages AS m ON (m.id_msg = t.id_first_msg)
			WHERE {query_see_board}
				AND b.id_board = {int:board}
				AND b.redirect = {string:blank_redirect}
			LIMIT 1',
			array(
				'current_topic' => $topic_id,
				'board' => $board_id,
				'blank_redirect' => '',
			)
		);
	}
	else
	{
		$request = $db->query('', '
			SELECT b.count_posts, b.name
			FROM {db_prefix}boards AS b
			WHERE {query_see_board}
				AND b.id_board = {int:board}
				AND b.redirect = {string:blank_redirect}
			LIMIT 1',
			array(
				'board' => $board_id,
				'blank_redirect' => '',
			)
		);
	}

	$returns = $db->fetch_assoc($request);
	$db->free_result($request);

	return $returns;
}

/**
 * Loads properties from non-standard groups
 *
 * @param int $curBoard
 * @return array
 */
function getOtherGroups($curBoard)
{
	$db = database();

	$groups = array();

	// Load membergroups.
	$request = $db->query('', '
		SELECT group_name, id_group, min_posts
		FROM {db_prefix}membergroups
		WHERE id_group > {int:moderator_group} OR id_group = {int:global_moderator}
		ORDER BY min_posts, id_group != {int:global_moderator}, group_name',
		array(
			'moderator_group' => 3,
			'global_moderator' => 2,
		)
	);
	while ($row = $db->fetch_assoc($request))
	{
		if ($_REQUEST['sa'] == 'newboard' && $row['min_posts'] == -1)
			$curBoard['member_groups'][] = $row['id_group'];

		$groups[(int) $row['id_group']] = array(
			'id' => $row['id_group'],
			'name' => trim($row['group_name']),
			'allow' => in_array($row['id_group'], $curBoard['member_groups']),
			'deny' => in_array($row['id_group'], $curBoard['deny_groups']),
			'is_post_group' => $row['min_posts'] != -1,
		);
		}
	$db->free_result($request);

	return $groups;
}

/**
 * Get a list of moderators from a specific board
 * @param int $idboard
 * @return array
 */
function getBoardModerators($idboard)
{
	$db = database();

	$moderators = array();

	$request = $db->query('', '
		SELECT mem.id_member, mem.real_name
		FROM {db_prefix}moderators AS mods
			INNER JOIN {db_prefix}members AS mem ON (mem.id_member = mods.id_member)
		WHERE mods.id_board = {int:current_board}',
		array(
			'current_board' => $idboard,
		)
	);

	while ($row = $db->fetch_assoc($request))
		$moderators[$row['id_member']] = $row['real_name'];
	$db->free_result($request);

	return $moderators;
}

/**
 * Get all available themes
 * @return array
 */
function getAllThemes()
{
	$db = database();

	$themes = array();

	// Get all the themes...
	$request = $db->query('', '
		SELECT id_theme AS id, value AS name
		FROM {db_prefix}themes
		WHERE variable = {string:name}',
		array(
			'name' => 'name',
		)
	);

	while ($row = $db->fetch_assoc($request))
		$themes[] = $row;
	$db->free_result($request);

	return $themes;
}

/**
 * Gets redirect infos and post count from a selected board.
 * @param int $idboard
 * @return array
 */
function getBoardProperties($idboard)
{
	$db = database();

	$properties = array();

	$request = $db->query('', '
		SELECT redirect, num_posts
		FROM {db_prefix}boards
		WHERE id_board = {int:current_board}',
		array(
			'current_board' => $idboard,
		)
	);
	list ($properties['oldRedirect'], $properties['numPosts']) = $db->fetch_row($request);
	$db->free_result($request);

	return $properties;
}

/**
 * Fetch the number of posts in an array of boards based on board IDs or category IDs
 * @param array $boards an array of board IDs
 * @param array $categories an array of category IDs
 * @param bool $wanna_see_board if true uses {query_wanna_see_board}, otherwise {query_see_board}
 */
function boardsPosts($boards, $categories, $wanna_see_board = false)
{
	$db = database();

	$clauses = array();
	$clauseParameters = array();
	if (!empty($categories))
	{
		$clauses[] = 'id_cat IN ({array_int:category_list})';
		$clauseParameters['category_list'] = $categories;
	}
	if (!empty($boards))
	{
		$clauses[] = 'id_board IN ({array_int:board_list})';
		$clauseParameters['board_list'] = $boards;
	}

	if (empty($clauses))
		return array();

	$request = $db->query('', '
		SELECT b.id_board, b.num_posts
		FROM {db_prefix}boards AS b
		WHERE ' . ($wanna_see_board ? '{query_wanna_see_board}' : '{query_see_board}') . '
			AND b.' . implode(' OR b.', $clauses),
		array_merge($clauseParameters, array(
		))
	);
	$return = array();
	while ($row = $db->fetch_assoc($request))
		$return[$row['id_board']] = $row['num_posts'];
	$db->free_result($request);

	return $return;
}

/**
 * Returns information of a set of boards based on board IDs or category IDs
 *
 * @param mixed $conditions is an associative array that holds the board or the cat IDs
 *              'categories' => an array of category IDs (it accepts a single ID too)
 *              'boards' => an array of board IDs (it accepts a single ID too)
 *              if conditions is set to 'all' (not an array) all the boards are queried
 * @param array $params is an optional array that allows to control the results returned:
 *              'sort_by' => (string) defines the sorting of the results (allowed: id_board, name)
 *              'count' => (bool) the number of boards found is returned
 *              'selects' => (string) determines what informations are retrieved and returned
 *                           Allowed values: 'name', 'posts', 'detailed', 'permissions';
 *                           default: 'name';
 *                           see the function for detailes on the fields associated to each value
 *              'wanna_see_board' => (bool) if true uses {query_wanna_see_board}, otherwise {query_see_board}
 *              'exclude_recycle' => (bool) recycle board is not included (default false)
 *              'exclude_redirects' => (bool) redirects are not included (default false)
 *
 * @todo unify the two queries?
 */
function fetchBoardsInfo($conditions, $params = array())
{
	global $modSettings;

	$db = database();

	$clauses = array();
	$clauseParameters = array();
	$allowed_sort = array(
		'id_board',
		'name'
	);

	if (!empty($params['sort_by']) && in_array($params['sort_by'], $allowed_sort))
		$sort_by = 'ORDER BY ' . $params['sort_by'];
	else
		$sort_by = '';

	if (!is_array($conditions) && $conditions == 'all')
	{
		// id_board, name, id_profile => used in admin/Reports.php
		$request = $db->query('', '
			SELECT ' . (!empty($params['count']) ? 'COUNT(*)' : 'id_board, name, id_profile') . '
			FROM {db_prefix}boards',
			array()
		);
	}
	else
	{
		if (!empty($conditions['categories']))
		{
			$clauses[] = 'id_cat IN ({array_int:category_list})';
			$clauseParameters['category_list'] = is_array($conditions['categories']) ? $conditions['categories'] : array($conditions['categories']);
		}
		if (!empty($conditions['boards']))
		{
			$clauses[] = 'id_board IN ({array_int:board_list})';
			$clauseParameters['board_list'] = is_array($conditions['boards']) ? $conditions['boards'] : array($conditions['boards']);
		}

		// @todo: memos for optimization
		/*
			id_board    => MergeTopic + MergeTopic + MessageIndex + Search + ScheduledTasks
			name        => MergeTopic + ScheduledTasks + News
			count_posts => MessageIndex
			num_posts   => News
		*/
		$known_selects = array(
			'name' => 'b.id_board, b.name',
			'posts' => 'b.id_board, b.count_posts, b.num_posts',
			'detailed' => 'b.id_board, b.name, b.count_posts, b.num_posts',
			'permissions' => 'b.member_groups, b.id_profile',
		);
		if (!empty($params['count']))
			$select = 'COUNT(*)';
		else
			$select = $known_selects[empty($params['selects']) || !isset($known_selects[$params['selects']]) ? 'name' : $params['selects']];

		$request = $db->query('', '
			SELECT ' . $select . '
			FROM {db_prefix}boards AS b
			WHERE ' . (!empty($params['wanna_see_board']) ? '{query_wanna_see_board}' : '{query_see_board}') . (!empty($clauses) ? '
				AND b.' . implode(' OR b.', $clauses) : '') . (!empty($params['exclude_recycle']) ? '
				AND b.id_board != {int:recycle_board}' : '') . (!empty($params['exclude_redirects']) ? '
				AND b.redirect = {string:empty_string}' : ''),
			array_merge($clauseParameters, array(
				'recycle_board' => !empty($modSettings['recycle_board']) ? $modSettings['recycle_board'] : 0,
				'empty_string' => '',
			))
		);
	}

	if (!empty($params['count']))
	{
		list($return) = $db->fetch_row($request);
	}
	else
	{
		$return = array();
		while ($row = $db->fetch_assoc($request))
			$return[$row['id_board']] = $row;
	}
	$db->free_result($request);

	return $return;
}

/**
 * Retrieve the all the child boards of an array of boards
 * and add the ids to the same array
 * @param mixed $boards an array of board IDs (it accepts a single board too
 *              The param is passed by ref and the result it returned through the param itself
 */
function addChildBoards(&$boards)
{
	$db = database();

	if (!is_array($boards))
		$boards = array($boards);

	$request = $db->query('', '
		SELECT b.id_board, b.id_parent
		FROM {db_prefix}boards AS b
		WHERE {query_see_board}
			AND b.child_level > {int:no_parents}
			AND b.id_board NOT IN ({array_int:board_list})
		ORDER BY child_level ASC
		',
		array(
			'no_parents' => 0,
			'board_list' => $boards,
		)
	);
	while ($row = $db->fetch_assoc($request))
		if (in_array($row['id_parent'], $boards))
			$boards[] = $row['id_board'];
	$db->free_result($request);
}

/**
 * Increment a board stat field, for example num_posts.
 *
 * @param int $board
 * @param string $stat
 */
function incrementBoard($board, $stat)
{
	// @todo refactor it as increment any table perhaps
	// or update any board fields

	$db = database();

	$db->query('', '
		UPDATE {db_prefix}boards
		SET ' . $stat . ' = ' . $stat . ' + 1
		WHERE id_board = {int:board}',
		array(
			'board' => $board,
		)
	);
}
