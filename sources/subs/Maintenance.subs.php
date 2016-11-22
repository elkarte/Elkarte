<?php

/**
 * Forum maintenance support functions. Important stuff.
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * This file contains code covered by:
 * copyright:	2011 Simple Machines (http://www.simplemachines.org)
 * license:  	BSD, See included LICENSE.TXT for terms and conditions.
 *
 * @version 1.1 beta 3
 *
 */
/**
 * Counts the total number of messages
 *
 * @package Maintenance
 * @return int
 */
function countMessages()
{
	$db = database();

	$request = $db->query('', '
		SELECT COUNT(*)
		FROM {db_prefix}messages',
		array()
	);
	list ($messages) = $db->fetch_row($request);
	$db->free_result($request);

	return $messages;
}

/**
 * Flushes all log tables
 *
 * @package Maintenance
 */
function flushLogTables()
{
	$db = database();

	$db->query('', '
		DELETE FROM {db_prefix}log_online');

	// Dump the banning logs.
	$db->query('', '
		DELETE FROM {db_prefix}log_banned');

	// Start id_error back at 0 and dump the error log.
	$db->query('truncate_table', '
		TRUNCATE {db_prefix}log_errors');

	// Clear out the spam log.
	$db->query('', '
		DELETE FROM {db_prefix}log_floodcontrol');

	// Clear out the karma actions.
	$db->query('', '
		DELETE FROM {db_prefix}log_karma');

	// Last but not least, the search logs!
	$db->query('truncate_table', '
		TRUNCATE {db_prefix}log_search_topics');

	$db->query('truncate_table', '
		TRUNCATE {db_prefix}log_search_messages');

	$db->query('truncate_table', '
		TRUNCATE {db_prefix}log_search_results');
}

/**
 * Gets the table columns from the messages table, just a wrapper function
 *
 * @package Maintenance
 * @return array
 */
function getMessageTableColumns()
{
	$table = db_table();
	$colData = $table->db_list_columns('{db_prefix}messages', true);

	return $colData;
}

/**
 * Retrieve information about the body column of the messages table
 * Used in action_database
 *
 * @package Maintenance
 */
function fetchBodyType()
{
	$table = db_table();

	$colData = $table->db_list_columns('{db_prefix}messages', true);
	foreach ($colData as $column)
		if ($column['name'] == 'body')
			$body_type = $column['type'];

	return $body_type;
}

/**
 * Resizes the body column from the messages table
 *
 * @package Maintenance
 * @param string $type
 */
function resizeMessageTableBody($type)
{
	$table = db_table();
	$table->db_change_column('{db_prefix}messages', 'body', array('type' => $type));
}

/**
 * Detects messages, which exceed the max message size
 *
 * @package Maintenance
 * @param int $start The item to start with (for pagination purposes)
 * @param int $increment
 */
function detectExceedingMessages($start, $increment)
{
	$db = database();

	return $db->fetchQueryCallback('
		SELECT /*!40001 SQL_NO_CACHE */ id_msg
		FROM {db_prefix}messages
		WHERE id_msg BETWEEN {int:start} AND {int:start} + {int:increment}
			AND LENGTH(body) > 65535',
		array(
			'start' => $start,
			'increment' => $increment - 1,
		),
		function($row)
		{
			return $row['id_msg'];
		}
	);
}

/**
 * loads messages, which exceed the length that will fit in the col field
 *
 * - Used by maintenance when convert the column "body" of the table from TEXT
 * to MEDIUMTEXT and vice versa.
 *
 * @package Maintenance
 * @param int[] $msg
 * @return array
 */
function getExceedingMessages($msg)
{
	global $scripturl;

	$db = database();

	return $db->fetchQueryCallback('
		SELECT id_msg, id_topic, subject
		FROM {db_prefix}messages
		WHERE id_msg IN ({array_int:messages})',
		array(
			'messages' => $msg,
		),
		function($row) use ($scripturl)
		{
			return '<a href="' . $scripturl . '?topic=' . $row['id_topic'] . '.msg' . $row['id_msg'] . '#msg' . $row['id_msg'] . '">' . $row['subject'] . '</a>';
		}
	);
}

/**
 * Lists all the tables from our ElkArte installation.
 *
 * - Additional tables from addons are also included.
 *
 * @package Maintenance
 * @return array
 */
function getElkTables()
{
	global $db_prefix;

	$db = database();

	$tables = array();

	// Only optimize the tables related to this installation, not all the tables in the db
	$real_prefix = preg_match('~^(`?)(.+?)\\1\\.(.*?)$~', $db_prefix, $match) === 1 ? $match[3] : $db_prefix;

	// Get a list of tables, as well as how many there are.
	$temp_tables = $db->db_list_tables(false, $real_prefix . '%');
	foreach ($temp_tables as $table)
			$tables[] = array('table_name' => $table);

	return $tables;
}

/**
 * Wrapper function for db_optimize_table
 *
 * @package Maintenance
 * @param string $tablename
 */
function optimizeTable($tablename)
{
	$db_table = db_table();

	return $db_table->optimize($tablename);
}

/**
 * Gets the last topics id.
 *
 * @package Maintenance
 * @return int
 */
function getMaxTopicID()
{
	$db = database();

	$request = $db->query('', '
		SELECT MAX(id_topic)
		FROM {db_prefix}topics',
		array(
		)
	);
	list ($id_topic) = $db->fetch_row($request);
	$db->free_result($request);

	return $id_topic;
}

/**
 * Recounts all approved messages
 *
 * @package Maintenance
 * @param int $start The item to start with (for pagination purposes)
 * @param int $increment
 */
function recountApprovedMessages($start, $increment)
{
	$db = database();

	// Recount approved messages
	$db->fetchQueryCallback('
		SELECT /*!40001 SQL_NO_CACHE */ t.id_topic, MAX(t.num_replies) AS num_replies,
			CASE WHEN COUNT(ma.id_msg) >= 1 THEN COUNT(ma.id_msg) - 1 ELSE 0 END AS real_num_replies
		FROM {db_prefix}topics AS t
			LEFT JOIN {db_prefix}messages AS ma ON (ma.id_topic = t.id_topic AND ma.approved = {int:is_approved})
		WHERE t.id_topic > {int:start}
			AND t.id_topic <= {int:max_id}
		GROUP BY t.id_topic
		HAVING CASE WHEN COUNT(ma.id_msg) >= 1 THEN COUNT(ma.id_msg) - 1 ELSE 0 END != MAX(t.num_replies)',
		array(
			'is_approved' => 1,
			'start' => $start,
			'max_id' => $start + $increment,
		),
		function($row)
		{
			setTopicAttribute($row['id_topic'], array('num_replies' => $row['real_num_replies']));
		}
	);
}

/**
 * Recounts all unapproved messages
 *
 * @package Maintenance
 * @param int $start The item to start with (for pagination purposes)
 * @param int $increment
 */
function recountUnapprovedMessages($start, $increment)
{
	$db = database();

	// Recount unapproved messages
	$db->fetchQueryCallback('
		SELECT /*!40001 SQL_NO_CACHE */ t.id_topic, MAX(t.unapproved_posts) AS unapproved_posts,
			COUNT(mu.id_msg) AS real_unapproved_posts
		FROM {db_prefix}topics AS t
			LEFT JOIN {db_prefix}messages AS mu ON (mu.id_topic = t.id_topic AND mu.approved = {int:not_approved})
		WHERE t.id_topic > {int:start}
			AND t.id_topic <= {int:max_id}
		GROUP BY t.id_topic
		HAVING COUNT(mu.id_msg) != MAX(t.unapproved_posts)',
		array(
			'not_approved' => 0,
			'start' => $start,
			'max_id' => $start + $increment,
		),
		function($row)
		{
			setTopicAttribute($row['id_topic'], array('unapproved_posts' => $row['real_unapproved_posts']));
		}
	);
}

/**
 * Reset the boards table's counter for posts, topics, unapproved posts and
 * unapproved topics
 *
 * - Allowed parameters: num_posts, num_topics, unapproved_posts, unapproved_topics
 *
 * @package Maintenance
 * @param string $column
 */
function resetBoardsCounter($column)
{
	$db = database();

	$allowed_columns = array('num_posts', 'num_topics', 'unapproved_posts', 'unapproved_topics');

	if (!in_array($column, $allowed_columns))
			return false;

	$db->query('', '
		UPDATE {db_prefix}boards
		SET ' . $column . ' = {int:counter}
		WHERE redirect = {string:redirect}',
		array(
			'counter' => 0,
			'redirect' => '',
		)
	);
}

/**
 * Recalculates the boards table's counter
 *
 * @package Maintenance
 * @param string $type - can be 'posts', 'topic', 'unapproved_posts', 'unapproved_topics'
 * @param int $start The item to start with (for pagination purposes)
 * @param int $increment
 */
function updateBoardsCounter($type, $start, $increment)
{
	$db = database();

	switch ($type)
	{
		case 'posts':
			$request = $db->query('', '
				SELECT /*!40001 SQL_NO_CACHE */ m.id_board, COUNT(*) AS real_num_posts
				FROM {db_prefix}messages AS m
				WHERE m.id_topic > {int:id_topic_min}
					AND m.id_topic <= {int:id_topic_max}
					AND m.approved = {int:is_approved}
				GROUP BY m.id_board',
				array(
					'id_topic_min' => $start,
					'id_topic_max' => $start + $increment,
					'is_approved' => 1,
				)
			);
			while ($row = $db->fetch_assoc($request))
			{
				$db->query('', '
					UPDATE {db_prefix}boards
					SET num_posts = num_posts + {int:real_num_posts}
					WHERE id_board = {int:id_board}',
					array(
						'id_board' => $row['id_board'],
						'real_num_posts' => $row['real_num_posts'],
					)
				);
			}
			$db->free_result($request);
			break;

		case 'topics':
			$request = $db->query('', '
				SELECT /*!40001 SQL_NO_CACHE */ t.id_board, COUNT(*) AS real_num_topics
				FROM {db_prefix}topics AS t
				WHERE t.approved = {int:is_approved}
					AND t.id_topic > {int:id_topic_min}
					AND t.id_topic <= {int:id_topic_max}
				GROUP BY t.id_board',
				array(
					'is_approved' => 1,
					'id_topic_min' => $start,
					'id_topic_max' => $start + $increment,
				)
			);
			while ($row = $db->fetch_assoc($request))
			{
				$db->query('', '
					UPDATE {db_prefix}boards
					SET num_topics = num_topics + {int:real_num_topics}
					WHERE id_board = {int:id_board}',
					array(
						'id_board' => $row['id_board'],
						'real_num_topics' => $row['real_num_topics'],
					)
				);
			}
			$db->free_result($request);
			break;

		case 'unapproved_posts':
			$request = $db->query('', '
				SELECT /*!40001 SQL_NO_CACHE */ m.id_board, COUNT(*) AS real_unapproved_posts
				FROM {db_prefix}messages AS m
				WHERE m.id_topic > {int:id_topic_min}
					AND m.id_topic <= {int:id_topic_max}
					AND m.approved = {int:is_approved}
				GROUP BY m.id_board',
				array(
					'id_topic_min' => $start,
					'id_topic_max' => $start + $increment,
					'is_approved' => 0,
				)
			);
			while ($row = $db->fetch_assoc($request))
			{
				$db->query('', '
					UPDATE {db_prefix}boards
					SET unapproved_posts = unapproved_posts + {int:unapproved_posts}
					WHERE id_board = {int:id_board}',
					array(
						'id_board' => $row['id_board'],
						'unapproved_posts' => $row['real_unapproved_posts'],
					)
				);
			}
			$db->free_result($request);
			break;

		case 'unapproved_topics':
			$request = $db->query('', '
				SELECT /*!40001 SQL_NO_CACHE */ t.id_board, COUNT(*) AS real_unapproved_topics
				FROM {db_prefix}topics AS t
				WHERE t.approved = {int:is_approved}
					AND t.id_topic > {int:id_topic_min}
					AND t.id_topic <= {int:id_topic_max}
				GROUP BY t.id_board',
				array(
					'is_approved' => 0,
					'id_topic_min' => $start,
					'id_topic_max' => $start + $increment,
				)
			);
			while ($row = $db->fetch_assoc($request))
			{
				$db->query('', '
					UPDATE {db_prefix}boards
					SET unapproved_topics = unapproved_topics + {int:real_unapproved_topics}
					WHERE id_board = {int:id_board}',
					array(
						'id_board' => $row['id_board'],
						'real_unapproved_topics' => $row['real_unapproved_topics'],
					)
				);
			}
			$db->free_result($request);
			break;

		default:
			trigger_error('updateBoardsCounter(): Invalid counter type \'' . $type . '\'', E_USER_NOTICE);
	}
}

/**
 * Update the personal messages counter
 *
 * @package Maintenance
 */
function updatePersonalMessagesCounter()
{
	$db = database();

	require_once(SUBSDIR . '/Members.subs.php');

	$db->fetchQueryCallback('
		SELECT /*!40001 SQL_NO_CACHE */ mem.id_member, COUNT(pmr.id_pm) AS real_num,
			MAX(mem.personal_messages) AS personal_messages
		FROM {db_prefix}members AS mem
			LEFT JOIN {db_prefix}pm_recipients AS pmr ON (mem.id_member = pmr.id_member AND pmr.deleted = {int:is_not_deleted})
		GROUP BY mem.id_member
		HAVING COUNT(pmr.id_pm) != MAX(mem.personal_messages)',
		array(
			'is_not_deleted' => 0,
		),
		function($row)
		{
			updateMemberData($row['id_member'], array('personal_messages' => $row['real_num']));
		}
	);

	$db->fetchQueryCallback('
		SELECT /*!40001 SQL_NO_CACHE */ mem.id_member, COUNT(pmr.id_pm) AS real_num,
			MAX(mem.unread_messages) AS unread_messages
		FROM {db_prefix}members AS mem
			LEFT JOIN {db_prefix}pm_recipients AS pmr ON (mem.id_member = pmr.id_member AND pmr.deleted = {int:is_not_deleted} AND pmr.is_read = {int:is_not_read})
		GROUP BY mem.id_member
		HAVING COUNT(pmr.id_pm) != MAX(mem.unread_messages)',
		array(
			'is_not_deleted' => 0,
			'is_not_read' => 0,
		),
		function($row)
		{
			updateMemberData($row['id_member'], array('unread_messages' => $row['real_num']));
		}
	);
}

/**
 * Fixes the column id_board from the messages table.
 *
 * @package Maintenance
 * @param int $start The item to start with (for pagination purposes)
 * @param int $increment
 */
function updateMessagesBoardID($start, $increment)
{
	$db = database();

	$request = $db->query('', '
		SELECT /*!40001 SQL_NO_CACHE */ t.id_board, m.id_msg
		FROM {db_prefix}messages AS m
			INNER JOIN {db_prefix}topics AS t ON (t.id_topic = m.id_topic AND t.id_board != m.id_board)
		WHERE m.id_msg > {int:id_msg_min}
			AND m.id_msg <= {int:id_msg_max}',
		array(
			'id_msg_min' => $start,
			'id_msg_max' => $start + $increment,
		)
	);
	$boards = array();
	while ($row = $db->fetch_assoc($request))
		$boards[$row['id_board']][] = $row['id_msg'];
	$db->free_result($request);

	foreach ($boards as $board_id => $messages)
		$db->query('', '
			UPDATE {db_prefix}messages
			SET id_board = {int:id_board}
			WHERE id_msg IN ({array_int:id_msg_array})',
			array(
				'id_msg_array' => $messages,
				'id_board' => $board_id,
			)
		);
}

/**
 * Updates the latest message of each board.
 *
 * @package Maintenance
 */
function updateBoardsLastMessage()
{
	$db = database();

	// Update the latest message of each board.
	$request = $db->query('', '
		SELECT m.id_board, MAX(m.id_msg) AS local_last_msg
		FROM {db_prefix}messages AS m
		WHERE m.approved = {int:is_approved}
		GROUP BY m.id_board',
		array(
			'is_approved' => 1,
		)
	);
	$realBoardCounts = array();
	while ($row = $db->fetch_assoc($request))
		$realBoardCounts[$row['id_board']] = $row['local_last_msg'];
	$db->free_result($request);

	$request = $db->query('', '
		SELECT /*!40001 SQL_NO_CACHE */ id_board, id_parent, id_last_msg, child_level, id_msg_updated
		FROM {db_prefix}boards',
		array(
		)
	);
	$resort_me = array();
	while ($row = $db->fetch_assoc($request))
	{
		$row['local_last_msg'] = isset($realBoardCounts[$row['id_board']]) ? $realBoardCounts[$row['id_board']] : 0;
		$resort_me[$row['child_level']][] = $row;
	}
	$db->free_result($request);

	krsort($resort_me);

	$lastModifiedMsg = array();
	foreach ($resort_me as $rows)
		foreach ($rows as $row)
		{
			// The latest message is the latest of the current board and its children.
			if (isset($lastModifiedMsg[$row['id_board']]))
				$curLastModifiedMsg = max($row['local_last_msg'], $lastModifiedMsg[$row['id_board']]);
			else
				$curLastModifiedMsg = $row['local_last_msg'];
				// If what is and what should be the latest message differ, an update is necessary.
			if ($row['local_last_msg'] != $row['id_last_msg'] || $curLastModifiedMsg != $row['id_msg_updated'])
				$db->query('', '
					UPDATE {db_prefix}boards
					SET id_last_msg = {int:id_last_msg}, id_msg_updated = {int:id_msg_updated}
					WHERE id_board = {int:id_board}',
					array(
						'id_last_msg' => $row['local_last_msg'],
						'id_msg_updated' => $curLastModifiedMsg,
						'id_board' => $row['id_board'],
					)
				);

			// Parent boards inherit the latest modified message of their children.
			if (isset($lastModifiedMsg[$row['id_parent']]))
				$lastModifiedMsg[$row['id_parent']] = max($row['local_last_msg'], $lastModifiedMsg[$row['id_parent']]);
			else
				$lastModifiedMsg[$row['id_parent']] = $row['local_last_msg'];
		}
}

/**
 * Counts topics from a given board.
 *
 * @package Maintenance
 * @param int $id_board
 * @return int
 */
function countTopicsFromBoard($id_board)
{
	$db = database();

	$request = $db->query('', '
		SELECT COUNT(*)
		FROM {db_prefix}topics
		WHERE id_board = {int:id_board}',
		array(
			'id_board' => $id_board,
		)
	);
	list ($total_topics) = $db->fetch_row($request);
	$db->free_result($request);

	return $total_topics;
}

/**
 * Gets a list of the next 10 topics which should be moved to a different board.
 *
 * @package Maintenance
 * @param int $id_board
 *
 * @return int[]
 */
function getTopicsToMove($id_board)
{
	$db = database();

	// Lets get the topics.
	return $db->fetchQueryCallback('
		SELECT id_topic
		FROM {db_prefix}topics
		WHERE id_board = {int:id_board}
		LIMIT 10',
		array(
			'id_board' => $id_board,
		),
		function($row)
		{
			return $row['id_topic'];
		}
	);
}

/**
 * Counts members with posts > 0, we name them contributors
 *
 * @package Maintenance
 * @return int
 */
function countContributors()
{
	$db = database();

	$request = $db->query('', '
		SELECT COUNT(DISTINCT m.id_member)
		FROM ({db_prefix}messages AS m, {db_prefix}boards AS b)
		WHERE m.id_member != 0
			AND b.count_posts = 0
			AND m.id_board = b.id_board',
		array(
		)
	);

	// save it so we don't do this again for this task
	list ($total_members) = $db->fetch_row($request);
	$db->free_result($request);

	return $total_members;
}

/**
 * Recount the members posts.
 *
 * @package Maintenance
 * @param int $start The item to start with (for pagination purposes)
 * @param int $increment
 * @return int
 */
function updateMembersPostCount($start, $increment)
{
	global $modSettings;

	$db = database();

	$request = $db->query('', '
		SELECT /*!40001 SQL_NO_CACHE */ m.id_member, COUNT(m.id_member) AS posts
		FROM ({db_prefix}messages AS m, {db_prefix}boards AS b)
		WHERE m.id_member != {int:zero}
			AND b.count_posts = {int:zero}
			AND m.id_board = b.id_board ' . (!empty($modSettings['recycle_enable']) ? '
			AND b.id_board != {int:recycle}' : '') . '
		GROUP BY m.id_member
		LIMIT {int:start}, {int:number}',
		array(
			'start' => $start,
			'number' => $increment,
			'recycle' => $modSettings['recycle_board'],
			'zero' => 0,
		)
	);
	$total_rows = $db->num_rows($request);

	// Update the post count for this group
	require_once(SUBSDIR . '/Members.subs.php');
	while ($row = $db->fetch_assoc($request))
		updateMemberData($row['id_member'], array('posts' => $row['posts']));
	$db->free_result($request);

	return $total_rows;
}

/**
 * Used to find members who have a post count >0 that should not.
 *
 * - Made more difficult since we don't yet support sub-selects on joins so we
 * place all members who have posts in the message table in a temp table
 *
 * @package Maintenance
 */
function updateZeroPostMembers()
{
	global $modSettings;

	$db = database();

	$createTemporary = $db->query('', '
			CREATE TEMPORARY TABLE {db_prefix}tmp_maint_recountposts (
				id_member mediumint(8) unsigned NOT NULL default {string:string_zero},
				PRIMARY KEY (id_member)
			)
			SELECT m.id_member
			FROM ({db_prefix}messages AS m,{db_prefix}boards AS b)
			WHERE m.id_member != {int:zero}
				AND b.count_posts = {int:zero}
				AND m.id_board = b.id_board ' . (!empty($modSettings['recycle_enable']) ? '
				AND b.id_board != {int:recycle}' : '') . '
			GROUP BY m.id_member',
			array(
				'zero' => 0,
				'string_zero' => '0',
				'db_error_skip' => true,
				'recycle' => $modSettings['recycle_board'],
			)
		) !== false;

		if ($createTemporary)
		{
			// Outer join the members table on the temporary table finding the members that
			// have a post count but no posts in the message table
			$members = $db->fetchQueryCallback('
				SELECT mem.id_member, mem.posts
				FROM {db_prefix}members AS mem
				LEFT OUTER JOIN {db_prefix}tmp_maint_recountposts AS res
				ON res.id_member = mem.id_member
				WHERE res.id_member IS null
					AND mem.posts != {int:zero}',
				array(
					'zero' => 0,
				),
				function($row)
				{
					// Set the post count to zero for any delinquents we may have found
					return $row['id_member'];
				}
			);

			if (!empty($members))
			{
				require_once(SUBSDIR . '/Members.subs.php');
				updateMemberData($members, array('posts' => 0));
			}
		}
}

/**
 * Removing old and inactive members
 *
 * @package Maintenance
 * @param string $type
 * @param int[] $groups
 * @param int $time_limit
 * @return array
 */
function purgeMembers($type, $groups, $time_limit)
{
	$db = database();

	$where_vars = array(
		'time_limit' => $time_limit,
	);
	if ($type == 'activated')
	{
		$where = 'mem.date_registered < {int:time_limit} AND mem.is_activated = {int:is_activated}';
		$where_vars['is_activated'] = 0;
	}
	else
		$where = 'mem.last_login < {int:time_limit} AND (mem.last_login != 0 OR mem.date_registered < {int:time_limit})';

	// Need to get *all* groups then work out which (if any) we avoid.
	$request = $db->query('', '
		SELECT id_group, group_name, min_posts
		FROM {db_prefix}membergroups',
		array(
		)
	);
	while ($row = $db->fetch_assoc($request))
	{
		// Avoid this one?
		if (!in_array($row['id_group'], $groups))
		{
			// Post group?
			if ($row['min_posts'] != -1)
			{
				$where .= ' AND mem.id_post_group != {int:id_post_group_' . $row['id_group'] . '}';
				$where_vars['id_post_group_' . $row['id_group']] = $row['id_group'];
			}
			else
			{
				$where .= ' AND mem.id_group != {int:id_group_' . $row['id_group'] . '} AND FIND_IN_SET({int:id_group_' . $row['id_group'] . '}, mem.additional_groups) = 0';
				$where_vars['id_group_' . $row['id_group']] = $row['id_group'];
			}
		}
	}
	$db->free_result($request);

	// If we have ungrouped unselected we need to avoid those guys.
	if (!in_array(0, $groups))
	{
		$where .= ' AND (mem.id_group != 0 OR mem.additional_groups != {string:blank_add_groups})';
		$where_vars['blank_add_groups'] = '';
	}

	// Select all the members we're about to remove...
	$request = $db->query('', '
		SELECT mem.id_member, IFNULL(m.id_member, 0) AS is_mod
		FROM {db_prefix}members AS mem
			LEFT JOIN {db_prefix}moderators AS m ON (m.id_member = mem.id_member)
		WHERE ' . $where,
		$where_vars
	);
	$members = array();
	while ($row = $db->fetch_assoc($request))
	{
		if (!$row['is_mod'] || !in_array(3, $groups))
			$members[] = $row['id_member'];
	}
	$db->free_result($request);

	return $members;
}