<?php

/**
 * This file has functions in it to handle merging of two or more topics
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * This file contains code covered by:
 * copyright:	2011 Simple Machines (http://www.simplemachines.org)
 * license:  	BSD, See included LICENSE.TXT for terms and conditions.
 *
 * @version 1.1 Release Candidate 1
 *
 */

/**
 * This class has functions to handle merging of two or more topics
 * in to a single new or existing topic.
 *
 * Class TopicsMerge
 */
class TopicsMerge
{
	/**
	 * For each topic a set of information (id, board, subject, poll, etc.)
	 *
	 * @var mixed[]
	 */
	public $topic_data = array();

	/**
	 * All the boards the topics are in
	 *
	 * @var int[]
	 */
	public $boards = array();

	/**
	 * The id_topic with the lowest id_first_msg
	 *
	 * @var int
	 */
	public $firstTopic = 0;

	/**
	 * The id_board of the topic TopicsMerge::$firstTopic
	 *
	 * @var int
	 */
	public $firstBoard = 0;

	/**
	 * Just the array of topics to merge.
	 *
	 * @var int[]
	 */
	private $_topics = array();

	/**
	 * Sum of the number of views of each topic.
	 *
	 * @var int
	 */
	private $_num_views = 0;

	/**
	 * If at least one of the topics is sticky
	 *
	 * @var int
	 */
	private $_is_sticky = 0;

	/**
	 * An array of "totals" (number of topics/messages, unapproved, etc.) for
	 * each board involved
	 *
	 * @var mixed[]
	 */
	private $_boardTotals = array();

	/**
	 * If any topic has a poll, the array of poll id
	 *
	 * @var int[]
	 */
	private $_polls = array();

	/**
	 * List of errors occurred
	 *
	 * @var string[]
	 */
	private $_errors = array();

	/**
	 * The database object
	 *
	 * @var object
	 */
	private $_db = null;

	/**
	 * Initialize the class with a list of topics to merge
	 *
	 * @param int[] $topics array of topics to merge into one
	 */
	public function __construct($topics)
	{
		// Prepare the vars
		$this->_db = database();

		// Ensure all the id's are integers
		$topics = array_map('intval', $topics);
		$this->_topics = array_filter($topics);

		// Find out some preliminary information
		$this->_loadTopicDetails();
	}

	/**
	 * If errors occurred while working
	 *
	 * @return bool
	 */
	public function hasErrors()
	{
		return !empty($this->_errors);
	}

	/**
	 * The first error occurred
	 *
	 * @return array|string
	 */
	public function firstError()
	{
		if (!empty($this->_errors))
		{
			$errors = array_values($this->_errors);

			return array_shift($errors);
		}
		else
		{
			return '';
		}
	}

	/**
	 * Returns the polls information if any of the topics has a poll.
	 *
	 * @return mixed[]
	 */
	public function getPolls()
	{
		$polls = array();

		if (count($this->_polls) > 1)
		{
			$request = $this->_db->query('', '
				SELECT
					t.id_topic, t.id_poll, m.subject, p.question
				FROM {db_prefix}polls AS p
					INNER JOIN {db_prefix}topics AS t ON (t.id_poll = p.id_poll)
					INNER JOIN {db_prefix}messages AS m ON (m.id_msg = t.id_first_msg)
				WHERE p.id_poll IN ({array_int:polls})
				LIMIT {int:limit}',
				array(
					'polls' => $this->_polls,
					'limit' => count($this->_polls),
				)
			);
			while ($row = $this->_db->fetch_assoc($request))
			{
				$polls[] = array(
					'id' => $row['id_poll'],
					'topic' => array(
						'id' => $row['id_topic'],
						'subject' => $row['subject']
					),
					'question' => $row['question'],
					'selected' => $row['id_topic'] == $this->firstTopic
				);
			}
			$this->_db->free_result($request);
		}

		return $polls;
	}

