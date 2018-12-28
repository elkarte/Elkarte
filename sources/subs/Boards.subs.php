<?php

/**
 * This file is mainly concerned with minor tasks relating to boards, such as
 * marking them read, collapsing categories, or quick moderation.
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

/**
 * Mark a board or multiple boards read.
 *
 * @package Boards
 * @param int[]|int $boards
 * @param bool $unread = false
 * @param bool $resetTopics = false
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
	// I'm adding a bool to control the processing of log_topics. We might want to just dissociate it from boards,
	// and call the log_topics clear-up only from the controller that needs it..

	// Notes (for read/unread rework)
	// MessageIndex::action_messageindex() does not update log_topics at all (only the above).
	// Display controller needed only to update log_boards.

	if ($resetTopics)
	{
		// Update log_mark_read and log_boards.
		// @todo check this condition <= I think I did, but better double check
		if (!$unread && !empty($markRead))
			$db->insert('replace',
				'{db_prefix}log_mark_read',
				array('id_msg' => 'int', 'id_member' => 'int', 'id_board' => 'int'),
				$markRead,
				array('id_board', 'id_member')
			);

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
		$delete_topics = array();
		$update_topics = array();
		$db->fetchQuery('
			SELECT lt.id_topic, lt.unwatched
			FROM {db_prefix}log_topics AS lt
				INNER JOIN {db_prefix}topics AS t /*!40000 USE INDEX (PRIMARY) */ ON (t.id_topic = lt.id_topic
					AND t.id_board IN ({array_int:board_list}))
			WHERE lt.id_member = {int:current_member}
				AND lt.id_topic >= {int:lowest_topic}',
			array(
				'current_member' => $user_info['id'],
				'board_list' => $boards,
				'lowest_topic' => $lowest_topic,
			)
		)->fetch_callback(
			function ($row) use (&$delete_topics, &$update_topics, $user_info, $modSettings)
			{
				if (!empty($row['unwatched']))
					$update_topics[] = array(
						$user_info['id'],
						$modSettings['maxMsgID'],
						$row['id_topic'],
						1,
					);
				else
					$delete_topics[] = $row['id_topic'];
			}
		);

		if (!empty($update_topics))
			$db->insert('replace',
				'{db_prefix}log_topics',
				array(
					'id_member' => 'int',
					'id_msg' => 'int',
					'id_topic' => 'int',
					'unwatched' => 'int'
				),
				$update_topics,
				array('id_topic', 'id_member')
			);

		if (!empty($delete_topics))
			$db->query('', '
				DELETE FROM {db_prefix}log_topics
				WHERE id_member = {int:current_member}
					AND id_topic IN ({array_int:topic_list})',
				array(
					'current_member' => $user_info['id'],
					'topic_list' => $delete_topics,
				)
			);
	}
}

/**
 * Get the id_member associated with the specified message ID.
 *
 * @package Boards
 * @param int $messageID message ID
 * @return int the member id
 */
function getMsgMemberID($messageID)
{
	require_once(SUBSDIR . '/Messages.subs.php');
	$message_info = basicMessageInfo((int) $messageID, true);

	return empty($message_info['id_member']) ? 0 : (int) $message_info['id_member'];
}

/**
 * Modify the settings and position of a board.
 *
 * - Used by ManageBoards.controller.php to change the settings of a board.
 *
 * @package Boards
 *
 * @param int     $board_id
 * @param mixed[] $boardOptions
 *
 * @throws \ElkArte\Exceptions\Exception no_board
 */
