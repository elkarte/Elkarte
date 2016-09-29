<?php

/**
 * Find and retrieve information about recently posted topics, messages, and the like.
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * This file contains code covered by:
 * copyright:	2011 Simple Machines (http://www.simplemachines.org)
 * license:		BSD, See included LICENSE.TXT for terms and conditions.
 *
 * @version 1.1 beta 3
 *
 */

/**
 * Recent_Controller Class
 * Retrieve information about recent posts
 */
class Recent_Controller extends Action_Controller
{
	/**
	 * The object that will retrieve the data
	 * @var Recent_Class
	 */
	private $_grabber;

	/**
	 * Sets range to query
	 * @var int[]
	 */
	private $_maxMsgID;

	/**
	 * The url for the recent action
	 * @var string
	 */
	private $_base_url;

	/**
	 * The number of posts found
	 * @var int
	 */
	private $_total_posts;

	/**
	 * The starting place for pagination
	 * @var
	 */
	private $_start;

	/**
	 * The permissions own/any for use in the query
	 * @var array
	 */
	private $_permissions = array();

	/**
	 * Pass to pageindex, to use "url.page" instead of "url;start=page"
	 * @var bool
	 */
	private $_flex_start = false;

	/**
	 * Called before any other action method in this class.
	 *
	 * - Allows for initializations, such as default values or
	 * loading templates or language files.
	 */
	public function pre_dispatch()
	{
		// Guests can't have unread things, we don't know anything about them.
		is_not_guest();

		// Prefetching + lots of MySQL work = bad mojo.
		stop_prefetching();

		// Some common method dependencies
		require_once(SUBSDIR . '/Recent.subs.php');
		require_once(SUBSDIR . '/Boards.subs.php');

		// There might be - and are - different permissions between any and own.
		$this->_permissions = array(
			'own' => array(
				'post_reply_own' => 'can_reply',
				'delete_own' => 'can_delete',
			),
			'any' => array(
				'post_reply_any' => 'can_reply',
				'mark_any_notify' => 'can_mark_notify',
				'delete_any' => 'can_delete',
			)
		);
	}

	/**
	 * Intended entry point for recent controller class.
	 *
	 * @see Action_Controller::action_index()
	 */
	public function action_index()
	{
		// Figure out what action to do, thinking, thinking ...
		$this->action_recent();
	}

	/**
	 * Find the ten most recent posts.
	 *
	 * Accessed by action=recent.
	 */
	public function action_recent()
	{
		global $txt, $scripturl, $context, $modSettings, $board, $user_info;

		// Start up a new recent posts grabber
		require_once(SUBSDIR . '/Recent.class.php');
		$this->_grabber = new Recent_Class($user_info['id']);

		// Set or use a starting point for pagination
		$this->_start = $this->_req->getQuery('start', 'intval', 0);

		// Recent posts by category id's
		if (!empty($this->_req->query->c) && empty($board))
			$categories = $this->_recentPostsCategory();
		// Or recent posts by board id's?
		elseif (!empty($this->_req->query->boards))
			$this->_recentPostsBoards();
		// Or just the recent posts for a specific board
		elseif (!empty($board))
			$this->_recentPostsBoard();
		// All the recent posts across boards and categories it is then
		else
			$this->_recentPostsAll();

		if (!empty($this->_maxMsgID))
			$this->_grabber->setEarliestMsg(max(0, $modSettings['maxMsgID'] - $this->_maxMsgID[0] - $this->_start * $this->_maxMsgID[1]));

		// Set up the pageindex
		$context['page_index'] = constructPageIndex($this->_base_url, $this->_start, min(100, $this->_total_posts), 10, !empty($this->_flex_start));

		// Rest of the items for the template
		loadTemplate('Recent');
		$context['page_title'] = $txt['recent_posts'];
		$context['sub_template'] = 'recent';
		$quote_enabled = empty($modSettings['disabledBBC']) || !in_array('quote', explode(',', $modSettings['disabledBBC']));

		// Linktree
		$context['linktree'][] = array(
			'url' => $scripturl . '?action=recent' . (empty($board) ? (empty($categories) ? '' : ';c=' . implode(',', $categories)) : ';board=' . $board . '.0'),
			'name' => $context['page_title']
		);

		// Nothing here... Or at least, nothing you can see...
		if (!$this->_grabber->findRecentMessages($this->_start, 10))
		{
			$context['posts'] = array();
		}
		else
		{
			$context['posts'] = $this->_grabber->getRecentPosts($this->_start, $this->_permissions);
		}

		foreach ($context['posts'] as $counter => $post)
		{
			// Some posts - the first posts - can't just be deleted.
			$context['posts'][$counter]['tests']['can_delete'] &= $context['posts'][$counter]['delete_possible'];

			// And some cannot be quoted...
			$context['posts'][$counter]['tests']['can_quote'] = $context['posts'][$counter]['tests']['can_reply'] && $quote_enabled;

			// Let's add some buttons here!
			$context['posts'][$counter]['buttons'] = array(
				// How about... even... remove it entirely?!
				'remove' => array(
					'href' => $scripturl . '?action=deletemsg;msg=' . $post['id'] . ';topic=' . $post['topic'] . ';recent;' . $context['session_var'] . '=' . $context['session_id'],
					'text' => $txt['remove'],
					'test' => 'can_delete',
					'custom' => 'onclick="return confirm(' . JavaScriptEscape($txt['remove_message'] . '?') . ');"',
				),
				// Can we request notification of topics?
				'notify' => array(
					'href' => $scripturl . '?action=notify;topic=' . $post['topic'] . '.' . $post['start'],
					'text' => $txt['notify'],
					'test' => 'can_mark_notify',
				),
				// If they *can* reply?
				'reply' => array(
					'href' => $scripturl . '?action=post;topic=' . $post['topic'] . '.' . $post['start'],
					'text' => $txt['reply'],
					'test' => 'can_reply',
				),
				// If they *can* quote?
				'quote' => array(
					'href' => $scripturl . '?action=post;topic=' . $post['topic'] . '.' . $post['start'] . ';quote=' . $post['id'],
					'text' => $txt['quote'],
					'test' => 'can_quote',
				),
			);
		}
	}

