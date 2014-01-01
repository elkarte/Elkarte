<?php

/**
 * This file contains functions for dealing with messages.
 * Low-level functions, i.e. database operations needed to perform.
 * These functions (probably) do NOT make permissions checks. (they assume
 * those were already made).
 *
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
 * @version 1.0 Beta
 *
 */

if (!defined('ELK'))
	die('No access...');

/**
 * Get message and attachments data, for a message ID.
 * The function returns the data in an array with:
 *  'message' => array with message data
 *  'attachment_stuff' => array with attachments
 *
 * @param int $id_msg
 * @param int $id_topic = 0
 * @param int $attachment_type = 0
 */
function messageDetails($id_msg, $id_topic = 0, $attachment_type = 0)
{
	global $modSettings;

	$db = database();

	if (empty($id_msg))
		return false;

	$request = $db->query('', '
		SELECT
			m.id_member, m.modified_time, m.modified_name, m.smileys_enabled, m.body,
			m.poster_name, m.poster_email, m.subject, m.icon, m.approved,
			IFNULL(a.size, -1) AS filesize, a.filename, a.id_attach,
			a.approved AS attachment_approved, t.id_member_started AS id_member_poster,
			m.poster_time, log.id_action
		FROM {db_prefix}messages AS m
			INNER JOIN {db_prefix}topics AS t ON (t.id_topic = {int:current_topic})
			LEFT JOIN {db_prefix}attachments AS a ON (a.id_msg = m.id_msg AND a.attachment_type = {int:attachment_type})
			LEFT JOIN {db_prefix}log_actions AS log ON (m.id_topic = log.id_topic AND log.action = {string:announce_action})
		WHERE m.id_msg = {int:id_msg}
			AND m.id_topic = {int:current_topic}',
		array(
			'current_topic' => $id_topic,
			'attachment_type' => $attachment_type,
			'id_msg' => $id_msg,
			'announce_action' => 'announce_topic',
		)
	);
	// The message they were trying to edit was most likely deleted.
	if ($db->num_rows($request) == 0)
		return false;
	$row = $db->fetch_assoc($request);

	$attachment_stuff = array($row);
	while ($row2 = $db->fetch_assoc($request))
		$attachment_stuff[] = $row2;
	$db->free_result($request);

	$temp = array();
	foreach ($attachment_stuff as $attachment)
	{
		if ($attachment['filesize'] >= 0 && !empty($modSettings['attachmentEnable']))
			$temp[$attachment['id_attach']] = $attachment;

	}
	ksort($temp);

	return array('message' => $row, 'attachment_stuff' => $temp);
}

/**
 * Get some basic info of a certain message
 * Will use query_see_board unless $override_permissions is set to true
 * Will return additional topic information if $topic_basics is set to true
 * Returns an associative array of the results or false on error
 *
 * @param int $id_msg
 * @param boolean $override_permissions
 * @param boolean $topic_basics
 */
function basicMessageInfo($id_msg, $override_permissions = false, $topic_basics = false)
{
	global $modSettings;

	$db = database();

	if (empty($id_msg))
		return false;

	$request = $db->query('', '
		SELECT
			m.id_member, m.id_topic, m.id_board, m.id_msg, m.body, m.subject,
			m.poster_name, m.poster_email, m.poster_time, m.approved' . ($topic_basics === false ? '' : ',
			t.id_first_msg, t.num_replies, t.unapproved_posts, t.id_first_msg, t.id_member_started, t.approved AS topic_approved') . '
		FROM {db_prefix}messages AS m' . ($override_permissions === true ? '' : '
			INNER JOIN {db_prefix}boards AS b ON (b.id_board = m.id_board AND {query_see_board})') . ($topic_basics === false ? '' : '
			LEFT JOIN {db_prefix}topics AS t ON (t.id_topic = m.id_topic)') . '
		WHERE id_msg = {int:message}' . (!$modSettings['postmod_active'] || allowedTo('approve_posts') ? '' : '
			AND m.approved = 1') . '
		LIMIT 1',
		array(
			'message' => $id_msg,
		)
	);

	$messageInfo = $db->fetch_assoc($request);
	$db->free_result($request);

	return empty($messageInfo) ? false : $messageInfo;
}

/**
 * Checks permissions to modify a message.
 * This function will give a fatal error if the current user
 * doesn't have permissions to modify the message.
 *
 * @param int $message
 */
function checkMessagePermissions($message)
{
	global $user_info, $modSettings, $context;

	if ($message['id_member'] == $user_info['id'] && !allowedTo('modify_any'))
	{
		// Give an extra five minutes over the disable time threshold, so they can type - assuming the post is public.
		if ($message['approved'] && !empty($modSettings['edit_disable_time']) && $message['poster_time'] + ($modSettings['edit_disable_time'] + 5) * 60 < time())
			fatal_lang_error('modify_post_time_passed', false);
		elseif ($message['id_member_poster'] == $user_info['id'] && !allowedTo('modify_own'))
			isAllowedTo('modify_replies');
		else
			isAllowedTo('modify_own');
	}
	elseif ($message['id_member_poster'] == $user_info['id'] && !allowedTo('modify_any'))
		isAllowedTo('modify_replies');
	else
		isAllowedTo('modify_any');

	if ($context['can_announce'] && !empty($message['id_action']))
		return array('topic_already_announced');

	return false;
}

