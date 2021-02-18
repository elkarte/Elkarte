<?php

/**
 * Handle merging of topics
 *
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * This file contains code covered by:
 * copyright: 2011 Simple Machines (http://www.simplemachines.org)
 *
 * @version 2.0 dev
 *
 * Original module by Mach8 - We'll never forget you.
 * ETA: Sorry, we did.
 */

namespace ElkArte\Controller;

use ElkArte\AbstractController;
use ElkArte\Action;
use ElkArte\Exceptions\Exception;
use ElkArte\TopicsMerge;

/**
 * Merges two or more topics into a single topic.
 */
class MergeTopics extends AbstractController
{
	/**
	 * Merges two or more topics into one topic.
	 *
	 * What it does:
	 *
	 * - delegates to the other functions (based on the URL parameter sa).
	 * - loads the MergeTopics template.
	 * - requires the merge_any permission.
	 * - is accessed with ?action=mergetopics.
	 *
	 * @see \ElkArte\AbstractController::action_index()
	 */
	public function action_index()
	{
		global $context;

		// Load the template....
		theme()->getTemplates()->load('MergeTopics');

		$subActions = array(
			'done' => array($this, 'action_mergeDone'),
			'execute' => array($this, 'action_mergeExecute'),
			'index' => array($this, 'action_mergeIndex'),
			'options' => array($this, 'action_mergeExecute')
		);

		// ?action=mergetopics;sa=LETSBREAKIT won't work, sorry.
		$action = new Action('merge_topics');
		$subAction = $action->initialize($subActions, 'index');
		$context['sub_action'] = $subAction;
		$action->dispatch($subAction);
	}

	/**
	 * Allows to pick a topic to merge the current topic with.
	 *
	 * What it does:
	 *
	 * - is accessed with ?action=mergetopics;sa=index
	 * - default sub action for ?action=mergetopics.
	 * - uses 'merge' sub template of the MergeTopics template.
	 * - allows to set a different target board.
	 */
	public function action_mergeIndex()
	{
		global $txt, $board, $context, $modSettings;

		// If we don't know where you are from we know where you go
		$from = $this->_req->getQuery('from', 'intval', null);
		if (!isset($from))
		{
			throw new Exception('no_access', false);
		}

		$target_board = $this->_req->getPost('targetboard', 'intval', $board);
		$context['target_board'] = $target_board;

		// Prepare a handy query bit for approval...
		if ($modSettings['postmod_active'])
		{
			$can_approve_boards = !empty($this->user->mod_cache['ap']) ? $this->user->mod_cache['ap'] : boardsAllowedTo('approve_posts');
			$onlyApproved = $can_approve_boards !== array(0) && !in_array($target_board, $can_approve_boards);
		}
		else
		{
			$onlyApproved = false;
		}

		// How many topics are on this board?  (used for paging.)
		require_once(SUBSDIR . '/Topic.subs.php');
		$topiccount = countTopicsByBoard($target_board, $onlyApproved);

		// Make the page list.
		$context['page_index'] = constructPageIndex('{scripturl}?action=mergetopics;from=' . $from . ';targetboard=' . $target_board . ';board=' . $board . '.%1$d', $this->_req->query->start, $topiccount, $modSettings['defaultMaxTopics'], true);

		// Get the topic's subject.
		$topic_info = getTopicInfo($from, 'message');

		// @todo review: double check the logic
		if (empty($topic_info) || ($topic_info['id_board'] != $board) || ($onlyApproved && empty($topic_info['approved'])))
		{
			throw new Exception('no_board');
		}

		// Tell the template a few things..
		$context['origin_topic'] = $from;
		$context['origin_subject'] = $topic_info['subject'];
		$context['origin_js_subject'] = addcslashes(addslashes($topic_info['subject']), '/');
		$context['page_title'] = $txt['merge'];

		// Check which boards you have merge permissions on.
		$merge_boards = boardsAllowedTo('merge_any');

		if (empty($merge_boards))
		{
			throw new Exception('cannot_merge_any', 'user');
		}

		// Get a list of boards they can navigate to to merge.
		require_once(SUBSDIR . '/Boards.subs.php');
		$boardListOptions = array(
			'not_redirection' => true
		);

		if (!in_array(0, $merge_boards))
		{
			$boardListOptions['included_boards'] = $merge_boards;
		}
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
		$context['topics'] = mergeableTopics($target_board, $from, $onlyApproved, $this->_req->query->start);

		if (empty($context['topics']) && count($context['boards']) <= 1)
		{
			throw new Exception('merge_need_more_topics');
		}

		$context['sub_template'] = 'merge';
	}

