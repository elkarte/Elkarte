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
 * This file contains functions for dealing with topics. Low-level functions,
 * i.e. database operations needed to perform.
 * These functions do NOT make permissions checks. (they assume those were
 * already made).
 *
 */

if (!defined('ELKARTE'))
	die('No access...');

/**
 * Removes the passed id_topic's. (permissions are NOT checked here!).
 *
 * @param array/int $topics The topics to remove (can be an id or an array of ids).
 * @param bool $decreasePostCount if true users' post count will be reduced
 * @param bool $ignoreRecycling if true topics are not moved to the recycle board (if it exists).
 */
function removeTopics($topics, $decreasePostCount = true, $ignoreRecycling = false)
{
	global $modSettings, $smcFunc;

	// Nothing to do?
	if (empty($topics))
		return;

	// Only a single topic.
	if (is_numeric($topics))
		$topics = array($topics);

	// Decrease the post counts.
	if ($decreasePostCount)
	{
		$requestMembers = $smcFunc['db_query']('', '
			SELECT m.id_member, COUNT(*) AS posts
			FROM {db_prefix}messages AS m
				INNER JOIN {db_prefix}boards AS b ON (b.id_board = m.id_board)
			WHERE m.id_topic IN ({array_int:topics})
				AND m.icon != {string:recycled}
				AND b.count_posts = {int:do_count_posts}
				AND m.approved = {int:is_approved}
			GROUP BY m.id_member',
			array(
				'do_count_posts' => 0,
				'recycled' => 'recycled',
				'topics' => $topics,
				'is_approved' => 1,
			)
		);
		if ($smcFunc['db_num_rows']($requestMembers) > 0)
		{
			while ($rowMembers = $smcFunc['db_fetch_assoc']($requestMembers))
				updateMemberData($rowMembers['id_member'], array('posts' => 'posts - ' . $rowMembers['posts']));
		}
		$smcFunc['db_free_result']($requestMembers);
	}

	// Recycle topics that aren't in the recycle board...
	if (!empty($modSettings['recycle_enable']) && $modSettings['recycle_board'] > 0 && !$ignoreRecycling)
	{
		$request = $smcFunc['db_query']('', '
			SELECT id_topic, id_board, unapproved_posts, approved
			FROM {db_prefix}topics
			WHERE id_topic IN ({array_int:topics})
				AND id_board != {int:recycle_board}
			LIMIT ' . count($topics),
			array(
				'recycle_board' => $modSettings['recycle_board'],
				'topics' => $topics,
			)
		);
		if ($smcFunc['db_num_rows']($request) > 0)
		{
			// Get topics that will be recycled.
			$recycleTopics = array();
			while ($row = $smcFunc['db_fetch_assoc']($request))
			{
				if (function_exists('apache_reset_timeout'))
					@apache_reset_timeout();

				$recycleTopics[] = $row['id_topic'];

				// Set the id_previous_board for this topic - and make it not sticky.
				$smcFunc['db_query']('', '
					UPDATE {db_prefix}topics
					SET id_previous_board = {int:id_previous_board}, is_sticky = {int:not_sticky}
					WHERE id_topic = {int:id_topic}',
					array(
						'id_previous_board' => $row['id_board'],
						'id_topic' => $row['id_topic'],
						'not_sticky' => 0,
					)
				);
			}
			$smcFunc['db_free_result']($request);

			// Mark recycled topics as recycled.
			$smcFunc['db_query']('', '
				UPDATE {db_prefix}messages
				SET icon = {string:recycled}
				WHERE id_topic IN ({array_int:recycle_topics})',
				array(
					'recycle_topics' => $recycleTopics,
					'recycled' => 'recycled',
				)
			);

			// Move the topics to the recycle board.
			require_once(SUBSDIR . '/Topic.subs.php');
			moveTopics($recycleTopics, $modSettings['recycle_board']);

			// Close reports that are being recycled.
			require_once(SUBSDIR . '/Moderation.subs.php');

			$smcFunc['db_query']('', '
				UPDATE {db_prefix}log_reported
				SET closed = {int:is_closed}
				WHERE id_topic IN ({array_int:recycle_topics})',
				array(
					'recycle_topics' => $recycleTopics,
					'is_closed' => 1,
				)
			);

			updateSettings(array('last_mod_report_action' => time()));
			recountOpenReports();

			// Topics that were recycled don't need to be deleted, so subtract them.
			$topics = array_diff($topics, $recycleTopics);
		}
		else
			$smcFunc['db_free_result']($request);
	}

	// Still topics left to delete?
	if (empty($topics))
		return;

	$adjustBoards = array();

	// Find out how many posts we are deleting.
	$request = $smcFunc['db_query']('', '
		SELECT id_board, approved, COUNT(*) AS num_topics, SUM(unapproved_posts) AS unapproved_posts,
			SUM(num_replies) AS num_replies
		FROM {db_prefix}topics
		WHERE id_topic IN ({array_int:topics})
		GROUP BY id_board, approved',
		array(
			'topics' => $topics,
		)
	);
	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		if (!isset($adjustBoards[$row['id_board']]['num_posts']))
		{
			$adjustBoards[$row['id_board']] = array(
				'num_posts' => 0,
				'num_topics' => 0,
				'unapproved_posts' => 0,
				'unapproved_topics' => 0,
				'id_board' => $row['id_board']
			);
		}
		// Posts = (num_replies + 1) for each approved topic.
		$adjustBoards[$row['id_board']]['num_posts'] += $row['num_replies'] + ($row['approved'] ? $row['num_topics'] : 0);
		$adjustBoards[$row['id_board']]['unapproved_posts'] += $row['unapproved_posts'];

		// Add the topics to the right type.
		if ($row['approved'])
			$adjustBoards[$row['id_board']]['num_topics'] += $row['num_topics'];
		else
			$adjustBoards[$row['id_board']]['unapproved_topics'] += $row['num_topics'];
	}
	$smcFunc['db_free_result']($request);

	// Decrease the posts/topics...
	foreach ($adjustBoards as $stats)
	{
		if (function_exists('apache_reset_timeout'))
			@apache_reset_timeout();

		$smcFunc['db_query']('', '
			UPDATE {db_prefix}boards
			SET
				num_posts = CASE WHEN {int:num_posts} > num_posts THEN 0 ELSE num_posts - {int:num_posts} END,
				num_topics = CASE WHEN {int:num_topics} > num_topics THEN 0 ELSE num_topics - {int:num_topics} END,
				unapproved_posts = CASE WHEN {int:unapproved_posts} > unapproved_posts THEN 0 ELSE unapproved_posts - {int:unapproved_posts} END,
				unapproved_topics = CASE WHEN {int:unapproved_topics} > unapproved_topics THEN 0 ELSE unapproved_topics - {int:unapproved_topics} END
			WHERE id_board = {int:id_board}',
			array(
				'id_board' => $stats['id_board'],
				'num_posts' => $stats['num_posts'],
				'num_topics' => $stats['num_topics'],
				'unapproved_posts' => $stats['unapproved_posts'],
				'unapproved_topics' => $stats['unapproved_topics'],
			)
		);
	}

	// Remove Polls.
	$request = $smcFunc['db_query']('', '
		SELECT id_poll
		FROM {db_prefix}topics
		WHERE id_topic IN ({array_int:topics})
			AND id_poll > {int:no_poll}
		LIMIT ' . count($topics),
		array(
			'no_poll' => 0,
			'topics' => $topics,
		)
	);
	$polls = array();
	while ($row = $smcFunc['db_fetch_assoc']($request))
		$polls[] = $row['id_poll'];
	$smcFunc['db_free_result']($request);

	if (!empty($polls))
	{
		$smcFunc['db_query']('', '
			DELETE FROM {db_prefix}polls
			WHERE id_poll IN ({array_int:polls})',
			array(
				'polls' => $polls,
			)
		);
		$smcFunc['db_query']('', '
			DELETE FROM {db_prefix}poll_choices
			WHERE id_poll IN ({array_int:polls})',
			array(
				'polls' => $polls,
			)
		);
		$smcFunc['db_query']('', '
			DELETE FROM {db_prefix}log_polls
			WHERE id_poll IN ({array_int:polls})',
			array(
				'polls' => $polls,
			)
		);
	}

	// Get rid of the attachment(s), if they exist.
	require_once(SUBSDIR . '/Attachments.subs.php');
	$attachmentQuery = array(
		'attachment_type' => 0,
		'id_topic' => $topics,
	);
	removeAttachments($attachmentQuery, 'messages');

	// Delete possible search index entries.
	if (!empty($modSettings['search_custom_index_config']))
	{
		$customIndexSettings = unserialize($modSettings['search_custom_index_config']);

		$words = array();
		$messages = array();
		$request = $smcFunc['db_query']('', '
			SELECT id_msg, body
			FROM {db_prefix}messages
			WHERE id_topic IN ({array_int:topics})',
			array(
				'topics' => $topics,
			)
		);
		while ($row = $smcFunc['db_fetch_assoc']($request))
		{
			if (function_exists('apache_reset_timeout'))
				@apache_reset_timeout();

			$words = array_merge($words, text2words($row['body'], $customIndexSettings['bytes_per_word'], true));
			$messages[] = $row['id_msg'];
		}
		$smcFunc['db_free_result']($request);
		$words = array_unique($words);

		if (!empty($words) && !empty($messages))
			$smcFunc['db_query']('', '
				DELETE FROM {db_prefix}log_search_words
				WHERE id_word IN ({array_int:word_list})
					AND id_msg IN ({array_int:message_list})',
				array(
					'word_list' => $words,
					'message_list' => $messages,
				)
			);
	}

	// Delete anything related to the topic.
	$smcFunc['db_query']('', '
		DELETE FROM {db_prefix}messages
		WHERE id_topic IN ({array_int:topics})',
		array(
			'topics' => $topics,
		)
	);
	$smcFunc['db_query']('', '
		DELETE FROM {db_prefix}calendar
		WHERE id_topic IN ({array_int:topics})',
		array(
			'topics' => $topics,
		)
	);
	$smcFunc['db_query']('', '
		DELETE FROM {db_prefix}log_topics
		WHERE id_topic IN ({array_int:topics})',
		array(
			'topics' => $topics,
		)
	);
	$smcFunc['db_query']('', '
		DELETE FROM {db_prefix}log_notify
		WHERE id_topic IN ({array_int:topics})',
		array(
			'topics' => $topics,
		)
	);
	$smcFunc['db_query']('', '
		DELETE FROM {db_prefix}topics
		WHERE id_topic IN ({array_int:topics})',
		array(
			'topics' => $topics,
		)
	);
	$smcFunc['db_query']('', '
		DELETE FROM {db_prefix}log_search_subjects
		WHERE id_topic IN ({array_int:topics})',
		array(
			'topics' => $topics,
		)
	);

	// Maybe there's a mod that wants to delete topic related data of its own
 	call_integration_hook('integrate_remove_topics', array($topics));

	// Update the totals...
	updateStats('message');
	updateStats('topic');
	updateSettings(array(
		'calendar_updated' => time(),
	));

	require_once(SUBSDIR . '/Post.subs.php');
	$updates = array();
	foreach ($adjustBoards as $stats)
		$updates[] = $stats['id_board'];
	updateLastMessages($updates);
}

