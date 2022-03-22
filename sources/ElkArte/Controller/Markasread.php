<?php

/**
 * Handles all mark as read options, boards, topics, replies
 *
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * @version 2.0 dev
 *
 */

namespace ElkArte\Controller;

use ElkArte\AbstractController;
use ElkArte\Action;
use ElkArte\Languages\Txt;

/**
 * This class handles a part of the actions to mark boards, topics, or replies,
 * as read/unread.
 */
class Markasread extends AbstractController
{
	/** @var array used to redirect user to the correct boards when marking unread */
	private $_querystring_board_limits;

	/** @var array used to remember user's sorting options when marking unread */
	private $_querystring_sort_limits;

	/** @var bool if this is an api call */
	private $api = false;

	/**
	 * This is the pre-dispatch function, actions common to all methods
	 */
	public function pre_dispatch()
	{
		$this->api = $this->getApi() === 'xml';

		// We will check these items in the ajax function
		if (!$this->api)
		{
			// Guests can't mark things.
			is_not_guest();

			checkSession('get');
		}
	}

	/**
	 * This is the main function for markasread file
	 *
	 * markasread;sa=topic;t=###;topic=###.0;session Mark a topic unread
	 * markasread;sa=board;board=#.0;session Mark a board (all its topics) as read
	 * markasread;sa=board;c=#;start=0;session Mark a category read
	 * markasread;sa=all;session everything is read
	 * markasread;sa=unreadreplies;topics=6056-4692-6026-5817;session
	 */
	public function action_index()
	{
		global $context;

		$subActions = array(
			'all' => array($this, 'action_markboards'),
			'unreadreplies' => array($this, 'action_markreplies'),
			'topic' => array($this, 'action_marktopic_unread'),
			'markasread' => array($this, 'action_markasread')
		);

		$action = new Action('markasread');
		$subAction = $action->initialize($subActions, 'markasread');
		$context['sub_action'] = $subAction;

		if ($this->api)
		{
			$this->action_index_api($action, $subAction);
			return '';
		}

		$action->dispatch($subAction);
	}

	/**
	 * This is the controller when using APIs.
	 *
	 * @uses Xml template generic_xml_buttons sub template
	 */
	public function action_index_api($action, $subAction)
	{
		global $context, $txt;

		// Setup for an Ajax response
		theme()->getTemplates()->load('Xml');
		theme()->getLayers()->removeAll();
		$context['sub_template'] = 'generic_xml_buttons';

		// Guests can't mark things.
		if ($this->user->is_guest)
		{
			Txt::load('Errors');
			$context['xml_data'] = array(
				'error' => 1,
				'text' => $txt['not_guests']
			);

			return '';
		}

		// Best have a valid session
		if (checkSession('get', '', false))
		{
			// Again, this is a special case, someone will deal with the others later :P
			if ($this->_req->getQuery('sa') === 'all')
			{
				Txt::load('Errors');
				$context['xml_data'] = array(
					'error' => 1,
					'url' => getUrl('action', ['action' => 'markasread', 'sa' => 'all', '{session_data}']),
				);

				return '';
			}

			obExit(false);
		}

		// Dispatch to the right method
		$action->dispatch($subAction);

		// For the time being this is a special case, but in BoardIndex no, we don't want it
		if ($this->_req->getQuery('sa') === 'all' || ($this->_req->getQuery('sa') === 'board' && !isset($this->_req->query->bi)))
		{
			$url_params = ['action' => 'unread', 'all', '{session_data}'];
			if (!empty($this->_querystring_board_limits))
			{
				$url_params += $this->_querystring_board_limits;
				$url_params['start'] = 0;
			}

			if (!empty($this->_querystring_sort_limits))
			{
				$url_params += $this->_querystring_sort_limits;
			}

			$context['xml_data'] = array(
				'text' => $txt['topic_alert_none'],
				'body' => str_replace('{unread_all_url}', getUrl('action', $url_params), $txt['unread_topics_visit_none']),
			);

			return '';
		}

		// No need to output anything, just return to the button
		obExit(false);
	}

	/**
	 * Marks boards as read (or unread)
	 *
	 * - Accessed by action=markasread;sa=all
	 */
	public function action_markboards()
	{
		global $modSettings;

		require_once(SUBSDIR . '/Boards.subs.php');

		// Find all the boards this user can see.
		$boards = accessibleBoards();

		// Mark boards as read
		if (!empty($boards))
		{
			markBoardsRead($boards, isset($this->_req->query->unread), true);
		}

		$_SESSION['id_msg_last_visit'] = $modSettings['maxMsgID'];
		$redirectAction = '';
		if (!empty($_SESSION['old_url']) && strpos($_SESSION['old_url'], 'action=unread') !== false)
		{
			$redirectAction = 'action=unread';
		}

		if (isset($_SESSION['topicseen_cache']))
		{
			$_SESSION['topicseen_cache'] = array();
		}

		if (!empty($modSettings['default_forum_action']) && $redirectAction === '')
		{
			$redirectAction = getUrlQuery('action', $modSettings['default_forum_action']);
		}

		if ($this->api)
		{
			return '';
		}

		return redirectexit($redirectAction);
	}

