<?php

/**
 * This file contains just the functions that turn on and off notifications
 * to topics or boards.
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

/**
 * Functions that turn on and off various member notifications
 */
class Notify extends AbstractController
{
	/**
	 * Pre Dispatch, called before other methods, used to load common needs.
	 */
	public function pre_dispatch()
	{
		// Our topic functions are here
		require_once(SUBSDIR . '/Topic.subs.php');
		require_once(SUBSDIR . '/Boards.subs.php');
	}

	/**
	 * Dispatch to the right action.
	 *
	 * @see \ElkArte\AbstractController::action_index()
	 */
	public function action_index()
	{
		// The number of choices is boggling, ok there are just 2
		$subActions = array(
			'notify' => array($this, 'action_notify'),
			'unsubscribe' => array($this, 'action_unsubscribe'),
		);

		// We like action, so lets get ready for some
		$action = new Action('');

		// Get the subAction, or just go to action_notify
		$subAction = $action->initialize($subActions, 'notify');

		// forward to our respective method.
		$action->dispatch($subAction);
	}

	/**
	 * Turn off/on notification for a particular topic.
	 *
	 * What it does:
	 *
	 * - Must be called with a topic specified in the URL.
	 * - The sub-action can be 'on', 'off', or nothing for what to do.
	 * - Requires the mark_any_notify permission.
	 * - Upon successful completion of action will direct user back to topic.
	 * - Accessed via ?action=notify.
	 *
	 * @uses Notify.template, main sub-template
	 */
	public function action_notify()
	{
		global $topic, $scripturl, $txt, $context;

		// Make sure they aren't a guest or something - guests can't really receive notifications!
		is_not_guest();
		isAllowedTo('mark_any_notify');

		// Make sure the topic has been specified.
		if (empty($topic))
		{
			throw new Exception('not_a_topic', false);
		}

		// What do we do?  Better ask if they didn't say..
		if (empty($this->_req->query->sa))
		{
			// Load the template, but only if it is needed.
			theme()->getTemplates()->load('Notify');

			// Find out if they have notification set for this topic already.
			$context['notification_set'] = hasTopicNotification($this->user->id, $topic);

			// Set the template variables...
			$context['topic_href'] = $scripturl . '?topic=' . $topic . '.' . $this->_req->query->start;
			$context['start'] = $this->_req->query->start;
			$context['page_title'] = $txt['notifications'];
			$context['sub_template'] = 'notification_settings';

			return true;
		}
		else
		{
			checkSession('get');

			$this->_toggle_topic_notification();
		}

		// Send them back to the topic.
		redirectexit('topic=' . $topic . '.' . $this->_req->query->start);

		return true;
	}

	/**
	 * Toggle a topic notification on/off
	 */
	private function _toggle_topic_notification()
	{
		global $topic;

		// Attempt to turn notifications on/off.
		setTopicNotification($this->user->id, $topic, $this->_req->query->sa === 'on');
	}

	/**
	 * Turn off/on notifications for a particular topic
	 *
	 * - Intended for use in XML or JSON calls
	 */
	public function action_notify_api()
	{
		global $topic, $txt, $scripturl, $context;

		theme()->getTemplates()->load('Xml');

		theme()->getLayers()->removeAll();
		$context['sub_template'] = 'generic_xml_buttons';

		// Even with Ajax, guests still can't do this
		if ($this->user->is_guest)
		{
			theme()->getTemplates()->loadLanguageFile('Errors');
			$context['xml_data'] = array(
				'error' => 1,
				'text' => $txt['not_guests']
			);

			return;
		}

		// And members still need the right permissions
		if (!allowedTo('mark_any_notify') || empty($topic) || empty($this->_req->query->sa))
		{
			theme()->getTemplates()->loadLanguageFile('Errors');
			$context['xml_data'] = array(
				'error' => 1,
				'text' => $txt['cannot_mark_any_notify']
			);

			return;
		}

		// And sessions still matter, so you better have a valid one
		if (checkSession('get', '', false))
		{
			theme()->getTemplates()->loadLanguageFile('Errors');
			$context['xml_data'] = array(
				'error' => 1,
				'url' => $scripturl . '?action=notify;sa=' . ($this->_req->query->sa === 'on' ? 'on' : 'off') . ';topic=' . $topic . '.' . $this->_req->query->start . ';' . $context['session_var'] . '=' . $context['session_id'],
			);

			return;
		}

		$this->_toggle_topic_notification();

		// Return the results so the UI can be updated properly
		$context['xml_data'] = array(
			'text' => $this->_req->query->sa === 'on' ? $txt['unnotify'] : $txt['notify'],
			'url' => $scripturl . '?action=notify;sa=' . ($this->_req->query->sa === 'on' ? 'off' : 'on') . ';topic=' . $topic . '.' . $this->_req->query->start . ';' . $context['session_var'] . '=' . $context['session_id'] . ';api',
			'confirm' => $this->_req->query->sa === 'on' ? $txt['notification_disable_topic'] : $txt['notification_enable_topic']
		);
	}

