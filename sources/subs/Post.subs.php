<?php

/**
 * This file contains those functions pertaining to posting, and other such
 * operations, including sending emails, ims, blocking spam, preparsing posts,
 * spell checking, and the post box.
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * This file contains code covered by:
 * copyright:	2011 Simple Machines (http://www.simplemachines.org)
 * license:  	BSD, See included LICENSE.TXT for terms and conditions.
 *
 * @version 1.1 beta 4
 *
 */

/**
 * Takes a message and parses it, returning the prepared message as a reference.
 *
 * - Cleans up links (javascript, etc.) and code/quote sections.
 * - Won't convert \n's and a few other things if previewing is true.
 *
 * @package Posts
 * @param string $message
 * @param boolean $previewing
 */
function preparsecode(&$message, $previewing = false)
{
	$preparse = \BBC\PreparseCode::getInstance();
	$preparse->preparsecode($message, $previewing);
}

/**
 * This is very simple, and just removes things done by preparsecode.
 *
 * @package Posts
 * @param string $message
 */
function un_preparsecode($message)
{
	$un_preparse = \BBC\PreparseCode::getInstance();
	return $un_preparse->un_preparsecode($message);
}

/**
 * Create a post, either as new topic (id_topic = 0) or in an existing one.
 *
 * The input parameters of this function assume:
 * - Strings have been escaped.
 * - Integers have been cast to integer.
 * - Mandatory parameters are set.
 *
 * @package Posts
 * @param mixed[] $msgOptions
 * @param mixed[] $topicOptions
 * @param mixed[] $posterOptions
 */