	/**
	 * Performs the merge operations
	 *
	 * @param mixed[] $details
	 * @return bool|int[]
	 * @throws Elk_Exception
	 */
	public function doMerge($details = array())
	{
		// Just to be sure, here we should not have any error around
		$this->_errors = array();

		// Determine target board.
		$target_board = count($this->boards) > 1 ? (int) $details['board'] : $this->boards[0];
		if (!in_array($target_board, $details['accessible_boards']))
		{
			$this->_errors[] = array('no_board', true);
			return false;
		}

		// Determine which poll will survive and which polls won't.
		$target_poll = count($this->_polls) > 1 ? (int) $details['poll'] : (count($this->_polls) == 1 ? $this->_polls[0] : 0);
		if ($target_poll > 0 && !in_array($target_poll, $this->_polls))
		{
			$this->_errors[] = array('no_access', false);
			return false;
		}

		$deleted_polls = empty($target_poll) ? $this->_polls : array_diff($this->_polls, array($target_poll));

		// Determine the subject of the newly merged topic - was a custom subject specified?
		if (empty($details['subject']) && $details['custom_subject'] != '')
		{
			$target_subject = strtr(Util::htmltrim(Util::htmlspecialchars($details['custom_subject'])), array("\r" => '', "\n" => '', "\t" => ''));

			// Keep checking the length.
			if (Util::strlen($target_subject) > 100)
				$target_subject = Util::substr($target_subject, 0, 100);

			// Nothing left - odd but pick the first topics subject.
			if ($target_subject == '')
				$target_subject = $this->topic_data[$this->firstTopic]['subject'];
		}
		// A subject was selected from the list.
		elseif (!empty($this->topic_data[(int) $details['subject']]['subject']))
			$target_subject = $this->topic_data[(int) $details['subject']]['subject'];
		// Nothing worked? Just take the subject of the first message.
		else
			$target_subject = $this->topic_data[$this->firstTopic]['subject'];

		// Get the first and last message and the number of messages....
		$request = $this->_db->query('', '
			SELECT
				approved, MIN(id_msg) AS first_msg, MAX(id_msg) AS last_msg, COUNT(*) AS message_count
			FROM {db_prefix}messages
			WHERE id_topic IN ({array_int:topics})
			GROUP BY approved
			ORDER BY approved DESC',
			array(
				'topics' => $this->_topics,
			)
		);
		$topic_approved = 1;
		$first_msg = 0;
		$num_replies = 0;
		while ($row = $this->_db->fetch_assoc($request))
		{
			// If this is approved, or is fully unapproved.
			if ($row['approved'] || !isset($first_msg))
			{
				$first_msg = $row['first_msg'];
				$last_msg = $row['last_msg'];
				if ($row['approved'])
				{
					$num_replies = $row['message_count'] - 1;
					$num_unapproved = 0;
				}
				else
				{
					$topic_approved = 0;
					$num_replies = 0;
					$num_unapproved = $row['message_count'];
				}
			}
			else
			{
				// If this has a lower first_msg then the first post is not approved and hence the number of replies was wrong!
				if ($first_msg > $row['first_msg'])
				{
					$first_msg = $row['first_msg'];
					$num_replies++;
					$topic_approved = 0;
				}
				$num_unapproved = $row['message_count'];
			}
		}
		$this->_db->free_result($request);

		// Ensure we have a board stat for the target board.
		if (!isset($this->_boardTotals[$target_board]))
		{
			$this->_boardTotals[$target_board] = array(
				'num_posts' => 0,
				'num_topics' => 0,
				'unapproved_posts' => 0,
				'unapproved_topics' => 0
			);
		}

		// Fix the topic count stuff depending on what the new one counts as.
		if ($topic_approved)
			$this->_boardTotals[$target_board]['num_topics']--;
		else
			$this->_boardTotals[$target_board]['unapproved_topics']--;

		$this->_boardTotals[$target_board]['unapproved_posts'] -= $num_unapproved;
		$this->_boardTotals[$target_board]['num_posts'] -= $topic_approved ? $num_replies + 1 : $num_replies;

		// Get the member ID of the first and last message.
		$request = $this->_db->query('', '
			SELECT
				id_member
			FROM {db_prefix}messages
			WHERE id_msg IN ({int:first_msg}, {int:last_msg})
			ORDER BY id_msg
			LIMIT 2',
			array(
				'first_msg' => $first_msg,
				'last_msg' => $last_msg,
			)
		);
		list ($member_started) = $this->_db->fetch_row($request);
		list ($member_updated) = $this->_db->fetch_row($request);

		// First and last message are the same, so only row was returned.
		if ($member_updated === null)
			$member_updated = $member_started;

		$this->_db->free_result($request);

		// Obtain all the message ids we are going to affect.
		$affected_msgs = messagesInTopics($this->_topics);

		// Assign the first topic ID to be the merged topic.
		$id_topic = min($this->_topics);

		$enforce_subject = Util::htmlspecialchars(trim($details['enforce_subject']));

		// Merge topic notifications.
		$notifications = is_array($details['notifications']) ? array_intersect($this->_topics, $details['notifications']) : array();
		fixMergedTopics($first_msg, $this->_topics, $id_topic, $target_board, $target_subject, $enforce_subject, $notifications);

		// Assign the properties of the newly merged topic.
		setTopicAttribute($id_topic, array(
			'id_board' => $target_board,
			'is_sticky' => $this->_is_sticky,
			'approved' => $topic_approved,
			'id_member_started' => $member_started,
			'id_member_updated' => $member_updated,
			'id_first_msg' => $first_msg,
			'id_last_msg' => $last_msg,
			'id_poll' => $target_poll,
			'num_replies' => $num_replies,
			'unapproved_posts' => $num_unapproved,
			'num_views' => $this->_num_views,
		));

		// Get rid of the redundant polls.
		if (!empty($deleted_polls))
		{
			require_once(SUBSDIR . '/Poll.subs.php');
			removePoll($deleted_polls);
		}

		$this->_updateStats($affected_msgs, $id_topic, $target_subject, $enforce_subject);

		return array($id_topic, $target_board);
	}