	/**
	 * Marks the selected topics as read.
	 *
	 * - Accessed by action=markasread;sa=unreadreplies
	 */
	public function action_markreplies()
	{
		global $modSettings;

		// Make sure all the topics are integers!
		$topics = array_map('intval', explode('-', $this->_req->query->topics));

		require_once(SUBSDIR . '/Topic.subs.php');
		$logged_topics = getLoggedTopics($this->user->id, $topics);

		$markRead = array();
		foreach ($topics as $id_topic)
		{
			$markRead[] = array($this->user->id, (int) $id_topic, $modSettings['maxMsgID'], (int) !empty($logged_topics[$id_topic]));
		}

		markTopicsRead($markRead, true);

		if (isset($_SESSION['topicseen_cache']))
		{
			$_SESSION['topicseen_cache'] = array();
		}

		if ($this->api)
		{
			return '';
		}

		return redirectexit('action=unreadreplies');
	}

	/**
	 * Mark a single topic as unread, returning to the board topic listing
	 *
	 * - Accessed by action=markasread;sa=topic;topic=123;t=123
	 * - Button URL set in Display.php Controller
	 */
	public function action_marktopic_unread()
	{
		global $board, $topic;

		require_once(SUBSDIR . '/Topic.subs.php');
		require_once(SUBSDIR . '/Messages.subs.php');

		// First, let's figure out what the latest message is.
		$topicinfo = getTopicInfo($topic, 'all');
		$topic_msg_id = $this->_req->getQuery('t', 'intval');
		if (!empty($topic_msg_id))
		{
			// If they read the whole topic, go back to the beginning.
			if ($topic_msg_id >= $topicinfo['id_last_msg'])
			{
				$earlyMsg = 0;
			}
			// If they want to mark the whole thing read, same.
			elseif ($topic_msg_id <= $topicinfo['id_first_msg'])
			{
				$earlyMsg = 0;
			}
			// Otherwise, get the latest message before the named one.
			else
			{
				$earlyMsg = previousMessage($topic_msg_id, $topic);
			}
		}
		// Marking read from first page?  That's the whole topic.
		elseif ($this->_req->query->start == 0)
		{
			$earlyMsg = 0;
		}
		else
		{
			list ($earlyMsg) = messageAt((int) $this->_req->query->start, $topic);
			$earlyMsg--;
		}

		// Blam, unread!
		markTopicsRead(array($this->user->id, $topic, $earlyMsg, $topicinfo['unwatched']), true);

		if ($this->api)
		{
			return '';
		}

		return redirectexit('board=' . $board . '.0');
	}

	/**
	 * Mark as read: boards, topics, unread replies.
	 *
	 * - Accessed by action=markasread;sa=board;board=1.0;session
	 * - Subactions: sa=topic, sa=all, sa=unreadreplies, sa=board
	 */
	public function action_markasread()
	{
		global $board, $board_info;

		require_once(SUBSDIR . '/Boards.subs.php');

		$categories = array();
		$boards = array();

		if (isset($this->_req->query->c))
		{
			$categories = array_map('intval', explode(',', $this->_req->query->c));
		}

		if (isset($this->_req->query->boards))
		{
			$boards = array_map('intval', explode(',', $this->_req->query->boards));
		}

		if (!empty($board))
		{
			$boards[] = (int) $board;
		}

		if (isset($this->_req->query->children) && !empty($boards))
		{
			// Mark all children of the boards we got (selected by the user).
			$boards = addChildBoards($boards);
		}

		$boards = array_keys(boardsPosts($boards, $categories));

		if (empty($boards))
		{
			if ($this->api)
			{
				return '';
			}

			return redirectexit();
		}

		// Mark boards as read.
		markBoardsRead($boards, isset($this->_req->query->unread), true);

		foreach ($boards as $b)
		{
			if (isset($_SESSION['topicseen_cache'][$b]))
			{
				$_SESSION['topicseen_cache'][$b] = array();
			}
		}

		$this->_querystring_board_limits = $this->_req->getQuery('sa') === 'board' ? ['boards' => implode(',', $boards), 'start' => '%d'] : [];

		$this->_setQuerystringSortLimits();

		$this->_markAsRead($boards);

		if (empty($board_info['parent']) && !$this->api)
		{
			return redirectexit();
		}

		if ($this->api)
		{
			return '';
		}

		return redirectexit('board=' . $board_info['parent'] . '.0');
	}

	/**
	 * Sets the sorting parameters
	 */
	private function _setQuerystringSortLimits()
	{
		$sort_methods = [
			'subject',
			'starter',
			'replies',
			'views',
			'first_post',
			'last_post'
		];

		// The default is the most logical: newest first.
		if (!isset($this->_req->query->sort) || !in_array($this->_req->query->sort, $sort_methods))
		{
			$this->_querystring_sort_limits = isset($this->_req->query->asc) ? ['asc'] : [];
		}
		// But, for other methods the default sort is ascending.
		else
		{
			$this->_querystring_sort_limits = ['sort' => $this->_req->query->sort, isset($this->_req->query->desc) ? 'desc' : ''];
		}
	}

	/**
	 * Mark a group of boards as read
	 *
	 * @param array $boards
	 */
	private function _markAsRead($boards)
	{
		global $board;

		// Want to mark as unread, nothing to do here
		if (isset($this->_req->query->unread))
		{
			return '';
		}

		// Find all boards with the parents in the board list
		$boards_to_add = accessibleBoards(null, $boards);
		if (!empty($boards_to_add))
		{
			markBoardsRead($boards_to_add);
		}

		$redirectAction = 'board=' . $board . '.0';
		if (empty($board))
		{
			$redirectAction = '';
		}

		if ($this->api)
		{
			return '';
		}

		return redirectexit($redirectAction);
	}
}
