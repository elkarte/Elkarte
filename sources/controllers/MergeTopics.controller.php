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
 * Handle merging of topics
 *
 * Original module by Mach8 - We'll never forget you.
 */

if (!defined('ELKARTE'))
	die('No access...');

class MergeTopics_Controller
{
	/**
	 * Merges two or more topics into one topic.
	 * delegates to the other functions (based on the URL parameter sa).
	 * loads the MergeTopics template.
	 * requires the merge_any permission.
	 * is accessed with ?action=mergetopics.
	 */
	function action_index()
	{
		// Load the template....
		loadTemplate('MergeTopics');

		$subActions = array(
			'done' => 'action_mergeDone',
			'execute' => 'action_mergeExecute',
			'index' => 'action_mergeIndex',
			'options' => 'action_mergeExecute',
		);

		// ?action=mergetopics;sa=LETSBREAKIT won't work, sorry.
		if (empty($_REQUEST['sa']) || !isset($subActions[$_REQUEST['sa']]))
			$this->action_mergeIndex();
		else
			$this->{$subActions[$_REQUEST['sa']]}();
	}

	/**
	 * Allows to pick a topic to merge the current topic with.
	 * is accessed with ?action=mergetopics;sa=index
	 * default sub action for ?action=mergetopics.
	 * uses 'merge' sub template of the MergeTopics template.
	 * allows to set a different target board.
	 */
	function action_mergeIndex()
	{
		global $txt, $board, $context;
		global $scripturl, $user_info, $modSettings;

		$db = database();

		if (!isset($_GET['from']))
			fatal_lang_error('no_access', false);
		$_GET['from'] = (int) $_GET['from'];

		$_REQUEST['targetboard'] = isset($_REQUEST['targetboard']) ? (int) $_REQUEST['targetboard'] : $board;
		$context['target_board'] = $_REQUEST['targetboard'];

		// Prepare a handy query bit for approval...
		if ($modSettings['postmod_active'])
		{
			$can_approve_boards = !empty($user_info['mod_cache']['ap']) ? $user_info['mod_cache']['ap'] : boardsAllowedTo('approve_posts');
			$onlyApproved = $can_approve_boards !== array(0) && !in_array($_REQUEST['targetboard'], $can_approve_boards);
		}
		else
			$onlyApproved = false;

		// How many topics are on this board?  (used for paging.)
		$request = $db->query('', '
			SELECT COUNT(*)
			FROM {db_prefix}topics AS t
			WHERE t.id_board = {int:id_board}' . ($onlyApproved ? '
				AND t.approved = {int:is_approved}' : ''),
			array(
				'id_board' => $_REQUEST['targetboard'],
				'is_approved' => 1,
			)
		);
		list ($topiccount) = $db->fetch_row($request);
		$db->free_result($request);

		// Make the page list.
		$context['page_index'] = constructPageIndex($scripturl . '?action=mergetopics;from=' . $_GET['from'] . ';targetboard=' . $_REQUEST['targetboard'] . ';board=' . $board . '.%1$d', $_REQUEST['start'], $topiccount, $modSettings['defaultMaxTopics'], true);

		// Get the topic's subject.
		require_once(SUBSDIR . '/Topic.subs.php');
		$topic_info = getTopicInfo($_GET['from'], 'message');

		// @todo review: double check the logic
		if (empty($topic_info) || ($topic_info['id_board'] != $board) || ($onlyApproved && empty($topic_info['approved'])))
			fatal_lang_error('no_board');

		// Tell the template a few things..
		$context['origin_topic'] = $_GET['from'];
		$context['origin_subject'] = $topic_info['subject'];
		$context['origin_js_subject'] = addcslashes(addslashes($topic_info['subject']), '/');
		$context['page_title'] = $txt['merge'];

		// Check which boards you have merge permissions on.
		$merge_boards = boardsAllowedTo('merge_any');

		if (empty($merge_boards))
			fatal_lang_error('cannot_merge_any', 'user');

		// Get a list of boards they can navigate to to merge.
		require_once(SUBSDIR . '/Boards.subs.php');
		$boardListOptions = array(
			'use_permissions' => true,
			'not_redirection' => true
		);
		if (!in_array(0, $merge_boards))
			$boardListOptions['included_boards'] = $merge_boards;
		$boards_list = getBoardList($boardListOptions, true);
		$context['boards'] = array();
		foreach ($boards_list as $board)
		{
			$context['boards'][] = array(
				'id' => $board['id_board'],
				'name' => $board['board_name'],
				'category' => $board['cat_name']
			);
		}

		// Get some topics to merge it with.
		$request = $db->query('', '
			SELECT t.id_topic, m.subject, m.id_member, IFNULL(mem.real_name, m.poster_name) AS poster_name
			FROM {db_prefix}topics AS t
				INNER JOIN {db_prefix}messages AS m ON (m.id_msg = t.id_first_msg)
				LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = m.id_member)
			WHERE t.id_board = {int:id_board}
				AND t.id_topic != {int:id_topic}' . ($onlyApproved ? '
				AND t.approved = {int:is_approved}' : '') . '
			ORDER BY {raw:sort}
			LIMIT {int:offset}, {int:limit}',
			array(
				'id_board' => $_REQUEST['targetboard'],
				'id_topic' => $_GET['from'],
				'sort' => (!empty($modSettings['enableStickyTopics']) ? 't.is_sticky DESC, ' : '') . 't.id_last_msg DESC',
				'offset' => $_REQUEST['start'],
				'limit' => $modSettings['defaultMaxTopics'],
				'is_approved' => 1,
			)
		);
		$context['topics'] = array();
		while ($row = $db->fetch_assoc($request))
		{
			censorText($row['subject']);

			$context['topics'][] = array(
				'id' => $row['id_topic'],
				'poster' => array(
					'id' => $row['id_member'],
					'name' => $row['poster_name'],
					'href' => empty($row['id_member']) ? '' : $scripturl . '?action=profile;u=' . $row['id_member'],
					'link' => empty($row['id_member']) ? $row['poster_name'] : '<a href="' . $scripturl . '?action=profile;u=' . $row['id_member'] . '" target="_blank" class="new_win">' . $row['poster_name'] . '</a>'
				),
				'subject' => $row['subject'],
				'js_subject' => addcslashes(addslashes($row['subject']), '/')
			);
		}
		$db->free_result($request);