	/**
	 * Takes care of updating all the relevant statistics
	 *
	 * @param int[] $affected_msgs
	 * @param int $id_topic
	 * @param string $target_subject
	 * @param bool $enforce_subject
	 * @throws Elk_Exception
	 */
	protected function _updateStats($affected_msgs, $id_topic, $target_subject, $enforce_subject)
	{
		// Cycle through each board...
		foreach ($this->_boardTotals as $id_board => $stats)
			decrementBoard($id_board, $stats);

		// Determine the board the final topic resides in
		$topic_info = getTopicInfo($id_topic);
		$id_board = $topic_info['id_board'];

		// Update all the statistics.
		require_once(SUBSDIR . '/Topic.subs.php');
		updateTopicStats();

		require_once(SUBSDIR . '/Messages.subs.php');
		updateSubjectStats($id_topic, $target_subject);
		updateLastMessages($this->boards);

		logAction('merge', array('topic' => $id_topic, 'board' => $id_board));

		// Notify people that these topics have been merged?
		require_once(SUBSDIR . '/Notification.subs.php');
		sendNotifications($id_topic, 'merge');

		// Grab the response prefix (like 'Re: ') in the default forum language.
		$response_prefix = response_prefix();

		// If there's a search index that needs updating, update it...
		$search = new \ElkArte\Search\Search;
		$searchAPI = $search->findSearchAPI();
		if (is_callable(array($searchAPI, 'topicMerge')))
			$searchAPI->topicMerge($id_topic, $this->_topics, $affected_msgs, empty($enforce_subject) ? null : array($response_prefix, $target_subject));
	}

