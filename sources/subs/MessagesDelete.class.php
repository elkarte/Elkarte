<?php

/**
 * This class takes care of deleting and restoring messages in boards
 * that means posts and topics
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
 * Class MessagesDelete
 *
 * Methods for deleting and restoring messages in boards
 */
class MessagesDelete
{
	/**
	 * Id of the messages not found.
	 *
	 * @var int[]
	 */
	private $_unfound_messages = array();

	/**
	 * Id of the topics that should be restored
	 *
	 * @var int[]
	 */
	private $_topics_to_restore = array();

	/**
	 * The board id of the recycle board
	 *
	 * @var int
	 */
	private $_recycle_board = null;

	/**
	 * List of errors occurred
	 *
	 * @var string[]
	 */
	private $_errors = array();

	/**
	 * Initialize the class! :P
	 *
	 * @param int|bool $recycle_enabled if the recycling is enabled.
	 * @param int|null $recycle_board the id the the recycle board (if any)
	 */
	public function __construct($recycle_enabled = false, $recycle_board = null)
	{
		if ($recycle_enabled)
			$this->_recycle_board = (int) $recycle_board;
		else
			$this->_recycle_board = null;
	}

	/**
	 * Restores a bunch of messages the recycle bin to the appropriate board.
	 * If any "first message" is within the array, it is added to the list of
	 * topics to restore (see MessagesDelete::restoreTopics)
	 *
	 * @param int[] $msgs_id Messages to restore
	 */
	public function restoreMessages($msgs_id)
	{
		$msgs = array();
		foreach ($msgs_id as $msg)
		{
			$msg = (int) $msg;
			if (!empty($msg))
				$msgs[] = $msg;
		}

		if (empty($msgs))
			return;

		$db = database();

		// Get the id_previous_board and id_previous_topic.
		$request = $db->query('', '
			SELECT m.id_topic, m.id_msg, m.id_board, m.subject, m.id_member, t.id_previous_board, t.id_previous_topic,
				t.id_first_msg, b.count_posts, COALESCE(pt.id_board, 0) AS possible_prev_board
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
				$this->_topics_to_restore[] = $row['id_topic'];
				continue;
			}

			// Don't know where it's going?
			if (empty($row['id_previous_topic']))
			{
				$this->_unfound_messages[$row['id_msg']] = $row['subject'];
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
			if (in_array($topic, $this->_topics_to_restore))
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
			if (in_array($topic, $this->_topics_to_restore))
			{
				unset($actioned_messages[$topic]);
				continue;
			}

			// Move the posts back then!
			if (isset($previous_topics[$topic]))
			{
				$this->_mergePosts(array_keys($data['msgs']), $data['current_topic'], $topic);

				// Log em.
				logAction('restore_posts', array('topic' => $topic, 'subject' => $previous_topics[$topic]['subject'], 'board' => empty($data['previous_board']) ? $data['possible_prev_board'] : $data['previous_board']));
				$messages = array_merge(array_keys($data['msgs']), $messages);
			}
			else
			{
				foreach ($data['msgs'] as $msg)
					$this->_unfound_messages[$msg['id']] = $msg['subject'];
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

		return $actioned_messages;
	}

	/**
	 * Prepares topics to be restored from the recycle bin to the appropriate board
	 *
	 * @param int[] $topics_id Topics to restore
	 */
	public function restoreTopics($topics_id)
	{
		foreach ($topics_id as $topic)
		{
			$topic = (int) $topic;
			if (!empty($topic))
				$this->_topics_to_restore[] = $topic;
		}
	}

	/**
	 * Actually restore the topics previously "collected"
	 */
	public function doRestore()
	{
		if (empty($this->_topics_to_restore))
			return;

		$db = database();

		require_once(SUBSDIR . '/Boards.subs.php');

		// Lets get the data for these topics.
		$request = $db->query('', '
			SELECT t.id_topic, t.id_previous_board, t.id_board, t.id_first_msg, m.subject
			FROM {db_prefix}topics AS t
				INNER JOIN {db_prefix}messages AS m ON (m.id_msg = t.id_first_msg)
			WHERE t.id_topic IN ({array_int:topics})',
			array(
				'topics' => $this->_topics_to_restore,
			)
		);

		while ($row = $db->fetch_assoc($request))
		{
			// We can only restore if the previous board is set.
			if (empty($row['id_previous_board']))
			{
				$this->_unfound_messages[$row['id_first_msg']] = $row['subject'];
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

				require_once(SUBSDIR . '/Members.subs.php');
				while ($member = $db->fetch_assoc($request2))
					updateMemberData($member['id_member'], array('posts' => 'posts + ' . $member['post_count']));
				$db->free_result($request2);
			}

			// Log it.
			logAction('restore_topic', array('topic' => $row['id_topic'], 'board' => $row['id_board'], 'board_to' => $row['id_previous_board']));
		}
		$db->free_result($request);
	}

	/**
	 * Returns the either the list of messages not found, or the number
	 *
	 * @param bool $return_msg If true returns the array of unfound messages,
	 *                         if false their number
	 * @return bool|int[]
	 */
	public function unfoundRestoreMessages($return_msg = false)
	{
		if ($return_msg)
			return $this->_unfound_messages;
		else
			return !empty($this->_unfound_messages);
	}

	/**
	 * Remove a specific message.
	 * This may include permission checks.
	 *
	 * - normally, local and global should be the localCookies and globalCookies settings, respectively.
	 * - uses boardurl to determine these two things.
	 *
	 * @param int  $message The message id
	 * @param bool $decreasePostCount if true users' post count will be reduced
	 * @param bool $check_permissions if true the method will also check
	 *              permissions to delete the message/topic (may result in fatal
	 *              errors or login screens)
	 *
	 * @return bool
	 * @throws Elk_Exception recycle_no_valid_board
	 */
	public function removeMessage($message, $decreasePostCount = true, $check_permissions = true)
	{
		global $board, $modSettings, $user_info;

		$db = database();
		$this->_errors = array();

		$message = (int) $message;

		if (empty($message))
			return false;

		$request = $db->query('', '
			SELECT
				m.id_msg, m.id_member, m.icon, m.poster_time, m.subject,' . (empty($modSettings['search_custom_index_config']) ? '' : ' m.body,') . '
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

		if ($check_permissions)
		{
			$check = $this->_checkDeletePermissions($row, $board);
			if ($check === true)
			{
				// This needs to be included for topic functions
				require_once(SUBSDIR . '/Topic.subs.php');

				removeTopics($row['id_topic']);
				return true;
			}
			elseif ($check == 'exit')
				return false;
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
		if (!empty($this->_recycle_board) && $row['id_board'] != $this->_recycle_board && $row['icon'] != 'recycled')
		{
			// Check if the recycle board exists and if so get the read status.
			$request = $db->query('', '
				SELECT (COALESCE(lb.id_msg, 0) >= b.id_msg_updated) AS is_seen, id_last_msg
				FROM {db_prefix}boards AS b
					LEFT JOIN {db_prefix}log_boards AS lb ON (lb.id_board = b.id_board AND lb.id_member = {int:current_member})
				WHERE b.id_board = {int:recycle_board}',
				array(
					'current_member' => $user_info['id'],
					'recycle_board' => $this->_recycle_board,
				)
			);
			if ($db->num_rows($request) == 0)
				throw new Elk_Exception('recycle_no_valid_board');
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
					'recycle_board' => $this->_recycle_board,
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
						$this->_recycle_board, $row['id_member'], $row['id_member'], $message,
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
						'recycle_board' => $this->_recycle_board,
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
					WHERE id_msg = {int:id_msg}
						AND type = {string:msg}',
					array(
						'id_topic' => $topicID,
						'recycle_board' => $this->_recycle_board,
						'id_msg' => $message,
						'msg' => 'msg',
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
					markBoardsRead($this->_recycle_board);
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
						'recycle_board' => $this->_recycle_board,
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
				require_once(SUBSDIR . '/Messages.subs.php');
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
		{
			require_once(SUBSDIR . '/Members.subs.php');
			updateMemberData($row['id_member'], array('posts' => '-'));
		}

		// Only remove posts if they're not recycled.
		if (!$recycle)
		{
			// Update the like counts
			require_once(SUBSDIR . '/Likes.subs.php');
			decreaseLikeCounts($message);

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
				WHERE id_target = {int:id_msg}
				AND mention_type IN ({array_string:mension_types})',
				array(
					'id_msg' => $message,
					'mension_types' => array('mentionmem', 'likemsg', 'rlikemsg'),
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
				$customIndexSettings = Util::unserialize($modSettings['search_custom_index_config']);
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
		require_once(SUBSDIR . '/Topic.subs.php');
		updateTopicStats();
		updateSettings(array(
			'calendar_updated' => time(),
		));

		// And now to update the last message of each board we messed with.
		require_once(SUBSDIR . '/Post.subs.php');
		if ($recycle)
			updateLastMessages(array($row['id_board'], $this->_recycle_board));
		else
			updateLastMessages($row['id_board']);

		// Close any moderation reports for this message.
		require_once(SUBSDIR . '/Moderation.subs.php');
		$updated_reports = updateReportsStatus($message, 'close', 1);
		if ($updated_reports != 0)
		{
			updateSettings(array('last_mod_report_action' => time()));
			recountOpenReports(true, allowedTo('admin_forum'));
		}

		// Add it to the mod log.
		if (allowedTo('delete_any') && (!allowedTo('delete_own') || $row['id_member'] != $user_info['id']))
			logAction('delete', array('topic' => $row['id_topic'], 'subject' => $row['subject'], 'member' => $row['id_member'], 'board' => $row['id_board']));

		return false;
	}

	/**
	 * Performs all the permission checks to see if the current user can
	 * delete the topic/message he would like to delete
	 *
	 * @param mixed[] $row Details on the message
	 * @param mixed[] $board The the user is in (?)
	 *
	 * @return bool|string
	 * @throws Elk_Exception cannot_delete_replies, cannot_delete_own, modify_post_time_passed
	 */
	protected function _checkDeletePermissions($row, $board)
	{
		global $user_info, $modSettings;

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
								throw new Elk_Exception('cannot_delete_replies', 'permission');
						}
						else
							throw new Elk_Exception('cannot_delete_own', 'permission');
					}
					elseif (($row['id_member_poster'] != $user_info['id'] || !$delete_replies) && !empty($modSettings['edit_disable_time']) && $row['poster_time'] + $modSettings['edit_disable_time'] * 60 < time())
						throw new Elk_Exception('modify_post_time_passed', false);
				}
				elseif ($row['id_member_poster'] == $user_info['id'])
				{
					if (!$delete_replies)
						throw new Elk_Exception('cannot_delete_replies', 'permission');
				}
				else
					throw new Elk_Exception('cannot_delete_any', 'permission');
			}

			// Can't delete an unapproved message, if you can't see it!
			if ($modSettings['postmod_active'] && !$row['approved'] && $row['id_member'] != $user_info['id'] && !(in_array(0, $delete_any) || in_array($row['id_board'], $delete_any)))
			{
				$approve_posts = !empty($user_info['mod_cache']['ap']) ? $user_info['mod_cache']['ap'] : boardsAllowedTo('approve_posts');
				if (!in_array(0, $approve_posts) && !in_array($row['id_board'], $approve_posts))
					return 'exit';
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
					throw new Elk_Exception('modify_post_time_passed', false);
			}
			elseif ($row['id_member_poster'] == $user_info['id'] && !allowedTo('delete_any'))
				isAllowedTo('delete_replies');
			else
				isAllowedTo('delete_any');

			if ($modSettings['postmod_active'] && !$row['approved'] && $row['id_member'] != $user_info['id'] && !allowedTo('delete_own'))
				isAllowedTo('approve_posts');
		}

		// Delete the *whole* topic, but only if the topic consists of one message.
		if ($row['id_first_msg'] == $row['id_msg'])
		{
			if (empty($board) || $row['id_board'] != $board)
			{
				$remove_own = false;
				$remove_any = boardsAllowedTo('remove_any');
				$remove_any = in_array(0, $remove_any) || in_array($row['id_board'], $remove_any);

				if (!$remove_any)
				{
					$remove_own = boardsAllowedTo('remove_own');
					$remove_own = in_array(0, $remove_own) || in_array($row['id_board'], $remove_own);
				}

				if ($row['id_member'] != $user_info['id'] && !$remove_any)
					throw new Elk_Exception('cannot_remove_any', 'permission');
				elseif (!$remove_any && !$remove_own)
					throw new Elk_Exception('cannot_remove_own', 'permission');
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
				throw new Elk_Exception('delFirstPost', false);

			return true;
		}
	}

	/**
	 * Take a load of messages from one place and stick them in a topic
	 *
	 * @param int[] $msgs
	 * @param integer $from_topic
	 * @param integer $target_topic
	 */
	private function _mergePosts($msgs, $from_topic, $target_topic)
	{
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

			require_once(SUBSDIR . '/Members.subs.php');
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
				'icon' => $target_board == $this->_recycle_board ? 'recycled' : 'xx',
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
		if ($topic_exists === true)
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
			setTopicAttribute($from_topic, array(
				'id_first_msg' => $source_topic_data['id_first_msg'],
				'id_last_msg' => $source_topic_data['id_last_msg'],
				'num_replies' => $source_topic_data['num_replies'],
				'unapproved_posts' => $source_topic_data['unapproved_posts'],
			));

			// We have a new post count for the source board.
			incrementBoard($target_board, array(
				'num_posts' => $source_topic_data['num_replies'] - $from_replies, // Lets keep in mind that the first message in a topic counts towards num_replies in a board.
				'unapproved_posts' => $source_topic_data['unapproved_posts'] - $from_unapproved_posts,
			));
		}

		// Finally get around to updating the destination topic, now all indexes etc on the source are fixed.
		setTopicAttribute($target_topic, array(
			'id_first_msg' => $target_topic_data['id_first_msg'],
			'id_last_msg' => $target_topic_data['id_last_msg'],
			'num_replies' => $target_topic_data['num_replies'],
			'unapproved_posts' => $target_topic_data['unapproved_posts'],
		));

		// Need it to update some stats.
		require_once(SUBSDIR . '/Post.subs.php');

		// Update stats.
		require_once(SUBSDIR . '/Topic.subs.php');
		updateTopicStats();
		require_once(SUBSDIR . '/Messages.subs.php');
		updateMessageStats();

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
			require_once(SUBSDIR . '/Messages.subs.php');
			while ($row = $db->fetch_assoc($request))
				updateSubjectStats($row['id_topic'], $row['subject']);
			$db->free_result($request);
		}

		updateLastMessages(array($from_board, $target_board));
	}
}