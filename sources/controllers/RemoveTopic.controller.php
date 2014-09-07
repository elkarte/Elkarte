<?php

/**
 * The contents of this file handle the deletion of topics, posts, and related
 * paraphernalia.
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * This software is a derived product, based on:
 *
 * Simple Machines Forum (SMF)
 * copyright:	2011 Simple Machines (http://www.simplemachines.org)
 * license:		BSD, See included LICENSE.TXT for terms and conditions.
 *
 * @version 1.0
 *
 */

if (!defined('ELK'))
	die('No access...');

/**
 * Remove Topic Controller
 */
class RemoveTopic_Controller extends Action_Controller
{
	/**
	 * Intended entry point for this class.
	 *
	 * @see Action_Controller::action_index()
	 */
	public function action_index()
	{
		// call the right method
	}

	/**
	 * Completely remove an entire topic.
	 * Redirects to the board when completed.
	 * Accessed by ?action=removetopic2
	 */
	public function action_removetopic2()
	{
		global $user_info, $topic, $board, $modSettings;

		// Make sure they aren't being lead around by someone. (:@)
		checkSession('get');

		// This file needs to be included for sendNotifications().
		require_once(SUBSDIR . '/Notification.subs.php');

		// This needs to be included for all the topic db functions
		require_once(SUBSDIR . '/Topic.subs.php');

		// Trying to fool us around, are we?
		if (empty($topic))
			redirectexit();

		$this->removeDeleteConcurrence();

		$topic_info = getTopicInfo($topic, 'message');

		if ($topic_info['id_member_started'] == $user_info['id'] && !allowedTo('remove_any'))
			isAllowedTo('remove_own');
		else
			isAllowedTo('remove_any');

		// Can they see the topic?
		if ($modSettings['postmod_active'] && !$topic_info['approved'] && $topic_info['id_member_started'] != $user_info['id'])
			isAllowedTo('approve_posts');

		// Notify people that this topic has been removed.
		sendNotifications($topic, 'remove');

		removeTopics($topic);

		// Note, only log topic ID in native form if it's not gone forever.
		if (allowedTo('remove_any') || (allowedTo('remove_own') && $topic_info['id_member_started'] == $user_info['id']))
		{
			logAction('remove', array(
				(empty($modSettings['recycle_enable']) || $modSettings['recycle_board'] != $board ? 'topic' : 'old_topic_id') => $topic,
				'subject' => $topic_info['subject'],
				'member' => $topic_info['id_member_started'],
				'board' => $board)
			);
		}

		redirectexit('board=' . $board . '.0');
	}