function modifyBoard($board_id, &$boardOptions)
{
	$db = database();

	// Get some basic information about all boards and categories.
	$boardTree = new \ElkArte\BoardsTree($db);
	$cat_tree = $boardTree->getCategories();
	$boards = $boardTree->getBoards();

	// Make sure given boards and categories exist.
	if (!isset($boards[$board_id]))
	{
		throw new \ElkArte\Exceptions\Exception('no_board');
	}
	if(isset($boardOptions['target_board']) && !isset($boards[$boardOptions['target_board']]))
	{
		throw new \ElkArte\Exceptions\Exception('no_board');
	}
	if (isset($boardOptions['target_category']) && !isset($cat_tree[$boardOptions['target_category']]))
	{
		throw new \ElkArte\Exceptions\Exception('no_board');
	}

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
			{
				$after = max($after, $boards[$id_board]['order']);
			}
		}

		// Make the board a child of a given board.
		elseif ($boardOptions['move_to'] == 'child')
		{
			$id_cat = $boards[$boardOptions['target_board']]['category'];
			$child_level = $boards[$boardOptions['target_board']]['level'] + 1;
			$id_parent = $boardOptions['target_board'];

			// People can be creative, in many ways...
			if ($boardTree->isChildOf($id_parent, $board_id))
			{
				throw new \ElkArte\Exceptions\Exception('mboards_parent_own_child_error', false);
			}
			elseif ($id_parent == $board_id)
			{
				throw new \ElkArte\Exceptions\Exception('mboards_board_own_child_error', false);
			}

			$after = $boards[$boardOptions['target_board']]['order'];

			// Check if there are already children and (if so) get the max board order.
			if (!empty($boards[$id_parent]['tree']['children']) && empty($boardOptions['move_first_child']))
			{
				foreach ($boards[$id_parent]['tree']['children'] as $childBoard_id => $dummy)
				{
					$after = max($after, $boards[$childBoard_id]['order']);
				}
			}
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
		{
			trigger_error('modifyBoard(): The move_to value \'' . $boardOptions['move_to'] . '\' is incorrect', E_USER_ERROR);
		}

		// Get a list of children of this board.
		$childList = $boardTree->allChildsOf($board_id);

		// See if there are changes that affect children.
		$childUpdates = array();
		$levelDiff = $child_level - $boards[$board_id]['level'];
		if ($levelDiff != 0)
		{
			$childUpdates[] = 'child_level = child_level ' . ($levelDiff > 0 ? '+ ' : '') . '{int:level_diff}';
		}
		if ($id_cat != $boards[$board_id]['category'])
		{
			$childUpdates[] = 'id_cat = {int:category}';
		}

		// Fix the children of this board.
		if (!empty($childList) && !empty($childUpdates))
		{
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
		}

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

	call_integration_hook('integrate_modify_board', array($board_id, $boardOptions, &$boardUpdates, &$boardUpdateParameters));

	// Do the updates (if any).
	if (!empty($boardUpdates))
	{
		$db->query('', '
			UPDATE {db_prefix}boards
			SET
				' . implode(',
				', $boardUpdates) . '
			WHERE id_board = {int:selected_board}',
			array_merge($boardUpdateParameters, array(
				'selected_board' => $board_id,
			))
		);
	}

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
			$moderator_string = strtr(\ElkArte\Util::htmlspecialchars($boardOptions['moderator_string'], ENT_QUOTES), array('&quot;' => '"'));
			preg_match_all('~"([^"]+)"~', $moderator_string, $matches);
			$moderators = array_merge($matches[1], explode(',', preg_replace('~"[^"]+"~', '', $moderator_string)));
			for ($k = 0, $n = count($moderators); $k < $n; $k++)
			{
				$moderators[$k] = trim($moderators[$k]);

				if (strlen($moderators[$k]) == 0)
				{
					unset($moderators[$k]);
				}
			}

			// Find all the id_member's for the member_name's in the list.
			if (empty($boardOptions['moderators']))
			{
				$boardOptions['moderators'] = array();
			}
			if (!empty($moderators))
			{
				$boardOptions['moderators'] = $db->fetchQuery('
					SELECT id_member
					FROM {db_prefix}members
					WHERE member_name IN ({array_string:moderator_list}) OR real_name IN ({array_string:moderator_list})
					LIMIT ' . count($moderators),
					array(
						'moderator_list' => $moderators,
					)
				)->fetch_callback(
					function ($row)
					{
						return $row['id_member'];
					}
				);
			}
		}

		// Add the moderators to the board.
		if (!empty($boardOptions['moderators']))
		{
			$inserts = array();
			foreach ($boardOptions['moderators'] as $moderator)
			{
				$inserts[] = array($board_id, $moderator);
			}

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
	{
		$boardTree->reorderBoards();
	}

	\ElkArte\Cache\Cache::instance()->clean('data');

	if (empty($boardOptions['dont_log']))
	{
		logAction('edit_board', array('board' => $board_id), 'admin');
	}
}

/**
 * Create a new board and set its properties and position.
 *
 * - Allows (almost) the same options as the modifyBoard() function.
 * - With the option inherit_permissions set, the parent board permissions
 * will be inherited.
 *
 * @package Boards
 * @param mixed[] $boardOptions
 * @return int The new board id
 * @throws \ElkArte\Exceptions\Exception
 */
function createBoard($boardOptions)
{
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
		$boardOptions['target_category'], $boardOptions['board_name'], '', 0,
		'-1,0', '',
	);

	// Insert a board, the settings are dealt with later.
	$result = $db->insert('',
		'{db_prefix}boards',
		$board_columns,
		$board_parameters,
		array('id_board')
	);
	$board_id = $result->insert_id();

	if (empty($board_id))
	{
		return 0;
	}

	// Change the board according to the given specifications.
	modifyBoard($board_id, $boardOptions);

	// Do we want the parent permissions to be inherited?
	if ($boardOptions['inherit_permissions'])
	{
		$boardTree = new \ElkArte\BoardsTree($db);

		try
		{
			$board = $boardTree->getBoardById($board_id);
			$board_data = fetchBoardsInfo(array('boards' => $board['parent']), array('selects' => 'permissions'));

			$db->query('', '
				UPDATE {db_prefix}boards
				SET id_profile = {int:new_profile}
				WHERE id_board = {int:current_board}',
				array(
					'new_profile' => $board_data[$board['parent']]['id_profile'],
					'current_board' => $board_id,
				)
			);
		}
		catch (\Exception $e)
		{
		}
	}

	// Clean the data cache.
	\ElkArte\Cache\Cache::instance()->clean('data');

	// Created it.
	logAction('add_board', array('board' => $board_id), 'admin');

	// Here you are, a new board, ready to be spammed.
	return $board_id;
}

