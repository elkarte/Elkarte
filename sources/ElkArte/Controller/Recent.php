<?php

/**
 * Find and retrieve information about recently posted topics, messages, and the like.
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
 */

namespace ElkArte\Controller;

use ElkArte\AbstractController;
use ElkArte\Exceptions\Exception;
use ElkArte\FrontpageInterface;

/**
 * Retrieve information about recent posts
 */
class Recent extends AbstractController implements FrontpageInterface
{
	/** @var \ElkArte\Recent The object that will retrieve the data */
	private $_grabber;

	/** @var int[] Sets range to query */
	private $_maxMsgID;

	/** @var string The url for the recent action */
	private $_base_url;

	/** @var int The number of posts found */
	private $_total_posts;

	/** @var int The starting place for pagination */
	private $_start;

	/** @var array The permissions own/any for use in the query */
	private $_permissions = [];

	/** @var bool Pass to pageindex, to use "url.page" instead of "url;start=page" */
	private $_flex_start = false;

	/** @var int Number of posts per page */
	private $_num_per_page = 10;

	/**
	 * {@inheritDoc}
	 */
	public static function frontPageHook(&$default_action)
	{
		add_integration_function('integrate_menu_buttons', '\\ElkArte\\Controller\\MessageIndex::addForumButton', '', false);
		add_integration_function('integrate_current_action', '\\ElkArte\\Controller\\MessageIndex::fixCurrentAction', '', false);

		$default_action = [
			'controller' => Recent::class,
			'function' => 'action_recent_front'
		];
	}

