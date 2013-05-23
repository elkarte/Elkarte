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
 * This file contains functions for dealing with messages.
 * Low-level functions, i.e. database operations needed to perform.
 * These functions (probably) do NOT make permissions checks. (they assume
 * those were already made).
 *
 */

if (!defined('ELKARTE'))
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
function getExistingMessage($id_msg, $id_topic = 0, $attachment_type = 0)
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
 *
 * @param int $id_msg
 */
function getMessageInfo($id_msg, $override_permissions = false)
{
	$db = database();

	if (empty($id_msg))
		return false;

	$request = $db->query('', '
		SELECT
			m.id_member, m.id_topic, m.id_board,
			m.body, m.subject,
			m.poster_name, m.poster_email, m.poster_time,
			m.approved
		FROM {db_prefix}messages AS m' . ($override_permissions === true ? '' : '
			INNER JOIN {db_prefix}boards AS b ON (b.id_board = m.id_board AND {query_see_board})') . '
		WHERE id_msg = {int:message}
		LIMIt 1',
		array(
			'message' => $id_msg,
		)
	);

	$row = $db->fetch_assoc($request);
	$db->free_result($request);

	return empty($row) ? false : $row;
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
			'name' => htmlspecialchars($attachment['filename']),
			'size' => $attachment['filesize'],
			'id' => $attachment['id_attach'],
			'approved' => $attachment['attachment_approved'],
		);
	}

	// Allow moderators to change names....
	if (allowedTo('moderate_forum') && empty($message['message']['id_member']))
	{
		$context['name'] = htmlspecialchars($message['message']['poster_name']);
		$context['email'] = htmlspecialchars($message['message']['poster_email']);
	}

	// When was it last modified?
	if (!empty($message['message']['modified_time']))
	{
		$context['last_modified'] = relativeTime($message['message']['modified_time']);
		$context['last_modified_text'] = sprintf($txt['last_edit_by'], $context['last_modified'], $message['message']['modified_name']);
	}

	// Show an "approve" box if the user can approve it, and the message isn't approved.
	if (! $message['message']['approved'] && !$context['show_approval'])
		$context['show_approval'] = allowedTo('approve_posts');
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
	global $board, $modSettings, $user_info, $context;

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

	// Close any moderation reports for this message.
	$db->query('', '
		UPDATE {db_prefix}log_reported
		SET closed = {int:is_closed}
		WHERE id_msg = {int:id_msg}',
		array(
			'is_closed' => 1,
			'id_msg' => $message,
		)
	);
	if ($db->affected_rows() != 0)
	{
		require_once(SUBSDIR . '/Moderation.subs.php');
		updateSettings(array('last_mod_report_action' => time()));
		recountOpenReports();
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
				$db->insert('replace',
					'{db_prefix}log_boards',
					array('id_board' => 'int', 'id_member' => 'int', 'id_msg' => 'int'),
					array($modSettings['recycle_board'], $user_info['id'], $modSettings['maxMsgID']),
					array('id_board', 'id_member')
				);

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
			updateStats('subject', $topicID, $row['subject']);
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
		require_once(SUBSDIR . '/Attachments.subs.php');
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
	updateStats('message');
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

	$message_info = getMessageInfo($id_msg);

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
		SELECT ' . ($next ? 'MIN(id_msg)' : 'MAX($id_msg)') . '
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

function messageAt($start, $id_topic)
{
	$db = database();

	$result = $db->query('', '
		SELECT id_msg
		FROM {db_prefix}messages
		WHERE id_topic = {int:current_topic}
		ORDER BY id_msg
		LIMIT {int:start}, 1',
		array(
			'current_topic' => $id_topic,
			'start' => $start,
		)
	);
	list ($msg) = $db->fetch_row($result);
	$db->free_result($result);

	return $msg;
}