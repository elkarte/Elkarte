<?php

/**
 * Handle merging of topics
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
 * @version 1.0 Release Candidate 1
 *
 * Original module by Mach8 - We'll never forget you.
 * ETA: Sorry, we did.
 */

if (!defined('ELK'))
	die('No access...');

/**
 * MergeTopics_Controller class.  Merges two or more topics into a single topic.
 */
class MergeTopics_Controller extends Action_Controller
{
	/**
	 * Merges two or more topics into one topic.
	 * delegates to the other functions (based on the URL parameter sa).
	 * loads the MergeTopics template.
	 * requires the merge_any permission.
	 * is accessed with ?action=mergetopics.
	 *
	 * @see Action_Controller::action_index()
	 */
	public function action_index()
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
	public function action_mergeIndex()
	{
		global $txt, $board, $context, $scripturl, $user_info, $modSettings;

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
		require_once(SUBSDIR . '/Topic.subs.php');
		$topiccount = countTopicsByBoard($_REQUEST['targetboard'], $onlyApproved);

		// Make the page list.
		$context['page_index'] = constructPageIndex($scripturl . '?action=mergetopics;from=' . $_GET['from'] . ';targetboard=' . $_REQUEST['targetboard'] . ';board=' . $board . '.%1$d', $_REQUEST['start'], $topiccount, $modSettings['defaultMaxTopics'], true);

		// Get the topic's subject.
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
		$context['topics'] = mergeableTopics($_REQUEST['targetboard'], $_GET['from'], $onlyApproved, $_REQUEST['start']);

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
	 *
	 * @param int[] $topics = array() of topic ids
	 */
	public function action_mergeExecute($topics = array())
	{
		global $user_info, $txt, $context, $scripturl, $language, $modSettings;

		$db = database();

		// Check the session.
		checkSession('request');

		require_once(SUBSDIR . '/Topic.subs.php');
		require_once(SUBSDIR . '/Post.subs.php');

		// Handle URLs from action_mergeIndex.
		if (!empty($_GET['from']) && !empty($_GET['to']))
			$topics = array((int) $_GET['from'], (int) $_GET['to']);

		// If we came from a form, the topic IDs came by post.
		if (!empty($_POST['topics']) && is_array($_POST['topics']))
			$topics = $_POST['topics'];

		// There's nothing to merge with just one topic...
		if (empty($topics) || !is_array($topics) || count($topics) == 1)
			fatal_lang_error('merge_need_more_topics');

		$merger = new TopicsMerge($topics);

		// If we didn't get any topics then they've been messing with unapproved stuff.
		if ($merger->hasErrors())
			fatal_lang_error($merger->firstError());

		// The parameters of action_mergeExecute were set, so this must've been an internal call.
		if (!empty($topics))
		{
			isAllowedTo('merge_any', $merger->boards);
			loadTemplate('MergeTopics');
		}

		// Get the boards a user is allowed to merge in.
		$merge_boards = boardsAllowedTo('merge_any');
		if (empty($merge_boards))
			fatal_lang_error('cannot_merge_any', 'user');

		require_once(SUBSDIR . '/Boards.subs.php');

		// Make sure they can see all boards....
		$query_boards = array('boards' => $merger->boards);

		if (!in_array(0, $merge_boards))
			$query_boards['boards'] = array_merge($query_boards['boards'], $merge_boards);

		// Saved in a variable to (potentially) save a query later
		$boards_info = fetchBoardsInfo($query_boards);

		// This happens when a member is moderator of a board he cannot see
		foreach ($merger->boards as $board)
			if (!isset($boards_info[$board]))
				fatal_lang_error('no_board');

		if (empty($_REQUEST['sa']) || $_REQUEST['sa'] == 'options')
		{
			$context['polls'] = $merger->getPolls();

			if (count($merger->boards) > 1)
			{
				foreach ($boards_info as $row)
					$context['boards'][] = array(
						'id' => $row['id_board'],
						'name' => $row['name'],
						'selected' => $row['id_board'] == $merger->topic_data[$merger->firstTopic]['board']
					);
			}

			$context['topics'] = $merger->topic_data;
			foreach ($merger->topic_data as $id => $topic)
				$context['topics'][$id]['selected'] = $topic['id'] == $merger->firstTopic;

			$context['page_title'] = $txt['merge'];
			$context['sub_template'] = 'merge_extra_options';

			return;
		}

		$result = $merger->doMerge(array(
			'board' => isset($_REQUEST['board']) ? $_REQUEST['board'] : 0,
			'poll' => isset($_POST['poll']) ? $_POST['poll'] : 0,
			'subject' => !empty($_POST['subject']) ? $_POST['subject'] : '',
			'custom_subject' => isset($_POST['custom_subject']) ? $_POST['custom_subject'] : '',
			'enforce_subject' => isset($_POST['enforce_subject']) ? $_POST['enforce_subject'] : '',
			'notifications' => isset($_POST['notifications']) ? $_POST['notifications'] : '',
		));

		if ($merger->hasErrors())
		{
			$error = $merger->firstError();
			fatal_lang_error($error[0], $error[1]);
		}

		// Send them to the all done page.
		redirectexit('action=mergetopics;sa=done;to=' . $result[0] . ';targetboard=' . $result[1]);
	}

	/**
	 * Shows a 'merge completed' screen.
	 * is accessed with ?action=mergetopics;sa=done.
	 * uses 'merge_done' sub template of the MergeTopics template.
	 */
	public function action_mergeDone()
	{
		global $txt, $context;

		// Make sure the template knows everything...
		$context['target_board'] = (int) $_GET['targetboard'];
		$context['target_topic'] = (int) $_GET['to'];

		$context['page_title'] = $txt['merge'];
		$context['sub_template'] = 'merge_done';
	}
}