	/**
	 * Remove just a single post.
	 * On completion redirect to the topic or to the board.
	 * Accessed by ?action=deletemsg
	 */
	public function action_deletemsg()
	{
		global $user_info, $topic, $board, $modSettings;

		checkSession('get');

		// This has some handy functions for topics
		require_once(SUBSDIR . '/Topic.subs.php');
		require_once(SUBSDIR . '/Messages.subs.php');

		$_REQUEST['msg'] = (int) $_REQUEST['msg'];

		// Is $topic set?
		if (empty($topic) && isset($_REQUEST['topic']))
			$topic = (int) $_REQUEST['topic'];

		$this->removeDeleteConcurrence();

		$topic_info = loadMessageDetails(array('t.id_member_started'), array('LEFT JOIN {db_prefix}topics AS t ON (m.id_topic = t.id_topic)'), array('message_list' => $_REQUEST['msg']));

		// Verify they can see this!
		if ($modSettings['postmod_active'] && !$topic_info['approved'] && !empty($topic_info['id_member']) && $topic_info['id_member'] != $user_info['id'])
			isAllowedTo('approve_posts');

		if ($topic_info['id_member'] == $user_info['id'])
		{
			if (!allowedTo('delete_own'))
			{
				if ($topic_info['id_member_started'] == $user_info['id'] && !allowedTo('delete_any'))
					isAllowedTo('delete_replies');
				elseif (!allowedTo('delete_any'))
					isAllowedTo('delete_own');
			}
			elseif (!allowedTo('delete_any') && ($topic_info['id_member_started'] != $user_info['id'] || !allowedTo('delete_replies')) && !empty($modSettings['edit_disable_time']) && $topic_info['poster_time'] + $modSettings['edit_disable_time'] * 60 < time())
				fatal_lang_error('modify_post_time_passed', false);
		}
		elseif ($topic_info['id_member_started'] == $user_info['id'] && !allowedTo('delete_any'))
			isAllowedTo('delete_replies');
		else
			isAllowedTo('delete_any');

		// If the full topic was removed go back to the board.
		require_once(SUBSDIR . '/Messages.subs.php');
		$full_topic = removeMessage($_REQUEST['msg']);

		if (allowedTo('delete_any') && (!allowedTo('delete_own') || $topic_info['id_member'] != $user_info['id']))
		{
			logAction('delete', array(
				'topic' => $topic,
				'subject' => $topic_info['subject'],
				'member' => $topic_info['id_member'],
				'board' => $board)
			);
		}

		// We want to redirect back to recent action.
		if (isset($_REQUEST['recent']))
			redirectexit('action=recent');
		elseif (isset($_REQUEST['profile'], $_REQUEST['start'], $_REQUEST['u']))
			redirectexit('action=profile;u=' . $_REQUEST['u'] . ';area=showposts;start=' . $_REQUEST['start']);
		elseif ($full_topic)
			redirectexit('board=' . $board . '.0');
		else
			redirectexit('topic=' . $topic . '.' . $_REQUEST['start']);
	}

