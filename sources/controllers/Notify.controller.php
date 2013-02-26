<?php

/**
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * This software is a derived product, based on:
 *
 * Simple Machines Forum (SMF)
 * copyright:	2011 Simple Machines (http://www.simplemachines.org)
 * license:  	BSD, See included LICENSE.TXT for terms and conditions.
 *
 * @version 1.0 Alpha
 *
 * This file contains just the functions that turn on and off notifications
 * to topics or boards.
 *
 */

if (!defined('ELKARTE'))
	die('No access...');

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
function action_notify()
{
	global $scripturl, $txt, $topic, $user_info, $context, $smcFunc;

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
		$context['page_title'] = $txt['notification'];

		return;
	}
	elseif ($_GET['sa'] == 'on')
	{
		checkSession('get');

		// Attempt to turn notifications on.
		setTopicNotification($user_info['id'], $topic, true);
	}
	else
	{
		checkSession('get');

		// Just turn notifications off.
		setTopicNotification($user_info['id'], $topic, false);
	}

	// Send them back to the topic.
	redirectexit('topic=' . $topic . '.' . $_REQUEST['start']);
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
function action_notifyboard()
{
	global $scripturl, $txt, $board, $user_info, $context, $smcFunc;

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
		$context['page_title'] = $txt['notification'];
		$context['sub_template'] = 'notify_board';

		return;
	}
	// Turn the board level notification on....
	elseif ($_GET['sa'] == 'on')
	{
		checkSession('get');

		// Turn notification on.  (note this just blows smoke if it's already on.)
		setBoardNotification($user_info['id'], $board, true);
	}
	// ...or off?
	else
	{
		checkSession('get');

		// Turn notification off for this board.
		setBoardNotification($user_info['id'], $board, false);
	}

	// Back to the board!
	redirectexit('board=' . $board . '.' . $_REQUEST['start']);
}

/**
 * Turn off/on unread replies subscription for a topic
 * Must be called with a topic specified in the URL.
 * The sub-action can be 'on', 'off', or nothing for what to do.
 * Requires the mark_any_notify permission.
 * Upon successful completion of action will direct user back to topic.
 * Accessed via ?action=disregardtopic.
 */
function action_disregardtopic()
{
	global $smcFunc, $user_info, $topic, $modSettings;

	// our topic functions are here
	require_once(SUBSDIR . '/Topic.subs.php');

	// Let's do something only if the function is enabled
	if (!$user_info['is_guest'] && !empty($modSettings['enable_disregard']))
	{
		checkSession('get');

		if ($_GET['sa'] === 'on')
			setTopicRegard($user_info['id'], $topic, true);
		elseif ($_GET['sa'] === 'off')
			setTopicRegard($user_info['id'], $topic, false);
	}

	// Back to the topic.
	redirectexit('topic=' . $topic . '.' . $_REQUEST['start']);
}