/**
 * Prepare context for a message.
 *
 * @param int $message the message id
 */
function prepareMessageContext($message)
{
	global $context, $txt;

	// Load up 'em attachments!
	foreach ($message['attachment_stuff'] as $attachment)
	{
		$context['current_attachments'][] = array(
			'name' => htmlspecialchars($attachment['filename'], ENT_COMPAT, 'UTF-8'),
			'size' => $attachment['filesize'],
			'id' => $attachment['id_attach'],
			'approved' => $attachment['attachment_approved'],
		);
	}

	// Allow moderators to change names....
	if (allowedTo('moderate_forum') && empty($message['message']['id_member']))
	{
		$context['name'] = htmlspecialchars($message['message']['poster_name'], ENT_COMPAT, 'UTF-8');
		$context['email'] = htmlspecialchars($message['message']['poster_email'], ENT_COMPAT, 'UTF-8');
	}

	// When was it last modified?
	if (!empty($message['message']['modified_time']))
	{
		$context['last_modified'] = standardTime($message['message']['modified_time']);
		$context['last_modified_text'] = sprintf($txt['last_edit_by'], $context['last_modified'], $message['message']['modified_name']);
	}

	// Show an "approve" box if the user can approve it, and the message isn't approved.
	if (! $message['message']['approved'] && !$context['show_approval'])
		$context['show_approval'] = allowedTo('approve_posts');
}


/**
 * This function removes all the messages of a certain user that are *not*
 * first messages of a topic
 *
 * @param int $memID The member id
 */
function removeNonTopicMessages($memID)
{
	$db = database();

	$request = $db->query('', '
		SELECT m.id_msg
		FROM {db_prefix}messages AS m
			INNER JOIN {db_prefix}topics AS t ON (t.id_topic = m.id_topic
				AND t.id_first_msg != m.id_msg)
		WHERE m.id_member = {int:selected_member}',
		array(
			'selected_member' => $memID,
		)
	);
	// This could take a while... but ya know it's gonna be worth it in the end.
	while ($row = $db->fetch_assoc($request))
	{
		if (function_exists('apache_reset_timeout'))
			@apache_reset_timeout();

		removeMessage($row['id_msg']);
	}
	$db->free_result($request);
}

/**
 * Remove a specific message.
 * !! This includes permission checks.
 *
 * - normally, local and global should be the localCookies and globalCookies settings, respectively.
 * - uses boardurl to determine these two things.
 *
 * @param int $message The message id
 * @param bool $decreasePostCount if true users' post count will be reduced
 * @return array an array to set the cookie on with domain and path in it, in that order
 */