/**
 * Generates the query to determine the list of available boards for a user
 *
 * - Executes the query and returns the list
 *
 * @package Boards
 * @param mixed[] $boardListOptions
 * @param boolean $simple if true a simple array is returned containing some basic
 *                information regarding the board (id_board, board_name, child_level, id_cat, cat_name)
 *                if false the boards are returned in an array subdivided by categories including also
 *                additional data like the number of boards
 * @return array An array of boards sorted according to the normal boards order
 */
function getBoardList($boardListOptions = array(), $simple = false)
{
	global $modSettings;

	$db = database();

	if ((isset($boardListOptions['excluded_boards']) || isset($boardListOptions['allowed_to'])) && isset($boardListOptions['included_boards']))
		trigger_error('getBoardList(): Setting both excluded_boards and included_boards is not allowed.', E_USER_ERROR);

	$where = array();
	$join = array();
	$select = '';
	$where_parameters = array();

	// Any boards to exclude
	if (isset($boardListOptions['excluded_boards']))
	{
		$where[] = 'b.id_board NOT IN ({array_int:excluded_boards})';
		$where_parameters['excluded_boards'] = $boardListOptions['excluded_boards'];
	}

	// Get list of boards to which they have specific permissions
	if (isset($boardListOptions['allowed_to']))
	{
		$boardListOptions['included_boards'] = boardsAllowedTo($boardListOptions['allowed_to']);
		if (in_array(0, $boardListOptions['included_boards']))
			unset($boardListOptions['included_boards']);
	}

	// Just want to include certain boards in the query
	if (isset($boardListOptions['included_boards']))
	{
		$where[] = 'b.id_board IN ({array_int:included_boards})';
		$where_parameters['included_boards'] = $boardListOptions['included_boards'];
	}

	// Determine if they can access a given board and return yea or nay in the results array
	if (isset($boardListOptions['access']))
	{
		$select .= ',
			FIND_IN_SET({string:current_group}, b.member_groups) != 0 AS can_access,
			FIND_IN_SET({string:current_group}, b.deny_member_groups) != 0 AS cannot_access';
		$where_parameters['current_group'] = $boardListOptions['access'];
	}

	// Leave out the boards that the user may be ignoring
	if (isset($boardListOptions['ignore']))
	{
		$select .= ',' . (!empty($boardListOptions['ignore']) ? 'b.id_board IN ({array_int:ignore_boards})' : '0') . ' AS is_ignored';
		$where_parameters['ignore_boards'] = $boardListOptions['ignore'];
	}

	// Want to check if the member is a moderators for any boards
	if (isset($boardListOptions['moderator']))
	{
		$join[] = '
			LEFT JOIN {db_prefix}moderators AS mods ON (mods.id_board = b.id_board AND mods.id_member = {int:current_member})';
		$select .= ', b.id_profile, b.member_groups, COALESCE(mods.id_member, 0) AS is_mod';
		$where_parameters['current_member'] = $boardListOptions['moderator'];
	}

	if (!empty($boardListOptions['ignore_boards']) && empty($boardListOptions['override_permissions']))
		$where[] = '{query_wanna_see_board}';

	elseif (empty($boardListOptions['override_permissions']))
		$where[] = '{query_see_board}';

	if (!empty($boardListOptions['not_redirection']))
	{
		$where[] = 'b.redirect = {string:blank_redirect}';
		$where_parameters['blank_redirect'] = '';
	}

	// Bring all the options together and make the query
	$request = $db->query('', '
		SELECT c.name AS cat_name, c.id_cat, b.id_board, b.name AS board_name, b.child_level' . $select . '
		FROM {db_prefix}boards AS b
			LEFT JOIN {db_prefix}categories AS c ON (c.id_cat = b.id_cat)' . (empty($join) ? '' : implode(' ', $join)) . (empty($where) ? '' : '
		WHERE ' . implode('
			AND ', $where)) . '
		ORDER BY c.cat_order, b.board_order',
		$where_parameters
	);

	// Build our output arrays, simple or complete
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

			// Do we want access information?
			if (isset($boardListOptions['access']) && $boardListOptions['access'] !== false)
			{
				$return_value[$row['id_board']]['allow'] = !(empty($row['can_access']) || $row['can_access'] == 'f');
				$return_value[$row['id_board']]['deny'] = !(empty($row['cannot_access']) || $row['cannot_access'] == 'f');
			}

			// Do we want moderation information?
			if (!empty($boardListOptions['moderator']))
			{
				$return_value[$row['id_board']] += array(
					'id_profile' => $row['id_profile'],
					'member_groups' => $row['member_groups'],
					'is_mod' => $row['is_mod'],
				);
			}
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

			// Shortcuts are useful to keep things simple
			$this_cat = &$return_value['categories'][$row['id_cat']];

			$this_cat['boards'][$row['id_board']] = array(
				'id' => $row['id_board'],
				'name' => $row['board_name'],
				'child_level' => $row['child_level'],
				'allow' => false,
				'deny' => false,
				'selected' => isset($boardListOptions['selected_board']) && $boardListOptions['selected_board'] == $row['id_board'],
			);
			// Do we want access information?

			if (!empty($boardListOptions['access']))
			{
				$this_cat['boards'][$row['id_board']]['allow'] = !(empty($row['can_access']) || $row['can_access'] == 'f');
				$this_cat['boards'][$row['id_board']]['deny'] = !(empty($row['cannot_access']) || $row['cannot_access'] == 'f');
			}

			// If is_ignored is set, it means we could have to deselect a board
			if (isset($row['is_ignored']))
			{
				$this_cat['boards'][$row['id_board']]['selected'] = $row['is_ignored'];

				// If a board wasn't checked that probably should have been ensure the board selection is selected, yo!
				if (!empty($this_cat['boards'][$row['id_board']]['selected']) && (empty($modSettings['recycle_enable']) || $row['id_board'] != $modSettings['recycle_board']))
					$return_value['boards_check_all'] = false;
			}

			// Do we want moderation information?
			if (!empty($boardListOptions['moderator']))
			{
				$this_cat['boards'][$row['id_board']] += array(
					'id_profile' => $row['id_profile'],
					'member_groups' => $row['member_groups'],
					'is_mod' => $row['is_mod'],
				);
			}
		}
	}

	$db->free_result($request);

	return $return_value;
}

