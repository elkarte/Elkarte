<?php

/**
 * This class takes care of deleting and restoring messages in boards
 * that means posts and topics
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
 * @version 1.0 Release Candidate 1
 *
 */

if (!defined('ELK'))
	die('No access...');

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
	 * Initialize the class! :P
	 *
	 * @param int $recycle_board the id the the recycle board (if any)
	 */
	public function __construct($recycle_board = null)
	{
		$this->_recycle_board = (int) $recycle_board;
	}

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
	}

	public function restoreTopics($topics_id)
	{
		foreach ($topics_id as $topic)
		{
			$topic = (int) $topic;
			if (!empty($topic))
				$this->_topics_to_restore[] = $topic;
		}
	}

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

	public function unfoundRestoreMessages($return_msg = false)
	{
		if ($return_msg)
			return $this->_unfound_messages;
		else
			return !empty($this->_unfound_messages);
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