function createPost(&$msgOptions, &$topicOptions, &$posterOptions)
{
	global $user_info, $txt, $modSettings;

	$db = database();

	// Set optional parameters to the default value.
	$msgOptions['icon'] = empty($msgOptions['icon']) ? 'xx' : $msgOptions['icon'];
	$msgOptions['smileys_enabled'] = !empty($msgOptions['smileys_enabled']);
	// @todo 2015/03/02 - The following line should probably be moved to a module
	$msgOptions['attachments'] = empty($msgOptions['attachments']) ? array() : $msgOptions['attachments'];
	$msgOptions['approved'] = isset($msgOptions['approved']) ? (int) $msgOptions['approved'] : 1;
	$topicOptions['id'] = empty($topicOptions['id']) ? 0 : (int) $topicOptions['id'];
	$topicOptions['poll'] = isset($topicOptions['poll']) ? (int) $topicOptions['poll'] : null;
	$topicOptions['lock_mode'] = isset($topicOptions['lock_mode']) ? $topicOptions['lock_mode'] : null;
	$topicOptions['sticky_mode'] = isset($topicOptions['sticky_mode']) ? $topicOptions['sticky_mode'] : null;
	$topicOptions['redirect_expires'] = isset($topicOptions['redirect_expires']) ? $topicOptions['redirect_expires'] : null;
	$topicOptions['redirect_topic'] = isset($topicOptions['redirect_topic']) ? $topicOptions['redirect_topic'] : null;
	$posterOptions['id'] = empty($posterOptions['id']) ? 0 : (int) $posterOptions['id'];
	$posterOptions['ip'] = empty($posterOptions['ip']) ? $user_info['ip'] : $posterOptions['ip'];

	// We need to know if the topic is approved. If we're told that's great - if not find out.
	if (!$modSettings['postmod_active'])
		$topicOptions['is_approved'] = true;
	elseif (!empty($topicOptions['id']) && !isset($topicOptions['is_approved']))
	{
		$is_approved = topicAttribute($topicOptions['id'], array('approved'));
		$topicOptions['is_approved'] = $is_approved['approved'];
	}

	// If nothing was filled in as name/email address, try the member table.
	if (!isset($posterOptions['name']) || $posterOptions['name'] == '' || (empty($posterOptions['email']) && !empty($posterOptions['id'])))
	{
		if (empty($posterOptions['id']))
		{
			$posterOptions['id'] = 0;
			$posterOptions['name'] = $txt['guest_title'];
			$posterOptions['email'] = '';
		}
		elseif ($posterOptions['id'] != $user_info['id'])
		{
			require_once(SUBSDIR . '/Members.subs.php');
			$result = getBasicMemberData($posterOptions['id']);
			// Couldn't find the current poster?
			if (empty($result))
			{
				trigger_error('createPost(): Invalid member id ' . $posterOptions['id'], E_USER_NOTICE);
				$posterOptions['id'] = 0;
				$posterOptions['name'] = $txt['guest_title'];
				$posterOptions['email'] = '';
			}
			else
			{
				$posterOptions['name'] = $result['member_name'];
				$posterOptions['email'] = $result['email_address'];
			}
		}
		else
		{
			$posterOptions['name'] = $user_info['name'];
			$posterOptions['email'] = $user_info['email'];
		}
	}

	// It's do or die time: forget any user aborts!
	$previous_ignore_user_abort = ignore_user_abort(true);

	$new_topic = empty($topicOptions['id']);

	$message_columns = array(
		'id_board' => 'int',
		'id_topic' => 'int',
		'id_member' => 'int',
		'subject' => 'string-255',
		'body' => (!empty($modSettings['max_messageLength']) && $modSettings['max_messageLength'] > 65534 ? 'string-' . $modSettings['max_messageLength'] : (empty($modSettings['max_messageLength']) ? 'string' : 'string-65534')),
		'poster_name' => 'string-255',
		'poster_email' => 'string-255',
		'poster_time' => 'int',
		'poster_ip' => 'string-255',
		'smileys_enabled' => 'int',
		'modified_name' => 'string',
		'icon' => 'string-16',
		'approved' => 'int',
	);

	$message_parameters = array(
		'id_board' => $topicOptions['board'],
		'id_topic' => $topicOptions['id'],
		'id_member' => $posterOptions['id'],
		'subject' => $msgOptions['subject'],
		'body' => $msgOptions['body'],
		'poster_name' => $posterOptions['name'],
		'poster_email' => $posterOptions['email'],
		'poster_time' => empty($posterOptions['time']) ? time() : $posterOptions['time'],
		'poster_ip' => $posterOptions['ip'],
		'smileys_enabled' => $msgOptions['smileys_enabled'] ? 1 : 0,
		'modified_name' => '',
		'icon' => $msgOptions['icon'],
		'approved' => $msgOptions['approved'],
	);

	// What if we want to do anything with posts?
	call_integration_hook('integrate_before_create_post', array(&$msgOptions, &$topicOptions, &$posterOptions, &$message_columns, &$message_parameters));

	// Insert the post.
	$db->insert('',
		'{db_prefix}messages',
		$message_columns,
		$message_parameters,
		array('id_msg')
	);
	$msgOptions['id'] = $db->insert_id('{db_prefix}messages', 'id_msg');

	// Something went wrong creating the message...
	if (empty($msgOptions['id']))
		return false;

	// What if we want to export new posts out to a CMS?
	call_integration_hook('integrate_create_post', array($msgOptions, $topicOptions, $posterOptions, $message_columns, $message_parameters));

	// Insert a new topic (if the topicID was left empty.)
	if ($new_topic)
	{
		$topic_columns = array(
			'id_board' => 'int', 'id_member_started' => 'int',
			'id_member_updated' => 'int', 'id_first_msg' => 'int',
			'id_last_msg' => 'int', 'locked' => 'int',
			'is_sticky' => 'int', 'num_views' => 'int',
			'id_poll' => 'int',
			'unapproved_posts' => 'int', 'approved' => 'int',
			'redirect_expires' => 'int',
			'id_redirect_topic' => 'int',
		);
		$topic_parameters = array(
			'id_board' => $topicOptions['board'],
			'id_member_started' => $posterOptions['id'],
			'id_member_updated' => $posterOptions['id'],
			'id_first_msg' => $msgOptions['id'],
			'id_last_msg' => $msgOptions['id'],
			'locked' => $topicOptions['lock_mode'] === null ? 0 : $topicOptions['lock_mode'],
			'is_sticky' => $topicOptions['sticky_mode'] === null ? 0 : $topicOptions['sticky_mode'],
			'num_views' => 0,
			'id_poll' => $topicOptions['poll'] === null ? 0 : $topicOptions['poll'],
			'unapproved_posts' =>  $msgOptions['approved'] ? 0 : 1,
			'approved' => $msgOptions['approved'],
			'redirect_expires' => $topicOptions['redirect_expires'] === null ? 0 : $topicOptions['redirect_expires'],
			'id_redirect_topic' => $topicOptions['redirect_topic'] === null ? 0 : $topicOptions['redirect_topic'],
		);

		call_integration_hook('integrate_before_create_topic', array(&$msgOptions, &$topicOptions, &$posterOptions, &$topic_columns, &$topic_parameters));

		$db->insert('',
			'{db_prefix}topics',
			$topic_columns,
			$topic_parameters,
			array('id_topic')
		);
		$topicOptions['id'] = $db->insert_id('{db_prefix}topics', 'id_topic');

		// The topic couldn't be created for some reason.
		if (empty($topicOptions['id']))
		{
			// We should delete the post that did work, though...
			$db->query('', '
				DELETE FROM {db_prefix}messages
				WHERE id_msg = {int:id_msg}',
				array(
					'id_msg' => $msgOptions['id'],
				)
			);

			return false;
		}

		// Fix the message with the topic.
		$db->query('', '
			UPDATE {db_prefix}messages
			SET id_topic = {int:id_topic}
			WHERE id_msg = {int:id_msg}',
			array(
				'id_topic' => $topicOptions['id'],
				'id_msg' => $msgOptions['id'],
			)
		);

		// There's been a new topic AND a new post today.
		trackStats(array('topics' => '+', 'posts' => '+'));

		require_once(SUBSDIR . '/Topic.subs.php');
		updateTopicStats(true);
		require_once(SUBSDIR . '/Messages.subs.php');
		updateSubjectStats($topicOptions['id'], $msgOptions['subject']);

		// What if we want to export new topics out to a CMS?
		call_integration_hook('integrate_create_topic', array($msgOptions, $topicOptions, $posterOptions));
	}
	// The topic already exists, it only needs a little updating.
	else
	{
		$update_parameters = array(
			'poster_id' => $posterOptions['id'],
			'id_msg' => $msgOptions['id'],
			'locked' => $topicOptions['lock_mode'],
			'is_sticky' => $topicOptions['sticky_mode'],
			'id_topic' => $topicOptions['id'],
			'counter_increment' => 1,
		);

		if ($msgOptions['approved'])
			$topics_columns = array(
				'id_member_updated = {int:poster_id}',
				'id_last_msg = {int:id_msg}',
				'num_replies = num_replies + {int:counter_increment}',
			);
		else
			$topics_columns = array(
				'unapproved_posts = unapproved_posts + {int:counter_increment}',
			);

		if ($topicOptions['lock_mode'] !== null)
			$topics_columns[] = 'locked = {int:locked}';

		if ($topicOptions['sticky_mode'] !== null)
			$topics_columns[] = 'is_sticky = {int:is_sticky}';

		call_integration_hook('integrate_before_modify_topic', array(&$topics_columns, &$update_parameters, &$msgOptions, &$topicOptions, &$posterOptions));

		// Update the number of replies and the lock/sticky status.
		$db->query('', '
			UPDATE {db_prefix}topics
			SET
				' . implode(', ', $topics_columns) . '
			WHERE id_topic = {int:id_topic}',
			$update_parameters
		);

		// One new post has been added today.
		trackStats(array('posts' => '+'));
	}

	// Creating is modifying...in a way.
	// @todo id_msg_modified needs to be set when you create a post, now this query is
	// the only place it does get set for post creation.  Why not set it on the insert?
	$db->query('', '
		UPDATE {db_prefix}messages
		SET id_msg_modified = {int:id_msg}
		WHERE id_msg = {int:id_msg}',
		array(
			'id_msg' => $msgOptions['id'],
		)
	);

	// Increase the number of posts and topics on the board.
	if ($msgOptions['approved'])
		$db->query('', '
			UPDATE {db_prefix}boards
			SET num_posts = num_posts + 1' . ($new_topic ? ', num_topics = num_topics + 1' : '') . '
			WHERE id_board = {int:id_board}',
			array(
				'id_board' => $topicOptions['board'],
			)
		);
	else
	{
		$db->query('', '
			UPDATE {db_prefix}boards
			SET unapproved_posts = unapproved_posts + 1' . ($new_topic ? ', unapproved_topics = unapproved_topics + 1' : '') . '
			WHERE id_board = {int:id_board}',
			array(
				'id_board' => $topicOptions['board'],
			)
		);

		// Add to the approval queue too.
		$db->insert('',
			'{db_prefix}approval_queue',
			array(
				'id_msg' => 'int',
			),
			array(
				$msgOptions['id'],
			),
			array()
		);
	}

	// Mark inserted topic as read (only for the user calling this function).
	if (!empty($topicOptions['mark_as_read']) && !$user_info['is_guest'])
	{
		// Since it's likely they *read* it before replying, let's try an UPDATE first.
		if (!$new_topic)
		{
			$db->query('', '
				UPDATE {db_prefix}log_topics
				SET id_msg = {int:id_msg}
				WHERE id_member = {int:current_member}
					AND id_topic = {int:id_topic}',
				array(
					'current_member' => $posterOptions['id'],
					'id_msg' => $msgOptions['id'],
					'id_topic' => $topicOptions['id'],
				)
			);

			$flag = $db->affected_rows() != 0;
		}

		if (empty($flag))
		{
			require_once(SUBSDIR . '/Topic.subs.php');
			markTopicsRead(array($posterOptions['id'], $topicOptions['id'], $msgOptions['id'], 0), false);
		}
	}

	// If there's a custom search index, it may need updating...
	$search = new \ElkArte\Search\Search;
	$searchAPI = $search->findSearchAPI();
	if (is_callable(array($searchAPI, 'postCreated')))
		$searchAPI->postCreated($msgOptions, $topicOptions, $posterOptions);

	// Increase the post counter for the user that created the post.
	if (!empty($posterOptions['update_post_count']) && !empty($posterOptions['id']) && $msgOptions['approved'])
	{
		// Are you the one that happened to create this post?
		if ($user_info['id'] == $posterOptions['id'])
			$user_info['posts']++;

		require_once(SUBSDIR . '/Members.subs.php');
		updateMemberData($posterOptions['id'], array('posts' => '+'));
	}

	// They've posted, so they can make the view count go up one if they really want. (this is to keep views >= replies...)
	$_SESSION['last_read_topic'] = 0;

	// Better safe than sorry.
	if (isset($_SESSION['topicseen_cache'][$topicOptions['board']]))
		$_SESSION['topicseen_cache'][$topicOptions['board']]--;

	// Update all the stats so everyone knows about this new topic and message.
	require_once(SUBSDIR . '/Messages.subs.php');
	updateMessageStats(true, $msgOptions['id']);

	// Update the last message on the board assuming it's approved AND the topic is.
	if ($msgOptions['approved'])
		updateLastMessages($topicOptions['board'], $new_topic || !empty($topicOptions['is_approved']) ? $msgOptions['id'] : 0);

	// Alright, done now... we can abort now, I guess... at least this much is done.
	ignore_user_abort($previous_ignore_user_abort);

	// Success.
	return true;
}