function removeMessage($message, $decreasePostCount = true)
{
	global $board, $modSettings, $user_info;

	$db = database();

	if (empty($message) || !is_numeric($message))
		return false;

	$request = $db->query('', '
		SELECT
			m.id_member, m.icon, m.poster_time, m.subject,' . (empty($modSettings['search_custom_index_config']) ? '' : ' m.body,') . '
			m.approved, t.id_topic, t.id_first_msg, t.id_last_msg, t.num_replies, t.id_board,
			t.id_member_started AS id_member_poster,
			b.count_posts
		FROM {db_prefix}messages AS m
			INNER JOIN {db_prefix}topics AS t ON (t.id_topic = m.id_topic)
			INNER JOIN {db_prefix}boards AS b ON (b.id_board = t.id_board)
		WHERE m.id_msg = {int:id_msg}
		LIMIT 1',
		array(
			'id_msg' => $message,
		)
	);
	if ($db->num_rows($request) == 0)
		return false;
	$row = $db->fetch_assoc($request);
	$db->free_result($request);

	if (empty($board) || $row['id_board'] != $board)
	{
		$delete_any = boardsAllowedTo('delete_any');

		if (!in_array(0, $delete_any) && !in_array($row['id_board'], $delete_any))
		{
			$delete_own = boardsAllowedTo('delete_own');
			$delete_own = in_array(0, $delete_own) || in_array($row['id_board'], $delete_own);
			$delete_replies = boardsAllowedTo('delete_replies');
			$delete_replies = in_array(0, $delete_replies) || in_array($row['id_board'], $delete_replies);

			if ($row['id_member'] == $user_info['id'])
			{
				if (!$delete_own)
				{
					if ($row['id_member_poster'] == $user_info['id'])
					{
						if (!$delete_replies)
							fatal_lang_error('cannot_delete_replies', 'permission');
					}
					else
						fatal_lang_error('cannot_delete_own', 'permission');
				}
				elseif (($row['id_member_poster'] != $user_info['id'] || !$delete_replies) && !empty($modSettings['edit_disable_time']) && $row['poster_time'] + $modSettings['edit_disable_time'] * 60 < time())
					fatal_lang_error('modify_post_time_passed', false);
			}
			elseif ($row['id_member_poster'] == $user_info['id'])
			{
				if (!$delete_replies)
					fatal_lang_error('cannot_delete_replies', 'permission');
			}
			else
				fatal_lang_error('cannot_delete_any', 'permission');
		}

		// Can't delete an unapproved message, if you can't see it!
		if ($modSettings['postmod_active'] && !$row['approved'] && $row['id_member'] != $user_info['id'] && !(in_array(0, $delete_any) || in_array($row['id_board'], $delete_any)))
		{
			$approve_posts = !empty($user_info['mod_cache']['ap']) ? $user_info['mod_cache']['ap'] : boardsAllowedTo('approve_posts');
			if (!in_array(0, $approve_posts) && !in_array($row['id_board'], $approve_posts))
				return false;
		}
	}
	else
	{
		// Check permissions to delete this message.
		if ($row['id_member'] == $user_info['id'])
		{
			if (!allowedTo('delete_own'))
			{
				if ($row['id_member_poster'] == $user_info['id'] && !allowedTo('delete_any'))
					isAllowedTo('delete_replies');
				elseif (!allowedTo('delete_any'))
					isAllowedTo('delete_own');
			}
			elseif (!allowedTo('delete_any') && ($row['id_member_poster'] != $user_info['id'] || !allowedTo('delete_replies')) && !empty($modSettings['edit_disable_time']) && $row['poster_time'] + $modSettings['edit_disable_time'] * 60 < time())
				fatal_lang_error('modify_post_time_passed', false);
		}
		elseif ($row['id_member_poster'] == $user_info['id'] && !allowedTo('delete_any'))
			isAllowedTo('delete_replies');
		else
			isAllowedTo('delete_any');

		if ($modSettings['postmod_active'] && !$row['approved'] && $row['id_member'] != $user_info['id'] && !allowedTo('delete_own'))
			isAllowedTo('approve_posts');
	}

	// Delete the *whole* topic, but only if the topic consists of one message.
	if ($row['id_first_msg'] == $message)
	{
		if (empty($board) || $row['id_board'] != $board)
		{
			$remove_any = boardsAllowedTo('remove_any');
			$remove_any = in_array(0, $remove_any) || in_array($row['id_board'], $remove_any);
			if (!$remove_any)
			{
				$remove_own = boardsAllowedTo('remove_own');
				$remove_own = in_array(0, $remove_own) || in_array($row['id_board'], $remove_own);
			}

			if ($row['id_member'] != $user_info['id'] && !$remove_any)
				fatal_lang_error('cannot_remove_any', 'permission');
			elseif (!$remove_any && !$remove_own)
				fatal_lang_error('cannot_remove_own', 'permission');
		}
		else
		{
			// Check permissions to delete a whole topic.
			if ($row['id_member'] != $user_info['id'])
				isAllowedTo('remove_any');
			elseif (!allowedTo('remove_any'))
				isAllowedTo('remove_own');
		}

		// ...if there is only one post.
		if (!empty($row['num_replies']))
			fatal_lang_error('delFirstPost', false);

		// This needs to be included for topic functions
		require_once(SUBSDIR . '/Topic.subs.php');

		removeTopics($row['id_topic']);
		return true;
	}

	// Deleting a recycled message can not lower anyone's post count.
	if ($row['icon'] == 'recycled')
		$decreasePostCount = false;

	// This is the last post, update the last post on the board.
	if ($row['id_last_msg'] == $message)
	{
		// Find the last message, set it, and decrease the post count.
		$request = $db->query('', '
			SELECT id_msg, id_member
			FROM {db_prefix}messages
			WHERE id_topic = {int:id_topic}
				AND id_msg != {int:id_msg}
			ORDER BY ' . ($modSettings['postmod_active'] ? 'approved DESC, ' : '') . 'id_msg DESC
			LIMIT 1',
			array(
				'id_topic' => $row['id_topic'],
				'id_msg' => $message,
			)
		);
		$row2 = $db->fetch_assoc($request);
		$db->free_result($request);

		$db->query('', '
			UPDATE {db_prefix}topics
			SET
				id_last_msg = {int:id_last_msg},
				id_member_updated = {int:id_member_updated}' . (!$modSettings['postmod_active'] || $row['approved'] ? ',
				num_replies = CASE WHEN num_replies = {int:no_replies} THEN 0 ELSE num_replies - 1 END' : ',
				unapproved_posts = CASE WHEN unapproved_posts = {int:no_unapproved} THEN 0 ELSE unapproved_posts - 1 END') . '
			WHERE id_topic = {int:id_topic}',
			array(
				'id_last_msg' => $row2['id_msg'],
				'id_member_updated' => $row2['id_member'],
				'no_replies' => 0,
				'no_unapproved' => 0,
				'id_topic' => $row['id_topic'],
			)
		);
	}
	// Only decrease post counts.
	else
		$db->query('', '
			UPDATE {db_prefix}topics
			SET ' . ($row['approved'] ? '
				num_replies = CASE WHEN num_replies = {int:no_replies} THEN 0 ELSE num_replies - 1 END' : '
				unapproved_posts = CASE WHEN unapproved_posts = {int:no_unapproved} THEN 0 ELSE unapproved_posts - 1 END') . '
			WHERE id_topic = {int:id_topic}',
			array(
				'no_replies' => 0,
				'no_unapproved' => 0,
				'id_topic' => $row['id_topic'],
			)
		);

	// Default recycle to false.
	$recycle = false;

	// If recycle topics has been set, make a copy of this message in the recycle board.
	// Make sure we're not recycling messages that are already on the recycle board.
	if (!empty($modSettings['recycle_enable']) && $row['id_board'] != $modSettings['recycle_board'] && $row['icon'] != 'recycled')
	{
		// Check if the recycle board exists and if so get the read status.
		$request = $db->query('', '
			SELECT (IFNULL(lb.id_msg, 0) >= b.id_msg_updated) AS is_seen, id_last_msg
			FROM {db_prefix}boards AS b
				LEFT JOIN {db_prefix}log_boards AS lb ON (lb.id_board = b.id_board AND lb.id_member = {int:current_member})
			WHERE b.id_board = {int:recycle_board}',
			array(
				'current_member' => $user_info['id'],
				'recycle_board' => $modSettings['recycle_board'],
			)
		);
		if ($db->num_rows($request) == 0)
			fatal_lang_error('recycle_no_valid_board');
		list ($isRead, $last_board_msg) = $db->fetch_row($request);
		$db->free_result($request);

		// Is there an existing topic in the recycle board to group this post with?
		$request = $db->query('', '
			SELECT id_topic, id_first_msg, id_last_msg
			FROM {db_prefix}topics
			WHERE id_previous_topic = {int:id_previous_topic}
				AND id_board = {int:recycle_board}',
			array(
				'id_previous_topic' => $row['id_topic'],
				'recycle_board' => $modSettings['recycle_board'],
			)
		);
		list ($id_recycle_topic, $first_topic_msg, $last_topic_msg) = $db->fetch_row($request);
		$db->free_result($request);

		// Insert a new topic in the recycle board if $id_recycle_topic is empty.
		if (empty($id_recycle_topic))
			$db->insert('',
				'{db_prefix}topics',
				array(
					'id_board' => 'int', 'id_member_started' => 'int', 'id_member_updated' => 'int', 'id_first_msg' => 'int',
					'id_last_msg' => 'int', 'unapproved_posts' => 'int', 'approved' => 'int', 'id_previous_topic' => 'int',
				),
				array(
					$modSettings['recycle_board'], $row['id_member'], $row['id_member'], $message,
					$message, 0, 1, $row['id_topic'],
				),
				array('id_topic')
			);

		// Capture the ID of the new topic...
		$topicID = empty($id_recycle_topic) ? $db->insert_id('{db_prefix}topics', 'id_topic') : $id_recycle_topic;

		// If the topic creation went successful, move the message.
		if ($topicID > 0)
		{
			$db->query('', '
				UPDATE {db_prefix}messages
				SET
					id_topic = {int:id_topic},
					id_board = {int:recycle_board},
					icon = {string:recycled},
					approved = {int:is_approved}
				WHERE id_msg = {int:id_msg}',
				array(
					'id_topic' => $topicID,
					'recycle_board' => $modSettings['recycle_board'],
					'id_msg' => $message,
					'recycled' => 'recycled',
					'is_approved' => 1,
				)
			);

			// Take any reported posts with us...
			$db->query('', '
				UPDATE {db_prefix}log_reported
				SET
					id_topic = {int:id_topic},
					id_board = {int:recycle_board}
				WHERE id_msg = {int:id_msg}',
				array(
					'id_topic' => $topicID,
					'recycle_board' => $modSettings['recycle_board'],
					'id_msg' => $message,
				)
			);

			// Mark recycled topic as read.
			if (!$user_info['is_guest'])
			{
				require_once(SUBSDIR . '/Topic.subs.php');
				markTopicsRead(array($user_info['id'], $topicID, $modSettings['maxMsgID'], 0), true);
			}

			// Mark recycle board as seen, if it was marked as seen before.
			if (!empty($isRead) && !$user_info['is_guest'])
			{
				require_once(SUBSDIR . '/Boards.subs.php');
				markBoardsRead($modSettings['recycle_board']);
			}

			// Add one topic and post to the recycle bin board.
			$db->query('', '
				UPDATE {db_prefix}boards
				SET
					num_topics = num_topics + {int:num_topics_inc},
					num_posts = num_posts + 1' .
						($message > $last_board_msg ? ', id_last_msg = {int:id_merged_msg}' : '') . '
				WHERE id_board = {int:recycle_board}',
				array(
					'num_topics_inc' => empty($id_recycle_topic) ? 1 : 0,
					'recycle_board' => $modSettings['recycle_board'],
					'id_merged_msg' => $message,
				)
			);

			// Lets increase the num_replies, and the first/last message ID as appropriate.
			if (!empty($id_recycle_topic))
				$db->query('', '
					UPDATE {db_prefix}topics
					SET num_replies = num_replies + 1' .
						($message > $last_topic_msg ? ', id_last_msg = {int:id_merged_msg}' : '') .
						($message < $first_topic_msg ? ', id_first_msg = {int:id_merged_msg}' : '') . '
					WHERE id_topic = {int:id_recycle_topic}',
					array(
						'id_recycle_topic' => $id_recycle_topic,
						'id_merged_msg' => $message,
					)
				);

			// Make sure this message isn't getting deleted later on.
			$recycle = true;

			// Make sure we update the search subject index.
			updateSubjectStats($topicID, $row['subject']);
		}

		// If it wasn't approved don't keep it in the queue.
		if (!$row['approved'])
			$db->query('', '
				DELETE FROM {db_prefix}approval_queue
				WHERE id_msg = {int:id_msg}
					AND id_attach = {int:id_attach}',
				array(
					'id_msg' => $message,
					'id_attach' => 0,
				)
			);
	}

	$db->query('', '
		UPDATE {db_prefix}boards
		SET ' . ($row['approved'] ? '
			num_posts = CASE WHEN num_posts = {int:no_posts} THEN 0 ELSE num_posts - 1 END' : '
			unapproved_posts = CASE WHEN unapproved_posts = {int:no_unapproved} THEN 0 ELSE unapproved_posts - 1 END') . '
		WHERE id_board = {int:id_board}',
		array(
			'no_posts' => 0,
			'no_unapproved' => 0,
			'id_board' => $row['id_board'],
		)
	);

	// If the poster was registered and the board this message was on incremented
	// the member's posts when it was posted, decrease his or her post count.
	if (!empty($row['id_member']) && $decreasePostCount && empty($row['count_posts']) && $row['approved'])
		updateMemberData($row['id_member'], array('posts' => '-'));

	// Only remove posts if they're not recycled.
	if (!$recycle)
	{
		// Remove the likes!
		$db->query('', '
			DELETE FROM {db_prefix}message_likes
			WHERE id_msg = {int:id_msg}',
			array(
				'id_msg' => $message,
			)
		);

		// Remove the mentions!
		$db->query('', '
			DELETE FROM {db_prefix}log_mentions
			WHERE id_msg = {int:id_msg}',
			array(
				'id_msg' => $message,
			)
		);

		// Remove the message!
		$db->query('', '
			DELETE FROM {db_prefix}messages
			WHERE id_msg = {int:id_msg}',
			array(
				'id_msg' => $message,
			)
		);

		if (!empty($modSettings['search_custom_index_config']))
		{
			$customIndexSettings = unserialize($modSettings['search_custom_index_config']);
			$words = text2words($row['body'], $customIndexSettings['bytes_per_word'], true);
			if (!empty($words))
				$db->query('', '
					DELETE FROM {db_prefix}log_search_words
					WHERE id_word IN ({array_int:word_list})
						AND id_msg = {int:id_msg}',
					array(
						'word_list' => $words,
						'id_msg' => $message,
					)
				);
		}

		// Delete attachment(s) if they exist.
		require_once(SUBSDIR . '/ManageAttachments.subs.php');
		$attachmentQuery = array(
			'attachment_type' => 0,
			'id_msg' => $message,
		);
		removeAttachments($attachmentQuery);

		// Delete follow-ups too
		require_once(SUBSDIR . '/FollowUps.subs.php');
		// If it is an entire topic
		if ($row['id_first_msg'] == $message)
		{
			$db->query('', '
				DELETE FROM {db_prefix}follow_ups
				WHERE follow_ups IN ({array_int:topics})',
				array(
					'topics' => $row['id_topic'],
				)
			);
		}

		// Allow mods to remove message related data of their own (likes, maybe?)
		call_integration_hook('integrate_remove_message', array($message));
	}

	// Update the pesky statistics.
	updateMessageStats();
	updateStats('topic');
	updateSettings(array(
		'calendar_updated' => time(),
	));

	// And now to update the last message of each board we messed with.
	require_once(SUBSDIR . '/Post.subs.php');
	if ($recycle)
		updateLastMessages(array($row['id_board'], $modSettings['recycle_board']));
	else
		updateLastMessages($row['id_board']);

	// Close any moderation reports for this message.
	require_once(SUBSDIR . '/Moderation.subs.php');
	$updated_reports = updateReportsStatus($message, 'close', 1);
	if ($updated_reports != 0)
	{
		updateSettings(array('last_mod_report_action' => time()));
		recountOpenReports();
	}

	return false;
}

