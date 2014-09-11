<?php

/**
 * This file contains just the functions that turn on and off notifications
 * to topics or boards.
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
 * @version 1.0
 *
 */

if (!defined('ELK'))
	die('No access...');

/**
 * Notify Controller
 */
class Notify_Controller extends Action_Controller
{
	/**
	 * Dispatch to the right action.
	 *
	 * @see Action_Controller::action_index()
	 */
	public function action_index()
	{
		// forward to our respective method.
		// $this->action_notify();
	}

	/**
	 * Turn off/on notification for a particular topic.
	 * Must be called with a topic specified in the URL.
	 * The sub-action can be 'on', 'off', or nothing for what to do.
	 * Requires the mark_any_notify permission.
	 * Upon successful completion of action will direct user back to topic.
	 * Accessed via ?action=notify.
	 *
	 * @uses Notify template, main sub-template
	 */
	public function action_notify()
	{
		global $topic, $scripturl, $txt, $user_info, $context;

		// Make sure they aren't a guest or something - guests can't really receive notifications!
		is_not_guest();
		isAllowedTo('mark_any_notify');

		// Make sure the topic has been specified.
		if (empty($topic))
			fatal_lang_error('not_a_topic', false);

		// Our topic functions are here
		require_once(SUBSDIR . '/Topic.subs.php');

		// What do we do?  Better ask if they didn't say..
		if (empty($_GET['sa']))
		{
			// Load the template, but only if it is needed.
			loadTemplate('Notify');

			// Find out if they have notification set for this topic already.
			$context['notification_set'] = hasTopicNotification($user_info['id'], $topic);

			// Set the template variables...
			$context['topic_href'] = $scripturl . '?topic=' . $topic . '.' . $_REQUEST['start'];
			$context['start'] = $_REQUEST['start'];
			$context['page_title'] = $txt['notifications'];
			$context['sub_template'] = 'notification_settings';

			return;
		}
		else
		{
			checkSession('get');

			// Attempt to turn notifications on.
			setTopicNotification($user_info['id'], $topic, $_GET['sa'] == 'on');
		}

		// Send them back to the topic.
		redirectexit('topic=' . $topic . '.' . $_REQUEST['start']);
	}

	/**
	 * Turn off/on notifications for a particular topic
	 * Intended for use in XML or JSON calls
	 */
	public function action_notify_api()
	{
		global $topic, $txt, $scripturl, $context, $user_info;

		loadTemplate('Xml');

		Template_Layers::getInstance()->removeAll();
		$context['sub_template'] = 'generic_xml_buttons';

		if ($user_info['is_guest'])
		{
			loadLanguage('Errors');
			$context['xml_data'] = array(
				'error' => 1,
				'text' => $txt['not_guests']
			);
			return;
		}

		if (!allowedTo('mark_any_notify') || empty($topic) || empty($_GET['sa']))
		{
			loadLanguage('Errors');
			$context['xml_data'] = array(
				'error' => 1,
				'text' => $txt['cannot_mark_any_notify']
			);
			return;
		}

		if (checkSession('get', '', false))
		{
			loadLanguage('Errors');
			$context['xml_data'] = array(
				'error' => 1,
				'url' => $scripturl . '?action=notify;sa=' . ($_GET['sa'] == 'on' ? 'on' : 'off') . ';topic=' . $topic . '.' . $_REQUEST['start'] . ';' . $context['session_var'] . '=' . $context['session_id'],
			);
			return;
		}

		// Our topic functions are here
		require_once(SUBSDIR . '/Topic.subs.php');

		// Attempt to turn notifications on/off.
		setTopicNotification($user_info['id'], $topic, $_GET['sa'] == 'on');

		$context['xml_data'] = array(
			'text' => $_GET['sa'] == 'on' ? $txt['unnotify'] : $txt['notify'],
			'url' => $scripturl . '?action=notify;sa=' . ($_GET['sa'] == 'on' ? 'off' : 'on') . ';topic=' . $topic . '.' . $_REQUEST['start'] . ';' . $context['session_var'] . '=' . $context['session_id'] . ';api',
			'confirm' => $_GET['sa'] == 'on' ? $txt['notification_disable_topic'] : $txt['notification_enable_topic']
		);
	}

	/**
	 * Turn off/on notification for a particular board.
	 * Must be called with a board specified in the URL.
	 * Only uses the template if no sub action is used. (on/off)
	 * Requires the mark_notify permission.
	 * Redirects the user back to the board after it is done.
	 * Accessed via ?action=notifyboard.
	 *
	 * @uses Notify template, notify_board sub-template.
	 */
	public function action_notifyboard()
	{
		global $scripturl, $txt, $board, $user_info, $context;
		// Permissions are an important part of anything ;).

		is_not_guest();
		isAllowedTo('mark_notify');

		// our board functions are here
		require_once(SUBSDIR . '/Boards.subs.php');

		// You have to specify a board to turn notifications on!
		if (empty($board))
			fatal_lang_error('no_board', false);

		// No subaction: find out what to do.
		if (empty($_GET['sa']))
		{
			// We're gonna need the notify template...
			loadTemplate('Notify');

			// Find out if they have notification set for this board already.
			$context['notification_set'] = hasBoardNotification($user_info['id'], $board);

			// Set the template variables...
			$context['board_href'] = $scripturl . '?board=' . $board . '.' . $_REQUEST['start'];
			$context['start'] = $_REQUEST['start'];
			$context['page_title'] = $txt['notifications'];
			$context['sub_template'] = 'notify_board';

			return;
		}
		// Turn the board level notification on/off?
		else
		{
			checkSession('get');

			// Turn notification on/off for this board.
			setBoardNotification($user_info['id'], $board, $_GET['sa'] == 'on');
		}

		// Back to the board!
		redirectexit('board=' . $board . '.' . $_REQUEST['start']);
	}

