<?php

/**
 * The contents of this file handle the deletion of topics, posts, and related
 * paraphernalia.
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
 * @version 1.0.3
 *
 */

if (!defined('ELK'))
	die('No access...');

/**
 * Remove Topic Controller
 */
class RemoveTopic_Controller extends Action_Controller
{
	/**
	 * Intended entry point for this class.
	 *
	 * @see Action_Controller::action_index()
	 */
	public function action_index()
	{
		// call the right method
	}

	/**
	 * Completely remove an entire topic.
	 * Redirects to the board when completed.
	 * Accessed by ?action=removetopic2
	 */
	public function action_removetopic2()
	{
		global $user_info, $topic, $board, $modSettings;

		// Make sure they aren't being lead around by someone. (:@)
		checkSession('get');

		// This file needs to be included for sendNotifications().
		require_once(SUBSDIR . '/Notification.subs.php');

		// This needs to be included for all the topic db functions
		require_once(SUBSDIR . '/Topic.subs.php');

		// Trying to fool us around, are we?
		if (empty($topic))
			redirectexit();

		removeDeleteConcurrence();

		$topic_info = getTopicInfo($topic, 'message');

		if ($topic_info['id_member_started'] == $user_info['id'] && !allowedTo('remove_any'))
			isAllowedTo('remove_own');
		else
			isAllowedTo('remove_any');

		// Can they see the topic?
		if ($modSettings['postmod_active'] && !$topic_info['approved'] && $topic_info['id_member_started'] != $user_info['id'])
			isAllowedTo('approve_posts');

		// Notify people that this topic has been removed.
		sendNotifications($topic, 'remove');

		removeTopics($topic);

		// Note, only log topic ID in native form if it's not gone forever.
		if (allowedTo('remove_any') || (allowedTo('remove_own') && $topic_info['id_member_started'] == $user_info['id']))
		{
			logAction('remove', array(
				(empty($modSettings['recycle_enable']) || $modSettings['recycle_board'] != $board ? 'topic' : 'old_topic_id') => $topic,
				'subject' => $topic_info['subject'],
				'member' => $topic_info['id_member_started'],
				'board' => $board)
			);
		}

		redirectexit('board=' . $board . '.0');
	}

	/**
	 * Remove just a single post.
	 * On completion redirect to the topic or to the board.
	 * Accessed by ?action=deletemsg
	 */
	public function action_deletemsg()
	{
		global $user_info, $topic, $board, $modSettings;

		checkSession('get');

		// This has some handy functions for topics
		require_once(SUBSDIR . '/Topic.subs.php');
		require_once(SUBSDIR . '/Messages.subs.php');

		$_REQUEST['msg'] = (int) $_REQUEST['msg'];

		// Is $topic set?
		if (empty($topic) && isset($_REQUEST['topic']))
			$topic = (int) $_REQUEST['topic'];

		removeDeleteConcurrence();

		$topic_info = loadMessageDetails(array('t.id_member_started'), array('LEFT JOIN {db_prefix}topics AS t ON (m.id_topic = t.id_topic)'), array('message_list' => $_REQUEST['msg']));

		// Verify they can see this!
		if ($modSettings['postmod_active'] && !$topic_info['approved'] && !empty($topic_info['id_member']) && $topic_info['id_member'] != $user_info['id'])
			isAllowedTo('approve_posts');

		if ($topic_info['id_member'] == $user_info['id'])
		{
			if (!allowedTo('delete_own'))
			{
				if ($topic_info['id_member_started'] == $user_info['id'] && !allowedTo('delete_any'))
					isAllowedTo('delete_replies');
				elseif (!allowedTo('delete_any'))
					isAllowedTo('delete_own');
			}
			elseif (!allowedTo('delete_any') && ($topic_info['id_member_started'] != $user_info['id'] || !allowedTo('delete_replies')) && !empty($modSettings['edit_disable_time']) && $topic_info['poster_time'] + $modSettings['edit_disable_time'] * 60 < time())
				Errors::instance()->fatal_lang_error('modify_post_time_passed', false);
		}
		elseif ($topic_info['id_member_started'] == $user_info['id'] && !allowedTo('delete_any'))
			isAllowedTo('delete_replies');
		else
			isAllowedTo('delete_any');

		// If the full topic was removed go back to the board.
		$remover = new MessagesDelete($modSettings['recycle_enable'], $modSettings['recycle_board']);
		$full_topic = $remover->removeMessage($_REQUEST['msg']);

		// We want to redirect back to recent action.
		if (isset($_REQUEST['recent']))
			redirectexit('action=recent');
		elseif (isset($_REQUEST['profile'], $_REQUEST['start'], $_REQUEST['u']))
			redirectexit('action=profile;u=' . $_REQUEST['u'] . ';area=showposts;start=' . $_REQUEST['start']);
		elseif ($full_topic)
			redirectexit('board=' . $board . '.0');
		else
			redirectexit('topic=' . $topic . '.' . $_REQUEST['start']);
	}

	/**
	 * Move back a topic or post from the recycle board to its original board.
	 * Merges back the posts to the original as necessary.
	 * Accessed by ?action=restoretopic
	 */
	public function action_restoretopic()
	{
		global $modSettings;

		// Check session.
		checkSession('get');

		// Is recycled board enabled?
		if (empty($modSettings['recycle_enable']))
			Errors::instance()->fatal_lang_error('restored_disabled', 'critical');

		// Can we be in here?
		isAllowedTo('move_any', $modSettings['recycle_board']);

		// We need this file.
		require_once(SUBSDIR . '/Topic.subs.php');

		$restorer = new MessagesDelete($modSettings['recycle_enable'], $modSettings['recycle_board']);

		// Restoring messages?
		if (!empty($_REQUEST['msgs']))
			$restorer->restoreMessages(explode(',', $_REQUEST['msgs']));

		// Now any topics?
		if (!empty($_REQUEST['topics']))
			$restorer->restoreTopics(explode(',', $_REQUEST['topics']));

		$restorer->doRestore();

		// Didn't find some things?
		if ($restorer->unfoundRestoreMessages())
			Errors::instance()->fatal_lang_error('restore_not_found', false, array('<ul style="margin-top: 0px;"><li>' . implode('</li><li>', $restorer->unfoundRestoreMessages(true)) . '</li></ul>'));

		// Lets send them back somewhere that may make sense
		if (count($actioned_messages) == 1 && empty($topics_to_restore))
		{
			reset($actioned_messages);
			redirectexit('topic=' . key($actioned_messages));
		}
		elseif (count($topics_to_restore) == 1)
			redirectexit('topic=' . $topics_to_restore[0]);
		else
			redirectexit();
	}

	/**
	 * Try to determine if the topic has already been deleted by another user.
	 *
	 * @deprecated since 1.1
	 */
	public function removeDeleteConcurrence()
	{
		removeDeleteConcurrence();
	}
}