/**
 * This function deals with the topic associated to a message.
 * It allows to retrieve or update the topic to which the message belongs.
 *
 * If $topicID is not passed, the current topic ID of the message is returned.
 * If $topicID is passed, the message is updated to point to the new topic.
 *
 * @param int $msg_id message ID
 * @param int $topicID = null topic ID, if null is passed the ID of the topic is retrieved and returned
 * @return mixed, int topic ID if any, or false
 */
function associatedTopic($msg_id, $topicID = null)
{
	$db = database();

	if ($topicID === null)
	{
		$request = $db->query('', '
			SELECT id_topic
			FROM {db_prefix}messages
			WHERE id_msg = {int:msg}',
			array(
				'msg' => $msg_id,
		));
		if ($db->num_rows($request) != 1)
			$topic = false;
		else
			list ($topic) = $db->fetch_row($request);
		$db->free_result($request);

		return $topic;
	}
	else
	{
		$db->query('', '
			UPDATE {db_prefix}messages
			SET id_topic = {int:topic}
			WHERE id_msg = {int:msg}',
			array(
				'msg' => $msg_id,
				'topic' => $topicID,
			)
		);
	}
}

/**
 * Small function that simply verifies if the current
 * user can access a specific message
 *
 * @param int $id_msg a message id
 * @param bool $check_approval if true messages are checked for approval (default true)
 * @return boolean
 */