/**
 * Modifying a post...
 *
 * @package Posts
 * @param mixed[] $msgOptions
 * @param mixed[] $topicOptions
 * @param mixed[] $posterOptions
 */
function modifyPost(&$msgOptions, &$topicOptions, &$posterOptions)
{
	global $user_info, $modSettings;

	$db = database();

	$topicOptions['poll'] = isset($topicOptions['poll']) ? (int) $topicOptions['poll'] : null;
	$topicOptions['lock_mode'] = isset($topicOptions['lock_mode']) ? $topicOptions['lock_mode'] : null;
	$topicOptions['sticky_mode'] = isset($topicOptions['sticky_mode']) ? $topicOptions['sticky_mode'] : null;

	// This is longer than it has to be, but makes it so we only set/change what we have to.
	$messages_columns = array();
	if (isset($posterOptions['name']))
		$messages_columns['poster_name'] = $posterOptions['name'];
	if (isset($posterOptions['email']))
		$messages_columns['poster_email'] = $posterOptions['email'];
	if (isset($msgOptions['icon']))
		$messages_columns['icon'] = $msgOptions['icon'];
	if (isset($msgOptions['subject']))
		$messages_columns['subject'] = $msgOptions['subject'];
	if (isset($msgOptions['body']))
	{
		$messages_columns['body'] = $msgOptions['body'];

		// using a custom search index, then lets get the old message so we can update our index as needed
		if (!empty($modSettings['search_custom_index_config']))
		{
			require_once(SUBSDIR . '/Messages.subs.php');
			$message = basicMessageInfo($msgOptions['id'], true);
			$msgOptions['old_body'] = $message['body'];
		}
	}
	if (!empty($msgOptions['modify_time']))
	{
		$messages_columns['modified_time'] = $msgOptions['modify_time'];
		$messages_columns['modified_name'] = $msgOptions['modify_name'];
		$messages_columns['id_msg_modified'] = $modSettings['maxMsgID'];
	}
	if (isset($msgOptions['smileys_enabled']))
		$messages_columns['smileys_enabled'] = empty($msgOptions['smileys_enabled']) ? 0 : 1;

	// Which columns need to be ints?
	$messageInts = array('modified_time', 'id_msg_modified', 'smileys_enabled');
	$update_parameters = array(
		'id_msg' => $msgOptions['id'],
	);

	call_integration_hook('integrate_before_modify_post', array(&$messages_columns, &$update_parameters, &$msgOptions, &$topicOptions, &$posterOptions, &$messageInts));

	foreach ($messages_columns as $var => $val)
	{
		$messages_columns[$var] = $var . ' = {' . (in_array($var, $messageInts) ? 'int' : 'string') . ':var_' . $var . '}';
		$update_parameters['var_' . $var] = $val;
	}

	// Nothing to do?
	if (empty($messages_columns))
		return true;

	// Change the post.
	$db->query('', '
		UPDATE {db_prefix}messages
		SET ' . implode(', ', $messages_columns) . '
		WHERE id_msg = {int:id_msg}',
		$update_parameters
	);

	$attributes = array();
	// Lock and or sticky the post.
	if ($topicOptions['sticky_mode'] !== null)
		$attributes['is_sticky'] = $topicOptions['sticky_mode'];
	if ($topicOptions['lock_mode'] !== null)
		$attributes['locked'] = $topicOptions['lock_mode'];
	if ($topicOptions['poll'] !== null)
		$attributes['id_poll'] = $topicOptions['poll'];

	// If anything to do, do it.
	if (!empty($attributes))
		setTopicAttribute($topicOptions['id'], $attributes);

	// Mark the edited post as read.
	if (!empty($topicOptions['mark_as_read']) && !$user_info['is_guest'])
	{
		// Since it's likely they *read* it before editing, let's try an UPDATE first.
		$db->query('', '
			UPDATE {db_prefix}log_topics
			SET id_msg = {int:id_msg}
			WHERE id_member = {int:current_member}
				AND id_topic = {int:id_topic}',
			array(
				'current_member' => $user_info['id'],
				'id_msg' => $modSettings['maxMsgID'],
				'id_topic' => $topicOptions['id'],
			)
		);

		$flag = $db->affected_rows() != 0;

		if (empty($flag))
		{
			require_once(SUBSDIR . '/Topic.subs.php');
			markTopicsRead(array($user_info['id'], $topicOptions['id'], $modSettings['maxMsgID'], 0), false);
		}
	}

	// If there's a custom search index, it needs to be modified...
	$search = new \ElkArte\Search\Search;
	$searchAPI = $search->findSearchAPI();
	if (is_callable(array($searchAPI, 'postModified')))
		$searchAPI->postModified($msgOptions, $topicOptions, $posterOptions);

	if (isset($msgOptions['subject']))
	{
		// Only update the subject if this was the first message in the topic.
		$request = $db->query('', '
			SELECT id_topic
			FROM {db_prefix}topics
			WHERE id_first_msg = {int:id_first_msg}
			LIMIT 1',
			array(
				'id_first_msg' => $msgOptions['id'],
			)
		);
		if ($db->num_rows($request) == 1)
		{
			require_once(SUBSDIR . '/Messages.subs.php');
			updateSubjectStats($topicOptions['id'], $msgOptions['subject']);
		}
		$db->free_result($request);
	}

	// Finally, if we are setting the approved state we need to do much more work :(
	if ($modSettings['postmod_active'] && isset($msgOptions['approved']))
		approvePosts($msgOptions['id'], $msgOptions['approved']);

	return true;
}

