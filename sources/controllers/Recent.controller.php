<?php

/**
 * Find and retrieve information about recently posted topics, messages, and the like.
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
 * @version 1.0.2
 *
 */

if (!defined('ELK'))
	die('No access...');

/**
 * Recent Post Controller, retrieve information about recent posts
 */
class Recent_Controller extends Action_Controller
{
	/**
	 * The object that will retrieve the data
	 */
	private $_grabber = null;

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

		require_once(SUBSDIR . '/Recent.subs.php');
		require_once(SUBSDIR . '/Boards.subs.php');
	}

	/**
	 * Intended entry point for recent controller class.
	 *
	 * @see Action_Controller::action_index()
	 */
	public function action_index()
	{
		// Figure out what action to do
		$this->action_recent();
	}

	/**
	 * Find the ten most recent posts.
	 * Accessed by action=recent.
	 */
	public function action_recent()
	{
		global $txt, $scripturl, $context, $modSettings, $board, $user_info;

		require_once(SUBSDIR . '/Recent.class.php');
		$this->_grabber = new Recent_Class($user_info['id']);

		loadTemplate('Recent');
		$context['page_title'] = $txt['recent_posts'];
		$context['sub_template'] = 'recent';

		$start = isset($_REQUEST['start']) ? $_REQUEST['start'] : 0;

		// Recent posts by category id's
		if (!empty($_REQUEST['c']) && empty($board))
		{
			$categories = array_map('intval', explode(',', $_REQUEST['c']));

			if (count($categories) === 1)
			{
				require_once(SUBSDIR . '/Categories.subs.php');
				$name = categoryName($categories[0]);

				if (empty($name))
					fatal_lang_error('no_access', false);

				$context['linktree'][] = array(
					'url' => $scripturl . '#c' . $categories[0],
					'name' => $name
				);
			}

			// Find the number of posts in these categorys, exclude the recycle board.
			$boards_posts = boardsPosts(array(), $categories, false, false);
			$total_posts = array_sum($boards_posts);
			$boards = array_keys($boards_posts);

			if (empty($boards))
				fatal_lang_error('error_no_boards_selected');

			// The query for getting the messages
			$this->_grabber->setBoards($boards);

			// If this category has a significant number of posts in it...
			if ($total_posts > 100 && $total_posts > $modSettings['totalMessages'] / 15)
				$maxMsgID = array(400, 7);

			$base_url = $scripturl . '?action=recent;c=' . implode(',', $categories);
		}
		// Or recent posts by board id's?
		elseif (!empty($_REQUEST['boards']))
		{
			$_REQUEST['boards'] = explode(',', $_REQUEST['boards']);
			foreach ($_REQUEST['boards'] as $i => $b)
				$_REQUEST['boards'][$i] = (int) $b;

			// Fetch the number of posts for the supplied board IDs
			$boards_posts = boardsPosts($_REQUEST['boards'], array());
			$total_posts = array_sum($boards_posts);
			$boards = array_keys($boards_posts);

			if (empty($boards))
				fatal_lang_error('error_no_boards_selected');

			// Build the query for finding the messages
			$this->_grabber->setBoards($boards);

			// If these boards have a significant number of posts in them...
			if ($total_posts > 100 && $total_posts > $modSettings['totalMessages'] / 12)
				$maxMsgID = array(500, 9);

			$base_url = $scripturl . '?action=recent;boards=' . implode(',', $_REQUEST['boards']);
		}
		// Or just the recent posts for a specific board
		elseif (!empty($board))
		{
			$board_data = fetchBoardsInfo(array('boards' => $board), array('selects' => 'posts'));
			$total_posts = $board_data[$board]['num_posts'];

			$this->_grabber->setBoards($board);

			// If this board has a significant number of posts in it...
			if ($total_posts > 80 && $total_posts > $modSettings['totalMessages'] / 10)
				$maxMsgID = array(600, 10);

			$base_url = $scripturl . '?action=recent;board=' . $board . '.%1$d';
			$flex_start = true;
		}
		// All the recent posts across boards and categories it is then
		else
		{
			$total_posts = sumRecentPosts();

			$this->_grabber->setVisibleBoards(max(0, $modSettings['maxMsgID'] - 100 - $start * 6), !empty($modSettings['recycle_enable']) && $modSettings['recycle_board'] > 0 ? $modSettings['recycle_board'] : 0);

			// Set up the pageindex
			$base_url = $scripturl . '?action=recent';
		}

		if (!empty($maxMsgID))
			$this->_grabber->setEarliestMsg(max(0, $modSettings['maxMsgID'] - $maxMsgID[0] - $start * $maxMsgID[1]));

		// Set up the pageindex
		$context['page_index'] = constructPageIndex($base_url, $start, min(100, $total_posts), 10, !empty($flex_start));

		$context['linktree'][] = array(
			'url' => $scripturl . '?action=recent' . (empty($board) ? (empty($categories) ? '' : ';c=' . implode(',', $categories)) : ';board=' . $board . '.0'),
			'name' => $context['page_title']
		);

		// Nothing here... Or at least, nothing you can see...
		if (!$this->_grabber->findRecentMessages($start, 10))
		{
			$context['posts'] = array();
			return;
		}

		// There might be - and are - different permissions between any and own.
		$permissions = array(
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

		$context['posts'] = $this->_grabber->getRecentPosts($start, $permissions);

		$quote_enabled = empty($modSettings['disabledBBC']) || !in_array('quote', explode(',', $modSettings['disabledBBC']));
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
}