	/**
	 * Turn off/on notification for a particular board.
	 * Intended for use in XML or JSON calls
	 */
	public function action_notifyboard_api()
	{
		global $scripturl, $txt, $board, $user_info, $context;

		loadTemplate('Xml');

		Template_Layers::getInstance()->removeAll();
		$context['sub_template'] = 'generic_xml_buttons';

		// Permissions are an important part of anything ;).
		if ($user_info['is_guest'])
		{
			loadLanguage('Errors');
			$context['xml_data'] = array(
				'error' => 1,
				'text' => $txt['not_guests']
			);
			return;
		}

		if (!allowedTo('mark_notify') || empty($board) || empty($_GET['sa']))
		{
			loadLanguage('Errors');
			$context['xml_data'] = array(
				'error' => 1,
				'text' => $txt['cannot_mark_notify'],
			);
			return;
		}

		if (checkSession('get', '', false))
		{
			loadLanguage('Errors');
			$context['xml_data'] = array(
				'error' => 1,
				'url' => $scripturl . '?action=notifyboard;sa=' . ($_GET['sa'] == 'on' ? 'on' : 'off') . ';board=' . $board . '.' . $_REQUEST['start'] . ';' . $context['session_var'] . '=' . $context['session_id'],
			);
			return;
		}

		// our board functions are here
		require_once(SUBSDIR . '/Boards.subs.php');

		// Turn notification on/off for this board.
		setBoardNotification($user_info['id'], $board, $_GET['sa'] == 'on');

		$context['xml_data'] = array(
			'text' => $_GET['sa'] == 'on' ? $txt['unnotify'] : $txt['notify'],
			'url' => $scripturl . '?action=notifyboard;sa=' . ($_GET['sa'] == 'on' ? 'off' : 'on') . ';board=' . $board . '.' . $_REQUEST['start'] . ';' . $context['session_var'] . '=' . $context['session_id'] . ';api' . (isset($_REQUEST['json']) ? ';json' : ''),
			'confirm' => $_GET['sa'] == 'on' ? $txt['notification_disable_board'] : $txt['notification_enable_board']
		);
	}

	/**
	 * Turn off/on unread replies subscription for a topic
	 * Must be called with a topic specified in the URL.
	 * The sub-action can be 'on', 'off', or nothing for what to do.
	 * Requires the mark_any_notify permission.
	 * Upon successful completion of action will direct user back to topic.
	 * Accessed via ?action=unwatchtopic.
	 */
	public function action_unwatchtopic()
	{
		global $user_info, $topic, $modSettings;

		is_not_guest();

		// our topic functions are here
		require_once(SUBSDIR . '/Topic.subs.php');

		// Let's do something only if the function is enabled
		if (!$user_info['is_guest'] && !empty($modSettings['enable_unwatch']))
		{
			checkSession('get');

			setTopicWatch($user_info['id'], $topic, $_GET['sa'] == 'on');
		}

		// Back to the topic.
		redirectexit('topic=' . $topic . '.' . $_REQUEST['start']);
	}

	/**
	 * Turn off/on unread replies subscription for a topic
	 * Intended for use in XML or JSON calls
	 */
	public function action_unwatchtopic_api()
	{
		global $user_info, $topic, $modSettings, $txt, $context, $scripturl;

		loadTemplate('Xml');

		Template_Layers::getInstance()->removeAll();
		$context['sub_template'] = 'generic_xml_buttons';

		if ($user_info['is_guest'])
		{
			loadLanguage('Errors');
			$context['xml_data'] = array(
				'error' => 1,
				'text' => $txt['not_guests']
			);
			return;
		}

		// Let's do something only if the function is enabled
		if (empty($modSettings['enable_unwatch']))
		{
			loadLanguage('Errors');
			$context['xml_data'] = array(
				'error' => 1,
				'text' => $txt['feature_disabled'],
			);
			return;
		}

		if (checkSession('get', '', false))
		{
			loadLanguage('Errors');
			$context['xml_data'] = array(
				'error' => 1,
				'url' => $scripturl . '?action=unwatchtopic;sa=' . ($_GET['sa'] == 'on' ? 'on' : 'off') . ';topic=' . $topic . '.' . $_REQUEST['start'] . ';' . $context['session_var'] . '=' . $context['session_id'],
			);
			return;
		}

		// our topic functions are here
		require_once(SUBSDIR . '/Topic.subs.php');

		setTopicWatch($user_info['id'], $topic, $_GET['sa'] == 'on');

		$context['xml_data'] = array(
			'text' => $_GET['sa'] == 'on' ? $txt['watch'] : $txt['unwatch'],
			'url' => $scripturl . '?action=unwatchtopic;topic=' . $context['current_topic'] . '.' . $_REQUEST['start'] . ';sa=' . ($_GET['sa'] == 'on' ? 'off' : 'on') . ';' . $context['session_var'] . '=' . $context['session_id'] . ';api' . (isset($_REQUEST['json']) ? ';json' : ''),
		);
	}
}