/**
 * Moves one or more topics to a specific board. (doesn't check permissions.)
 * Determines the source boards for the supplied topics
 * Handles the moving of mark_read data
 * Updates the posts count of the affected boards
 *
 * @param type $topics
 * @param type $toBoard
 * @return type
 */
function moveTopics($topics, $toBoard)
{
	global $user_info, $modSettings, $smcFunc;

	// Empty array?
	if (empty($topics))
		return;

	// Only a single topic.
	if (is_numeric($topics))
		$topics = array($topics);
	$num_topics = count($topics);
	$fromBoards = array();

	// Destination board empty or equal to 0?
	if (empty($toBoard))
		return;

	// Are we moving to the recycle board?
	$isRecycleDest = !empty($modSettings['recycle_enable']) && $modSettings['recycle_board'] == $toBoard;

	// Determine the source boards...
	$request = $smcFunc['db_query']('', '
		SELECT id_board, approved, COUNT(*) AS num_topics, SUM(unapproved_posts) AS unapproved_posts,
			SUM(num_replies) AS num_replies
		FROM {db_prefix}topics
		WHERE id_topic IN ({array_int:topics})
		GROUP BY id_board, approved',
		array(
			'topics' => $topics,
		)
	);
	// Num of rows = 0 -> no topics found. Num of rows > 1 -> topics are on multiple boards.
	if ($smcFunc['db_num_rows']($request) == 0)
		return;
	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		if (!isset($fromBoards[$row['id_board']]['num_posts']))
		{
			$fromBoards[$row['id_board']] = array(
				'num_posts' => 0,
				'num_topics' => 0,
				'unapproved_posts' => 0,
				'unapproved_topics' => 0,
				'id_board' => $row['id_board']
			);
		}
		// Posts = (num_replies + 1) for each approved topic.
		$fromBoards[$row['id_board']]['num_posts'] += $row['num_replies'] + ($row['approved'] ? $row['num_topics'] : 0);
		$fromBoards[$row['id_board']]['unapproved_posts'] += $row['unapproved_posts'];

		// Add the topics to the right type.
		if ($row['approved'])
			$fromBoards[$row['id_board']]['num_topics'] += $row['num_topics'];
		else
			$fromBoards[$row['id_board']]['unapproved_topics'] += $row['num_topics'];
	}
	$smcFunc['db_free_result']($request);

	// Move over the mark_read data. (because it may be read and now not by some!)
	$SaveAServer = max(0, $modSettings['maxMsgID'] - 50000);
	$request = $smcFunc['db_query']('', '
		SELECT lmr.id_member, lmr.id_msg, t.id_topic, IFNULL(lt.disregarded, 0) as disregarded
		FROM {db_prefix}topics AS t
			INNER JOIN {db_prefix}log_mark_read AS lmr ON (lmr.id_board = t.id_board
				AND lmr.id_msg > t.id_first_msg AND lmr.id_msg > {int:protect_lmr_msg})
			LEFT JOIN {db_prefix}log_topics AS lt ON (lt.id_topic = t.id_topic AND lt.id_member = lmr.id_member)
		WHERE t.id_topic IN ({array_int:topics})
			AND lmr.id_msg > IFNULL(lt.id_msg, 0)',
		array(
			'protect_lmr_msg' => $SaveAServer,
			'topics' => $topics,
		)
	);
	$log_topics = array();
	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		$log_topics[] = array($row['id_member'], $row['id_topic'], $row['id_msg'], $row['disregarded']);

		// Prevent queries from getting too big. Taking some steam off.
		if (count($log_topics) > 500)
		{
			markTopicsRead($log_topics, true);
			$log_topics = array();
		}
	}
	$smcFunc['db_free_result']($request);

	// Now that we have all the topics that *should* be marked read, and by which members...
	if (!empty($log_topics))
	{
		// Insert that information into the database!
		markTopicsRead($log_topics, true);
	}

	// Update the number of posts on each board.
	$totalTopics = 0;
	$totalPosts = 0;
	$totalUnapprovedTopics = 0;
	$totalUnapprovedPosts = 0;
	foreach ($fromBoards as $stats)
	{
		$smcFunc['db_query']('', '
			UPDATE {db_prefix}boards
			SET
				num_posts = CASE WHEN {int:num_posts} > num_posts THEN 0 ELSE num_posts - {int:num_posts} END,
				num_topics = CASE WHEN {int:num_topics} > num_topics THEN 0 ELSE num_topics - {int:num_topics} END,
				unapproved_posts = CASE WHEN {int:unapproved_posts} > unapproved_posts THEN 0 ELSE unapproved_posts - {int:unapproved_posts} END,
				unapproved_topics = CASE WHEN {int:unapproved_topics} > unapproved_topics THEN 0 ELSE unapproved_topics - {int:unapproved_topics} END
			WHERE id_board = {int:id_board}',
			array(
				'id_board' => $stats['id_board'],
				'num_posts' => $stats['num_posts'],
				'num_topics' => $stats['num_topics'],
				'unapproved_posts' => $stats['unapproved_posts'],
				'unapproved_topics' => $stats['unapproved_topics'],
			)
		);
		$totalTopics += $stats['num_topics'];
		$totalPosts += $stats['num_posts'];
		$totalUnapprovedTopics += $stats['unapproved_topics'];
		$totalUnapprovedPosts += $stats['unapproved_posts'];
	}
	$smcFunc['db_query']('', '
		UPDATE {db_prefix}boards
		SET
			num_topics = num_topics + {int:total_topics},
			num_posts = num_posts + {int:total_posts},' . ($isRecycleDest ? '
			unapproved_posts = {int:no_unapproved}, unapproved_topics = {int:no_unapproved}' : '
			unapproved_posts = unapproved_posts + {int:total_unapproved_posts},
			unapproved_topics = unapproved_topics + {int:total_unapproved_topics}') . '
		WHERE id_board = {int:id_board}',
		array(
			'id_board' => $toBoard,
			'total_topics' => $totalTopics,
			'total_posts' => $totalPosts,
			'total_unapproved_topics' => $totalUnapprovedTopics,
			'total_unapproved_posts' => $totalUnapprovedPosts,
			'no_unapproved' => 0,
		)
	);

	// Move the topic.  Done.  :P
	$smcFunc['db_query']('', '
		UPDATE {db_prefix}topics
		SET id_board = {int:id_board}' . ($isRecycleDest ? ',
			unapproved_posts = {int:no_unapproved}, approved = {int:is_approved}' : '') . '
		WHERE id_topic IN ({array_int:topics})',
		array(
			'id_board' => $toBoard,
			'topics' => $topics,
			'is_approved' => 1,
			'no_unapproved' => 0,
		)
	);

	// If this was going to the recycle bin, check what messages are being recycled, and remove them from the queue.
	if ($isRecycleDest && ($totalUnapprovedTopics || $totalUnapprovedPosts))
	{
		$request = $smcFunc['db_query']('', '
			SELECT id_msg
			FROM {db_prefix}messages
			WHERE id_topic IN ({array_int:topics})
				and approved = {int:not_approved}',
			array(
				'topics' => $topics,
				'not_approved' => 0,
			)
		);
		$approval_msgs = array();
		while ($row = $smcFunc['db_fetch_assoc']($request))
			$approval_msgs[] = $row['id_msg'];
		$smcFunc['db_free_result']($request);

		// Empty the approval queue for these, as we're going to approve them next.
		if (!empty($approval_msgs))
			$smcFunc['db_query']('', '
				DELETE FROM {db_prefix}approval_queue
				WHERE id_msg IN ({array_int:message_list})
					AND id_attach = {int:id_attach}',
				array(
					'message_list' => $approval_msgs,
					'id_attach' => 0,
				)
			);

		// Get all the current max and mins.
		$request = $smcFunc['db_query']('', '
			SELECT id_topic, id_first_msg, id_last_msg
			FROM {db_prefix}topics
			WHERE id_topic IN ({array_int:topics})',
			array(
				'topics' => $topics,
			)
		);
		$topicMaxMin = array();
		while ($row = $smcFunc['db_fetch_assoc']($request))
		{
			$topicMaxMin[$row['id_topic']] = array(
				'min' => $row['id_first_msg'],
				'max' => $row['id_last_msg'],
			);
		}
		$smcFunc['db_free_result']($request);

		// Check the MAX and MIN are correct.
		$request = $smcFunc['db_query']('', '
			SELECT id_topic, MIN(id_msg) AS first_msg, MAX(id_msg) AS last_msg
			FROM {db_prefix}messages
			WHERE id_topic IN ({array_int:topics})
			GROUP BY id_topic',
			array(
				'topics' => $topics,
			)
		);
		while ($row = $smcFunc['db_fetch_assoc']($request))
		{
			// If not, update.
			if ($row['first_msg'] != $topicMaxMin[$row['id_topic']]['min'] || $row['last_msg'] != $topicMaxMin[$row['id_topic']]['max'])
				$smcFunc['db_query']('', '
					UPDATE {db_prefix}topics
					SET id_first_msg = {int:first_msg}, id_last_msg = {int:last_msg}
					WHERE id_topic = {int:selected_topic}',
					array(
						'first_msg' => $row['first_msg'],
						'last_msg' => $row['last_msg'],
						'selected_topic' => $row['id_topic'],
					)
				);
		}
		$smcFunc['db_free_result']($request);
	}

	$smcFunc['db_query']('', '
		UPDATE {db_prefix}messages
		SET id_board = {int:id_board}' . ($isRecycleDest ? ',approved = {int:is_approved}' : '') . '
		WHERE id_topic IN ({array_int:topics})',
		array(
			'id_board' => $toBoard,
			'topics' => $topics,
			'is_approved' => 1,
		)
	);
	$smcFunc['db_query']('', '
		UPDATE {db_prefix}log_reported
		SET id_board = {int:id_board}
		WHERE id_topic IN ({array_int:topics})',
		array(
			'id_board' => $toBoard,
			'topics' => $topics,
		)
	);
	$smcFunc['db_query']('', '
		UPDATE {db_prefix}calendar
		SET id_board = {int:id_board}
		WHERE id_topic IN ({array_int:topics})',
		array(
			'id_board' => $toBoard,
			'topics' => $topics,
		)
	);

	// Mark target board as seen, if it was already marked as seen before.
	$request = $smcFunc['db_query']('', '
		SELECT (IFNULL(lb.id_msg, 0) >= b.id_msg_updated) AS isSeen
		FROM {db_prefix}boards AS b
			LEFT JOIN {db_prefix}log_boards AS lb ON (lb.id_board = b.id_board AND lb.id_member = {int:current_member})
		WHERE b.id_board = {int:id_board}',
		array(
			'current_member' => $user_info['id'],
			'id_board' => $toBoard,
		)
	);
	list ($isSeen) = $smcFunc['db_fetch_row']($request);
	$smcFunc['db_free_result']($request);

	if (!empty($isSeen) && !$user_info['is_guest'])
	{
		$smcFunc['db_insert']('replace',
			'{db_prefix}log_boards',
			array('id_board' => 'int', 'id_member' => 'int', 'id_msg' => 'int'),
			array($toBoard, $user_info['id'], $modSettings['maxMsgID']),
			array('id_board', 'id_member')
		);
	}

	// Update the cache?
	if (!empty($modSettings['cache_enable']) && $modSettings['cache_enable'] >= 3)
		foreach ($topics as $topic_id)
			cache_put_data('topic_board-' . $topic_id, null, 120);

	require_once(SUBSDIR . '/Post.subs.php');

	$updates = array_keys($fromBoards);
	$updates[] = $toBoard;

	updateLastMessages(array_unique($updates));

	// Update 'em pesky stats.
	updateStats('topic');
	updateStats('message');
	updateSettings(array(
		'calendar_updated' => time(),
	));
}