	/**
	 * Move back a topic or post from the recycle board to its original board.
	 * Merges back the posts to the original as necessary.
	 * Accessed by ?action=restoretopic
	 */
	public function action_restoretopic()
	{
		global $modSettings;

		$db = database();

		// Check session.
		checkSession('get');

		// Is recycled board enabled?
		if (empty($modSettings['recycle_enable']))
			fatal_lang_error('restored_disabled', 'critical');

		// Can we be in here?
		isAllowedTo('move_any', $modSettings['recycle_board']);

		// We need this file.
		require_once(SUBSDIR . '/Topic.subs.php');

		$unfound_messages = array();
		$topics_to_restore = array();

		// Restoring messages?
		if (!empty($_REQUEST['msgs']))
		{
			$msgs = explode(',', $_REQUEST['msgs']);
			foreach ($msgs as $k => $msg)
				$msgs[$k] = (int) $msg;

			// Get the id_previous_board and id_previous_topic.
			$request = $db->query('', '
				SELECT m.id_topic, m.id_msg, m.id_board, m.subject, m.id_member, t.id_previous_board, t.id_previous_topic,
					t.id_first_msg, b.count_posts, IFNULL(pt.id_board, 0) AS possible_prev_board
				FROM {db_prefix}messages AS m
					INNER JOIN {db_prefix}topics AS t ON (t.id_topic = m.id_topic)
					INNER JOIN {db_prefix}boards AS b ON (b.id_board = m.id_board)
					LEFT JOIN {db_prefix}topics AS pt ON (pt.id_topic = t.id_previous_topic)
				WHERE m.id_msg IN ({array_int:messages})',
				array(
					'messages' => $msgs,
				)
			);

			$actioned_messages = array();
			$previous_topics = array();
			while ($row = $db->fetch_assoc($request))
			{
				// Restoring the first post means topic.
				if ($row['id_msg'] == $row['id_first_msg'] && $row['id_previous_topic'] == $row['id_topic'])
				{
					$topics_to_restore[] = $row['id_topic'];
					continue;
				}

				// Don't know where it's going?
				if (empty($row['id_previous_topic']))
				{
					$unfound_messages[$row['id_msg']] = $row['subject'];
					continue;
				}

				$previous_topics[] = $row['id_previous_topic'];
				if (empty($actioned_messages[$row['id_previous_topic']]))
					$actioned_messages[$row['id_previous_topic']] = array(
						'msgs' => array(),
						'count_posts' => $row['count_posts'],
						'subject' => $row['subject'],
						'previous_board' => $row['id_previous_board'],
						'possible_prev_board' => $row['possible_prev_board'],
						'current_topic' => $row['id_topic'],
						'current_board' => $row['id_board'],
						'members' => array(),
					);

				$actioned_messages[$row['id_previous_topic']]['msgs'][$row['id_msg']] = $row['subject'];
				if ($row['id_member'])
					$actioned_messages[$row['id_previous_topic']]['members'][] = $row['id_member'];
			}
			$db->free_result($request);

			// Check for topics we are going to fully restore.
			foreach ($actioned_messages as $topic => $data)
			{
				if (in_array($topic, $topics_to_restore))
					unset($actioned_messages[$topic]);
			}

			// Load any previous topics to check they exist.
			if (!empty($previous_topics))
			{
				$request = $db->query('', '
					SELECT t.id_topic, t.id_board, m.subject
					FROM {db_prefix}topics AS t
						INNER JOIN {db_prefix}messages AS m ON (m.id_msg = t.id_first_msg)
					WHERE t.id_topic IN ({array_int:previous_topics})',
					array(
						'previous_topics' => $previous_topics,
					)
				);
				$previous_topics = array();
				while ($row = $db->fetch_assoc($request))
					$previous_topics[$row['id_topic']] = array(
						'board' => $row['id_board'],
						'subject' => $row['subject'],
					);
				$db->free_result($request);
			}

			// Restore each topic.
			$messages = array();
			foreach ($actioned_messages as $topic => $data)
			{
				// If we have topics we are going to restore the whole lot ignore them.
				if (in_array($topic, $topics_to_restore))
				{
					unset($actioned_messages[$topic]);
					continue;
				}

				// Move the posts back then!
				if (isset($previous_topics[$topic]))
				{
					$this->mergePosts(array_keys($data['msgs']), $data['current_topic'], $topic);

					// Log em.
					logAction('restore_posts', array('topic' => $topic, 'subject' => $previous_topics[$topic]['subject'], 'board' => empty($data['previous_board']) ? $data['possible_prev_board'] : $data['previous_board']));
					$messages = array_merge(array_keys($data['msgs']), $messages);
				}
				else
				{
					foreach ($data['msgs'] as $msg)
						$unfound_messages[$msg['id']] = $msg['subject'];
				}
			}

			// Put the icons back.
			if (!empty($messages))
			{
				$db->query('', '
					UPDATE {db_prefix}messages
					SET icon = {string:icon}
					WHERE id_msg IN ({array_int:messages})',
					array(
						'icon' => 'xx',
						'messages' => $messages,
					)
				);
			}
		}

		// Now any topics?
		if (!empty($_REQUEST['topics']))
		{
			$topics = explode(',', $_REQUEST['topics']);
			foreach ($topics as $key => $id)
				$topics_to_restore[] = (int) $id;
		}

		if (!empty($topics_to_restore))
		{
			require_once(SUBSDIR . '/Boards.subs.php');

			// Lets get the data for these topics.
			$request = $db->query('', '
				SELECT t.id_topic, t.id_previous_board, t.id_board, t.id_first_msg, m.subject
				FROM {db_prefix}topics AS t
					INNER JOIN {db_prefix}messages AS m ON (m.id_msg = t.id_first_msg)
				WHERE t.id_topic IN ({array_int:topics})',
				array(
					'topics' => $topics_to_restore,
				)
			);

			while ($row = $db->fetch_assoc($request))
			{
				// We can only restore if the previous board is set.
				if (empty($row['id_previous_board']))
				{
					$unfound_messages[$row['id_first_msg']] = $row['subject'];
					continue;
				}

				// Ok we got here so me move them from here to there.
				moveTopics($row['id_topic'], $row['id_previous_board']);

				// Lets remove the recycled icon.
				$db->query('', '
					UPDATE {db_prefix}messages
					SET icon = {string:icon}
					WHERE id_topic = {int:id_topic}',
					array(
						'icon' => 'xx',
						'id_topic' => $row['id_topic'],
					)
				);

				// Lets see if the board that we are returning to has post count enabled.
				$board_data = boardInfo($row['id_previous_board']);

				if (empty($board_data['count_posts']))
				{
					// Lets get the members that need their post count restored.
					$request2 = $db->query('', '
						SELECT id_member, COUNT(id_msg) AS post_count
						FROM {db_prefix}messages
						WHERE id_topic = {int:topic}
							AND approved = {int:is_approved}
						GROUP BY id_member',
						array(
							'topic' => $row['id_topic'],
							'is_approved' => 1,
						)
					);

					while ($member = $db->fetch_assoc($request2))
						updateMemberData($member['id_member'], array('posts' => 'posts + ' . $member['post_count']));
					$db->free_result($request2);
				}

				// Log it.
				logAction('restore_topic', array('topic' => $row['id_topic'], 'board' => $row['id_board'], 'board_to' => $row['id_previous_board']));
			}
			$db->free_result($request);
		}

		// Didn't find some things?
		if (!empty($unfound_messages))
			fatal_lang_error('restore_not_found', false, array('<ul style="margin-top: 0px;"><li>' . implode('</li><li>', $unfound_messages) . '</li></ul>'));

		// Just send them to the index if they get here.
		redirectexit();
	}

