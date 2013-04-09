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
 * Forum maintenance. Important stuff.
 *
 */
if (!defined('ELKARTE'))
	die('No access...');

/**
 * counts the messages
 *
 * @return int
 */
function countMessages()
{
	global $smcFunc;

	$request = $smcFunc['db_query']('', '
		SELECT COUNT(*)
		FROM {db_prefix}messages',
		array()
	);
	list($messages) = $smcFunc['db_fetch_row']($request);
	$smcFunc['db_free_result']($request);

	return $messages;
}

/**
 * gets all membergroups
 * 
 * @return array
 */
function getMembergroups()
{
	global $smcFunc, $txt;

	$request = $smcFunc['db_query']('', '
		SELECT id_group, group_name
		FROM {db_prefix}membergroups',
		array(
		)
	);
	$membergroups = array(
		array(
			'id' => 0,
			'name' => $txt['maintain_members_ungrouped']
		),
	);
	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		$membergroups[] = array(
			'id' => $row['id_group'],
			'name' => $row['group_name']
		);
	}
	$smcFunc['db_free_result']($request);

	return $membergroups;
}

/**
 * flushes all log tables
 *  
 */
function flushLogTables()
{
	global $smcFunc;

	$smcFunc['db_query']('', '
		DELETE FROM {db_prefix}log_online');

	// Dump the banning logs.
	$smcFunc['db_query']('', '
		DELETE FROM {db_prefix}log_banned');

	// Start id_error back at 0 and dump the error log.
	$smcFunc['db_query']('truncate_table', '
		TRUNCATE {db_prefix}log_errors');

	// Clear out the spam log.
	$smcFunc['db_query']('', '
		DELETE FROM {db_prefix}log_floodcontrol');

	// Clear out the karma actions.
	$smcFunc['db_query']('', '
		DELETE FROM {db_prefix}log_karma');

	// Last but not least, the search logs!
	$smcFunc['db_query']('truncate_table', '
		TRUNCATE {db_prefix}log_search_topics');

	$smcFunc['db_query']('truncate_table', '
		TRUNCATE {db_prefix}log_search_messages');

	$smcFunc['db_query']('truncate_table', '
		TRUNCATE {db_prefix}log_search_results');
}

/**
 * gets the table columns from the messages table, just a wrapper function
 * 
 * @return array 
 */
function getMessageTableColumns()
{
	global $smcFunc;

	db_extend('packages');
	$colData = $smcFunc['db_list_columns']('{db_prefix}messages', true);

	return $colData;
}

/**
 * resizes the body column from the messages table
 *
 * @param string $type 
 */
function resizeMessageTableBody($type)
{
	global $smcFunc;

	db_extend('packages');
	$smcFunc['db_change_column']('{db_prefix}messages', 'body', array('type' => $type));
}

/**
 * detects messages, which exceed the max message size
 *
 * @param type $start
 * @param type $increment
 * @return type 
 */
function detectExceedingMessages($start, $increment)
{
	global $smcFunc;

	$id_msg_exceeding = array();

	$request = $smcFunc['db_query']('', '
		SELECT /*!40001 SQL_NO_CACHE */ id_msg
		FROM {db_prefix}messages
		WHERE id_msg BETWEEN {int:start} AND {int:start} + {int:increment}
			AND LENGTH(body) > 65535',
		array(
			'start' => $start,
			'increment' => $increment - 1,
		)
	);
	while ($row = $smcFunc['db_fetch_assoc']($request))
		$id_msg_exceeding[] = $row['id_msg'];
	$smcFunc['db_free_result']($request);

	return $id_msg_exceeding;
}

/**
 * loads messages, which exceed the lenght
 *
 * @param type $msg
 * @return array
 */
function getExceedingMessages($msg)
{
	global $smcFunc, $scripturl;

	$exceeding_messages = array();

	$request = $smcFunc['db_query']('', '
		SELECT id_msg, id_topic, subject
		FROM {db_prefix}messages
		WHERE id_msg IN ({array_int:messages})',
		array(
			'messages' => $msg,
		)
	);
	while ($row = $smcFunc['db_fetch_assoc']($request))
		$exceeding_messages[] = '<a href="' . $scripturl . '?topic=' . $row['id_topic'] . '.msg' . $row['id_msg'] . '#msg' . $row['id_msg'] . '">' . $row['subject'] . '</a>';
	$smcFunc['db_free_result']($request);

	return $exceeding_messages;
}

/**
 * lists all the tables from our ElkArte installation.
 * Additional tables from modifications are also included.
 * 
 * @return array 
 */
function getOurTables()
{
	global $smcFunc, $db_prefix;

	$tables = array();

	db_extend();
	
	// Only optimize the tables related to this installation, not all the tables in the db
	$real_prefix = preg_match('~^(`?)(.+?)\\1\\.(.*?)$~', $db_prefix, $match) === 1 ? $match[3] : $db_prefix;

	// Get a list of tables, as well as how many there are.
	$temp_tables = $smcFunc['db_list_tables'](false, $real_prefix . '%');
	foreach ($temp_tables as $table)
			$tables[] = array('table_name' => $table);

	return $tables;
}

/**
 * Wrapper function for db_optimize_table
 *
 * @param string $tablename 
 */
function optimizeTable($tablename)
{
	global $smcFunc;
	
	db_extend();
	$smcFunc['db_optimize_table']($tablename);
}

/**
 * gets the last topics id.
 * 
 * @return int 
 */
function getMaxTopicID()
{
	global $smcFunc;

	$request = $smcFunc['db_query']('', '
		SELECT MAX(id_topic)
		FROM {db_prefix}topics',
		array(
		)
	);
	list ($id_topic) = $smcFunc['db_fetch_row']($request);
	$smcFunc['db_free_result']($request);

	return $id_topic;
}

/**
 * Recounts all approved messages
 *
 * @param int $start
 * @param int $increment
 */
function recountApprovedMessages($start, $increment)
{
	global $smcFunc;

	// Recount approved messages
	$request = $smcFunc['db_query']('', '
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
		)
	);
	while ($row = $smcFunc['db_fetch_assoc']($request))
		$smcFunc['db_query']('', '
			UPDATE {db_prefix}topics
			SET num_replies = {int:num_replies}
			WHERE id_topic = {int:id_topic}',
			array(
				'num_replies' => $row['real_num_replies'],
				'id_topic' => $row['id_topic'],
			)
		);
	$smcFunc['db_free_result']($request);
}

/**
 * Recounts all unapproved messages
 *
 * @param int $start
 * @param int $increment
 */
function recountUnapprovedMessages($start, $increment)
{
	global $smcFunc;

	// Recount unapproved messages
	$request = $smcFunc['db_query']('', '
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
		)
	);
	while ($row = $smcFunc['db_fetch_assoc']($request))
		$smcFunc['db_query']('', '
			UPDATE {db_prefix}topics
			SET unapproved_posts = {int:unapproved_posts}
			WHERE id_topic = {int:id_topic}',
			array(
				'unapproved_posts' => $row['real_unapproved_posts'],
				'id_topic' => $row['id_topic'],
			)
		);
	$smcFunc['db_free_result']($request);
}

/**
 * Reset the boards table's counter for posts, topics, unapproved posts and
 * unapproved topics
 * 
 * @param string $column
 */
function resetBoardsCounter($column)
{
	global $smcFunc;

	$smcFunc['db_query']('', '
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
 * Update the boards table's post counter
 * 
 * @param int $start
 * @param int $increment 
 */
function updateBoardsPostCounter($start, $increment)
{
	global $smcFunc;

	$request = $smcFunc['db_query']('', '
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
	while ($row = $smcFunc['db_fetch_assoc']($request))
		$smcFunc['db_query']('', '
			UPDATE {db_prefix}boards
			SET num_posts = num_posts + {int:real_num_posts}
			WHERE id_board = {int:id_board}',
			array(
				'id_board' => $row['id_board'],
				'real_num_posts' => $row['real_num_posts'],
			)
		);
	$smcFunc['db_free_result']($request);
}

/**
 * Update the boards table's topic counter
 * 
 * @param int $start
 * @param int $increment 
 */
function updateBoardsTopicCounter($start, $increment)
{
	global $smcFunc;

	$request = $smcFunc['db_query']('', '
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
	while ($row = $smcFunc['db_fetch_assoc']($request))
		$smcFunc['db_query']('', '
			UPDATE {db_prefix}boards
			SET num_topics = num_topics + {int:real_num_topics}
			WHERE id_board = {int:id_board}',
			array(
				'id_board' => $row['id_board'],
				'real_num_topics' => $row['real_num_topics'],
			)
		);
	$smcFunc['db_free_result']($request);
}

/**
 * Update the boards table's unapproved post counter
 * 
 * @param int $start
 * @param int $increment 
 */
function updateBoardsUnapprovedPostCounter($start, $increment)
{
	global $smcFunc;

	$request = $smcFunc['db_query']('', '
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
	while ($row = $smcFunc['db_fetch_assoc']($request))
		$smcFunc['db_query']('', '
			UPDATE {db_prefix}boards
			SET unapproved_posts = unapproved_posts + {int:unapproved_posts}
			WHERE id_board = {int:id_board}',
			array(
				'id_board' => $row['id_board'],
				'unapproved_posts' => $row['real_unapproved_posts'],
			)
		);
	$smcFunc['db_free_result']($request);
}

/**
 * Update the boards table's unapproved topic counter
 * 
 * @param int $start
 * @param int $increment 
 */
function updateBoardsUnapprovedTopicCounter($start, $increment)
{
	global $smcFunc;

	$request = $smcFunc['db_query']('', '
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
	while ($row = $smcFunc['db_fetch_assoc']($request))
		$smcFunc['db_query']('', '
			UPDATE {db_prefix}boards
			SET unapproved_topics = unapproved_topics + {int:real_unapproved_topics}
			WHERE id_board = {int:id_board}',
			array(
				'id_board' => $row['id_board'],
				'real_unapproved_topics' => $row['real_unapproved_topics'],
			)
		);
	$smcFunc['db_free_result']($request);
}

/**
 * Update the personal messages counter
 */
function updatePersonalMessagesCounter()
{
	global $smcFunc;

	$request = $smcFunc['db_query']('', '
		SELECT /*!40001 SQL_NO_CACHE */ mem.id_member, COUNT(pmr.id_pm) AS real_num,
			MAX(mem.instant_messages) AS instant_messages
		FROM {db_prefix}members AS mem
			LEFT JOIN {db_prefix}pm_recipients AS pmr ON (mem.id_member = pmr.id_member AND pmr.deleted = {int:is_not_deleted})
		GROUP BY mem.id_member
		HAVING COUNT(pmr.id_pm) != MAX(mem.instant_messages)',
		array(
			'is_not_deleted' => 0,
		)
	);
	while ($row = $smcFunc['db_fetch_assoc']($request))
		updateMemberData($row['id_member'], array('instant_messages' => $row['real_num']));
			
	$smcFunc['db_free_result']($request);

	$request = $smcFunc['db_query']('', '
		SELECT /*!40001 SQL_NO_CACHE */ mem.id_member, COUNT(pmr.id_pm) AS real_num,
			MAX(mem.unread_messages) AS unread_messages
		FROM {db_prefix}members AS mem
			LEFT JOIN {db_prefix}pm_recipients AS pmr ON (mem.id_member = pmr.id_member AND pmr.deleted = {int:is_not_deleted} AND pmr.is_read = {int:is_not_read})
		GROUP BY mem.id_member
		HAVING COUNT(pmr.id_pm) != MAX(mem.unread_messages)',
		array(
			'is_not_deleted' => 0,
			'is_not_read' => 0,
		)
	);
	while ($row = $smcFunc['db_fetch_assoc']($request))
		updateMemberData($row['id_member'], array('unread_messages' => $row['real_num']));
	$smcFunc['db_free_result']($request);
}

/**
 * Fixes the column id_board from the messages table.
 * 
 * @param int $start
 * @param int $increment
 */
function updateMessagesBoardID($start, $increment)
{
	global $smcFunc;

	$request = $smcFunc['db_query']('', '
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
	while ($row = $smcFunc['db_fetch_assoc']($request))
		$boards[$row['id_board']][] = $row['id_msg'];
	$smcFunc['db_free_result']($request);

	foreach ($boards as $board_id => $messages)
		$smcFunc['db_query']('', '
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
 */
function updateBoardsLastMessage()
{
	global $smcFunc;

	// Update the latest message of each board.
	$request = $smcFunc['db_query']('', '
		SELECT m.id_board, MAX(m.id_msg) AS local_last_msg
		FROM {db_prefix}messages AS m
		WHERE m.approved = {int:is_approved}
		GROUP BY m.id_board',
		array(
			'is_approved' => 1,
		)
	);
	$realBoardCounts = array();
	while ($row = $smcFunc['db_fetch_assoc']($request))
		$realBoardCounts[$row['id_board']] = $row['local_last_msg'];
	$smcFunc['db_free_result']($request);

	$request = $smcFunc['db_query']('', '
		SELECT /*!40001 SQL_NO_CACHE */ id_board, id_parent, id_last_msg, child_level, id_msg_updated
		FROM {db_prefix}boards',
		array(
		)
	);
	$resort_me = array();
	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		$row['local_last_msg'] = isset($realBoardCounts[$row['id_board']]) ? $realBoardCounts[$row['id_board']] : 0;
		$resort_me[$row['child_level']][] = $row;
	}
	$smcFunc['db_free_result']($request);

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
				$smcFunc['db_query']('', '
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
 * @param int $id_board
 * @return int 
 */
function countTopicsFromBoard($id_board)
{
	global $smcFunc;

	$request = $smcFunc['db_query']('', '
		SELECT COUNT(*)
		FROM {db_prefix}topics
		WHERE id_board = {int:id_board}',
		array(
			'id_board' => $id_board,
		)
	);
	list ($total_topics) = $smcFunc['db_fetch_row']($request);
	$smcFunc['db_free_result']($request);

	return $total_topics;
}

/**
 * Gets a list of topics which should be moved to a different board.
 * 
 * @param type $id_board
 * @return type 
 */
function getTopicsToMove($id_board)
{
	global $smcFunc;

	$topics = array();
	
	// Lets get the topics.
	$request = $smcFunc['db_query']('', '
		SELECT id_topic
		FROM {db_prefix}topics
		WHERE id_board = {int:id_board}
		LIMIT 10',
		array(
			'id_board' => $id_board,
		)
	);

	// Get the ids.
	while ($row = $smcFunc['db_fetch_assoc']($request))
		$topics[] = $row['id_topic'];
	$smcFunc['db_free_result']($request);

	return $topics;
}

/**
 * Counts members with posts > 0, we name them contributors
 *
 * @return int
 */
function countContributors()
{
	global $smcFunc;

	$request = $smcFunc['db_query']('', '
		SELECT COUNT(DISTINCT m.id_member)
		FROM ({db_prefix}messages AS m, {db_prefix}boards AS b)
		WHERE m.id_member != 0
			AND b.count_posts = 0
			AND m.id_board = b.id_board',
		array(
		)
	);

	// save it so we don't do this again for this task
	list ($total_members) = $smcFunc['db_fetch_row']($request);
	$smcFunc['db_free_result']($request);

	return $total_members;
}

/**
 * Recount the members posts.
 *
 * @param type $start
 * @param type $increment
 * @return int
 */
function updateMembersPostCount($start, $increment)
{
	global $smcFunc, $modSettings;

	$request = $smcFunc['db_query']('', '
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
	$total_rows = $smcFunc['db_num_rows']($request);

	// Update the post count for this group
	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		$smcFunc['db_query']('', '
			UPDATE {db_prefix}members
			SET posts = {int:posts}
			WHERE id_member = {int:row}',
			array(
				'row' => $row['id_member'],
				'posts' => $row['posts'],
			)
		);
	}
	$smcFunc['db_free_result']($request);

	return $total_rows;
}

/**
 * Used to find members who have a post count >0 that should not..
 * made more difficult since we don't yet support sub-selects on joins
 * place all members who have posts in the message table in a temp table
 */
function updateZeroPostMembers()
{
	global $smcFunc, $modSettings;

	$createTemporary = $smcFunc['db_query']('', '
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
			)
		) !== false;

		if ($createTemporary)
		{
			// outer join the members table on the temporary table finding the members that have a post count but no posts in the message table
			$request = $smcFunc['db_query']('', '
				SELECT mem.id_member, mem.posts
				FROM {db_prefix}members AS mem
				LEFT OUTER JOIN {db_prefix}tmp_maint_recountposts AS res
				ON res.id_member = mem.id_member
				WHERE res.id_member IS null
					AND mem.posts != {int:zero}',
				array(
					'zero' => 0,
				)
			);

			// set the post count to zero for any delinquents we may have found
			while ($row = $smcFunc['db_fetch_assoc']($request))
			{
				$smcFunc['db_query']('', '
					UPDATE {db_prefix}members
					SET posts = {int:zero}
					WHERE id_member = {int:row}',
					array(
						'row' => $row['id_member'],
						'zero' => 0,
					)
				);
			}
			$smcFunc['db_free_result']($request);
		}
}