	/**
	 * Turn off/on notification for a particular board.
	 *
	 * What it does:
	 *
	 * - Must be called with a board specified in the URL.
	 * - Only uses the template if no sub action is used. (on/off)
	 * - Requires the mark_notify permission.
	 * - Redirects the user back to the board after it is done.
	 * - Accessed via ?action=notifyboard.
	 *
	 * @uses template_notify_board() sub-template in Notify.template
	 */
	public function action_notifyboard()
	{
		global $scripturl, $txt, $board, $context;

		// Permissions are an important part of anything ;).
		is_not_guest();
		isAllowedTo('mark_notify');

		// You have to specify a board to turn notifications on!
		if (empty($board))
		{
			throw new Exception('no_board', false);
		}

		// No subaction: find out what to do.
		if (empty($this->_req->query->sa))
		{
			// We're gonna need the notify template...
			theme()->getTemplates()->load('Notify');

			// Find out if they have notification set for this board already.
			$context['notification_set'] = hasBoardNotification($this->user->id, $board);

			// Set the template variables...
			$context['board_href'] = $scripturl . '?board=' . $board . '.' . $this->_req->query->start;
			$context['start'] = $this->_req->query->start;
			$context['page_title'] = $txt['notifications'];
			$context['sub_template'] = 'notify_board';

			return;
		}
		// Turn the board level notification on/off?
		else
		{
			checkSession('get');

			// Turn notification on/off for this board.
			$this->_toggle_board_notification();
		}

		// Back to the board!
		redirectexit('board=' . $board . '.' . $this->_req->query->start);
	}

	/**
	 * Toggle a board notification on/off
	 */
	private function _toggle_board_notification()
	{
		global $board;

		// Our board functions are here
		require_once(SUBSDIR . '/Boards.subs.php');

		// Turn notification on/off for this board.
		setBoardNotification($this->user->id, $board, $this->_req->query->sa === 'on');
	}