/**
 * Approve (or not) some posts... without permission checks...
 *
 * @package Posts
 * @param int|int[] $msgs - array of message ids
 * @param bool $approve = true
 */
function approvePosts($msgs, $approve = true)
{
	global $modSettings;

	$db = database();

	if (!is_array($msgs))
		$msgs = array($msgs);

	if (empty($msgs))
		return false;

	// May as well start at the beginning, working out *what* we need to change.
	$request = $db->query('', '
		SELECT m.id_msg, m.approved, m.id_topic, m.id_board, t.id_first_msg, t.id_last_msg,
			m.body, m.subject, COALESCE(mem.real_name, m.poster_name) AS poster_name, m.id_member,
			t.approved AS topic_approved, b.count_posts
		FROM {db_prefix}messages AS m
			INNER JOIN {db_prefix}topics AS t ON (t.id_topic = m.id_topic)
			INNER JOIN {db_prefix}boards AS b ON (b.id_board = m.id_board)
			LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = m.id_member)
		WHERE m.id_msg IN ({array_int:message_list})
			AND m.approved = {int:approved_state}',
		array(
			'message_list' => $msgs,
			'approved_state' => $approve ? 0 : 1,
		)
	);
	$msgs = array();
	$topics = array();
	$topic_changes = array();
	$board_changes = array();
	$notification_topics = array();
	$notification_posts = array();
	$member_post_changes = array();
	while ($row = $db->fetch_assoc($request))
	{
		// Easy...
		$msgs[] = $row['id_msg'];
		$topics[] = $row['id_topic'];

		// Ensure our change array exists already.
		if (!isset($topic_changes[$row['id_topic']]))
			$topic_changes[$row['id_topic']] = array(
				'id_last_msg' => $row['id_last_msg'],
				'approved' => $row['topic_approved'],
				'replies' => 0,
				'unapproved_posts' => 0,
			);
		if (!isset($board_changes[$row['id_board']]))
			$board_changes[$row['id_board']] = array(
				'posts' => 0,
				'topics' => 0,
				'unapproved_posts' => 0,
				'unapproved_topics' => 0,
			);

		// If it's the first message then the topic state changes!
		if ($row['id_msg'] == $row['id_first_msg'])
		{
			$topic_changes[$row['id_topic']]['approved'] = $approve ? 1 : 0;

			$board_changes[$row['id_board']]['unapproved_topics'] += $approve ? -1 : 1;
			$board_changes[$row['id_board']]['topics'] += $approve ? 1 : -1;

			// Note we need to ensure we announce this topic!
			$notification_topics[] = array(
				'body' => $row['body'],
				'subject' => $row['subject'],
				'name' => $row['poster_name'],
				'board' => $row['id_board'],
				'topic' => $row['id_topic'],
				'msg' => $row['id_first_msg'],
				'poster' => $row['id_member'],
			);
		}
		else
		{
			$topic_changes[$row['id_topic']]['replies'] += $approve ? 1 : -1;

			// This will be a post... but don't notify unless it's not followed by approved ones.
			if ($row['id_msg'] > $row['id_last_msg'])
				$notification_posts[$row['id_topic']][] = array(
					'id' => $row['id_msg'],
					'body' => $row['body'],
					'subject' => $row['subject'],
					'name' => $row['poster_name'],
					'topic' => $row['id_topic'],
				);
		}

		// If this is being approved and id_msg is higher than the current id_last_msg then it changes.
		if ($approve && $row['id_msg'] > $topic_changes[$row['id_topic']]['id_last_msg'])
			$topic_changes[$row['id_topic']]['id_last_msg'] = $row['id_msg'];
		// If this is being unapproved, and it's equal to the id_last_msg we need to find a new one!
		elseif (!$approve)
			// Default to the first message and then we'll override in a bit ;)
			$topic_changes[$row['id_topic']]['id_last_msg'] = $row['id_first_msg'];

		$topic_changes[$row['id_topic']]['unapproved_posts'] += $approve ? -1 : 1;
		$board_changes[$row['id_board']]['unapproved_posts'] += $approve ? -1 : 1;
		$board_changes[$row['id_board']]['posts'] += $approve ? 1 : -1;

		// Post count for the user?
		if ($row['id_member'] && empty($row['count_posts']))
			$member_post_changes[$row['id_member']] = isset($member_post_changes[$row['id_member']]) ? $member_post_changes[$row['id_member']] + 1 : 1;
	}
	$db->free_result($request);

	if (empty($msgs))
		return;

	// Now we have the differences make the changes, first the easy one.
	$db->query('', '
		UPDATE {db_prefix}messages
		SET approved = {int:approved_state}
		WHERE id_msg IN ({array_int:message_list})',
		array(
			'message_list' => $msgs,
			'approved_state' => $approve ? 1 : 0,
		)
	);

	// If we were unapproving find the last msg in the topics...
	if (!$approve)
	{
		$request = $db->query('', '
			SELECT id_topic, MAX(id_msg) AS id_last_msg
			FROM {db_prefix}messages
			WHERE id_topic IN ({array_int:topic_list})
				AND approved = {int:approved}
			GROUP BY id_topic',
			array(
				'topic_list' => $topics,
				'approved' => 1,
			)
		);
		while ($row = $db->fetch_assoc($request))
			$topic_changes[$row['id_topic']]['id_last_msg'] = $row['id_last_msg'];
		$db->free_result($request);
	}

	// ... next the topics...
	foreach ($topic_changes as $id => $changes)
		$db->query('', '
			UPDATE {db_prefix}topics
			SET
				approved = {int:approved},
				unapproved_posts = CASE WHEN unapproved_posts + {int:unapproved_posts} < 0 THEN 0 ELSE unapproved_posts + {int:unapproved_posts} END,
				num_replies = CASE WHEN num_replies + {int:num_replies} < 0 THEN 0 ELSE num_replies + {int:num_replies} END,
				id_last_msg = {int:id_last_msg}
			WHERE id_topic = {int:id_topic}',
			array(
				'approved' => $changes['approved'],
				'unapproved_posts' => $changes['unapproved_posts'],
				'num_replies' => $changes['replies'],
				'id_last_msg' => $changes['id_last_msg'],
				'id_topic' => $id,
			)
		);

	// ... finally the boards...
	foreach ($board_changes as $id => $changes)
		$db->query('', '
			UPDATE {db_prefix}boards
			SET
				num_posts = num_posts + {int:num_posts},
				unapproved_posts = CASE WHEN unapproved_posts + {int:unapproved_posts} < 0 THEN 0 ELSE unapproved_posts + {int:unapproved_posts} END,
				num_topics = CASE WHEN num_topics + {int:num_topics} < 0 THEN 0 ELSE num_topics + {int:num_topics} END,
				unapproved_topics = CASE WHEN unapproved_topics + {int:unapproved_topics} < 0 THEN 0 ELSE unapproved_topics + {int:unapproved_topics} END
			WHERE id_board = {int:id_board}',
			array(
				'num_posts' => $changes['posts'],
				'unapproved_posts' => $changes['unapproved_posts'],
				'num_topics' => $changes['topics'],
				'unapproved_topics' => $changes['unapproved_topics'],
				'id_board' => $id,
			)
		);

	// Finally, least importantly, notifications!
	if ($approve)
	{
		require_once(SUBSDIR . '/Notification.subs.php');

		if (!empty($notification_topics))
			sendBoardNotifications($notification_topics);

		if (!empty($notification_posts))
			sendApprovalNotifications($notification_posts);

		$db->query('', '
			DELETE FROM {db_prefix}approval_queue
			WHERE id_msg IN ({array_int:message_list})
				AND id_attach = {int:id_attach}',
			array(
				'message_list' => $msgs,
				'id_attach' => 0,
			)
		);
	}
	// If unapproving add to the approval queue!
	else
	{
		$msgInserts = array();
		foreach ($msgs as $msg)
			$msgInserts[] = array($msg);

		$db->insert('ignore',
			'{db_prefix}approval_queue',
			array('id_msg' => 'int'),
			$msgInserts,
			array('id_msg')
		);
	}

	if (!empty($modSettings['mentions_enabled']))
	{
		require_once(SUBSDIR . '/Mentions.subs.php');
		toggleMentionsApproval($msgs, $approve);
	}

	// Update the last messages on the boards...
	updateLastMessages(array_keys($board_changes));

	// Post count for the members?
	if (!empty($member_post_changes))
	{
		require_once(SUBSDIR . '/Members.subs.php');
		foreach ($member_post_changes as $id_member => $count_change)
			updateMemberData($id_member, array('posts' => 'posts ' . ($approve ? '+' : '-') . ' ' . $count_change));
	}

	return true;
}