	/**
	 * Set merge options and do the actual merge of two or more topics.
	 *
	 * The merge options screen:
	 * - shows topics to be merged and allows to set some merge options.
	 * - is accessed by ?action=mergetopics;sa=options and can also internally be called by action_quickmod().
	 * - uses 'merge_extra_options' sub template of the MergeTopics template.
	 *
	 * The actual merge:
	 * - is accessed with ?action=mergetopics;sa=execute.
	 * - updates the statistics to reflect the merge.
	 * - logs the action in the moderation log.
	 * - sends a notification is sent to all users monitoring this topic.
	 * - redirects to ?action=mergetopics;sa=done.
	 *
	 * @param int[] $topics = array() of topic ids
	 *
	 * @return bool
	 * @throws \ElkArte\Exceptions\Exception merge_need_more_topics
	 */
	public function action_mergeExecute($topics = array())
	{
		global $txt, $context;

		// Check the session.
		checkSession('request');

		require_once(SUBSDIR . '/Topic.subs.php');
		require_once(SUBSDIR . '/Post.subs.php');

		// Handle URLs from action_mergeIndex.
		if (!empty($this->_req->query->from) && !empty($this->_req->query->to))
		{
			$topics = array((int) $this->_req->query->from, (int) $this->_req->query->to);
		}

		// If we came from a form, the topic IDs came by post.
		if (!empty($this->_req->post->topics) && is_array($this->_req->post->topics))
		{
			$topics = $this->_req->post->topics;
		}

		// There's nothing to merge with just one topic...
		if (empty($topics) || !is_array($topics) || count($topics) === 1)
		{
			throw new Exception('merge_need_more_topics');
		}

		// Send the topics to the TopicsMerge class
		$merger = new TopicsMerge($topics);

		// If we didn't get any topics then they've been messing with unapproved stuff.
		if ($merger->hasErrors())
		{
			$error = $merger->firstError();
			throw new Exception($error[0], $error[1]);
		}

		// The parameters of action_mergeExecute were set, so this must've been an internal call.
		if (!empty($topics))
		{
			isAllowedTo('merge_any', $merger->boards);
			theme()->getTemplates()->load('MergeTopics');
		}

		// Get the boards a user is allowed to merge in.
		$allowedto_merge_boards = boardsAllowedTo('merge_any');

		// No permissions to merge, your effort ends here
		if (empty($allowedto_merge_boards))
		{
			throw new Exception('cannot_merge_any', 'user');
		}

		// Make sure they can see all boards....
		$query_boards = array('boards' => $merger->boards);

		if (!in_array(0, $allowedto_merge_boards))
		{
			$query_boards['boards'] = array_merge($query_boards['boards'], $allowedto_merge_boards);
		}

		// Saved in a variable to (potentially) save a query later
		require_once(SUBSDIR . '/Boards.subs.php');
		$boards_info = fetchBoardsInfo($query_boards);

		$boardListOptions = array(
			'not_redirection' => true,
			'selected_board' => $merger->firstBoard,
		);

		if (!in_array(0, $allowedto_merge_boards))
		{
			$boardListOptions['included_boards'] = $allowedto_merge_boards;
		}

		$context += getBoardList($boardListOptions);

		// This is removed to avoid the board not being selectable.
		$context['current_board'] = null;

		// This happens when a member is moderator of a board he cannot see
		foreach ($merger->boards as $board)
		{
			if (!isset($boards_info[$board]))
			{
				throw new Exception('no_board');
			}
		}

		if (empty($this->_req->query->sa) || $this->_req->query->sa === 'options')
		{
			$context['polls'] = $merger->getPolls();
			$context['topics'] = $merger->topic_data;
			$context['target_board'] = $this->_req->getQuery('board', 'intval', 0);

			foreach ($merger->topic_data as $id => $topic)
			{
				$context['topics'][$id]['selected'] = $topic['id'] == $merger->firstTopic;
			}

			$context['page_title'] = $txt['merge'];
			$context['sub_template'] = 'merge_extra_options';

			return true;
		}

		$result = $merger->doMerge(array(
			'board' => $merger->boards[0],
			'poll' => $this->_req->getPost('poll', 'intval', 0),
			'subject' => $this->_req->getPost('subject', 'trim', ''),
			'custom_subject' => $this->_req->getPost('custom_subject', 'trim', ''),
			'enforce_subject' => $this->_req->getPost('enforce_subject', 'trim', ''),
			'notifications' => $this->_req->getPost('notifications', 'trim', ''),
			'accessible_boards' => array_keys($boards_info),
		));

		if ($merger->hasErrors())
		{
			$error = $merger->firstError();
			throw new Exception($error[0], $error[1]);
		}

		// Send them to the all done page.
		redirectexit('action=mergetopics;sa=done;to=' . $result[0] . ';targetboard=' . $result[1]);

		return true;
	}

	/**
	 * Shows a 'merge completed' screen.
	 *
	 * - is accessed with ?action=mergetopics;sa=done.
	 * - uses 'merge_done' sub template of the MergeTopics template.
	 */
	public function action_mergeDone()
	{
		global $txt, $context;

		// Make sure the template knows everything...
		$context['target_board'] = (int) $this->_req->query->targetboard;
		$context['target_topic'] = (int) $this->_req->query->to;

		$context['page_title'] = $txt['merge'];
		$context['sub_template'] = 'merge_done';
	}
}
