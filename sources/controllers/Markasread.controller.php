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
 * This is the main function for markasread file.
 * It forwards the request to the appropriate function.
 */
function markasread()
{
	// These checks have been moved here.
	// Do NOT call the specific handlers directly.

	// Guests can't mark things.
	is_not_guest();

	checkSession('get');

	// sa=all action_markboards()
	if (isset($_REQUEST['sa']) && $_REQUEST['sa'] == 'all')
		action_markboards();
	elseif (isset($_REQUEST['sa']) && $_REQUEST['sa'] == 'unreadreplies')
		// mark topics from unread
		action_markreplies();
	elseif (isset($_REQUEST['sa']) && $_REQUEST['sa'] == 'topic')
		// mark a single topic as read
		action_marktopic();
	else
		// the rest, for now...
		action_markasread();
}

/**
 * action=markasread;sa=all
 * Marks boards as read (or unread).
 */
function action_markboards()
{
	global $modSettings;

	require_once(SUBSDIR . '/Boards.subs.php');

	// Find all the boards this user can see.
	$boards = accessibleBoards();

	if (!empty($boards))
		markBoardsRead($boards, isset($_REQUEST['unread']));

	$_SESSION['id_msg_last_visit'] = $modSettings['maxMsgID'];
	if (!empty($_SESSION['old_url']) && strpos($_SESSION['old_url'], 'action=unread') !== false)
		redirectexit('action=unread');

	if (isset($_SESSION['topicseen_cache']))
		$_SESSION['topicseen_cache'] = array();

	redirectexit();
}

/**
 * action=markasread;sa=unreadreplies
 * Marks the selected topics as read.
 */
function action_markreplies()
{
	global $user_info, $modSettings, $smcFunc;

	// Make sure all the topics are integers!
	$topics = array_map('intval', explode('-', $_REQUEST['topics']));

	$request = $smcFunc['db_query']('', '
		SELECT id_topic, disregarded
		FROM {db_prefix}log_topics
		WHERE id_topic IN ({array_int:selected_topics})
			AND id_member = {int:current_user}',
		array(
			'selected_topics' => $topics,
			'current_user' => $user_info['id'],
		)
	);
	$logged_topics = array();
	while ($row = $smcFunc['db_fetch_assoc']($request))
		$logged_topics[$row['id_topic']] = $row['disregarded'];
	$smcFunc['db_free_result']($request);

	$markRead = array();
	foreach ($topics as $id_topic)
		$markRead[] = array($user_info['id'], (int) $id_topic, $modSettings['maxMsgID'], $logged_topics[$id_topic]);

	require_once(SUBSDIR . '/Topic.subs.php');
	markTopicsRead($markRead, true);

	if (isset($_SESSION['topicseen_cache']))
		$_SESSION['topicseen_cache'] = array();

	redirectexit('action=unreadreplies');
}

/**
 * action=markasread;sa=topic
 * Mark a single topic as unread.
 */
function action_marktopic()
{
	global $board, $topic, $user_info, $smcFunc;

	require_once(SUBSDIR . '/Topic.subs.php');

	// Mark a topic unread.
	// First, let's figure out what the latest message is.
	$topicinfo = getTopicInfo($topic, 'all');

	if (!empty($_GET['t']))
	{
		// If they read the whole topic, go back to the beginning.
		if ($_GET['t'] >= $topicinfo['id_last_msg'])
			$earlyMsg = 0;
		// If they want to mark the whole thing read, same.
		elseif ($_GET['t'] <= $topicinfo['id_first_msg'])
			$earlyMsg = 0;
		// Otherwise, get the latest message before the named one.
		else
		{
			$result = $smcFunc['db_query']('', '
				SELECT MAX(id_msg)
				FROM {db_prefix}messages
				WHERE id_topic = {int:current_topic}
					AND id_msg >= {int:id_first_msg}
					AND id_msg < {int:topic_msg_id}',
				array(
					'current_topic' => $topic,
					'topic_msg_id' => (int) $_GET['t'],
					'id_first_msg' => $topicinfo['id_first_msg'],
				)
			);
			list ($earlyMsg) = $smcFunc['db_fetch_row']($result);
			$smcFunc['db_free_result']($result);
		}
	}
	// Marking read from first page?  That's the whole topic.
	elseif ($_REQUEST['start'] == 0)
		$earlyMsg = 0;
	else
	{
		$result = $smcFunc['db_query']('', '
			SELECT id_msg
			FROM {db_prefix}messages
			WHERE id_topic = {int:current_topic}
			ORDER BY id_msg
			LIMIT {int:start}, 1',
			array(
				'current_topic' => $topic,
				'start' => (int) $_REQUEST['start'],
			)
		);
		list ($earlyMsg) = $smcFunc['db_fetch_row']($result);
		$smcFunc['db_free_result']($result);

		$earlyMsg--;
	}

	// Blam, unread!
	markTopicsRead(array($user_info['id'], $topic, $earlyMsg, $topicinfo['disregarded']), true);

	redirectexit('board=' . $board . '.0');
}