/**
 * Takes an array of board IDs and updates their last messages.
 *
 * - If the board has a parent, that parent board is also automatically updated.
 * - The columns updated are id_last_msg and last_updated.
 * - Note that id_last_msg should always be updated using this function,
 * and is not automatically updated upon other changes.
 *
 * @package Posts
 * @param int[]|int $setboards
 * @param int $id_msg = 0
 */
function updateLastMessages($setboards, $id_msg = 0)
{
	global $board_info, $board;

	$db = database();

	// Please - let's be sane.
	if (empty($setboards))
		return false;

	if (!is_array($setboards))
		$setboards = array($setboards);

	$lastMsg = array();

	// If we don't know the id_msg we need to find it.
	if (!$id_msg)
	{
		// Find the latest message on this board (highest id_msg.)
		$request = $db->query('', '
			SELECT id_board, MAX(id_last_msg) AS id_msg
			FROM {db_prefix}topics
			WHERE id_board IN ({array_int:board_list})
				AND approved = {int:approved}
			GROUP BY id_board',
			array(
				'board_list' => $setboards,
				'approved' => 1,
			)
		);
		while ($row = $db->fetch_assoc($request))
			$lastMsg[$row['id_board']] = $row['id_msg'];
		$db->free_result($request);
	}
	else
	{
		// Just to note - there should only be one board passed if we are doing this.
		foreach ($setboards as $id_board)
			$lastMsg[$id_board] = $id_msg;
	}

	$parent_boards = array();

	// Keep track of last modified dates.
	$lastModified = $lastMsg;

	// Get all the sub-boards for the parents, if they have some...
	foreach ($setboards as $id_board)
	{
		if (!isset($lastMsg[$id_board]))
		{
			$lastMsg[$id_board] = 0;
			$lastModified[$id_board] = 0;
		}

		if (!empty($board) && $id_board == $board)
			$parents = $board_info['parent_boards'];
		else
			$parents = getBoardParents($id_board);

		// Ignore any parents on the top child level.
		foreach ($parents as $id => $parent)
		{
			if ($parent['level'] != 0)
			{
				// If we're already doing this one as a board, is this a higher last modified?
				if (isset($lastModified[$id]) && $lastModified[$id_board] > $lastModified[$id])
					$lastModified[$id] = $lastModified[$id_board];
				elseif (!isset($lastModified[$id]) && (!isset($parent_boards[$id]) || $parent_boards[$id] < $lastModified[$id_board]))
					$parent_boards[$id] = $lastModified[$id_board];
			}
		}
	}

	// Note to help understand what is happening here. For parents we update the timestamp of the last message for determining
	// whether there are sub-boards which have not been read. For the boards themselves we update both this and id_last_msg.
	$board_updates = array();
	$parent_updates = array();

	// Finally, to save on queries make the changes...
	foreach ($parent_boards as $id => $msg)
	{
		if (!isset($parent_updates[$msg]))
			$parent_updates[$msg] = array($id);
		else
			$parent_updates[$msg][] = $id;
	}

	foreach ($lastMsg as $id => $msg)
	{
		if (!isset($board_updates[$msg . '-' . $lastModified[$id]]))
			$board_updates[$msg . '-' . $lastModified[$id]] = array(
				'id' => $msg,
				'updated' => $lastModified[$id],
				'boards' => array($id)
			);

		else
			$board_updates[$msg . '-' . $lastModified[$id]]['boards'][] = $id;
	}

	// Now commit the changes!
	foreach ($parent_updates as $id_msg => $boards)
	{
		$db->query('', '
			UPDATE {db_prefix}boards
			SET id_msg_updated = {int:id_msg_updated}
			WHERE id_board IN ({array_int:board_list})
				AND id_msg_updated < {int:id_msg_updated}',
			array(
				'board_list' => $boards,
				'id_msg_updated' => $id_msg,
			)
		);
	}
	foreach ($board_updates as $board_data)
	{
		$db->query('', '
			UPDATE {db_prefix}boards
			SET id_last_msg = {int:id_last_msg}, id_msg_updated = {int:id_msg_updated}
			WHERE id_board IN ({array_int:board_list})',
			array(
				'board_list' => $board_data['boards'],
				'id_last_msg' => $board_data['id'],
				'id_msg_updated' => $board_data['updated'],
			)
		);
	}
}