	/**
	 * {@inheritDoc}
	 */
	public static function frontPageOptions()
	{
		parent::frontPageOptions();

		theme()->addInlineJavascript('
			document.getElementById("front_page").addEventListener("change", function() {
			    let base = document.getElementById("recent_frontpage").parentNode;
			
			    if (this.value.endsWith("Recent")) 
			    {
			        base.fadeIn();
			        base.previousElementSibling.fadeIn();
			    }
			    else 
			    {
			        base.fadeOut();
			        base.previousElementSibling.fadeOut();
			    }
			});', true);

		return [['int', 'recent_frontpage']];
	}

	/**
	 * Called before any other action method in this class.
	 *
	 * - Allows for initializations, such as default values or
	 * loading templates or language files.
	 */
	public function pre_dispatch()
	{
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
				'like_posts' => 'can_like'
			)
		);
	}

	/**
	 * Intended entry point for recent controller class.
	 *
	 * @see AbstractController::action_index
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
		global $txt, $context, $modSettings, $board;

		// Start up a new recent posts grabber
		$this->_grabber = new \ElkArte\Recent($this->user->id);

		// Set or use a starting point for pagination
		$this->_start = $this->_req->getQuery('start', 'intval', 0);

		// Recent posts by category id's
		if (!empty($this->_req->query->c) && empty($board))
		{
			$categories = $this->_recentPostsCategory();
		}
		// Or recent posts by board id's?
		elseif (!empty($this->_req->query->boards))
		{
			$this->_recentPostsBoards();
		}
		// Or just the recent posts for a specific board
		elseif (!empty($board))
		{
			$this->_recentPostsBoard();
		}
		// All the recent posts across boards and categories it is then
		else
		{
			$this->_recentPostsAll();
		}

		if (!empty($this->_maxMsgID))
		{
			$this->_grabber->setEarliestMsg(max(0, $modSettings['maxMsgID'] - $this->_maxMsgID[0] - $this->_start * $this->_maxMsgID[1]));
		}

		// Set up the pageindex
		$context['page_index'] = constructPageIndex($this->_base_url, $this->_start, min(100, $this->_total_posts), $this->_num_per_page, !empty($this->_flex_start));

		// Rest of the items for the template
		theme()->getTemplates()->load('Recent');
		$context['page_title'] = $txt['recent_posts'];
		$context['sub_template'] = 'recent';
		$quote_enabled = empty($modSettings['disabledBBC']) || !in_array('quote', explode(',', $modSettings['disabledBBC']));

		// Linktree
		$context['linktree'][] = array(
			'url' => getUrl('action', ['action' => 'recent'] + (empty($board) ? (empty($categories) ? [] : ['c' => implode(',', $categories)]) : ['board' => $board . '.0'])),
			'name' => $context['page_title']
		);

		// Nothing here... Or at least, nothing you can see...
		if (!$this->_grabber->findRecentMessages($this->_start, $this->_num_per_page))
		{
			$context['posts'] = array();
		}
		else
		{
			$context['posts'] = $this->_grabber->getRecentPosts($this->_start, $this->_permissions);
		}

		// Load any likes for the messages
		$context['likes'] = $this->_getLikes($context['posts']);

		foreach ($context['posts'] as $counter => $post)
		{
			// Some posts - the first posts - can't just be deleted.
			$context['posts'][$counter]['tests']['can_delete'] = $context['posts'][$counter]['tests']['can_delete'] && $context['posts'][$counter]['delete_possible'];

			// And some cannot be quoted...
			$context['posts'][$counter]['tests']['can_quote'] = $context['posts'][$counter]['tests']['can_reply'] && $quote_enabled;

			// Likes are always a bit particular
			$post['you_liked'] = !empty($context['likes'][$counter]['member'])
				&& isset($context['likes'][$counter]['member'][$this->user->id]);
			$post['use_likes'] = !empty($post['tests']['can_like']) && allowedTo('like_posts') && empty($context['is_locked'])
				&& ($post['poster']['id'] != $this->user->id || !empty($modSettings['likeAllowSelf']))
				&& (empty($modSettings['likeMinPosts']) || $modSettings['likeMinPosts'] <= $this->user->posts);
			$post['like_counter'] = empty($context['likes'][$counter]['count']) ? 0 : $context['likes'][$counter]['count'];
			$post['can_like'] = $post['use_likes'] && !$post['you_liked'];
			$post['can_unlike'] = $post['use_likes'] && $post['you_liked'];
			$post['likes_enabled'] = !empty($modSettings['likes_enabled']) && ($post['use_likes'] || ($post['like_counter'] != 0));

			// Let's add some buttons here!
			$context['posts'][$counter]['buttons'] = $this->_addButtons($post, $context['posts'][$counter]['tests']);
		}
	}

	/**
	 * Set up for getting recent posts on a category basis
	 */
	private function _recentPostsCategory()
	{
		global $modSettings, $context;

		$categories = array_map('intval', explode(',', $this->_req->query->c));

		if (count($categories) === 1)
		{
			require_once(SUBSDIR . '/Categories.subs.php');
			$name = categoryName($categories[0]);

			if (empty($name))
			{
				throw new Exception('no_access', false);
			}

			$context['linktree'][] = array(
				'url' => getUrl('action', $modSettings['default_forum_action']) . '#c' . $categories[0],
				'name' => $name
			);
		}

		// Find the number of posts in these category's, exclude the recycle board.
		$boards_posts = boardsPosts(array(), $categories, false, false);
		$this->_total_posts = (int) array_sum($boards_posts);
		$boards = array_keys($boards_posts);

		if (empty($boards))
		{
			throw new Exception('error_no_boards_selected');
		}

		// The query for getting the messages
		$this->_grabber->setBoards($boards);

		// If this category has a significant number of posts in it...
		if ($this->_total_posts > 100 && $this->_total_posts > $modSettings['totalMessages'] / 15)
		{
			$this->_maxMsgID = array(400, 7);
		}

		$this->_base_url = '{scripturl}?action=recent;c=' . implode(',', $categories);

		return $categories;
	}

	/**
	 * Setup for finding recent posts based on a list of boards
	 */
	private function _recentPostsBoards()
	{
		global $modSettings;

		$this->_req->query->boards = array_map('intval', explode(',', $this->_req->query->boards));

		// Fetch the number of posts for the supplied board IDs
		$boards_posts = boardsPosts($this->_req->query->boards, array());
		$this->_total_posts = (int) array_sum($boards_posts);
		$boards = array_keys($boards_posts);

		// No boards, your request ends here
		if (empty($boards))
		{
			throw new Exception('error_no_boards_selected');
		}

		// Build the query for finding the messages
		$this->_grabber->setBoards($boards);

		// If these boards have a significant number of posts in them...
		if ($this->_total_posts > 100 && $this->_total_posts > $modSettings['totalMessages'] / 12)
		{
			$this->_maxMsgID = array(500, 9);
		}

		$this->_base_url = '{scripturl}?action=recent;boards=' . implode(',', $this->_req->query->boards);
	}

	/**
	 * Setup for finding recent posts for a single board
	 */
	private function _recentPostsBoard()
	{
		global $modSettings, $board;

		$board_data = fetchBoardsInfo(array('boards' => $board), array('selects' => 'posts'));
		$this->_total_posts = $board_data[(int) $board]['num_posts'];

		$this->_grabber->setBoards($board);

		// If this board has a significant number of posts in it...
		if ($this->_total_posts > 80 && $this->_total_posts > $modSettings['totalMessages'] / $this->_num_per_page)
		{
			$this->_maxMsgID = array(600, 10);
		}

		$this->_base_url = '{scripturl}?action=recent;board=' . $board . '.%1$d';
		$this->_flex_start = true;
	}

	/**
	 * Setup to find all the recent posts across all boards and categories
	 */
	private function _recentPostsAll()
	{
		global $modSettings;

		$this->_total_posts = sumRecentPosts();

		$this->_grabber->setVisibleBoards(max(0, $modSettings['maxMsgID'] - 100 - $this->_start * 6), !empty($modSettings['recycle_enable']) && $modSettings['recycle_board'] > 0 ? $modSettings['recycle_board'] : 0);

		// Set up the pageindex
		$this->_base_url = '{scripturl}?action=recent';
	}

	/**
	 * Loads the likes for the set of recent messages
	 *
	 * @param array $messages
	 *
	 * @return array|int[]
	 * @throws \Exception
	 */
	private function _getLikes($messages)
	{
		global $modSettings;

		$likes = array();

		// Load in the likes for this group of messages
		if (!empty($modSettings['likes_enabled']))
		{
			// Just the message id please
			$messages = array_column($messages, 'id');

			require_once(SUBSDIR . '/Likes.subs.php');
			$likes = loadLikes($messages, true);

			theme()->getLayers()->addBefore('load_likes_button', 'body');
		}

		return $likes;
	}

	/**
	 * Create the buttons array for this post
	 * Array is used by template_button_strip(), see that function for parameter details
	 *
	 * @param array $post Details of this post
	 * @param array $tests array holding true false values for various test keys, like can_quote;
	 * @return array
	 */
	private function _addButtons($post, $tests)
	{
		global $txt;

		$postButtons = [
			// How about... even... remove it entirely?!
			'remove' => [
				'url' => getUrl('action', ['action' => 'deletemsg', 'msg' => $post['id'], 'topic' => $post['topic'], 'recent', '{session_data}']),
				'text' => 'remove',
				'icon' => 'delete',
				'enabled' => $tests['can_delete'],
				'custom' => 'onclick="return confirm(' . JavaScriptEscape($txt['remove_message'] . '?') . ');"',
			],
			// Can we request notification of topics?
			'notify' => [
				'url' => getUrl('action', ['action' => 'notify', 'topic' => $post['topic'] . '.' . $post['start']]),
				'text' => 'notify',
				'icon' => 'comment',
				'enabled' => $tests['can_mark_notify'],
			],
			// If they *can* reply?
			'reply' => [
				'url' => getUrl('action', ['action' => 'post', 'topic' => $post['topic'] . '.' . $post['start']]),
				'text' => 'reply',
				'icon' => 'modify',
				'enabled' => $tests['can_reply'],
			],
			// If they *can* quote?
			'quote' => [
				'text' => 'quote',
				'url' => getUrl('action', ['action' => 'post', 'topic' => $post['topic'] . '.' . $post['start'], 'quote' => $post['id']]),
				'class' => 'quote_button last',
				'icon' => 'quote',
				'enabled' => $tests['can_quote'],
			],
			// If they *can* like / unlike / just see totals
			'react' => [
				'text' => 'like_post',
				'url' => 'javascript:void(0);',
				'custom' => 'onclick="likePosts.prototype.likeUnlikePosts(event,' . $post['id'] . ',' . $post['topic'] . '); return false;"',
				'linkclass' => 'react_button',
				'icon' => 'thumbsup',
				'enabled' => $post['likes_enabled'] && $post['can_like'],
				'counter' => $post['like_counter'] ?? 0,
			],
			'unreact' => [
				'text' => 'unlike_post',
				'url' => 'javascript:void(0);',
				'custom' => 'onclick="likePosts.prototype.likeUnlikePosts(event,' . $post['id'] . ',' . $post['topic'] . '); return false;"',
				'linkclass' => 'unreact_button',
				'icon' => 'thumbsdown',
				'enabled' => $post['likes_enabled'] && $post['can_unlike'],
				'counter' => $post['like_counter'] ?? 0,
			],
			'liked' => [
				'text' => 'likes',
				'url' => 'javascript:void(0);',
				'custom' => 'onclick="this.blur();"',
				'icon' => 'thumbsup',
				'enabled' => $post['likes_enabled'] && !$post['can_unlike'] && !$post['can_like'],
				'counter' => $post['like_counter'] ?? 0,
			],
		];

		// Drop all non-enabled ones
		return array_filter($postButtons, static fn($button) => !isset($button['enabled']) || $button['enabled'] !== false);
	}

	/**
	 * Intended entry point for recent controller class.
	 *
	 * @see Action_Controller::action_index()
	 */
	public function action_recent_front()
	{
		global $modSettings;

		if (isset($modSettings['recent_frontpage']))
		{
			$this->_num_per_page = $modSettings['recent_frontpage'];
		}

		// Figure out what action to do, thinking, thinking ...
		$this->action_recent();
	}
}