/**
 * Mark as read: boards, topics, unread replies.
 * Accessed by action=markasread
 * Subactions: sa=topic, sa=all, sa=unreadreplies
 */
function action_markasread()
{
	global $board, $user_info, $board_info, $modSettings, $smcFunc;

	// no guests
	is_not_guest();

	checkSession('get');

	require_once(SUBSDIR . '/Boards.subs.php');

	$categories = array();
	$boards = array();

	if (isset($_REQUEST['c']))
	{
		$_REQUEST['c'] = explode(',', $_REQUEST['c']);
		foreach ($_REQUEST['c'] as $c)
			$categories[] = (int) $c;
	}
	if (isset($_REQUEST['boards']))
	{
		$_REQUEST['boards'] = explode(',', $_REQUEST['boards']);
		foreach ($_REQUEST['boards'] as $b)
			$boards[] = (int) $b;
	}
	if (!empty($board))
		$boards[] = (int) $board;

	if (isset($_REQUEST['children']) && !empty($boards))
	{
		// They want to mark the entire tree starting with the boards specified
		// The easist thing is to just get all the boards they can see, but since we've specified the top of tree we ignore some of them

		$request = $smcFunc['db_query']('', '
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
		while ($row = $smcFunc['db_fetch_assoc']($request))
			if (in_array($row['id_parent'], $boards))
				$boards[] = $row['id_board'];
		$smcFunc['db_free_result']($request);
	}

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
		redirectexit();

	$request = $smcFunc['db_query']('', '
		SELECT b.id_board
		FROM {db_prefix}boards AS b
		WHERE {query_see_board}
			AND b.' . implode(' OR b.', $clauses),
		array_merge($clauseParameters, array(
		))
	);
	$boards = array();
	while ($row = $smcFunc['db_fetch_assoc']($request))
		$boards[] = $row['id_board'];
	$smcFunc['db_free_result']($request);

	if (empty($boards))
		redirectexit();

	markBoardsRead($boards, isset($_REQUEST['unread']));

	foreach ($boards as $b)
	{
		if (isset($_SESSION['topicseen_cache'][$b]))
			$_SESSION['topicseen_cache'][$b] = array();
	}

	if (!isset($_REQUEST['unread']))
	{
		// Find all the boards this user can see.
		$result = $smcFunc['db_query']('', '
			SELECT b.id_board
			FROM {db_prefix}boards AS b
			WHERE b.id_parent IN ({array_int:parent_list})
				AND {query_see_board}',
			array(
				'parent_list' => $boards,
			)
		);
		if ($smcFunc['db_num_rows']($result) > 0)
		{
			$logBoardInserts = '';
			while ($row = $smcFunc['db_fetch_assoc']($result))
				$logBoardInserts[] = array($modSettings['maxMsgID'], $user_info['id'], $row['id_board']);
				$smcFunc['db_insert']('replace',
				'{db_prefix}log_boards',
				array('id_msg' => 'int', 'id_member' => 'int', 'id_board' => 'int'),
				$logBoardInserts,
				array('id_member', 'id_board')
			);
		}
		$smcFunc['db_free_result']($result);
		if (empty($board))
			redirectexit();
		else
			redirectexit('board=' . $board . '.0');
	}
	else
	{
		if (empty($board_info['parent']))
			redirectexit();
		else
			redirectexit('board=' . $board_info['parent'] . '.0');
	}
}