function canAccessMessage($id_msg, $check_approval = true)
{
	global $user_info;

	$message_info = basicMessageInfo($id_msg);

	// Do we even have a message to speak of?
	if (empty($message_info))
		return false;

	// Check for approval status?
	if ($check_approval)
	{
		// The user can access this message if it's approved or they're owner
		return (!empty($message_info['approved']) || $message_info['id_member'] == $user_info['id']);
	}

	// Otherwise, nope.
	return false;
}

/**
 * Advance message pointer in a topic.
 * (in either direction)
 * This function is used by previousMessage() and nextMessage().
 * The boolean parameter $next determines the direction.
 *
 * @param int $id_msg origin message id
 * @param int $id_topic topic
 * @param bool $next = true if true, it increases the pointer, otherwise it decreases it
 */
function messagePointer($id_msg, $id_topic, $next = true)
{
	$db = database();

	$result = $db->query('', '
		SELECT ' . ($next ? 'MIN(id_msg)' : 'MAX(id_msg)') . '
		FROM {db_prefix}messages
		WHERE id_topic = {int:current_topic}
			AND id_msg {raw:strictly} {int:topic_msg_id}',
		array(
			'current_topic' => $id_topic,
			'topic_msg_id' => $id_msg,
			'strictly' => $next ? '>' : '<'
		)
	);

	list ($msg) = $db->fetch_row($result);
	$db->free_result($result);

	return $msg;
}