	/**
	 * Set up for getting recent posts on a category basis
	 */
	private function _recentPostsCategory()
	{
		global $scripturl, $modSettings, $context;

		$categories = array_map('intval', explode(',', $this->_req->query->c));

		if (count($categories) === 1)
		{
			require_once(SUBSDIR . '/Categories.subs.php');
			$name = categoryName($categories[0]);

			if (empty($name))
				Errors::instance()->fatal_lang_error('no_access', false);

			$context['linktree'][] = array(
				'url' => $scripturl . '#c' . $categories[0],
				'name' => $name
			);
		}

		// Find the number of posts in these category's, exclude the recycle board.
		$boards_posts = boardsPosts(array(), $categories, false, false);
		$this->_total_posts = array_sum($boards_posts);
		$boards = array_keys($boards_posts);

		if (empty($boards))
			Errors::instance()->fatal_lang_error('error_no_boards_selected');

		// The query for getting the messages
		$this->_grabber->setBoards($boards);

		// If this category has a significant number of posts in it...
		if ($this->_total_posts > 100 && $this->_total_posts > $modSettings['totalMessages'] / 15)
			$this->_maxMsgID = array(400, 7);

		$this->_base_url = $scripturl . '?action=recent;c=' . implode(',', $categories);

		return $categories;
	}

	/**
	 * Setup for finding recent posts based on a list of boards
	 */
	private function _recentPostsBoards()
	{
		global $scripturl, $modSettings;

		$this->_req->query->boards = array_map('intval', explode(',', $this->_req->query->boards));

		// Fetch the number of posts for the supplied board IDs
		$boards_posts = boardsPosts($this->_req->query->boards, array());
		$this->_total_posts = array_sum($boards_posts);
		$boards = array_keys($boards_posts);

		// No boards, your request ends here
		if (empty($boards))
		{
			Errors::instance()->fatal_lang_error('error_no_boards_selected');
		}

		// Build the query for finding the messages
		$this->_grabber->setBoards($boards);

		// If these boards have a significant number of posts in them...
		if ($this->_total_posts > 100 && $this->_total_posts > $modSettings['totalMessages'] / 12)
		{
			$this->_maxMsgID = array(500, 9);
		}

		$this->_base_url = $scripturl . '?action=recent;boards=' . implode(',', $this->_req->query->boards);
	}

	/**
	 * Setup for finding recent posts for a single board
	 */
	private function _recentPostsBoard()
	{
		global $scripturl, $modSettings, $board;

		$board_data = fetchBoardsInfo(array('boards' => $board), array('selects' => 'posts'));
		$this->_total_posts = $board_data[(int) $board]['num_posts'];

		$this->_grabber->setBoards($board);

		// If this board has a significant number of posts in it...
		if ($this->_total_posts > 80 && $this->_total_posts > $modSettings['totalMessages'] / 10)
			$this->_maxMsgID = array(600, 10);

		$this->_base_url = $scripturl . '?action=recent;board=' . $board . '.%1$d';
		$this->_flex_start = true;
	}

	/**
	 * Setup to find all the recent posts across all boards and categories
	 */
	private function _recentPostsAll()
	{
		global $scripturl, $modSettings;

		$this->_total_posts = sumRecentPosts();

		$this->_grabber->setVisibleBoards(max(0, $modSettings['maxMsgID'] - 100 - $this->_start * 6), !empty($modSettings['recycle_enable']) && $modSettings['recycle_board'] > 0 ? $modSettings['recycle_board'] : 0);

		// Set up the pageindex
		$this->_base_url = $scripturl . '?action=recent';
	}
}