	/**
	 * Turn off/on notification for a particular board.
	 *
	 * - Intended for use in XML or JSON calls
	 * - Performs the same actions as action_notifyboard but provides ajax responses
	 */
	public function action_notifyboard_api()
	{
		global $scripturl, $txt, $board, $context;

		theme()->getTemplates()->load('Xml');

		theme()->getLayers()->removeAll();
		$context['sub_template'] = 'generic_xml_buttons';

		// Permissions are an important part of anything ;).
		if ($this->user->is_guest)
		{
			theme()->getTemplates()->loadLanguageFile('Errors');
			$context['xml_data'] = array(
				'error' => 1,
				'text' => $txt['not_guests']
			);

			return;
		}

		// Have to have provided the right information
		if (!allowedTo('mark_notify') || empty($board) || empty($this->_req->query->sa))
		{
			theme()->getTemplates()->loadLanguageFile('Errors');
			$context['xml_data'] = array(
				'error' => 1,
				'text' => $txt['cannot_mark_notify'],
			);

			return;
		}

		// Sessions are always verified
		if (checkSession('get', '', false))
		{
			theme()->getTemplates()->loadLanguageFile('Errors');
			$context['xml_data'] = array(
				'error' => 1,
				'url' => $scripturl . '?action=notifyboard;sa=' . ($this->_req->query->sa === 'on' ? 'on' : 'off') . ';board=' . $board . '.' . $this->_req->query->start . ';' . $context['session_var'] . '=' . $context['session_id'],
			);

			return;
		}

		$this->_toggle_board_notification();

		$context['xml_data'] = array(
			'text' => $this->_req->query->sa === 'on' ? $txt['unnotify'] : $txt['notify'],
			'url' => $scripturl . '?action=notifyboard;sa=' . ($this->_req->query->sa === 'on' ? 'off' : 'on') . ';board=' . $board . '.' . $this->_req->query->start . ';' . $context['session_var'] . '=' . $context['session_id'] . ';api' . (isset($_REQUEST['json']) ? ';json' : ''),
			'confirm' => $this->_req->query->sa === 'on' ? $txt['notification_disable_board'] : $txt['notification_enable_board']
		);
	}

	/**
	 * Turn off/on unread replies subscription for a topic
	 *
	 * What it does:
	 *
	 * - Must be called with a topic specified in the URL.
	 * - The sub-action can be 'on', 'off', or nothing for what to do.
	 * - Requires the mark_any_notify permission.
	 * - Upon successful completion of action will direct user back to topic.
	 * - Accessed via ?action=unwatchtopic.
	 */
	public function action_unwatchtopic()
	{
		global $topic, $modSettings;

		is_not_guest();

		// Let's do something only if the function is enabled
		if ($this->user->is_guest === false && !empty($modSettings['enable_unwatch']))
		{
			checkSession('get');

			$this->_toggle_topic_watch();
		}

		// Back to the topic.
		redirectexit('topic=' . $topic . '.' . $this->_req->query->start);
	}

	/**
	 * Toggle a watch topic on/off
	 */
	private function _toggle_topic_watch()
	{
		global $topic;

		setTopicWatch($this->user->id, $topic, $this->_req->query->sa === 'on');
	}

	/**
	 * Turn off/on unread replies subscription for a topic
	 *
	 * - Intended for use in XML or JSON calls
	 */
	public function action_unwatchtopic_api()
	{
		global $topic, $modSettings, $txt, $context, $scripturl;

		theme()->getTemplates()->load('Xml');

		theme()->getLayers()->removeAll();
		$context['sub_template'] = 'generic_xml_buttons';

		// Sorry guests just can't do this
		if ($this->user->is_guest)
		{
			theme()->getTemplates()->loadLanguageFile('Errors');
			$context['xml_data'] = array(
				'error' => 1,
				'text' => $txt['not_guests']
			);

			return;
		}

		// Let's do something only if the function is enabled
		if (empty($modSettings['enable_unwatch']))
		{
			theme()->getTemplates()->loadLanguageFile('Errors');
			$context['xml_data'] = array(
				'error' => 1,
				'text' => $txt['feature_disabled'],
			);

			return;
		}

		// Sessions need to be validated
		if (checkSession('get', '', false))
		{
			theme()->getTemplates()->loadLanguageFile('Errors');
			$context['xml_data'] = array(
				'error' => 1,
				'url' => $scripturl . '?action=unwatchtopic;sa=' . ($this->_req->query->sa === 'on' ? 'on' : 'off') . ';topic=' . $topic . '.' . $this->_req->query->start . ';' . $context['session_var'] . '=' . $context['session_id'],
			);

			return;
		}

		$this->_toggle_topic_watch();

		$context['xml_data'] = array(
			'text' => $this->_req->query->sa === 'on' ? $txt['watch'] : $txt['unwatch'],
			'url' => $scripturl . '?action=unwatchtopic;topic=' . $context['current_topic'] . '.' . $this->_req->query->start . ';sa=' . ($this->_req->query->sa === 'on' ? 'off' : 'on') . ';' . $context['session_var'] . '=' . $context['session_id'] . ';api' . (isset($_REQUEST['json']) ? ';json' : ''),
		);
		global $user_info, $topic;

		setTopicWatch($user_info['id'], $topic, $this->_req->query->sa === 'on');
	}