/**
 * Get previous message from where we were in the topic.
 *
 * @param int $id_msg
 * @param int $id_topic
 */
function previousMessage($id_msg, $id_topic)
{
	return messagePointer($id_msg, $id_topic, false);
}

/**
 * Get next message from where we were in the topic.
 *
 * @param int $id_msg
 * @param int $id_topic
 */
function nextMessage($id_msg, $id_topic)
{
	return messagePointer($id_msg, $id_topic);
}

/**
 * Retrieve the message id/s at a certain position in a topic
 *
 * @param int $start the offset of the message/s
 * @param int $id_topic the id of the topic
 * @param array $params an (optional) array of params, includes:
 *      - 'not_in' => array - of messages to exclude
 *      - 'include' => array - of messages to explicitely include
 *      - 'only_approved' => true/false - include or exclude the unapproved messages
 *      - 'limit' => mixed - the number of values to return (if false, no limits applied)
 * @todo very similar to selectMessages in Topics.subs.php
 */
function messageAt($start, $id_topic, $params = array())
{
	$db = database();

	$params = array_merge(
		// Defaults
		array(
			'not_in' => false,
			'include' => false,
			'only_approved' => false,
			'limit' => 1,
		),
		// Passed arguments
		$params,
		// Others
		array(
			'current_topic' => $id_topic,
			'start' => $start,
			'is_approved' => 1,
		)
	);

	$result = $db->query('', '
		SELECT id_msg
		FROM {db_prefix}messages
		WHERE id_topic = {int:current_topic}' . (!$params['include'] ? '' : '
			AND id_msg IN ({array_int:include})') . (!$params['not_in'] ? '' : '
			AND id_msg NOT IN ({array_int:not_in})') . (!$params['only_approved'] ? '' : '
			AND approved = {int:is_approved}') . '
		ORDER BY id_msg DESC' . ($params['limit'] === false ? '' : '
		LIMIT {int:start}, {int:limit}'),
		$params
	);
	$msg = array();
	while ($row = $db->fetch_assoc($result))
		$msg[] = $row['id_msg'];
	$db->free_result($result);

	return $msg;
}

/**
 * Finds an open report for a certain message if it exists and increase the
 * number of reports for that message, otherwise it creates one

 * @param array $message array of several message details (id_msg, id_topic, etc.)
 * @param string $poster_comment the comment made by the reporter
 *
 */