	/**
	 * Grabs all the details of the topics involved in the merge process and loads
	 * then in $this->topic_data
	 */
	protected function _loadTopicDetails()
	{
		global $scripturl, $modSettings, $user_info;

		// Joy of all joys, make sure they're not pi**ing about with unapproved topics they can't see :P
		if ($modSettings['postmod_active'])
			$can_approve_boards = !empty($user_info['mod_cache']['ap']) ? $user_info['mod_cache']['ap'] : boardsAllowedTo('approve_posts');

		// Get info about the topics and polls that will be merged.
		$request = $this->_db->query('', '
			SELECT
				t.id_topic, t.id_board, b.id_cat, t.id_poll, t.num_views, t.is_sticky, t.approved, t.num_replies, t.unapproved_posts,
				m1.subject, m1.poster_time AS time_started, COALESCE(mem1.id_member, 0) AS id_member_started, COALESCE(mem1.real_name, m1.poster_name) AS name_started,
				m2.poster_time AS time_updated, COALESCE(mem2.id_member, 0) AS id_member_updated, COALESCE(mem2.real_name, m2.poster_name) AS name_updated
			FROM {db_prefix}topics AS t
				INNER JOIN {db_prefix}messages AS m1 ON (m1.id_msg = t.id_first_msg)
				INNER JOIN {db_prefix}messages AS m2 ON (m2.id_msg = t.id_last_msg)
				LEFT JOIN {db_prefix}members AS mem1 ON (mem1.id_member = m1.id_member)
				LEFT JOIN {db_prefix}members AS mem2 ON (mem2.id_member = m2.id_member)
				LEFT JOIN {db_prefix}boards AS b ON (b.id_board = t.id_board)
			WHERE t.id_topic IN ({array_int:topic_list})
			ORDER BY t.id_first_msg
			LIMIT {int:limit}',
			array(
				'topic_list' => $this->_topics,
				'limit' => count($this->_topics),
			)
		);
		if ($this->_db->num_rows($request) < 2)
		{
			$this->_db->free_result($request);

			$this->_errors[] = array('no_topic_id', true);

			return false;
		}
		while ($row = $this->_db->fetch_assoc($request))
		{
			// Make a note for the board counts...
			if (!isset($this->_boardTotals[$row['id_board']]))
			{
				$this->_boardTotals[$row['id_board']] = array(
					'num_posts' => 0,
					'num_topics' => 0,
					'unapproved_posts' => 0,
					'unapproved_topics' => 0
				);
			}

			// We can't see unapproved topics here?
			if ($modSettings['postmod_active'] && !$row['approved'] && $can_approve_boards != array(0) && in_array($row['id_board'], $can_approve_boards))
				continue;
			elseif (!$row['approved'])
				$this->_boardTotals[$row['id_board']]['unapproved_topics']++;
			else
				$this->_boardTotals[$row['id_board']]['num_topics']++;

			$this->_boardTotals[$row['id_board']]['unapproved_posts'] += $row['unapproved_posts'];
			$this->_boardTotals[$row['id_board']]['num_posts'] += $row['num_replies'] + ($row['approved'] ? 1 : 0);

			$this->topic_data[$row['id_topic']] = array(
				'id' => $row['id_topic'],
				'board' => $row['id_board'],
				'poll' => $row['id_poll'],
				'num_views' => $row['num_views'],
				'subject' => $row['subject'],
				'started' => array(
					'time' => standardTime($row['time_started']),
					'html_time' => htmlTime($row['time_started']),
					'timestamp' => forum_time(true, $row['time_started']),
					'href' => empty($row['id_member_started']) ? '' : $scripturl . '?action=profile;u=' . $row['id_member_started'],
					'link' => empty($row['id_member_started']) ? $row['name_started'] : '<a href="' . $scripturl . '?action=profile;u=' . $row['id_member_started'] . '">' . $row['name_started'] . '</a>'
				),
				'updated' => array(
					'time' => standardTime($row['time_updated']),
					'html_time' => htmlTime($row['time_updated']),
					'timestamp' => forum_time(true, $row['time_updated']),
					'href' => empty($row['id_member_updated']) ? '' : $scripturl . '?action=profile;u=' . $row['id_member_updated'],
					'link' => empty($row['id_member_updated']) ? $row['name_updated'] : '<a href="' . $scripturl . '?action=profile;u=' . $row['id_member_updated'] . '">' . $row['name_updated'] . '</a>'
				)
			);
			$this->_num_views += $row['num_views'];
			$this->boards[] = $row['id_board'];

			// If there's no poll, id_poll == 0...
			if ($row['id_poll'] > 0)
				$this->_polls[] = $row['id_poll'];

			// Store the id_topic with the lowest id_first_msg.
			if (empty($this->firstTopic))
			{
				$this->firstTopic = $row['id_topic'];
				$this->firstBoard = $row['id_board'];
			}

			$this->_is_sticky = max($this->_is_sticky, $row['is_sticky']);
		}
		$this->_db->free_result($request);

		$this->boards = array_map('intval', array_values(array_unique($this->boards)));

		// If we didn't get any topics then they've been messing with unapproved stuff.
		if (empty($this->topic_data))
		{
			$this->_errors[] = array('no_topic_id', true);
		}

		return true;
	}
}