/**
 * Recursively get a list of boards.
 *
 * @package Boards
 * @param array $tree the board tree
 * @return array list of child boards id
 */
function recursiveBoards($tree)
{
	if (empty($tree['children']))
	{
		return [];
	}

	$boardsList = [];
	foreach ($tree['children'] as $id => $node)
	{
		$boardsList[] = $id;
		$boardsList = array_merge($boardsList, recursiveBoards($node));
	}

	return $boardsList;
}

/**
 * Returns whether this member has notification turned on for the specified board.
 *
 * @param int $id_member the member id
 * @param int $id_board the board to check
 * @return bool if they have notifications turned on for the board
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
 * @package Boards
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
 * This function returns a boolean equivalent with hasBoardNotification().
 * This is unexpected, but it's done this way to avoid any extra-query is executed on MessageIndex::action_messageindex().
 * Just ignore the return value for normal use.
 *
 * @package Boards
 * @param int $id_member
 * @param int $id_board
 * @param bool $check = true check if the user has notifications enabled for the board
 * @return bool if the board was marked for notifications
 */
function resetSentBoardNotification($id_member, $id_board, $check = true)
{
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
		// nothing to do
		if ($db->num_rows($request) == 0)
			return false;
		$sent = $db->fetch_row($request);
		$db->free_result($request);

		// not sent already? No need to stay around then
		if (empty($sent))
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
 * Counts the board notification for a given member.
 *
 * @package Boards
 * @param int $memID
 * @return int
 */
function getBoardNotificationsCount($memID)
{
	global $user_info;

	$db = database();

	// All the boards that you have notification enabled
	$request = $db->query('', '
		SELECT COUNT(*)
		FROM {db_prefix}log_notify AS ln
			INNER JOIN {db_prefix}boards AS b ON (b.id_board = ln.id_board)
			LEFT JOIN {db_prefix}log_boards AS lb ON (lb.id_board = b.id_board AND lb.id_member = {int:current_member})
		WHERE ln.id_member = {int:selected_member}
			AND {query_see_board}',
		array(
			'current_member' => $user_info['id'],
			'selected_member' => $memID,
		)
	);
	list ($totalNotifications) = $db->fetch_row($request);
	$db->free_result($request);

	return $totalNotifications;
}

/**
 * Returns all the boards accessible to the current user.
 *
 * - If $id_parents is given, return only the sub-boards of those boards.
 * - If $id_boards is given, filters the boards to only those accessible.
 * - The function doesn't guarantee the boards are properly sorted
 *
 * @package Boards
 *
 * @param int[]|null $id_parents array of ints representing board ids
 * @param int[]|null $id_boards
 *
 * @return array
 */
function accessibleBoards($id_boards = null, $id_parents = null)
{
	$db = database();

	$boards = array();
	if (!empty($id_parents))
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
	elseif (!empty($id_boards))
	{
		// Find all the boards this user can see between those selected
		$request = $db->query('', '
			SELECT b.id_board
			FROM {db_prefix}boards AS b
			WHERE b.id_board IN ({array_int:board_list})
				AND {query_see_board}',
			array(
				'board_list' => $id_boards,
			)
		);
	}
	else
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

	while ($row = $db->fetch_assoc($request))
		$boards[] = $row['id_board'];
	$db->free_result($request);

	return $boards;
}

/**
 * Returns the boards the current user wants to see.
 *
 * @package Boards
 *
 * @param string $see_board either 'query_see_board' or 'query_wanna_see_board'
 * @param bool $hide_recycle is tru the recycle bin is not returned
 *
 * @return array
 */
function wantedBoards($see_board, $hide_recycle = true)
{
	global $modSettings, $user_info;

	$db = database();
	$allowed_see = array(
		'query_see_board',
		'query_wanna_see_board'
	);

	// Find all boards down from $id_parent
	return $db->fetchQuery('
		SELECT b.id_board
		FROM {db_prefix}boards AS b
		WHERE ' . $user_info[in_array($see_board, $allowed_see) ? $see_board : $allowed_see[0]] . ($hide_recycle && !empty($modSettings['recycle_enable']) && $modSettings['recycle_board'] > 0 ? '
			AND b.id_board != {int:recycle_board}' : ''),
		array(
			'recycle_board' => (int) $modSettings['recycle_board'],
		)
	)->fetch_callback(
		function ($row)
		{
			return $row['id_board'];
		}
	);
}

/**
 * Returns the post count and name of a board
 *
 * - if supplied a topic id will also return the message subject
 * - honors query_see_board to ensure a user can see the information
 *
 * @package Boards
 * @param int $board_id
 * @param int|null $topic_id
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
 * @package Boards
 * @param int $curBoard
 * @param boolean $new_board = false Whether this is a new board
 * @return array
 */
function getOtherGroups($curBoard, $new_board = false)
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
		if ($new_board && $row['min_posts'] == -1)
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
 *
 * @package Boards
 * @param int $idboard
 * @param bool $only_id return only the id of the moderators instead of id and name (default false)
 * @return array
 */
function getBoardModerators($idboard, $only_id = false)
{
	$db = database();

	$moderators = array();

	if ($only_id)
	{
		$request = $db->query('', '
			SELECT id_member
			FROM {db_prefix}moderators
			WHERE id_board = {int:current_board}',
			array(
				'current_board' => $idboard,
			)
		);
		while ($row = $db->fetch_assoc($request))
			$moderators[] = $row['id_member'];
	}
	else
	{
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
	}
	$db->free_result($request);

	return $moderators;
}

/**
 * Get a list of all the board moderators (every board)
 *
 * @package Boards
 * @param bool $only_id return array with key of id_member of the moderator(s)
 * otherwise array with key of id_board id (default false)
 * @return array
 */
function allBoardModerators($only_id = false)
{
	$db = database();

	$moderators = array();

	if ($only_id)
		$request = $db->query('', '
			SELECT id_board, id_member
			FROM {db_prefix}moderators',
			array(
			)
		);
	else
		$request = $db->query('', '
			SELECT mods.id_board, mods.id_member, mem.real_name
			FROM {db_prefix}moderators AS mods
				INNER JOIN {db_prefix}members AS mem ON (mem.id_member = mods.id_member)',
			array(
			)
		);

	while ($row = $db->fetch_assoc($request))
	{
		if ($only_id)
			$moderators[$row['id_member']][] = $row;
		else
			$moderators[$row['id_board']][] = $row;
	}
	$db->free_result($request);

	return $moderators;
}

/**
 * Get a list of all the board moderated by a certain user
 *
 * @package Boards
 * @param int $id_member the id of a member
 * @return array
 */
function boardsModerated($id_member)
{
	$db = database();

	return $db->fetchQuery('
		SELECT id_board
		FROM {db_prefix}moderators
		WHERE id_member = {int:current_member}',
		array(
			'current_member' => $id_member,
		)
	)->fetch_callback(
		function ($row)
		{
			return $row['id_board'];
		}
	);
}

/**
 * Get all available themes
 *
 * @package Boards
 * @return array
 */
function getAllThemes()
{
	$db = database();

	// Get all the themes...
	return $db->fetchQuery('
		SELECT id_theme AS id, value AS name
		FROM {db_prefix}themes
		WHERE variable = {string:name}',
		array(
			'name' => 'name',
		)
	)->fetch_all();
}

/**
 * Gets redirect infos and post count from a selected board.
 *
 * @package Boards
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
 *
 * @package Boards
 *
 * @param int[]|null $boards an array of board IDs
 * @param int[]|null $categories an array of category IDs
 * @param bool $wanna_see_board if true uses {query_wanna_see_board}, otherwise {query_see_board}
 * @param bool $include_recycle if false excludes any results from the recycle board (if enabled)
 *
 * @return array
 */
function boardsPosts($boards, $categories, $wanna_see_board = false, $include_recycle = true)
{
	global $modSettings;

	$db = database();

	$clauses = array();
	$removals = array();
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

	if (empty($include_recycle) && (!empty($modSettings['recycle_enable']) && $modSettings['recycle_board'] > 0))
	{
		$removals[] = 'id_board != {int:recycle_board}';
		$clauseParameters['recycle_board'] = (int) $modSettings['recycle_board'];
	}

	if (empty($clauses))
		return array();

	$request = $db->query('', '
		SELECT b.id_board, b.num_posts
		FROM {db_prefix}boards AS b
		WHERE ' . ($wanna_see_board ? '{query_wanna_see_board}' : '{query_see_board}') . '
			AND b.' . implode(' OR b.', $clauses) . (!empty($removals) ? '
			AND b.' . implode(' AND b.', $removals) : ''),
		$clauseParameters
	);
	$return = array();
	while ($row = $db->fetch_assoc($request))
		$return[$row['id_board']] = $row['num_posts'];
	$db->free_result($request);

	return $return;
}

/**
 * Returns the total sum of posts in the boards defined by query_wanna_see_board
 * Excludes the count of any boards defined as a recycle board from the sum
 */
function sumRecentPosts()
{
	$db = database();

	global $modSettings;

	$request = $db->query('', '
		SELECT COALESCE(SUM(num_posts), 0)
		FROM {db_prefix}boards as b
		WHERE {query_wanna_see_board}' . (!empty($modSettings['recycle_enable']) && $modSettings['recycle_board'] > 0 ? '
			AND b.id_board != {int:recycle_board}' : ''),
		array(
			'recycle_board' => $modSettings['recycle_board']
		)
	);
	list ($result) = $db->fetch_row($request);
	$db->free_result($request);

	return $result;
}

/**
 * Returns information of a set of boards based on board IDs or category IDs
 *
 * @package Boards
 *
 * @param mixed[]|string $conditions is an associative array that holds the board or the cat IDs
 *              'categories' => an array of category IDs (it accepts a single ID too)
 *              'boards' => an array of board IDs (it accepts a single ID too)
 *              if conditions is set to 'all' (not an array) all the boards are queried
 * @param mixed[] $params is an optional array that allows to control the results returned:
 *              'sort_by' => (string) defines the sorting of the results (allowed: id_board, name)
 *              'selects' => (string) determines what information are retrieved and returned
 *                           Allowed values: 'name', 'posts', 'detailed', 'permissions', 'reports';
 *                           default: 'name';
 *                           see the function for details on the fields associated to each value
 *              'override_permissions' => (bool) if true doesn't use neither {query_wanna_see_board} nor
 *     {query_see_board} (default false)
 *              'wanna_see_board' => (bool) if true uses {query_wanna_see_board}, otherwise {query_see_board}
 *              'include_recycle' => (bool) recycle board is included (default true)
 *              'include_redirects' => (bool) redirects are included (default true)
 *
 * @todo unify the two queries?
 * @return array
 */
function fetchBoardsInfo($conditions = 'all', $params = array())
{
	global $modSettings;

	$db = database();

	// Ensure default values are set
	$params = array_merge(array('override_permissions' => false, 'wanna_see_board' => false, 'include_recycle' => true, 'include_redirects' => true), $params);

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
		'permissions' => 'b.id_board, b.name, b.member_groups, b.id_profile',
		'reports' => 'b.id_board, b.name, b.member_groups, b.id_profile, b.deny_member_groups',
	);

	$select = $known_selects[empty($params['selects']) || !isset($known_selects[$params['selects']]) ? 'name' : $params['selects']];

	// If $conditions wasn't set or is 'all', get all boards
	if (!is_array($conditions) && $conditions == 'all')
	{
		// id_board, name, id_profile => used in admin/Reports.controller.php
		$request = $db->query('', '
			SELECT ' . $select . '
			FROM {db_prefix}boards AS b
			' . $sort_by,
			array()
		);
	}
	else
	{
		// Only some categories?
		if (!empty($conditions['categories']))
		{
			$clauses[] = 'id_cat IN ({array_int:category_list})';
			$clauseParameters['category_list'] = is_array($conditions['categories']) ? $conditions['categories'] : array($conditions['categories']);
		}

		// Only a few boards, perhaps!
		if (!empty($conditions['boards']))
		{
			$clauses[] = 'id_board IN ({array_int:board_list})';
			$clauseParameters['board_list'] = is_array($conditions['boards']) ? $conditions['boards'] : array($conditions['boards']);
		}

		if ($params['override_permissions'])
			$security = '1=1';
		else
			$security = $params['wanna_see_board'] ? '{query_wanna_see_board}' : '{query_see_board}';

		$request = $db->query('', '
			SELECT ' . $select . '
			FROM {db_prefix}boards AS b
			WHERE ' . $security . (!empty($clauses) ? '
				AND b.' . implode(' OR b.', $clauses) : '') . ($params['include_recycle'] ? '' : '
				AND b.id_board != {int:recycle_board}') . ($params['include_redirects'] ? '' : '
				AND b.redirect = {string:empty_string}
			' . $sort_by),
			array_merge($clauseParameters, array(
				'recycle_board' => !empty($modSettings['recycle_board']) ? $modSettings['recycle_board'] : 0,
				'empty_string' => '',
			))
		);
	}
	$return = array();
	while ($row = $db->fetch_assoc($request))
		$return[$row['id_board']] = $row;

	$db->free_result($request);

	return $return;
}