function recordReport($message, $poster_comment)
{
	global $user_info;

	$db = database();

	$request = $db->query('', '
		SELECT id_report, ignore_all
		FROM {db_prefix}log_reported
		WHERE id_msg = {int:id_msg}
			AND (closed = {int:not_closed} OR ignore_all = {int:ignored})
		ORDER BY ignore_all DESC',
		array(
			'id_msg' => $message['id_msg'],
			'not_closed' => 0,
			'ignored' => 1,
		)
	);

	if ($db->num_rows($request) != 0)
		list ($id_report, $ignore_all) = $db->fetch_row($request);
	$db->free_result($request);

	if (!empty($ignore_all))
		return false;

	// Already reported? My god, we could be dealing with a real rogue here...
	if (!empty($id_report))
		$db->query('', '
			UPDATE {db_prefix}log_reported
			SET num_reports = num_reports + 1, time_updated = {int:current_time}
			WHERE id_report = {int:id_report}',
			array(
				'current_time' => time(),
				'id_report' => $id_report,
			)
		);
	// Otherwise, we shall make one!
	else
	{
		if (empty($message['real_name']))
			$message['real_name'] = $message['poster_name'];

		$db->insert('',
			'{db_prefix}log_reported',
			array(
				'id_msg' => 'int', 'id_topic' => 'int', 'id_board' => 'int', 'id_member' => 'int', 'membername' => 'string',
				'subject' => 'string', 'body' => 'string', 'time_started' => 'int', 'time_updated' => 'int',
				'num_reports' => 'int', 'closed' => 'int',
			),
			array(
				$message['id_msg'], $message['id_topic'], $message['id_board'], $message['id_poster'], $message['real_name'],
				$message['subject'], $message['body'] , time(), time(), 1, 0,
			),
			array('id_report')
		);
		$id_report = $db->insert_id('{db_prefix}log_reported', 'id_report');
	}

	// Now just add our report...
	if (!empty($id_report))
	{
		$db->insert('',
			'{db_prefix}log_reported_comments',
			array(
				'id_report' => 'int', 'id_member' => 'int', 'membername' => 'string', 'email_address' => 'string',
				'member_ip' => 'string', 'comment' => 'string', 'time_sent' => 'int',
			),
			array(
				$id_report, $user_info['id'], $user_info['name'], $user_info['email'],
				$user_info['ip'], $poster_comment, time(),
			),
			array('id_comment')
		);
	}

	return $id_report;
}

/**
 * Count the new posts for a specific topic
 * @param int $topic
 * @param int $topicinfo
 * @param int $timestamp
 * @return int
 */
function countNewPosts($topic, $topicinfo, $timestamp)
{
	global $user_info, $modSettings;

	$db = database();
	// Find the number of messages posted before said time...
	$request = $db->query('', '
		SELECT COUNT(*)
		FROM {db_prefix}messages
		WHERE poster_time < {int:timestamp}
			AND id_topic = {int:current_topic}' . ($modSettings['postmod_active'] && $topicinfo['unapproved_posts'] && !allowedTo('approve_posts') ? '
			AND (approved = {int:is_approved}' . ($user_info['is_guest'] ? '' : ' OR id_member = {int:current_member}') . ')' : ''),
		array(
			'current_topic' => $topic,
			'current_member' => $user_info['id'],
			'is_approved' => 1,
			'timestamp' => $timestamp,
		)
	);
	list ($start) = $db->fetch_row($request);
	$db->free_result($request);
	return $start;
}

/**
 * Loads the details from a message
 * @param array $msg_selects
 * @param array $msg_tables
 * @param array $msg_parameters
 * @param string $options
 * @return array
 */
function loadMessageDetails($msg_selects, $msg_tables, $msg_parameters, $options)
{
	$db = database();

	$request = $db->query('', '
		SELECT
			m.id_msg, m.icon, m.subject, m.poster_time, m.poster_ip, m.id_member, m.modified_time, m.modified_name, m.body,
			m.smileys_enabled, m.poster_name, m.poster_email, m.approved,
			m.id_msg_modified < {int:new_from} AS is_read
			' . (!empty($msg_selects) ? implode(',', $msg_selects) : '') . '
		FROM {db_prefix}messages AS m
			' . (!empty($msg_tables) ? implode("\n\t", $msg_tables) : '') . '
		WHERE m.id_msg IN ({array_int:message_list})
		ORDER BY m.id_msg' . (empty($options['view_newest_first']) ? '' : ' DESC'),
		$msg_parameters
	);

	return $request;
}

/**
 * Checks, which messages can be removed from a certain member.
 *
 * @global type $user_info
 * @global type $modSettings
 * @param int $topic
 * @param array $messages
 * @param bol $allowed_all
 * @return array
 */
function determineRemovableMessages($topic, $messages, $allowed_all)
{
	global $user_info, $modSettings;

	$db = database();

	// Allowed to remove which messages?
	$request = $db->query('', '
		SELECT id_msg, subject, id_member, poster_time
		FROM {db_prefix}messages
		WHERE id_msg IN ({array_int:message_list})
			AND id_topic = {int:current_topic}' . (!$allowed_all ? '
			AND id_member = {int:current_member}' : '') . '
		LIMIT ' . count($messages),
		array(
			'current_member' => $user_info['id'],
			'current_topic' => $topic,
			'message_list' => $messages,
		)
	);
	$messages_list = array();
	while ($row = $db->fetch_assoc($request))
	{
		if (!$allowed_all && !empty($modSettings['edit_disable_time']) && $row['poster_time'] + $modSettings['edit_disable_time'] * 60 < time())
			continue;
		$messages_list[$row['id_msg']] = array($row['subject'], $row['id_member']);
	}

	$db->free_result($request);

	return $messages_list;
}

