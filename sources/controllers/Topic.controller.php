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
 * This file takes care of actions on topics:
 * lock/unlock a topic, sticky/unsticky it
 *
 */

if (!defined('ELK'))
	die('No access...');

/**
 * Topics Controller
 */
class Topic_Controller extends Action_Controller
{
	/**
	 * Entry point for this class (by default).
	 *
	 * @see Action_Controller::action_index()
	 */
	public function action_index()
	{
		// Call the right method, if it ain't done yet.
		// this is done by the dispatcher, so lets leave it alone...
		// we don't want to assume what it means if the user doesn't
		// send us a ?sa=, do we? (lock topics out of nowhere?)
	}

	/**
	 * Locks a topic... either by way of a moderator or the topic starter.
	 * What this does:
	 *  - locks a topic, toggles between locked/unlocked/admin locked.
	 *  - only admins can unlock topics locked by other admins.
	 *  - requires the lock_own or lock_any permission.
	 *  - logs the action to the moderator log.
	 *  - returns to the topic after it is done.
	 *  - it is accessed via ?action=topic;sa=lock.
	*/
	public function action_lock()
	{
		global $topic, $user_info, $board;

		// Just quit if there's no topic to lock.
		if (empty($topic))
			fatal_lang_error('not_a_topic', false);

		checkSession('get');

		// Get subs/Post.subs.php for sendNotifications.
		require_once(SUBSDIR . '/Post.subs.php');
		require_once(SUBSDIR . '/Topic.subs.php');

		// Find out who started the topic and its lock status
		list ($starter, $locked) = topicStatus($topic);

		// Can you lock topics here, mister?
		$user_lock = !allowedTo('lock_any');

		if ($user_lock && $starter == $user_info['id'])
			isAllowedTo('lock_own');
		else
			isAllowedTo('lock_any');

		// Locking with high privileges.
		if ($locked == '0' && !$user_lock)
			$locked = '1';
		// Locking with low privileges.
		elseif ($locked == '0')
			$locked = '2';
		// Unlocking - make sure you don't unlock what you can't.
		elseif ($locked == '2' || ($locked == '1' && !$user_lock))
			$locked = '0';
		// You cannot unlock this!
		else
			fatal_lang_error('locked_by_admin', 'user');

		// Lock the topic!
		setTopicAttribute($topic, array('locked' => $locked));

		// If they are allowed a "moderator" permission, log it in the moderator log.
		if (!$user_lock)
			logAction($locked ? 'lock' : 'unlock', array('topic' => $topic, 'board' => $board));

		// Notify people that this topic has been locked?
		sendNotifications($topic, empty($locked) ? 'unlock' : 'lock');

		// Back to the topic!
		redirectexit('topic=' . $topic . '.' . $_REQUEST['start']);
	}

	/**
	 * Sticky a topic.
	 * Can't be done by topic starters - that would be annoying!
	 * What this does:
	 *  - stickies a topic - toggles between sticky and normal.
	 *  - requires the make_sticky permission.
	 *  - adds an entry to the moderator log.
	 *  - when done, sends the user back to the topic.
	 *  - accessed via ?action=topic;sa=sticky.
	 */
	public function action_sticky()
	{
		global $modSettings, $topic, $board;

		// Make sure the user can sticky it, and they are stickying *something*.
		isAllowedTo('make_sticky');

		// You shouldn't be able to (un)sticky a topic if the setting is disabled.
		if (empty($modSettings['enableStickyTopics']))
			fatal_lang_error('cannot_make_sticky', false);

		// You can't sticky a board or something!
		if (empty($topic))
			fatal_lang_error('not_a_topic', false);

		checkSession('get');

		// We need subs/Post.subs.php for the sendNotifications() function.
		require_once(SUBSDIR . '/Post.subs.php');
		// And Topic subs for topic attributes.
		require_once(SUBSDIR . '/Topic.subs.php');

		// Is this topic already stickied, or no?
		$is_sticky = topicAttribute($topic, 'sticky');

		// Toggle the sticky value.
		setTopicAttribute($topic, array('sticky' => (empty($is_sticky) ? 1 : 0)));

		// Log this sticky action - always a moderator thing.
		logAction(empty($is_sticky) ? 'sticky' : 'unsticky', array('topic' => $topic, 'board' => $board));

		// Notify people that this topic has been stickied?
		if (empty($is_sticky))
			sendNotifications($topic, 'sticky');

		// Take them back to the now stickied topic.
		redirectexit('topic=' . $topic . '.' . $_REQUEST['start']);
	}
}