/**
 * Retrieve the all the sub-boards of an array of boards and add the ids to the same array
 *
 * @package Boards
 * @param int[]|int $boards an array of board IDs (it accepts a single board too).
 * NOTE: the $boards param is deprecated since 1.1 - The param is passed by ref in 1.0 and the result
 * is returned through the param itself, starting from 1.1 the expected behaviour
 * is that the result is returned.
 * @return bool|int[]
 */
function addChildBoards($boards)
{
	$db = database();

	if (empty($boards))
	{
		return false;
	}

	if (!is_array($boards))
	{
		$boards = array($boards);
	}

	$request = $db->query('', '
		SELECT
			b.id_board, b.id_parent
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
	{
		if (in_array($row['id_parent'], $boards))
		{
			$boards[] = $row['id_board'];
		}
	}
	$db->free_result($request);

	return $boards;
}

/**
 * Increment a board stat field, for example num_posts.
 *
 * @package Boards
 * @param int $id_board
 * @param mixed[]|string $values an array of index => value of a string representing the index to increment
 */
function incrementBoard($id_board, $values)
{
	$db = database();

	$knownInts = array(
		'child_level', 'board_order', 'num_topics', 'num_posts', 'count_posts',
		'unapproved_posts', 'unapproved_topics'
	);

	call_integration_hook('integrate_board_fields', array(&$knownInts));

	$set = array();
	$params = array('id_board' => $id_board);
	$values = is_array($values) ? $values : array($values => 1);

	foreach ($values as $key => $val)
	{
		if (in_array($key, $knownInts))
		{
			$set[] = $key . ' = ' . $key . ' + {int:' . $key . '}';
			$params[$key] = $val;
		}
	}

	if (empty($set))
		return;

	$db->query('', '
		UPDATE {db_prefix}boards
		SET
			' . implode(',
			', $set) . '
		WHERE id_board = {int:id_board}',
		$params
	);
}

/**
 * Decrement a board stat field, for example num_posts.
 *
 * @package Boards
 * @param int $id_board
 * @param mixed[]|string $values an array of index => value of a string representing the index to decrement
 */
function decrementBoard($id_board, $values)
{
	$db = database();

	$knownInts = array(
		'child_level', 'board_order', 'num_topics', 'num_posts', 'count_posts',
		'unapproved_posts', 'unapproved_topics'
	);

	call_integration_hook('integrate_board_fields', array(&$knownInts));

	$set = array();
	$params = array('id_board' => $id_board);
	$values = is_array($values) ? $values : array($values => 1);

	foreach ($values as $key => $val)
	{
		if (in_array($key, $knownInts))
		{
			$set[] = $key . ' = CASE WHEN {int:' . $key . '} > ' . $key . ' THEN 0 ELSE ' . $key . ' - {int:' . $key . '} END';
			$params[$key] = $val;
		}
	}

	if (empty($set))
		return;

	$db->query('', '
		UPDATE {db_prefix}boards
		SET
			' . implode(',
			', $set) . '
		WHERE id_board = {int:id_board}',
		$params
	);
}

/**
 * Retrieve all the boards the user can see and their notification status:
 *
 * - if they're subscribed to notifications for new topics in each of them
 * or they're not.
 * - (used by createList() callbacks)
 *
 * @package Boards
 *
 * @param string $sort A string indicating how to sort the results
 * @param int $memID id_member
 *
 * @return array
 */
function boardNotifications($sort, $memID)
{
	global $user_info, $modSettings;

	$db = database();

	// All the boards that you have notification enabled
	$notification_boards = $db->fetchQuery('
		SELECT b.id_board, b.name, COALESCE(lb.id_msg, 0) AS board_read, b.id_msg_updated
		FROM {db_prefix}log_notify AS ln
			INNER JOIN {db_prefix}boards AS b ON (b.id_board = ln.id_board)
			LEFT JOIN {db_prefix}log_boards AS lb ON (lb.id_board = b.id_board AND lb.id_member = {int:current_member})
		WHERE ln.id_member = {int:selected_member}
			AND {query_see_board}
		ORDER BY ' . $sort,
		array(
			'current_member' => $user_info['id'],
			'selected_member' => $memID,
		)
	)->fetch_callback(
		function ($row)
		{
			$href = getUrl('board', ['board' => $row['id_board'], 'start' => '0', 'name' => $row['name']]);
			return array(
				'id' => $row['id_board'],
				'name' => $row['name'],
				'href' => $href,
				'link' => '<a href="' . $href . '"><strong>' . $row['name'] . '</strong></a>',
				'new' => $row['board_read'] < $row['id_msg_updated'],
				'checked' => 'checked="checked"',
			);
		}
	);

	// and all the boards that you can see but don't have notify turned on for
	$request = $db->query('', '
		SELECT b.id_board, b.name, COALESCE(lb.id_msg, 0) AS board_read, b.id_msg_updated
		FROM {db_prefix}boards AS b
			LEFT JOIN {db_prefix}log_notify AS ln ON (ln.id_board = b.id_board AND ln.id_member = {int:selected_member})
			LEFT JOIN {db_prefix}log_boards AS lb ON (lb.id_board = b.id_board AND lb.id_member = {int:current_member})
		WHERE {query_see_board}
			AND ln.id_board is null ' . (!empty($modSettings['recycle_enable']) && $modSettings['recycle_board'] > 0 ? '
			AND b.id_board != {int:recycle_board}' : '') . '
		ORDER BY ' . $sort,
		array(
			'selected_member' => $memID,
			'current_member' => $user_info['id'],
			'recycle_board' => $modSettings['recycle_board'],
		)
	);
	while ($row = $db->fetch_assoc($request))
	{
		$href = getUrl('board', ['board' => $row['id_board'], 'start' => '0', 'name' => $row['name']]);
		$notification_boards[] = array(
			'id' => $row['id_board'],
			'name' => $row['name'],
			'href' => $href,
			'link' => '<a href="' . $href . '">' . $row['name'] . '</a>',
			'new' => $row['board_read'] < $row['id_msg_updated'],
			'checked' => '',
		);
	}
	$db->free_result($request);

	return $notification_boards;
}

/**
 * Count boards all or specific depending on argument, redirect boards excluded by default.
 *
 * @package Boards
 * @param mixed[]|string $conditions is an associative array that holds the board or the cat IDs
 *              'categories' => an array of category IDs (it accepts a single ID too)
 *              'boards' => an array of board IDs (it accepts a single ID too)
 *              if conditions is set to 'all' (not an array) all the boards are queried
 * @param mixed[]|null $params is an optional array that allows to control the results returned if $conditions is not set to 'all':
 *              'wanna_see_board' => (bool) if true uses {query_wanna_see_board}, otherwise {query_see_board}
 *              'include_recycle' => (bool) recycle board is included (default true)
 *              'include_redirects' => (bool) redirects are included (default true)
 * @return int
 */
function countBoards($conditions = 'all', $params = array())
{
	global $modSettings;

	$db = database();

	// Ensure default values are set
	$params = array_merge(array('wanna_see_board' => false, 'include_recycle' => true, 'include_redirects' => true), $params);

	$clauses = array();
	$clauseParameters = array();

	// if $conditions wasn't set or is 'all', get all boards
	if (!is_array($conditions) && $conditions == 'all')
	{
		// id_board, name, id_profile => used in admin/Reports.controller.php
		$request = $db->query('', '
			SELECT COUNT(*)
			FROM {db_prefix}boards AS b',
			array()
		);
	}
	else
	{
		// only some categories?
		if (!empty($conditions['categories']))
		{
			$clauses[] = 'id_cat IN ({array_int:category_list})';
			$clauseParameters['category_list'] = is_array($conditions['categories']) ? $conditions['categories'] : array($conditions['categories']);
		}

		// only a few boards, perhaps!
		if (!empty($conditions['boards']))
		{
			$clauses[] = 'id_board IN ({array_int:board_list})';
			$clauseParameters['board_list'] = is_array($conditions['boards']) ? $conditions['boards'] : array($conditions['boards']);
		}

		$request = $db->query('', '
			SELECT COUNT(*)
			FROM {db_prefix}boards AS b
			WHERE ' . ($params['wanna_see_board'] ? '{query_wanna_see_board}' : '{query_see_board}') . (!empty($clauses) ? '
				AND b.' . implode(' OR b.', $clauses) : '') . ($params['include_recycle'] ? '' : '
				AND b.id_board != {int:recycle_board}') . ($params['include_redirects'] ? '' : '
				AND b.redirect = {string:empty_string}'),
			array_merge($clauseParameters, array(
				'recycle_board' => !empty($modSettings['recycle_board']) ? $modSettings['recycle_board'] : 0,
				'empty_string' => '',
			))
		);
	}

	list ($num_boards) = $db->fetch_row($request);
	$db->free_result($request);

	return $num_boards;
}