/**
 * Returns the number of messages that are being split to a new topic
 *
 * @param int $topic
 * @param bool $include_unapproved
 * @param array $selection
 */
function countSplitMessages($topic, $include_unapproved, $selection = array())
{
	$db = database();

	$return = array('not_selected' => 0, 'selected' => 0);
	$request = $db->query('', '
		SELECT ' . (empty($selection) ? '0' : 'm.id_msg IN ({array_int:split_msgs})') . ' AS is_selected, COUNT(*) AS num_messages
		FROM {db_prefix}messages AS m
		WHERE m.id_topic = {int:current_topic}' . ($include_unapproved ? '' : '
			AND approved = {int:is_approved}') . (empty($selection) ? '' : '
		GROUP BY is_selected'),
		array(
			'current_topic' => $topic,
			'split_msgs' => $selection,
			'is_approved' => 1,
		)
	);
	while ($row = $db->fetch_assoc($request))
		$return[empty($row['is_selected']) || $row['is_selected'] == 'f' ? 'not_selected' : 'selected'] = $row['num_messages'];
	$db->free_result($request);

	return $return;
}

/**
 * Returns an email (and few other things) associated with a message,
 * either the member's email or the poster_email (for example in case of guests)
 *
 * @todo very similar to posterDetails
 *
 * @param int $id_msg the id of a message
 * @return array
 */
function mailFromMesasge($id_msg)
{
	$db = database();

	$request = $db->query('', '
		SELECT IFNULL(mem.email_address, m.poster_email) AS email_address, IFNULL(mem.real_name, m.poster_name) AS real_name, IFNULL(mem.id_member, 0) AS id_member, hide_email
		FROM {db_prefix}messages AS m
			LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = m.id_member)
		WHERE m.id_msg = {int:id_msg}',
		array(
			'id_msg' => $id_msg,
		)
	);
	$row = $db->fetch_assoc($request);
	$db->free_result($request);

	return $row;
}

/**
 * This function changes the total number of messages,
 * and the highest message id by id_msg - which can be
 * parameters 1 and 2, respectively.
 * Used by updateStats('message').
 *
 * @param bool $increment = null If true and $max_msg_id != null, then increment the total messages by one, otherwise recount all messages and get the max message id
 * @param int $max_msg_id = null, Only used if $increment === true
 */
function updateMessageStats($increment = null, $max_msg_id = null)
{
	global $modSettings;

	$db = database();

	if ($increment === true && $max_msg_id !== null)
		updateSettings(array('totalMessages' => true, 'maxMsgID' => $max_msg_id), true);
	else
	{
		// SUM and MAX on a smaller table is better for InnoDB tables.
		$request = $db->query('', '
			SELECT SUM(num_posts + unapproved_posts) AS total_messages, MAX(id_last_msg) AS max_msg_id
			FROM {db_prefix}boards
			WHERE redirect = {string:blank_redirect}' . (!empty($modSettings['recycle_enable']) && $modSettings['recycle_board'] > 0 ? '
				AND id_board != {int:recycle_board}' : ''),
			array(
				'recycle_board' => isset($modSettings['recycle_board']) ? $modSettings['recycle_board'] : 0,
				'blank_redirect' => '',
			)
		);
		$row = $db->fetch_assoc($request);
		$db->free_result($request);

		updateSettings(array(
			'totalMessages' => $row['total_messages'] === null ? 0 : $row['total_messages'],
			'maxMsgID' => $row['max_msg_id'] === null ? 0 : $row['max_msg_id']
		));
	}
}

/**
 * This function updates the log_search_subjects in the event of a topic being
 * moved, removed or split. It is being sent the topic id, and optionally
 * the new subject.
 * Used by updateStats('subject').
 *
 * @param int $id_topic
 * @param string $subject
 */
function updateSubjectStats($id_topic, $subject = null)
{
	$db = database();

	// Remove the previous subject (if any).
	$db->query('', '
		DELETE FROM {db_prefix}log_search_subjects
		WHERE id_topic = {int:id_topic}',
		array(
			'id_topic' => (int) $id_topic,
		)
	);

	// Insert the new subject.
	if ($subject !== null)
	{
		$id_topic = (int) $id_topic;
		$subject_words = text2words($subject);

		$inserts = array();
		foreach ($subject_words as $word)
			$inserts[] = array($word, $id_topic);

		if (!empty($inserts))
			$db->insert('ignore',
				'{db_prefix}log_search_subjects',
				array('word' => 'string', 'id_topic' => 'int'),
				$inserts,
				array('word', 'id_topic')
			);
	}
}