/**
 * Called after a topic is moved to update $board_link and $topic_link to point to new location
 */
function moveTopicConcurrence()
{
	global $board, $topic, $smcFunc, $scripturl;

	if (isset($_GET['current_board']))
		$move_from = (int) $_GET['current_board'];

	if (empty($move_from) || empty($board) || empty($topic))
		return true;

	if ($move_from == $board)
		return true;
	else
	{
		$request = $smcFunc['db_query']('', '
			SELECT m.subject, b.name
			FROM {db_prefix}topics as t
				LEFT JOIN {db_prefix}boards AS b ON (t.id_board = b.id_board)
				LEFT JOIN {db_prefix}messages AS m ON (t.id_first_msg = m.id_msg)
			WHERE t.id_topic = {int:topic_id}
			LIMIT 1',
			array(
				'topic_id' => $topic,
			)
		);
		list($topic_subject, $board_name) = $smcFunc['db_fetch_row']($request);
		$smcFunc['db_free_result']($request);

		$board_link = '<a href="' . $scripturl . '?board=' . $board . '.0">' . $board_name . '</a>';
		$topic_link = '<a href="' . $scripturl . '?topic=' . $topic . '.0">' . $topic_subject . '</a>';
		fatal_lang_error('topic_already_moved', false, array($topic_link, $board_link));
	}
}