		if (empty($context['topics']) && count($context['boards']) <= 1)
			fatal_lang_error('merge_need_more_topics');

		$context['sub_template'] = 'merge';
	}

	/**
	 * Set merge options and do the actual merge of two or more topics.
	 *
	 * the merge options screen:
	 * * shows topics to be merged and allows to set some merge options.
	 * * is accessed by ?action=mergetopics;sa=options.and can also internally be called by action_quickmod().
	 * * uses 'merge_extra_options' sub template of the MergeTopics template.
	 *
	 * the actual merge:
	 * * is accessed with ?action=mergetopics;sa=execute.
	 * * updates the statistics to reflect the merge.
	 * * logs the action in the moderation log.
	 * * sends a notification is sent to all users monitoring this topic.
	 * * redirects to ?action=mergetopics;sa=done.
	 * @param array $topics = array()
	 */
	function action_mergeExecute($topics = array())
	{
		global $user_info, $txt, $context, $scripturl;
		global $language, $modSettings;

		$db = database();

		// Check the session.
		checkSession('request');

		// Handle URLs from action_mergeIndex.
		if (!empty($_GET['from']) && !empty($_GET['to']))
			$topics = array((int) $_GET['from'], (int) $_GET['to']);

		// If we came from a form, the topic IDs came by post.
		if (!empty($_POST['topics']) && is_array($_POST['topics']))
			$topics = $_POST['topics'];

		// There's nothing to merge with just one topic...
		if (empty($topics) || !is_array($topics) || count($topics) == 1)
			fatal_lang_error('merge_need_more_topics');

		// Make sure every topic is numeric, or some nasty things could be done with the DB.
		foreach ($topics as $id => $topic)
			$topics[$id] = (int) $topic;

		// Joy of all joys, make sure they're not pi**ing about with unapproved topics they can't see :P
		if ($modSettings['postmod_active'])
			$can_approve_boards = !empty($user_info['mod_cache']['ap']) ? $user_info['mod_cache']['ap'] : boardsAllowedTo('approve_posts');

		// Get info about the topics and polls that will be merged.
		$request = $db->query('', '
			SELECT
				t.id_topic, t.id_board, t.id_poll, t.num_views, t.is_sticky, t.approved, t.num_replies, t.unapproved_posts,
				m1.subject, m1.poster_time AS time_started, IFNULL(mem1.id_member, 0) AS id_member_started, IFNULL(mem1.real_name, m1.poster_name) AS name_started,
				m2.poster_time AS time_updated, IFNULL(mem2.id_member, 0) AS id_member_updated, IFNULL(mem2.real_name, m2.poster_name) AS name_updated
			FROM {db_prefix}topics AS t
				INNER JOIN {db_prefix}messages AS m1 ON (m1.id_msg = t.id_first_msg)
				INNER JOIN {db_prefix}messages AS m2 ON (m2.id_msg = t.id_last_msg)
				LEFT JOIN {db_prefix}members AS mem1 ON (mem1.id_member = m1.id_member)
				LEFT JOIN {db_prefix}members AS mem2 ON (mem2.id_member = m2.id_member)
			WHERE t.id_topic IN ({array_int:topic_list})
			ORDER BY t.id_first_msg
			LIMIT ' . count($topics),
			array(
				'topic_list' => $topics,
			)
		);
		if ($db->num_rows($request) < 2)
			fatal_lang_error('no_topic_id');
		$num_views = 0;
		$is_sticky = 0;
		$boardTotals = array();
		$boards = array();
		$polls = array();
		$firstTopic = 0;
		while ($row = $db->fetch_assoc($request))
		{
			// Make a note for the board counts...
			if (!isset($boardTotals[$row['id_board']]))
			{
				$boardTotals[$row['id_board']] = array(
					'posts' => 0,
					'topics' => 0,
					'unapproved_posts' => 0,
					'unapproved_topics' => 0
				);
			}

			// We can't see unapproved topics here?
			if ($modSettings['postmod_active'] && !$row['approved'] && $can_approve_boards != array(0) && in_array($row['id_board'], $can_approve_boards))
				continue;
			elseif (!$row['approved'])
				$boardTotals[$row['id_board']]['unapproved_topics']++;
			else
				$boardTotals[$row['id_board']]['topics']++;

			$boardTotals[$row['id_board']]['unapproved_posts'] += $row['unapproved_posts'];
			$boardTotals[$row['id_board']]['posts'] += $row['num_replies'] + ($row['approved'] ? 1 : 0);

			$topic_data[$row['id_topic']] = array(
				'id' => $row['id_topic'],
				'board' => $row['id_board'],
				'poll' => $row['id_poll'],
				'num_views' => $row['num_views'],
				'subject' => $row['subject'],
				'started' => array(
				'time' => standardTime($row['time_started']),
					'timestamp' => forum_time(true, $row['time_started']),
					'href' => empty($row['id_member_started']) ? '' : $scripturl . '?action=profile;u=' . $row['id_member_started'],
					'link' => empty($row['id_member_started']) ? $row['name_started'] : '<a href="' . $scripturl . '?action=profile;u=' . $row['id_member_started'] . '">' . $row['name_started'] . '</a>'
				),
				'updated' => array(
				'time' => standardTime($row['time_updated']),
					'timestamp' => forum_time(true, $row['time_updated']),
					'href' => empty($row['id_member_updated']) ? '' : $scripturl . '?action=profile;u=' . $row['id_member_updated'],
					'link' => empty($row['id_member_updated']) ? $row['name_updated'] : '<a href="' . $scripturl . '?action=profile;u=' . $row['id_member_updated'] . '">' . $row['name_updated'] . '</a>'
				)
			);
			$num_views += $row['num_views'];
			$boards[] = $row['id_board'];

			// If there's no poll, id_poll == 0...
			if ($row['id_poll'] > 0)
				$polls[] = $row['id_poll'];

			// Store the id_topic with the lowest id_first_msg.
			if (empty($firstTopic))
				$firstTopic = $row['id_topic'];

			$is_sticky = max($is_sticky, $row['is_sticky']);
		}
		$db->free_result($request);

		// If we didn't get any topics then they've been messing with unapproved stuff.
		if (empty($topic_data))
			fatal_lang_error('no_topic_id');

		$boards = array_values(array_unique($boards));

		// The parameters of action_mergeExecute were set, so this must've been an internal call.
		if (!empty($topics))
		{
			isAllowedTo('merge_any', $boards);
			loadTemplate('MergeTopics');
		}

		// Get the boards a user is allowed to merge in.
		$merge_boards = boardsAllowedTo('merge_any');
		if (empty($merge_boards))
			fatal_lang_error('cannot_merge_any', 'user');

		require_once(SUBSDIR . '/Boards.subs.php');

		// Make sure they can see all boards....
		$query_boards = array('boards' => $boards);

		if (!in_array(0, $merge_boards))
			$query_boards['boards'] = array_merge($query_boards['boards'], $merge_boards);

		// Saved in a variable to (potentially) save a query later
		$boards_info = fetchBoardsInfo($query_boards);

		// This happens when a member is moderator of a board he cannot see
		if (count($boards_info) != count($boards))
			fatal_lang_error('no_board');

		if (empty($_REQUEST['sa']) || $_REQUEST['sa'] == 'options')
		{
			if (count($polls) > 1)
			{
				$request = $db->query('', '
					SELECT t.id_topic, t.id_poll, m.subject, p.question
					FROM {db_prefix}polls AS p
						INNER JOIN {db_prefix}topics AS t ON (t.id_poll = p.id_poll)
						INNER JOIN {db_prefix}messages AS m ON (m.id_msg = t.id_first_msg)
					WHERE p.id_poll IN ({array_int:polls})
					LIMIT ' . count($polls),
					array(
						'polls' => $polls,
					)
				);
				while ($row = $db->fetch_assoc($request))
					$context['polls'][] = array(
						'id' => $row['id_poll'],
						'topic' => array(
							'id' => $row['id_topic'],
							'subject' => $row['subject']
						),
						'question' => $row['question'],
						'selected' => $row['id_topic'] == $firstTopic
					);
				$db->free_result($request);
			}
			if (count($boards) > 1)
			{
				foreach ($boards_info as $row)
					$context['boards'][] = array(
						'id' => $row['id_board'],
						'name' => $row['name'],
						'selected' => $row['id_board'] == $topic_data[$firstTopic]['board']
					);
			}

			$context['topics'] = $topic_data;
			foreach ($topic_data as $id => $topic)
				$context['topics'][$id]['selected'] = $topic['id'] == $firstTopic;

			$context['page_title'] = $txt['merge'];
			$context['sub_template'] = 'merge_extra_options';

			return;
		}

		// Determine target board.
		$target_board = count($boards) > 1 ? (int) $_REQUEST['board'] : $boards[0];
		if (!in_array($target_board, $boards))
			fatal_lang_error('no_board');

		// Determine which poll will survive and which polls won't.
		$target_poll = count($polls) > 1 ? (int) $_POST['poll'] : (count($polls) == 1 ? $polls[0] : 0);
		if ($target_poll > 0 && !in_array($target_poll, $polls))
			fatal_lang_error('no_access', false);
		$deleted_polls = empty($target_poll) ? $polls : array_diff($polls, array($target_poll));

		// Determine the subject of the newly merged topic - was a custom subject specified?
		if (empty($_POST['subject']) && isset($_POST['custom_subject']) && $_POST['custom_subject'] != '')
		{
			$target_subject = strtr(Util::htmltrim(Util::htmlspecialchars($_POST['custom_subject'])), array("\r" => '', "\n" => '', "\t" => ''));

			// Keep checking the length.
			if (Util::strlen($target_subject) > 100)
				$target_subject = Util::substr($target_subject, 0, 100);

			// Nothing left - odd but pick the first topics subject.
			if ($target_subject == '')
				$target_subject = $topic_data[$firstTopic]['subject'];
		}
		// A subject was selected from the list.
		elseif (!empty($topic_data[(int) $_POST['subject']]['subject']))
			$target_subject = $topic_data[(int) $_POST['subject']]['subject'];
		// Nothing worked? Just take the subject of the first message.
		else
			$target_subject = $topic_data[$firstTopic]['subject'];

		// Get the first and last message and the number of messages....
		$request = $db->query('', '
			SELECT approved, MIN(id_msg) AS first_msg, MAX(id_msg) AS last_msg, COUNT(*) AS message_count
			FROM {db_prefix}messages
			WHERE id_topic IN ({array_int:topics})
			GROUP BY approved
			ORDER BY approved DESC',
			array(
				'topics' => $topics,
			)
		);
		$topic_approved = 1;
		$first_msg = 0;
		while ($row = $db->fetch_assoc($request))
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
		$db->free_result($request);

		// Ensure we have a board stat for the target board.
		if (!isset($boardTotals[$target_board]))
		{
			$boardTotals[$target_board] = array(
				'posts' => 0,
				'topics' => 0,
				'unapproved_posts' => 0,
				'unapproved_topics' => 0
			);
		}

		// Fix the topic count stuff depending on what the new one counts as.
		if ($topic_approved)
			$boardTotals[$target_board]['topics']--;
		else
			$boardTotals[$target_board]['unapproved_topics']--;

		$boardTotals[$target_board]['unapproved_posts'] -= $num_unapproved;
		$boardTotals[$target_board]['posts'] -= $topic_approved ? $num_replies + 1 : $num_replies;

		// Get the member ID of the first and last message.
		$request = $db->query('', '
			SELECT id_member
			FROM {db_prefix}messages
			WHERE id_msg IN ({int:first_msg}, {int:last_msg})
			ORDER BY id_msg
			LIMIT 2',
			array(
				'first_msg' => $first_msg,
				'last_msg' => $last_msg,
			)
		);
		list ($member_started) = $db->fetch_row($request);
		list ($member_updated) = $db->fetch_row($request);

		// First and last message are the same, so only row was returned.
		if ($member_updated === null)
			$member_updated = $member_started;

		$db->free_result($request);

		// Obtain all the message ids we are going to affect.
		$affected_msgs = array();
		$request = $db->query('', '
			SELECT id_msg
			FROM {db_prefix}messages
			WHERE id_topic IN ({array_int:topic_list})',
			array(
				'topic_list' => $topics,
		));
		while ($row = $db->fetch_row($request))
			$affected_msgs[] = $row[0];
		$db->free_result($request);

		// Assign the first topic ID to be the merged topic.
		$id_topic = min($topics);

		// Delete the remaining topics.
		$deleted_topics = array_diff($topics, array($id_topic));
		$db->query('', '
			DELETE FROM {db_prefix}topics
			WHERE id_topic IN ({array_int:deleted_topics})',
			array(
				'deleted_topics' => $deleted_topics,
			)
		);

		$db->query('', '
			DELETE FROM {db_prefix}log_search_subjects
			WHERE id_topic IN ({array_int:deleted_topics})',
			array(
				'deleted_topics' => $deleted_topics,
			)
		);

		// Asssign the properties of the newly merged topic.
		$db->query('', '
			UPDATE {db_prefix}topics
			SET
				id_board = {int:id_board},
				id_member_started = {int:id_member_started},
				id_member_updated = {int:id_member_updated},
				id_first_msg = {int:id_first_msg},
				id_last_msg = {int:id_last_msg},
				id_poll = {int:id_poll},
				num_replies = {int:num_replies},
				unapproved_posts = {int:unapproved_posts},
				num_views = {int:num_views},
				is_sticky = {int:is_sticky},
				approved = {int:approved}
			WHERE id_topic = {int:id_topic}',
			array(
				'id_board' => $target_board,
				'is_sticky' => $is_sticky,
				'approved' => $topic_approved,
				'id_topic' => $id_topic,
				'id_member_started' => $member_started,
				'id_member_updated' => $member_updated,
				'id_first_msg' => $first_msg,
				'id_last_msg' => $last_msg,
				'id_poll' => $target_poll,
				'num_replies' => $num_replies,
				'unapproved_posts' => $num_unapproved,
				'num_views' => $num_views,
			)
		);

		// Grab the response prefix (like 'Re: ') in the default forum language.
		if (!isset($context['response_prefix']) && !($context['response_prefix'] = cache_get_data('response_prefix')))
		{
			if ($language === $user_info['language'])
				$context['response_prefix'] = $txt['response_prefix'];
			else
			{
				loadLanguage('index', $language, false);
				$context['response_prefix'] = $txt['response_prefix'];
				loadLanguage('index');
			}
			cache_put_data('response_prefix', $context['response_prefix'], 600);
		}

		// Change the topic IDs of all messages that will be merged.  Also adjust subjects if 'enforce subject' was checked.
		$db->query('', '
			UPDATE {db_prefix}messages
			SET
				id_topic = {int:id_topic},
				id_board = {int:target_board}' . (empty($_POST['enforce_subject']) ? '' : ',
				subject = {string:subject}') . '
			WHERE id_topic IN ({array_int:topic_list})',
			array(
				'topic_list' => $topics,
				'id_topic' => $id_topic,
				'target_board' => $target_board,
				'subject' => $context['response_prefix'] . $target_subject,
			)
		);

		// Any reported posts should reflect the new board.
		$db->query('', '
			UPDATE {db_prefix}log_reported
			SET
				id_topic = {int:id_topic},
				id_board = {int:target_board}
			WHERE id_topic IN ({array_int:topics_list})',
			array(
				'topics_list' => $topics,
				'id_topic' => $id_topic,
				'target_board' => $target_board,
			)
		);

		// Change the subject of the first message...
		$db->query('', '
			UPDATE {db_prefix}messages
			SET subject = {string:target_subject}
			WHERE id_msg = {int:first_msg}',
			array(
				'first_msg' => $first_msg,
				'target_subject' => $target_subject,
			)
		);

		// Adjust all calendar events to point to the new topic.
		$db->query('', '
			UPDATE {db_prefix}calendar
			SET
				id_topic = {int:id_topic},
				id_board = {int:target_board}
			WHERE id_topic IN ({array_int:deleted_topics})',
			array(
				'deleted_topics' => $deleted_topics,
				'id_topic' => $id_topic,
				'target_board' => $target_board,
			)
		);

		// Merge log topic entries.
		// The disregard setting comes from the oldest topic
		$request = $db->query('', '
			SELECT id_member, MIN(id_msg) AS new_id_msg, disregarded
			FROM {db_prefix}log_topics
			WHERE id_topic IN ({array_int:topics})
			GROUP BY id_member',
			array(
				'topics' => $topics,
			)
		);
		if ($db->num_rows($request) > 0)
		{
			$replaceEntries = array();
			while ($row = $db->fetch_assoc($request))
				$replaceEntries[] = array($row['id_member'], $id_topic, $row['new_id_msg'], $row['disregarded']);

			require_once(SUBSDIR . '/Topic.subs.php');
			markTopicsRead($replaceEntries, true);
			unset($replaceEntries);

			// Get rid of the old log entries.
			$db->query('', '
				DELETE FROM {db_prefix}log_topics
				WHERE id_topic IN ({array_int:deleted_topics})',
				array(
					'deleted_topics' => $deleted_topics,
				)
			);
		}
		$db->free_result($request);

		// Merge topic notifications.
		$notifications = isset($_POST['notifications']) && is_array($_POST['notifications']) ? array_intersect($topics, $_POST['notifications']) : array();
		if (!empty($notifications))
		{
			$request = $db->query('', '
				SELECT id_member, MAX(sent) AS sent
				FROM {db_prefix}log_notify
				WHERE id_topic IN ({array_int:topics_list})
				GROUP BY id_member',
				array(
					'topics_list' => $notifications,
				)
			);
			if ($db->num_rows($request) > 0)
			{
				$replaceEntries = array();
				while ($row = $db->fetch_assoc($request))
					$replaceEntries[] = array($row['id_member'], $id_topic, 0, $row['sent']);

				$db->insert('replace',
						'{db_prefix}log_notify',
						array('id_member' => 'int', 'id_topic' => 'int', 'id_board' => 'int', 'sent' => 'int'),
						$replaceEntries,
						array('id_member', 'id_topic', 'id_board')
					);
				unset($replaceEntries);

				$db->query('', '
					DELETE FROM {db_prefix}log_topics
					WHERE id_topic IN ({array_int:deleted_topics})',
					array(
						'deleted_topics' => $deleted_topics,
					)
				);
			}
			$db->free_result($request);
		}

		// Get rid of the redundant polls.
		if (!empty($deleted_polls))
		{
			$db->query('', '
				DELETE FROM {db_prefix}polls
				WHERE id_poll IN ({array_int:deleted_polls})',
				array(
					'deleted_polls' => $deleted_polls,
				)
			);

			$db->query('', '
				DELETE FROM {db_prefix}poll_choices
				WHERE id_poll IN ({array_int:deleted_polls})',
				array(
					'deleted_polls' => $deleted_polls,
				)
			);

			$db->query('', '
				DELETE FROM {db_prefix}log_polls
				WHERE id_poll IN ({array_int:deleted_polls})',
				array(
					'deleted_polls' => $deleted_polls,
				)
			);
		}

		// Cycle through each board...
		foreach ($boardTotals as $id_board => $stats)
		{
			$db->query('', '
				UPDATE {db_prefix}boards
				SET
					num_topics = CASE WHEN {int:topics} > num_topics THEN 0 ELSE num_topics - {int:topics} END,
					unapproved_topics = CASE WHEN {int:unapproved_topics} > unapproved_topics THEN 0 ELSE unapproved_topics - {int:unapproved_topics} END,
					num_posts = CASE WHEN {int:posts} > num_posts THEN 0 ELSE num_posts - {int:posts} END,
					unapproved_posts = CASE WHEN {int:unapproved_posts} > unapproved_posts THEN 0 ELSE unapproved_posts - {int:unapproved_posts} END
				WHERE id_board = {int:id_board}',
				array(
					'id_board' => $id_board,
					'topics' => $stats['topics'],
					'unapproved_topics' => $stats['unapproved_topics'],
					'posts' => $stats['posts'],
					'unapproved_posts' => $stats['unapproved_posts'],
				)
			);
		}

		// Determine the board the final topic resides in
		require_once(SUBSDIR . '/Topic.subs.php');
		$topic_info = getTopicInfo($id_topic);
		$id_board = $topic_info['id_board'];

		require_once(SUBSDIR . '/Post.subs.php');

		// Update all the statistics.
		updateStats('topic');
		updateStats('subject', $id_topic, $target_subject);
		updateLastMessages($boards);

		logAction('merge', array('topic' => $id_topic, 'board' => $id_board));

		// Notify people that these topics have been merged?
		sendNotifications($id_topic, 'merge');

		// If there's a search index that needs updating, update it...
		require_once(SUBSDIR . '/Search.subs.php');
		$searchAPI = findSearchAPI();
		if (is_callable(array($searchAPI, 'topicMerge')))
			$searchAPI->topicMerge($id_topic, $topics, $affected_msgs, empty($_POST['enforce_subject']) ? null : array($context['response_prefix'], $target_subject));

		// Send them to the all done page.
		redirectexit('action=mergetopics;sa=done;to=' . $id_topic . ';targetboard=' . $target_board);
	}

	/**
	 * Shows a 'merge completed' screen.
	 * is accessed with ?action=mergetopics;sa=done.
	 * uses 'merge_done' sub template of the MergeTopics template.
	 */
	function action_mergeDone()
	{
		global $txt, $context;

		// Make sure the template knows everything...
		$context['target_board'] = (int) $_GET['targetboard'];
		$context['target_topic'] = (int) $_GET['to'];

		$context['page_title'] = $txt['merge'];
		$context['sub_template'] = 'merge_done';
	}
}