	/**
	 * Take a load of messages from one place and stick them in a topic
	 *
	 * @param int[] $msgs
	 * @param integer $from_topic
	 * @param integer $target_topic
	 */
	public function mergePosts($msgs, $from_topic, $target_topic)
	{
		global $modSettings;

		$db = database();

		// @todo This really needs to be rewritten to take a load of messages from ANY topic, it's also inefficient.

		// Is it an array?
		if (!is_array($msgs))
			$msgs = array($msgs);

		// Lets make sure they are int.
		foreach ($msgs as $key => $msg)
			$msgs[$key] = (int) $msg;

		// Get the source information.
		$request = $db->query('', '
			SELECT t.id_board, t.id_first_msg, t.num_replies, t.unapproved_posts
			FROM {db_prefix}topics AS t
				INNER JOIN {db_prefix}boards AS b ON (b.id_board = t.id_board)
			WHERE t.id_topic = {int:from_topic}',
			array(
				'from_topic' => $from_topic,
			)
		);
		list ($from_board, $from_first_msg, $from_replies, $from_unapproved_posts) = $db->fetch_row($request);
		$db->free_result($request);

		// Get some target topic and board stats.
		$request = $db->query('', '
			SELECT t.id_board, t.id_first_msg, t.num_replies, t.unapproved_posts, b.count_posts
			FROM {db_prefix}topics AS t
				INNER JOIN {db_prefix}boards AS b ON (b.id_board = t.id_board)
			WHERE t.id_topic = {int:target_topic}',
			array(
				'target_topic' => $target_topic,
			)
		);
		list ($target_board, $target_first_msg, $target_replies, $target_unapproved_posts, $count_posts) = $db->fetch_row($request);
		$db->free_result($request);

		// Lets see if the board that we are returning to has post count enabled.
		if (empty($count_posts))
		{
			// Lets get the members that need their post count restored.
			$request = $db->query('', '
				SELECT id_member
				FROM {db_prefix}messages
				WHERE id_msg IN ({array_int:messages})
					AND approved = {int:is_approved}',
				array(
					'messages' => $msgs,
					'is_approved' => 1,
				)
			);

			while ($row = $db->fetch_assoc($request))
				updateMemberData($row['id_member'], array('posts' => '+'));
		}

		// Time to move the messages.
		$db->query('', '
			UPDATE {db_prefix}messages
			SET
				id_topic = {int:target_topic},
				id_board = {int:target_board},
				icon = {string:icon}
			WHERE id_msg IN({array_int:msgs})',
			array(
				'target_topic' => $target_topic,
				'target_board' => $target_board,
				'icon' => $target_board == $modSettings['recycle_board'] ? 'recycled' : 'xx',
				'msgs' => $msgs,
			)
		);

		// Fix the id_first_msg and id_last_msg for the target topic.
		$target_topic_data = array(
			'num_replies' => 0,
			'unapproved_posts' => 0,
			'id_first_msg' => 9999999999,
		);
		$request = $db->query('', '
			SELECT MIN(id_msg) AS id_first_msg, MAX(id_msg) AS id_last_msg, COUNT(*) AS message_count, approved
			FROM {db_prefix}messages
			WHERE id_topic = {int:target_topic}
			GROUP BY id_topic, approved
			ORDER BY approved ASC
			LIMIT 2',
			array(
				'target_topic' => $target_topic,
			)
		);
		while ($row = $db->fetch_assoc($request))
		{
			if ($row['id_first_msg'] < $target_topic_data['id_first_msg'])
				$target_topic_data['id_first_msg'] = $row['id_first_msg'];
			$target_topic_data['id_last_msg'] = $row['id_last_msg'];

			if (!$row['approved'])
				$target_topic_data['unapproved_posts'] = $row['message_count'];
			else
				$target_topic_data['num_replies'] = max(0, $row['message_count'] - 1);
		}
		$db->free_result($request);

		// We have a new post count for the board.
		require_once(SUBSDIR . '/Boards.subs.php');
		incrementBoard($target_board, array(
			'num_posts' => $target_topic_data['num_replies'] - $target_replies, // Lets keep in mind that the first message in a topic counts towards num_replies in a board.
			'unapproved_posts' => $target_topic_data['unapproved_posts'] - $target_unapproved_posts,
		));

		// In some cases we merged the only post in a topic so the topic data is left behind in the topic table.
		$request = $db->query('', '
			SELECT id_topic
			FROM {db_prefix}messages
			WHERE id_topic = {int:from_topic}',
			array(
				'from_topic' => $from_topic,
			)
		);

		// Remove the topic if it doesn't have any messages.
		$topic_exists = true;
		if ($db->num_rows($request) == 0)
		{
			require_once(SUBSDIR . '/Topic.subs.php');
			removeTopics($from_topic, false, true);
			$topic_exists = false;
		}
		$db->free_result($request);

		// Recycled topic.
		if ($topic_exists == true)
		{
			// Fix the id_first_msg and id_last_msg for the source topic.
			$source_topic_data = array(
				'num_replies' => 0,
				'unapproved_posts' => 0,
				'id_first_msg' => 9999999999,
			);

			$request = $db->query('', '
				SELECT MIN(id_msg) AS id_first_msg, MAX(id_msg) AS id_last_msg, COUNT(*) AS message_count, approved, subject
				FROM {db_prefix}messages
				WHERE id_topic = {int:from_topic}
				GROUP BY id_topic, approved
				ORDER BY approved ASC
				LIMIT 2',
				array(
					'from_topic' => $from_topic,
				)
			);
			while ($row = $db->fetch_assoc($request))
			{
				if ($row['id_first_msg'] < $source_topic_data['id_first_msg'])
					$source_topic_data['id_first_msg'] = $row['id_first_msg'];
				$source_topic_data['id_last_msg'] = $row['id_last_msg'];
				if (!$row['approved'])
					$source_topic_data['unapproved_posts'] = $row['message_count'];
				else
					$source_topic_data['num_replies'] = max(0, $row['message_count'] - 1);
			}
			$db->free_result($request);

			// Update the topic details for the source topic.
			$db->query('', '
				UPDATE {db_prefix}topics
				SET
					id_first_msg = {int:id_first_msg},
					id_last_msg = {int:id_last_msg},
					num_replies = {int:num_replies},
					unapproved_posts = {int:unapproved_posts}
				WHERE id_topic = {int:from_topic}',
				array(
					'id_first_msg' => $source_topic_data['id_first_msg'],
					'id_last_msg' => $source_topic_data['id_last_msg'],
					'num_replies' => $source_topic_data['num_replies'],
					'unapproved_posts' => $source_topic_data['unapproved_posts'],
					'from_topic' => $from_topic,
				)
			);

			// We have a new post count for the source board.
			incrementBoard($target_board, array(
				'num_posts' => $source_topic_data['num_replies'] - $from_replies, // Lets keep in mind that the first message in a topic counts towards num_replies in a board.
				'unapproved_posts' => $source_topic_data['unapproved_posts'] - $from_unapproved_posts,
			));
		}

		// Finally get around to updating the destination topic, now all indexes etc on the source are fixed.
		$db->query('', '
			UPDATE {db_prefix}topics
			SET
				id_first_msg = {int:id_first_msg},
				id_last_msg = {int:id_last_msg},
				num_replies = {int:num_replies},
				unapproved_posts = {int:unapproved_posts}
			WHERE id_topic = {int:target_topic}',
			array(
				'id_first_msg' => $target_topic_data['id_first_msg'],
				'id_last_msg' => $target_topic_data['id_last_msg'],
				'num_replies' => $target_topic_data['num_replies'],
				'unapproved_posts' => $target_topic_data['unapproved_posts'],
				'target_topic' => $target_topic,
			)
		);

		// Need it to update some stats.
		require_once(SUBSDIR . '/Post.subs.php');

		// Update stats.
		updateStats('topic');
		updateStats('message');

		// Subject cache?
		$cache_updates = array();
		if ($target_first_msg != $target_topic_data['id_first_msg'])
			$cache_updates[] = $target_topic_data['id_first_msg'];

		if (!empty($source_topic_data['id_first_msg']) && $from_first_msg != $source_topic_data['id_first_msg'])
			$cache_updates[] = $source_topic_data['id_first_msg'];

		if (!empty($cache_updates))
		{
			$request = $db->query('', '
				SELECT id_topic, subject
				FROM {db_prefix}messages
				WHERE id_msg IN ({array_int:first_messages})',
				array(
					'first_messages' => $cache_updates,
				)
			);
			while ($row = $db->fetch_assoc($request))
				updateStats('subject', $row['id_topic'], $row['subject']);
			$db->free_result($request);
		}

		updateLastMessages(array($from_board, $target_board));
	}

	/**
	 * Try to determine if the topic has already been deleted by another user.
	 */
	public function removeDeleteConcurrence()
	{
		global $modSettings, $board, $scripturl, $context;

		// No recycle no need to go further
		if (empty($modSettings['recycle_enable']) || empty($modSettings['recycle_board']))
			return false;

		// If it's confirmed go on and delete (from recycle)
		if (isset($_GET['confirm_delete']))
			return true;

		if (empty($board))
			return false;

		if ($modSettings['recycle_board'] != $board)
			return true;
		elseif (isset($_REQUEST['msg']))
			$confirm_url = $scripturl . '?action=deletemsg;confirm_delete;topic=' . $context['current_topic'] . '.0;msg=' . $_REQUEST['msg'] . ';' . $context['session_var'] . '=' . $context['session_id'];
		else
			$confirm_url = $scripturl . '?action=removetopic2;confirm_delete;topic=' . $context['current_topic'] . '.0;' . $context['session_var'] . '=' . $context['session_id'];

		fatal_lang_error('post_already_deleted', false, array($confirm_url));
	}
}