/**
 * Increase the number of views of this topic.
 *
 * @param int $id_topic, the topic being viewed or whatnot.
 */
function increaseViewCounter($id_topic)
{
	global $smcFunc;

	$smcFunc['db_query']('', '
		UPDATE {db_prefix}topics
		SET num_views = num_views + 1
		WHERE id_topic = {int:current_topic}',
		array(
			'current_topic' => $id_topic,
		)
	);
}

/**
 * Mark topic(s) as read by the given member, at the specified message.
 *
 * @param array $mark_topics array($id_member, $id_topic, $id_msg)
 * @param bool $was_set = false - whether the topic has been previously read by the user
 */
function markTopicsRead($mark_topics, $was_set = false)
{
	global $smcFunc;

	if (!is_array($mark_topics))
		return;

	$smcFunc['db_insert']($was_set ? 'replace' : 'ignore',
		'{db_prefix}log_topics',
		array(
			'id_member' => 'int', 'id_topic' => 'int', 'id_msg' => 'int', 'disregarded' => 'int',
		),
		$mark_topics,
		array('id_member', 'id_topic')
	);
}

/**
 * Update user notifications for a topic... or the board it's in.
 * @todo look at board notification...
 *
 * @param int $id_topic
 * @param int $id_board
 */
function updateReadNotificationsFor($id_topic, $id_board)
{
	global $smcFunc, $user_info, $context;

	// Check for notifications on this topic OR board.
	$request = $smcFunc['db_query']('', '
		SELECT sent, id_topic
		FROM {db_prefix}log_notify
		WHERE (id_topic = {int:current_topic} OR id_board = {int:current_board})
			AND id_member = {int:current_member}
		LIMIT 2',
		array(
			'current_board' => $id_board,
			'current_member' => $user_info['id'],
			'current_topic' => $id_topic,
		)
	);

	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		// Find if this topic is marked for notification...
		if (!empty($row['id_topic']))
			$context['is_marked_notify'] = true;

		// Only do this once, but mark the notifications as "not sent yet" for next time.
		if (!empty($row['sent']))
		{
			$smcFunc['db_query']('', '
				UPDATE {db_prefix}log_notify
				SET sent = {int:is_not_sent}
				WHERE (id_topic = {int:current_topic} OR id_board = {int:current_board})
					AND id_member = {int:current_member}',
				array(
					'current_board' => $id_board,
					'current_member' => $user_info['id'],
					'current_topic' => $id_topic,
					'is_not_sent' => 0,
				)
			);
			break;
		}
	}
	$smcFunc['db_free_result']($request);
}