/**
 * Get the latest post made on the system
 *
 * - respects approved, recycled, and board permissions
 *
 * @package Posts
 * @return array
 */
function lastPost()
{
	global $scripturl, $modSettings;

	$db = database();

	// Find it by the board - better to order by board than sort the entire messages table.
	$request = $db->query('substring', '
		SELECT ml.poster_time, ml.subject, ml.id_topic, ml.poster_name, SUBSTRING(ml.body, 1, 385) AS body,
			ml.smileys_enabled
		FROM {db_prefix}boards AS b
			INNER JOIN {db_prefix}messages AS ml ON (ml.id_msg = b.id_last_msg)
		WHERE {query_wanna_see_board}' . (!empty($modSettings['recycle_enable']) && $modSettings['recycle_board'] > 0 ? '
			AND b.id_board != {int:recycle_board}' : '') . '
			AND ml.approved = {int:is_approved}
		ORDER BY b.id_msg_updated DESC
		LIMIT 1',
		array(
			'recycle_board' => $modSettings['recycle_board'],
			'is_approved' => 1,
		)
	);
	if ($db->num_rows($request) == 0)
		return array();
	$row = $db->fetch_assoc($request);
	$db->free_result($request);

	// Censor the subject and post...
	$row['subject'] = censor($row['subject']);
	$row['body'] = censor($row['body']);

	$bbc_parser = \BBC\ParserWrapper::getInstance();

	$row['body'] = strip_tags(strtr($bbc_parser->parseMessage($row['body'], $row['smileys_enabled']), array('<br />' => '&#10;')));
	$row['body'] = Util::shorten_text($row['body'], !empty($modSettings['lastpost_preview_characters']) ? $modSettings['lastpost_preview_characters'] : 128, true);

	// Send the data.
	return array(
		'topic' => $row['id_topic'],
		'subject' => $row['subject'],
		'short_subject' => Util::shorten_text($row['subject'], $modSettings['subject_length']),
		'preview' => $row['body'],
		'time' => standardTime($row['poster_time']),
		'html_time' => htmlTime($row['poster_time']),
		'timestamp' => forum_time(true, $row['poster_time']),
		'href' => $scripturl . '?topic=' . $row['id_topic'] . '.new;topicseen#new',
		'link' => '<a href="' . $scripturl . '?topic=' . $row['id_topic'] . '.new;topicseen#new">' . $row['subject'] . '</a>'
	);
}

