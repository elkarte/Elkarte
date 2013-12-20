<?php

/**
 * Handles all mark as read options, boards, topics, replies
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 1.0 Beta
 *
 */

if (!defined('ELK'))
	die('No access...');

/**
 * This class handles a part of the actions to mark boards, topics, or replies, as read/unread.
 */
class MarkRead_Controller extends Action_Controller
{
	/**
	 * This is the main function for markasread file.
	 *
	 * @see Action_Controller::action_index()
	 */
	public function action_index()
	{
		// These checks have been moved here.
		// Do NOT call the specific handlers directly.

		// Guests can't mark things.
		is_not_guest();

		checkSession('get');

		$redir = $this->_dispatch();

		redirectexit($redir);
	}

	/**
	 * This function forwards the request to the appropriate function.
	 */
	private function _dispatch()
	{
		$subAction = isset($_REQUEST['sa']) ? $_REQUEST['sa'] : 'action_markasread';

		switch ($subAction)
		{
			// sa=all action_markboards()
			case 'all':
				$subAction = 'action_markboards';
				break;
			case 'unreadreplies':
				// mark topics from unread
				$subAction = 'action_markreplies';
				break;
			case 'topic':
				// mark a single topic as read
				$subAction = 'action_marktopic';
				break;
			default:
				// the rest, for now...
				$subAction = 'action_markasread';
				break;
		}

		return $this->{$subAction}();
	}

	/**
	 * This is the main method for markasread controller when using APIs.
	 * @uses Xml template generic_xml_buttons sub template
	 */
	public function action_index_api()
	{
		global $context, $txt, $user_info, $scripturl;

		loadTemplate('Xml');

		Template_Layers::getInstance()->removeAll();
		$context['sub_template'] = 'generic_xml_buttons';

		// Guests can't mark things.
		if ($user_info['is_guest'])
		{
			loadLanguage('Errors');
			$context['xml_data'] = array(
				'error' => 1,
				'text' => $txt['not_guests']
			);
			return;
		}

		if (checkSession('get', '', false))
		{
			// Again, this is a special case, someone will deal with the others later :P
			if (isset($_REQUEST['sa']) && $_REQUEST['sa'] == 'all')
			{
				loadLanguage('Errors');
				$context['xml_data'] = array(
					'error' => 1,
					'url' => $scripturl . '?action=markasread;sa=all;' . $context['session_var'] . '=' . $context['session_id'],
				);

				return;
			}
			else
				obExit(false);
		}

		$this->_dispatch();

		// For the time being this is a special case, but in BoardIndex no, we don't want it
		if (isset($_REQUEST['sa']) && $_REQUEST['sa'] == 'all' && !isset($_REQUEST['bi']))
		{
			$context['xml_data'] = array(
				'text' => $txt['unread_topics_visit_none'],
			);
		}
		// No need to do anything, just die
		else
			obExit(false);
	}

	/**
	 * Marks boards as read (or unread).
	 * Accessed by action=markasread;sa=all
	 */
	public function action_markboards()
	{
		global $modSettings;

		require_once(SUBSDIR . '/Boards.subs.php');

		// Find all the boards this user can see.
		$boards = accessibleBoards();

		// Mark boards as read
		if (!empty($boards))
			markBoardsRead($boards, isset($_REQUEST['unread']), true);

		$_SESSION['id_msg_last_visit'] = $modSettings['maxMsgID'];
		if (!empty($_SESSION['old_url']) && strpos($_SESSION['old_url'], 'action=unread') !== false)
			return 'action=unread';

		if (isset($_SESSION['topicseen_cache']))
			$_SESSION['topicseen_cache'] = array();

		return '';
	}

