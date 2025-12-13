<?php

/**
 * Handles all mark as read options, boards, topics, replies
 *
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * @version 2.0 Beta 1
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

		$subActions = [
			'all' => [$this, 'action_markboards'],
			'unreadreplies' => [$this, 'action_markreplies'],
			'topic' => [$this, 'action_marktopic_unread'],
			'markasread' => [$this, 'action_markasread']
		];

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
	public function action_index_api($action, $subAction): ?string
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
			$context['xml_data'] = [
				'error' => 1,
				'text' => $txt['not_guests']
			];

			return '';
		}

		// Best have a valid session
		if (checkSession('get', '', false) !== '')
		{
			// Provide a redirect URL for AJAX handlers when the session is invalid
			Txt::load('Errors');
			$sa = $this->_req->getQuery('sa');

			if ($sa === 'all')
			{
				$context['xml_data'] = [
					'error' => 1,
					'url' => getUrl('action', ['action' => 'markasread', 'sa' => 'all', '{session_data}']),
				];

				return '';
			}

			// For board-level and other mark actions, send back a URL including a fresh session token
			if ($sa === 'board')
			{
				$board_q = $this->_req->getQuery('board', 'trim', '');
				$board_param = [];
				if ($board_q !== '')
				{
					$board_param['board'] = $board_q;
				}

				$context['xml_data'] = [
					'error' => 1,
					'url' => getUrl('action', array_merge(['action' => 'markasread', 'sa' => 'board', '{session_data}'], $board_param)),
				];

				return '';
			}

			// Default: just output an error and let the generic handler redirect to a safe place
			$context['xml_data'] = [
				'error' => 1,
				'url' => getUrl('action', ['action' => 'unread', '{session_data}']),
			];

			return '';
		}

		// Dispatch to the right method
		$action->dispatch($subAction);

		// For the time being this is a special case, but in BoardIndex no, we don't want it
		if ($this->_req->getQuery('sa') === 'all' || ($this->_req->getQuery('sa') === 'board' && !$this->_req->hasQuery('bi')))
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

			$context['xml_data'] = [
				'text' => $txt['topic_alert_none'],
				'body' => str_replace('{unread_all_url}', getUrl('action', $url_params), $txt['unread_topics_visit_none']),
			];

			return '';
		}

		// No need to output anything, just return to the button
		obExit(false);
		return null;
	}

	/**
	 * Marks boards as read (or unread)
	 *
	 * - Accessed by action=markasread;sa=all
	 */
	public function action_markboards(): ?string
	{
		global $modSettings;

		require_once(SUBSDIR . '/Boards.subs.php');

		// Find all the boards this user can see.
		$boards = accessibleBoards();

		// Mark boards as read
		if (!empty($boards))
		{
			markBoardsRead($boards, $this->_req->hasQuery('unread'), true);
		}

		$_SESSION['id_msg_last_visit'] = $modSettings['maxMsgID'];
		$redirectAction = '';
		if (!empty($_SESSION['old_url']) && str_contains($_SESSION['old_url'], 'action=unread'))
		{
			$redirectAction = 'action=unread';
		}

		if (isset($_SESSION['topicseen_cache']))
		{
			$_SESSION['topicseen_cache'] = [];
		}

		if (!empty($modSettings['default_forum_action']) && $redirectAction === '')
		{
			$redirectAction = getUrlQuery('action', $modSettings['default_forum_action']);
		}

		if ($this->api)
		{
			return '';
		}

		redirectexit($redirectAction);
		return null;
	}

	/**
	 * Marks the selected topics as read.
	 *
	 * - Accessed by action=markasread;sa=unreadreplies
	 */
	public function action_markreplies(): ?string
	{
		global $modSettings;

		// Make sure all the topics are integers!
		$topics_param = $this->_req->getQuery('topics', 'trim|strval', '');
		$topics = $topics_param === '' ? [] : array_map('intval', explode('-', $topics_param));

		require_once(SUBSDIR . '/Topic.subs.php');
		$logged_topics = getLoggedTopics($this->user->id, $topics);

		$markRead = [];
		foreach ($topics as $id_topic)
		{
			$markRead[] = [$this->user->id, (int) $id_topic, $modSettings['maxMsgID'], (int) !empty($logged_topics[$id_topic])];
		}

		markTopicsRead($markRead, true);

		if (isset($_SESSION['topicseen_cache']))
		{
			$_SESSION['topicseen_cache'] = [];
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
	public function action_marktopic_unread(): ?string
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
			[$earlyMsg] = messageAt((int) $this->_req->query->start, $topic);
			$earlyMsg--;
		}

		// Blam, unread!
		markTopicsRead([$this->user->id, $topic, $earlyMsg, $topicinfo['unwatched']], true);

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
	public function action_markasread(): ?string
	{
		global $board, $board_info;

		require_once(SUBSDIR . '/Boards.subs.php');

		$categories = [];
		$boards = [];

		if ($this->_req->hasQuery('c'))
		{
			$c_param = $this->_req->getQuery('c', 'trim|strval', '');
			$categories = $c_param === '' ? [] : array_map('intval', explode(',', $c_param));
		}

		if ($this->_req->hasQuery('boards'))
		{
			$boards_param = $this->_req->getQuery('boards', 'trim|strval', '');
			$boards = $boards_param === '' ? [] : array_map('intval', explode(',', $boards_param));
		}

		if (!empty($board))
		{
			$boards[] = (int) $board;
		}

		if ($this->_req->hasQuery('children') && !empty($boards))
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

			redirectexit();
			return null;
		}

		// Mark boards as read.
		markBoardsRead($boards, $this->_req->hasQuery('unread'), true);

		foreach ($boards as $b)
		{
			if (isset($_SESSION['topicseen_cache'][$b]))
			{
				$_SESSION['topicseen_cache'][$b] = [];
			}
		}

		$this->_querystring_board_limits = $this->_req->getQuery('sa') === 'board' ? ['boards' => implode(',', $boards), 'start' => '%d'] : [];

		$this->_setQuerystringSortLimits();

		$this->_markAsRead($boards);

		if (empty($board_info['parent']) && !$this->api)
		{
			redirectexit();
			return null;
		}

		if ($this->api)
		{
			return '';
		}

		redirectexit('board=' . $board_info['parent'] . '.0');
		return null;
	}

	/**
	 * Sets the sorting parameters
	 */
	private function _setQuerystringSortLimits(): void
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
		$sort = $this->_req->getQuery('sort', 'trim|strval', null);
		if ($sort === null || !in_array($sort, $sort_methods))
		{
			$this->_querystring_sort_limits = $this->_req->hasQuery('asc') ? ['asc'] : [];
		}
		// But, for other methods the default sort is ascending.
		else
		{
			$this->_querystring_sort_limits = ['sort' => $sort, $this->_req->hasQuery('desc') ? 'desc' : ''];
		}
	}

	/**
	 * Mark a group of boards as read
	 *
	 * @param array $boards
	 */
	private function _markAsRead($boards): ?string
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

		redirectexit($redirectAction);
		return null;
	}
}