/**
 * Prepares a post subject for the post form
 *
 * What it does:
 * - Will add the appropriate Re: to the post subject if its a reply to an existing post
 * - If quoting a post, or editing a post, this function also prepares the message body
 * - if editing is true, returns $message|$message[errors], else returns array($subject, $message)
 *
 * @package Posts
 * @param int|bool $editing
 * @param int|null|false $topic
 * @param string $first_subject
 * @param int $msg_id
 *
 * @return false|mixed[]
 */
function getFormMsgSubject($editing, $topic, $first_subject = '', $msg_id = 0)
{
	global $modSettings;

	$db = database();

	$form_subject = '';
	$form_message = '';
	switch ($editing)
	{
		case 1:
		{
			require_once(SUBSDIR . '/Messages.subs.php');

			// Get the existing message.
			$message = messageDetails((int) $msg_id, (int) $topic);

			// The message they were trying to edit was most likely deleted.
			if ($message === false)
				return false;

			$errors = checkMessagePermissions($message['message']);

			prepareMessageContext($message);

			if (!empty($errors))
				$message['errors'] = $errors;

			return $message;
		}
		// Posting a quoted reply?
		case 2:
		{
			$msg_id = !empty($_REQUEST['quote']) ? (int) $_REQUEST['quote'] : (int) $_REQUEST['followup'];

			// Make sure they _can_ quote this post, and if so get it.
			$request = $db->query('', '
				SELECT
					m.subject, COALESCE(mem.real_name, m.poster_name) AS poster_name, m.poster_time, m.body
				FROM {db_prefix}messages AS m
					INNER JOIN {db_prefix}boards AS b ON (b.id_board = m.id_board AND {query_see_board})
					LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = m.id_member)
				WHERE m.id_msg = {int:id_msg}' . (!$modSettings['postmod_active'] || allowedTo('approve_posts') ? '' : '
					AND m.approved = {int:is_approved}') . '
				LIMIT 1',
				array(
					'id_msg' => $msg_id,
					'is_approved' => 1,
				)
			);
			if ($db->num_rows($request) == 0)
				throw new Elk_Exception('quoted_post_deleted', false);
			list ($form_subject, $mname, $mdate, $form_message) = $db->fetch_row($request);
			$db->free_result($request);

			// Add 'Re: ' to the front of the quoted subject.
			$response_prefix = response_prefix();
			if (trim($response_prefix) != '' && Util::strpos($form_subject, trim($response_prefix)) !== 0)
				$form_subject = $response_prefix . $form_subject;

			// Censor the message and subject.
			$form_message = censor($form_message);
			$form_subject = censor($form_subject);

			$form_message = un_preparsecode($form_message);
			$form_message = removeNestedQuotes($form_message);

			// Add a quote string on the front and end.
			$form_message = '[quote author=' . $mname . ' link=msg=' . (int) $msg_id . ' date=' . $mdate . ']' . "\n" . rtrim($form_message) . "\n" . '[/quote]';

			break;
		}
		// Posting a reply without a quote?
		case 3:
		{
			// Get the first message's subject.
			$form_subject = $first_subject;

			// Add 'Re: ' to the front of the subject.
			$response_prefix = response_prefix();
			if (trim($response_prefix) != '' && $form_subject != '' && Util::strpos($form_subject, trim($response_prefix)) !== 0)
				$form_subject = $response_prefix . $form_subject;

			// Censor the subject.
			$form_subject = censor($form_subject);

			$form_message = '';

			break;
		}
		case 4:
		{
			$form_subject = isset($_GET['subject']) ? $_GET['subject'] : '';
			$form_message = '';

			break;
		}
	}

	return array($form_subject, $form_message);
}

/**
 * Update topic subject.
 *
 * - If $all is true, for all messages in the topic, otherwise only the first message.
 *
 * @package Posts
 * @param mixed[] $topic_info topic information as returned by getTopicInfo()
 * @param string $custom_subject
 * @param string $response_prefix = ''
 * @param bool $all = false
 */
function topicSubject($topic_info, $custom_subject, $response_prefix = '', $all = false)
{
	$db = database();

	if ($all)
	{
		$db->query('', '
			UPDATE {db_prefix}messages
			SET subject = {string:subject}
			WHERE id_topic = {int:current_topic}',
			array(
				'current_topic' => $topic_info['id_topic'],
				'subject' => $response_prefix . $custom_subject,
			)
		);
	}
	$db->query('', '
		UPDATE {db_prefix}messages
		SET subject = {string:custom_subject}
		WHERE id_msg = {int:id_first_msg}',
		array(
			'id_first_msg' => $topic_info['id_first_msg'],
			'custom_subject' => $custom_subject,
		)
	);
}