/**
 * How many topics are still unread since (last visit)
 *
 * @param int $id_msg_last_visit
 * @return int
 */
function getUnreadCountSince($id_board, $id_msg_last_visit)
{
	global $smcFunc, $user_info;

	$request = $smcFunc['db_query']('', '
		SELECT COUNT(*)
		FROM {db_prefix}topics AS t
			LEFT JOIN {db_prefix}log_boards AS lb ON (lb.id_board = {int:current_board} AND lb.id_member = {int:current_member})
			LEFT JOIN {db_prefix}log_topics AS lt ON (lt.id_topic = t.id_topic AND lt.id_member = {int:current_member})
		WHERE t.id_board = {int:current_board}
			AND t.id_last_msg > IFNULL(lb.id_msg, 0)
			AND t.id_last_msg > IFNULL(lt.id_msg, 0)' .
				(empty($id_msg_last_visit) ? '' : '
			AND t.id_last_msg > {int:id_msg_last_visit}'),
		array(
			'current_board' => $id_board,
			'current_member' => $user_info['id'],
			'id_msg_last_visit' => (int) $id_msg_last_visit,
		)
	);
	list ($unread) = $smcFunc['db_fetch_row']($request);
	$smcFunc['db_free_result']($request);

	return $unread;
}

/**
 * Returns whether this member has notification turned on for the specified topic.
 *
 * @param int $id_member
 * @param int $id_topic
 * @return bool
 */
function hasTopicNotification($id_member, $id_topic)
{
	global $smcFunc;

	// Find out if they have notification set for this topic already.
	$request = $smcFunc['db_query']('', '
		SELECT id_member
		FROM {db_prefix}log_notify
		WHERE id_member = {int:current_member}
			AND id_topic = {int:current_topic}
		LIMIT 1',
		array(
			'current_member' => $id_member,
			'current_topic' => $id_topic,
		)
	);
	$hasNotification = $smcFunc['db_num_rows']($request) != 0;
	$smcFunc['db_free_result']($request);

	return $hasNotification;
}

/**
 * Set topic notification on or off for the given member.
 *
 * @param int $id_member
 * @param int $id_topic
 * @param bool $on
 */
function setTopicNotification($id_member, $id_topic, $on = false)
{
	global $smcFunc;

	if ($on)
	{
		// Attempt to turn notifications on.
		$smcFunc['db_insert']('ignore',
			'{db_prefix}log_notify',
			array('id_member' => 'int', 'id_topic' => 'int'),
			array($id_member, $id_topic),
			array('id_member', 'id_topic')
		);
	}
	else
	{
		// Just turn notifications off.
		$smcFunc['db_query']('', '
			DELETE FROM {db_prefix}log_notify
			WHERE id_member = {int:current_member}
				AND id_topic = {int:current_topic}',
			array(
				'current_member' => $id_member,
				'current_topic' => $id_topic,
			)
		);
	}
}

/**
 * Get the previous topic from where we are.
 *
 * @param int $id_topic origin topic id
 * @param int $id_board board id
 * @param int $id_member = 0 member id
 * @param bool $includeUnapproved = false whether to include unapproved topics
 * @param bool $includeStickies = true whether to include sticky topics
 */
function previousTopic($id_topic, $id_board, $id_member = 0, $includeUnapproved = false, $includeStickies = true)
{
	return topicPointer($id_topic, $id_board, false, $id_member = 0, $includeUnapproved = false, $includeStickies = true);
}

/**
 * Get the next topic from where we are.
 *
 * @param int $id_topic origin topic id
 * @param int $id_board board id
 * @param int $id_member = 0 member id
 * @param bool $includeUnapproved = false whether to include unapproved topics
 * @param bool $includeStickies = true whether to include sticky topics
 */
function nextTopic($id_topic, $id_board, $id_member = 0, $includeUnapproved = false, $includeStickies = true)
{
	return topicPointer($id_topic, $id_board, true, $id_member = 0, $includeUnapproved = false, $includeStickies = true);
}

/**
 * Advance topic pointer.
 * (in either direction)
 * This function is used by previousTopic() and nextTopic()
 * The boolean parameter $next determines direction.
 *
 * @param int $id_topic origin topic id
 * @param int $id_board board id
 * @param bool $next = true whether to increase or decrease the pointer
 * @param int $id_member = 0 member id
 * @param bool $includeUnapproved = false whether to include unapproved topics
 * @param bool $includeStickies = true whether to include sticky topics
 */
function topicPointer($id_topic, $id_board, $next = true, $id_member = 0, $includeUnapproved = false, $includeStickies = true)
{
	global $smcFunc;

	$request = $smcFunc['db_query']('', '
		SELECT t2.id_topic
		FROM {db_prefix}topics AS t
			INNER JOIN {db_prefix}topics AS t2 ON (' .
			(empty($includeStickies) ? '
				t2.id_last_msg {raw:strictly} t.id_last_msg' : '
				(t2.id_last_msg {raw:strictly} t.id_last_msg AND t2.is_sticky {raw:strictly_equal} t.is_sticky) OR t2.is_sticky {raw:strictly} t.is_sticky')
			. ')
		WHERE t.id_topic = {int:current_topic}
			AND t2.id_board = {int:current_board}' .
			($includeUnapproved ? '' : '
				AND (t2.approved = {int:is_approved} OR (t2.id_member_started != {int:id_member_started} AND t2.id_member_started = {int:current_member}))'
				) . '
		ORDER BY' . (
			$includeStickies ? '
				t2.is_sticky {raw:sorting},' :
				 '') .
			' t2.id_last_msg {raw:sorting}
		LIMIT 1',
		array(
			'strictly' => $next ? '<' : '>',
			'strictly_equal' => $next ? '<=' : '>=',
			'sorting' => $next ? 'DESC' : '',
			'current_board' => $id_board,
			'current_member' => $id_member,
			'current_topic' => $id_topic,
			'is_approved' => 1,
			'id_member_started' => 0,
		)
	);

	// Was there any?
	if ($smcFunc['db_num_rows']($request) == 0)
	{
		$smcFunc['db_free_result']($request);

		// Roll over - if we're going prev, get the last - otherwise the first.
		$request = $smcFunc['db_query']('', '
			SELECT id_topic
			FROM {db_prefix}topics
			WHERE id_board = {int:current_board}' .
			($includeUnapproved ? '' : '
				AND (approved = {int:is_approved} OR (id_member_started != {int:id_member_started} AND id_member_started = {int:current_member}))') . '
			ORDER BY' . (
				$includeStickies ? ' is_sticky {raw:sorting},' :
				'').
				' id_last_msg {raw:sorting}
			LIMIT 1',
			array(
				'sorting' => $next ? 'DESC' : '',
				'current_board' => $id_board,
				'current_member' => $id_member,
				'is_approved' => 1,
				'id_member_started' => 0,
			)
		);
	}
	// Now you can be sure $topic is the id_topic to view.
	list ($topic) = $smcFunc['db_fetch_row']($request);
	$smcFunc['db_free_result']($request);

	return $topic;
}

/**
 * Set off/on unread reply subscription for a topic
 *
 * @param int $id_member
 * @param int $topic
 * @param bool $on = false
 */
function setTopicRegard($id_member, $topic, $on = false)
{
	global $smcFunc, $user_info;

	// find the current entry if it exists that is
	$request = $smcFunc['db_query']('', '
		SELECT id_msg
		FROM {db_prefix}log_topics
		WHERE id_member = {int:current_user}
			AND id_topic = {int:current_topic}',
		array(
			'current_user' => $user_info['id'],
			'current_topic' => $topic,
		)
	);
	list($was_set) = $smcFunc['db_fetch_row']($request);
	$smcFunc['db_free_result']($request);

	// Set topic disregard on/off for this topic.
	$smcFunc['db_insert'](empty($was_set) ? 'ignore' : 'replace',
		'{db_prefix}log_topics',
		array('id_member' => 'int', 'id_topic' => 'int', 'id_msg' => 'int', 'disregarded' => 'int'),
		array($id_member, $topic, $was_set ? $was_set : 0, $on ? 1 : 0),
		array('id_member', 'id_topic')
	);
}

/**
 * Get all the details for a given topic
 * - returns the basic topic information when $full is false
 * - returns topic details, subject, last message read, etc when full is true
 * - uses any integration information (value selects, tables and parameters) if passed and full is true
 *
 * @param array $topic_parameters can also accept a int value for a topic
 * @param int $full defines the values returned by the function:
 *             - if empty returns only the data from {db_prefix}topics
 *             - if 'message' returns also informations about the message (subject, body, etc.)
 *             - if 'all' returns additional infos about the read/disregard status
 * @param array $selects (optional from integation)
 * @param array $tables (optional from integation)
 */
function getTopicInfo($topic_parameters, $full = '', $selects = array(), $tables = array())
{
	global $smcFunc, $user_info, $modSettings, $board;

	// Nothing to do
	if (empty($topic_parameters))
		return false;

	// Build what we can with what we were given
	if (!is_array($topic_parameters))
		$topic_parameters = array(
			'topic' => $topic_parameters,
			'member' => $user_info['id'],
			'board' => (int) $board,
		);

	$messages_table = !empty($full) && ($full === 'message' || $full === 'all');
	$logs_table = !empty($full) && $full === 'all';

	// Create the query, taking full and integration in to account
	$request = $smcFunc['db_query']('', '
		SELECT
			t.is_sticky, t.id_board, t.id_first_msg, t.id_last_msg,
			t.id_member_started, t.id_member_updated, t.id_poll,
			t.num_replies, t.num_views, t.locked, t.redirect_expires,
			t.id_redirect_topic, t.unapproved_posts, t.approved' . ($messages_table ? ',
			ms.subject, ms.body, ms.id_member, ms.poster_time, ms.approved as msg_approved' : '') .
			($logs_table ? ',
			' . ($user_info['is_guest'] ? 't.id_last_msg + 1' : 'IFNULL(lt.id_msg, IFNULL(lmr.id_msg, -1)) + 1') . ' AS new_from
			' . (!empty($modSettings['recycle_board']) && $modSettings['recycle_board'] == $board ? ', t.id_previous_board, t.id_previous_topic' : '') . '
			' . (!$user_info['is_guest'] ? ', IFNULL(lt.disregarded, 0) as disregarded' : '') : '') .
			(!empty($selects) ? implode(',', $selects) : '') . '
		FROM {db_prefix}topics AS t' . ($messages_table ? '
			INNER JOIN {db_prefix}messages AS ms ON (ms.id_msg = t.id_first_msg)' : '') .
			($logs_table && !$user_info['is_guest'] ? '
			LEFT JOIN {db_prefix}log_topics AS lt ON (lt.id_topic = {int:topic} AND lt.id_member = {int:member})
			LEFT JOIN {db_prefix}log_mark_read AS lmr ON (lmr.id_board = {int:board} AND lmr.id_member = {int:member})' : '') .
			(!empty($tables) ? implode("\n\t\t\t", $tables) : '') . '
		WHERE t.id_topic = {int:topic}
		LIMIT 1',
			$topic_parameters
	);
	$topic_info = array();
	if ($request !== false)
		$topic_info = $smcFunc['db_fetch_assoc']($request);
	$smcFunc['db_free_result']($request);

	return $topic_info;
}

/**
 * So long as you are sure... all old posts will be gone.
 * Used in ManageMaintenance.php to prune old topics.
 */
function removeOldTopics()
{
	global $modSettings, $smcFunc;

	isAllowedTo('admin_forum');
	checkSession('post', 'admin');

	// No boards at all?  Forget it then :/.
	if (empty($_POST['boards']))
		redirectexit('action=admin;area=maintain;sa=topics');

	// This should exist, but we can make sure.
	$_POST['delete_type'] = isset($_POST['delete_type']) ? $_POST['delete_type'] : 'nothing';

	// Custom conditions.
	$condition = '';
	$condition_params = array(
		'boards' => array_keys($_POST['boards']),
		'poster_time' => time() - 3600 * 24 * $_POST['maxdays'],
	);

	// Just moved notice topics?
	if ($_POST['delete_type'] == 'moved')
	{
		$condition .= '
			AND m.icon = {string:icon}
			AND t.locked = {int:locked}';
		$condition_params['icon'] = 'moved';
		$condition_params['locked'] = 1;
	}
	// Otherwise, maybe locked topics only?
	elseif ($_POST['delete_type'] == 'locked')
	{
		$condition .= '
			AND t.locked = {int:locked}';
		$condition_params['locked'] = 1;
	}

	// Exclude stickies?
	if (isset($_POST['delete_old_not_sticky']))
	{
		$condition .= '
			AND t.is_sticky = {int:is_sticky}';
		$condition_params['is_sticky'] = 0;
	}

	// All we're gonna do here is grab the id_topic's and send them to removeTopics().
	$request = $smcFunc['db_query']('', '
		SELECT t.id_topic
		FROM {db_prefix}topics AS t
			INNER JOIN {db_prefix}messages AS m ON (m.id_msg = t.id_last_msg)
		WHERE
			m.poster_time < {int:poster_time}' . $condition . '
			AND t.id_board IN ({array_int:boards})',
		$condition_params
	);
	$topics = array();
	while ($row = $smcFunc['db_fetch_assoc']($request))
		$topics[] = $row['id_topic'];
	$smcFunc['db_free_result']($request);

	removeTopics($topics, false, true);

	// Log an action into the moderation log.
	logAction('pruned', array('days' => $_POST['maxdays']));

	redirectexit('action=admin;area=maintain;sa=topics;done=purgeold');
}

/**
 * Retrieve all topics started by the given member.
 *
 * @param int $memberID
 */
function topicsStartedBy($memberID)
{
	global $smcFunc;

	// Fetch all topics started by this user.
	$request = $smcFunc['db_query']('', '
		SELECT t.id_topic
		FROM {db_prefix}topics AS t
		WHERE t.id_member_started = {int:selected_member}',
			array(
				'selected_member' => $memberID,
			)
		);
	$topicIDs = array();
	while ($row = $smcFunc['db_fetch_assoc']($request))
		$topicIDs[] = $row['id_topic'];
	$smcFunc['db_free_result']($request);

	return $topicIDs;
}

/**
 * Retrieve the messages of the given topic, that are at or after
 * a message.
 * Used by split topics actions.
 */
function messagesAfter($topic, $message)
{
	global $smcFunc;

	// Fetch the message IDs of the topic that are at or after the message.
	$request = $smcFunc['db_query']('', '
		SELECT id_msg
		FROM {db_prefix}messages
		WHERE id_topic = {int:current_topic}
			AND id_msg >= {int:split_at}',
		array(
			'current_topic' => $topic,
			'split_at' => $message,
		)
	);
	while ($row = $smcFunc['db_fetch_assoc']($request))
		$messages[] = $row['id_msg'];
	$smcFunc['db_free_result']($request);

	return $messages;
}

/**
 * Retrieve a few data on a particular message.
 *
 * @param int $topic
 * @param int $message
 */
function messageInfo($topic, $message)
{
	global $smcFunc, $modSettings;

	// @todo isn't this a duplicate?

	// Retrieve a few info on the specific message.
	$request = $smcFunc['db_query']('', '
		SELECT m.subject, t.num_replies, t.unapproved_posts, t.id_first_msg, t.approved
		FROM {db_prefix}messages AS m
			INNER JOIN {db_prefix}topics AS t ON (t.id_topic = {int:current_topic})
		WHERE m.id_msg = {int:split_at}' . (!$modSettings['postmod_active'] || allowedTo('approve_posts') ? '' : '
			AND m.approved = 1') . '
			AND m.id_topic = {int:current_topic}
		LIMIT 1',
		array(
			'current_topic' => $topic,
			'split_at' => $message,
		)
	);
	if ($smcFunc['db_num_rows']($request) == 0)
		fatal_lang_error('cant_find_messages');
	list ($subject, $num_replies, $unapproved_posts, $id_first_msg, $approved) = $smcFunc['db_fetch_row']($request);
	$smcFunc['db_free_result']($request);

	$messageInfo = array(
		'subject' => $subject,
		'num_replies' => $num_replies,
		'unapproved_posts' => $unapproved_posts,
		'id_first_msg' => $id_first_msg,
		'approved' => $approved
	);

	return $messageInfo;
}

/**
 * Select a part of the messages in a topic.
 *
 * @param int $topic
 * @param int $start
 * @param int $per_page
 * @param array $messages
 * @param bool $only_approved
 */
function selectMessages($topic, $start, $per_page, $messages = array(), $only_approved = false)
{
	global $smcFunc;

	// Get the messages and stick them into an array.
	$request = $smcFunc['db_query']('', '
		SELECT m.subject, IFNULL(mem.real_name, m.poster_name) AS real_name, m.poster_time, m.body, m.id_msg, m.smileys_enabled
		FROM {db_prefix}messages AS m
			LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = m.id_member)
		WHERE m.id_topic = {int:current_topic}' . (empty($messages['excluded']) ? '' : '
			AND m.id_msg NOT IN ({array_int:no_split_msgs})') . (empty($messages['included']) ? '' : '
			AND m.id_msg IN ({array_int:split_msgs})') . (!$only_approved ? '' : '
			AND approved = {int:is_approved}') . '
		ORDER BY m.id_msg DESC
		LIMIT {int:start}, {int:messages_per_page}',
		array(
			'current_topic' => $topic,
			'no_split_msgs' => !empty($messages['excluded']) ? $messages['excluded'] : array(),
			'split_msgs' => !empty($messages['included']) ? $messages['included'] : array(),
			'is_approved' => 1,
			'start' => $start,
			'messages_per_page' => $per_page,
		)
	);
	$messages = array();
	for ($counter = 0; $row = $smcFunc['db_fetch_assoc']($request); $counter ++)
	{
		censorText($row['subject']);
		censorText($row['body']);

		$row['body'] = parse_bbc($row['body'], $row['smileys_enabled'], $row['id_msg']);

		$messages[$row['id_msg']] = array(
			'id' => $row['id_msg'],
			'alternate' => $counter % 2,
			'subject' => $row['subject'],
			'time' => timeformat($row['poster_time']),
			'timestamp' => forum_time(true, $row['poster_time']),
			'body' => $row['body'],
			'poster' => $row['real_name'],
		);
	}
	$smcFunc['db_free_result']($request);

	return $messages;
}

/**
 * Retrieve unapproved posts of the member
 * in a specific topic
 *
 * @param int $id_topic topic id
 * @param int $id_member member id
 */
function unapprovedPosts($id_topic, $id_member)
{
	global $smcFunc;

	// not all guests are the same!
	if (empty($id_member))
		return array();

	$request = $smcFunc['db_query']('', '
			SELECT COUNT(id_member) AS my_unapproved_posts
			FROM {db_prefix}messages
			WHERE id_topic = {int:current_topic}
				AND id_member = {int:current_member}
				AND approved = 0',
			array(
				'current_topic' => $id_topic,
				'current_member' => $id_member,
			)
		);
	list ($myUnapprovedPosts) = $smcFunc['db_fetch_row']($request);
	$smcFunc['db_free_result']($request);

	return $myUnapprovedPosts;
}