	/**
	 * Marks the selected topics as read.
	 * Accessed by action=markasread;sa=unreadreplies
	 */
	public function action_markreplies()
	{
		global $user_info, $modSettings;

		// Make sure all the topics are integers!
		$topics = array_map('intval', explode('-', $_REQUEST['topics']));

		require_once(SUBSDIR . '/Topic.subs.php');
		$logged_topics = getLoggedTopics($user_info['id'], $topics);

		$markRead = array();
		foreach ($topics as $id_topic)
			$markRead[] = array($user_info['id'], (int) $id_topic, $modSettings['maxMsgID'], (int) !empty($logged_topics[$id_topic]));

		markTopicsRead($markRead, true);

		if (isset($_SESSION['topicseen_cache']))
			$_SESSION['topicseen_cache'] = array();

		return 'action=unreadreplies';
	}

	/**
	 * Mark a single topic as unread.
	 * Accessed by action=markasread;sa=topic
	 */
	public function action_marktopic()
	{
		global $board, $topic, $user_info;

		require_once(SUBSDIR . '/Topic.subs.php');
		require_once(SUBSDIR . '/Messages.subs.php');

		// Mark a topic unread.
		// First, let's figure out what the latest message is.
		$topicinfo = getTopicInfo($topic, 'all');
		$topic_msg_id = (int) $_GET['t'];

		if (!empty($topic_msg_id))
		{
			// If they read the whole topic, go back to the beginning.
			if ($topic_msg_id >= $topicinfo['id_last_msg'])
				$earlyMsg = 0;
			// If they want to mark the whole thing read, same.
			elseif ($topic_msg_id <= $topicinfo['id_first_msg'])
				$earlyMsg = 0;
			// Otherwise, get the latest message before the named one.
			else
				$earlyMsg = previousMessage($topic_msg_id, $topic);
		}
		// Marking read from first page?  That's the whole topic.
		elseif ($_REQUEST['start'] == 0)
			$earlyMsg = 0;
		else
		{
			list ($earlyMsg) = messageAt((int) $_REQUEST['start'], $topic);
			$earlyMsg--;
		}

		// Blam, unread!
		markTopicsRead(array($user_info['id'], $topic, $earlyMsg, $topicinfo['unwatched']), true);

		return 'board=' . $board . '.0';
	}

	/**
	 * Mark as read: boards, topics, unread replies.
	 * Accessed by action=markasread
	 * Subactions: sa=topic, sa=all, sa=unreadreplies
	 */
	public function action_markasread()
	{
		global $board, $board_info;

		checkSession('get');

		require_once(SUBSDIR . '/Boards.subs.php');

		$categories = array();
		$boards = array();

		if (isset($_REQUEST['c']))
		{
			$_REQUEST['c'] = explode(',', $_REQUEST['c']);
			foreach ($_REQUEST['c'] as $c)
				$categories[] = (int) $c;
		}

		if (isset($_REQUEST['boards']))
		{
			$_REQUEST['boards'] = explode(',', $_REQUEST['boards']);
			foreach ($_REQUEST['boards'] as $b)
				$boards[] = (int) $b;
		}

		if (!empty($board))
			$boards[] = (int) $board;

		if (isset($_REQUEST['children']) && !empty($boards))
		{
			// Mark all children of the boards we got (selected by the user).
			addChildBoards($boards);
		}

		$boards = array_keys(boardsPosts($boards, $categories));

		if (empty($boards))
			return '';

		// Mark boards as read.
		markBoardsRead($boards, isset($_REQUEST['unread']), true);

		foreach ($boards as $b)
		{
			if (isset($_SESSION['topicseen_cache'][$b]))
				$_SESSION['topicseen_cache'][$b] = array();
		}

		if (!isset($_REQUEST['unread']))
		{
			// Find all boards with the parents in the board list
			$boards_to_add = accessibleBoards($boards);
			if (!empty($boards_to_add))
				markBoardsRead($boards_to_add);

			if (empty($board))
				return '';
			else
				return 'board=' . $board . '.0';
		}
		else
		{
			if (empty($board_info['parent']))
				return '';
			else
				return 'board=' . $board_info['parent'] . '.0';
		}
	}
}