	/**
	 * Accessed via the unsubscribe link provided in site emails. This will then
	 * unsubscribe the user from a board or a topic (depending on the link) without them
	 * having to login.
	 */
	public function action_unsubscribe()
	{
		// Looks like we need to unsubscribe someone
		if ($this->_validateUnsubscribeToken($member, $area, $extra))
		{
			global $user_info, $board, $topic;

			// Look away while we stuff the old ballet box, power to the people!
			$user_info['id'] = $member['id_member'];
			$this->_req->query->sa = 'off';

			switch ($area)
			{
				case 'topic':
					$topic = $extra;
					$this->_toggle_topic_notification();
				case 'board':
					$board = $extra;
					$this->_toggle_board_notification();
					break;
				case 'buddy':
				case 'likemsg':
				case 'mentionmem':
				case 'quotedmem':
				case 'rlikemsg':
					$this->_setUserNotificationArea($member['id_member'], $area, 1);
					break;
			}

			die('made it');
		}
	}

	/**
	 * Validates a supplied token and extracts the needed bits
	 *
	 * @param mixed[] $member Member info from getBasicMemberData
	 * @param string $area area they want to be removed from
	 * @param string $extra parameters needed for some areas
	 * @return bool
	 */
	private function _validateUnsubscribeToken(&$member, &$area, &$extra)
	{
		$potentialAreas = array('topic', 'board', 'buddy', 'likemsg', 'mentionmem', 'quotedmem', 'rlikemsg');

		// Token was passed and matches our expected pattern
		$token = $this->_req->getQuery('token', 'trim', '');
		$token = urldecode($token);
		if (empty($token) || preg_match('~^(\d+_[a-zA-Z0-9./]{53}_.*)$~', $token, $match) !== 1)
		{
			return false;
		}

		// Expand the token
		list ($id_member, $hash, $area, $extra, $time) = explode('_', $match[1]);
		require_once(SUBSDIR . '/Members.subs.php');

		// The area is a known one
		if (!in_array($area, $potentialAreas))
		{
			return false;
		}

		// Not so old, 2 weeks is plenty
		if (time() - $time > 60 * 60 * 24 * 14)
		{
			return false;
		}

		// Find the claimed member
		$member = getBasicMemberData((int) $id_member, array('authentication' => true));
		if (empty($member))
		{
			return false;
		}

		// Validate its this members token
		require_once(SUBSDIR . '/Notification.subs.php');

		return validateNotifierToken(
			$member['email_address'],
			$member['password_salt'],
			$area . $extra . $time,
			$hash
		);
	}

	/**
	 * Used to set a specific mention area to a new value while keeping other
	 * areas as they are.
	 *
	 * @param int $memID
	 * @param string $area buddy, likemsg, mentionmem, quotedmem, rlikemsg
	 * @param int $value 1=notify 2=immediate email 3=daily email 4=weekly email
	 */
	private function _setUserNotificationArea($memID, $area, $value)
	{
		require_once(SUBSDIR . '/Profile.subs.php');

		$to_save = array();
		foreach (getMemberNotificationsProfile($memID) as $mention => $data)
		{
			$to_save[$mention] = 0;

			// The area we are changing
			if ($mention === $area)
			{
				// Off is always an option, but if the choice is valid set it
				if (isset($data['data'][$value]))
				{
					$to_save[$mention] = (int) $value;
				}

				continue;
			}

			// Some other area, keep the existing choice
			foreach ($data['data'] as $key => $choice)
			{
				if ($choice['enabled'] === true)
				{
					$to_save[$mention] = (int) $key;

					break;
				}
			}
		}

		saveUserNotificationsPreferences($memID, $to_save);
	}
}
