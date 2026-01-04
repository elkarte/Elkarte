<?php

/**
 * The contents of this file handle the deletion of topics, posts, and related
 * paraphernalia.
 *
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * This file contains code covered by:
 * copyright: 2011 Simple Machines (http://www.simplemachines.org)
 *
 * @version 2.0 Beta 1
 *
 */

namespace ElkArte\Controller;

use ElkArte\AbstractController;
use ElkArte\Exceptions\Exception;
use ElkArte\MessagesDelete;

/**
 * Handles the deletion of topics, posts
 */
class RemoveTopic extends AbstractController
{
	/** @var array Hold topic information for supplied message */
	private $_topic_info;

	/**
	 * Pre-dispatch, called before other methods.
	 */
	public function pre_dispatch()
	{
		// This has some handy functions for topics
		require_once(SUBSDIR . '/Topic.subs.php');
	}

	/**
	 * Intended entry point for this class.
	 *
	 * All actions are directly called from other points, so there
	 * is currently nothing to action in this method.
	 *
	 * @see AbstractController::action_index
	 */
	public function action_index()
	{
		// call the right method
	}

	/**
	 * Completely remove an entire topic.
	 *
	 * What it does:
	 *
	 * - Redirects to the board when completed.
	 * - Accessed by ?action=removetopic2
	 * - Removes a topic if it has not already been removed.
	 */
	public function action_removetopic2(): void
	{
		global $topic, $board, $modSettings;

		// Make sure they aren't being lead around by someone. (:@)
		checkSession('get');

		// Trying to fool us around, are we?
		if (empty($topic))
		{
			redirectexit();
		}

		// This file needs to be included for sendNotifications().
		require_once(SUBSDIR . '/Notification.subs.php');

		// Check if it's been recycled
		removeDeleteConcurrence();

		$this->_topic_info = getTopicInfo($topic, 'message');

		// Can you remove your own or any topic
		if ($this->_topic_info['id_member_started'] == $this->user->id && !allowedTo('remove_any'))
		{
			isAllowedTo('remove_own');
		}
		else
		{
			isAllowedTo('remove_any');
		}

		// Can they see the topic to remove it?
		$this->_checkApproval();

		// Notify people that this topic has been removed.
		sendNotifications($topic, 'remove');

		// Remove the topic
		removeTopics($topic);

		// Note, only log topic ID in native form if it's not gone forever.
		if (allowedTo('remove_any') || (allowedTo('remove_own') && $this->_topic_info['id_member_started'] == $this->user->id))
		{
			logAction('remove', [
					(empty($modSettings['recycle_enable']) || $modSettings['recycle_board'] != $board ? 'topic' : 'old_topic_id') => $topic,
					'subject' => $this->_topic_info['subject'],
					'member' => $this->_topic_info['id_member_started'],
					'board' => $board]
			);
		}

		// Back to the board where the topic was removed from
		redirectexit('board=' . $board . '.0');
	}

	/**
	 * Verifies the user has permissions to remove an unapproved message/topic
	 */
	private function _checkApproval(): void
	{
		global $modSettings;

		// Verify they can see this!
		if ($modSettings['postmod_active']
			&& !$this->_topic_info['approved']
			&& !empty($this->_topic_info['id_member'])
			&& $this->_topic_info['id_member'] != $this->user->id)
		{
			isAllowedTo('approve_posts');
		}
	}

	/**
	 * Remove just a single post.
	 *
	 * What it does:
	 *  - On completion redirect to the topic or to the board.
	 *  - Accessed by ?action=deletemsg
	 *  - Verifies the message exists and that they can see the message
	 */
	public function action_deletemsg(): void
	{
		global $topic, $modSettings;

		checkSession('get');

		// This has some handy functions for topics
		require_once(SUBSDIR . '/Messages.subs.php');

		// Need a message to remove
		$_msg = $this->_req->getQuery('msg', 'intval');

		// Is $topic set?
		if (empty($topic) && $this->_req->hasQuery('topic'))
		{
			$topic = $this->_req->getQuery('topic', 'intval', 0);
		}

		// Trying to mess around, are we?
		if (empty($_msg))
		{
			redirectexit();
		}

		// Permanently removing from the recycle bin?
		removeDeleteConcurrence();

		// Load the message details
		$this->_topic_info = loadMessageDetails(
			['t.id_member_started'],
			['LEFT JOIN {db_prefix}topics AS t ON (m.id_topic = t.id_topic)'],
			['message_list' => $_msg]
		);

		// Can they see the message to remove it?
		$this->_checkApproval();

		// Ensure they can do this
		$this->_verifyDeletePermissions();

		// Do the removal, track if we removed the entire topic, so we redirect back to the board.
		$remover = new MessagesDelete($modSettings['recycle_enable'], $modSettings['recycle_board']);
		$full_topic = $remover->removeMessage($_msg);

		$this->_redirectBack($full_topic);
	}

	/**
	 * Verifies the user has the permissions needed to remove a message
	 *
	 * - @uses isAllowedTo() which will end processing if user lacks proper permissions.
	 */
	private function _verifyDeletePermissions(): void
	{
		global $modSettings;

		if ($this->_topic_info['id_member'] == $this->user->id)
		{
			// Are you allowed to delete it
			if (!allowedTo('delete_own'))
			{
				if ($this->_topic_info['id_member_started'] == $this->user->id && !allowedTo('delete_any'))
				{
					isAllowedTo('delete_replies');
				}
				elseif (!allowedTo('delete_any'))
				{
					isAllowedTo('delete_own');
				}
			}
			elseif (!allowedTo('delete_any')
				&& ($this->_topic_info['id_member_started'] != $this->user->id || !allowedTo('delete_replies'))
				&& !empty($modSettings['edit_disable_time'])
				&& $this->_topic_info['poster_time'] + $modSettings['edit_disable_time'] * 60 < time())
			{
				throw new Exception('modify_post_time_passed', false);
			}
		}
		elseif ($this->_topic_info['id_member_started'] == $this->user->id && !allowedTo('delete_any'))
		{
			isAllowedTo('delete_replies');
		}
		else
		{
			isAllowedTo('delete_any');
		}
	}

	/**
	 * After deleting a message(s) returns the user to the best possible location
	 *
	 * @param bool $full_topic if the entire topic was removed
	 */
	private function _redirectBack($full_topic): void
	{
		global $topic, $board;

		// We want to redirect back to recent action.
		if ($this->_req->hasQuery('recent'))
		{
			redirectexit('action=recent');
		}
		// Back to profile
		elseif ($this->_req->hasQuery('profile') && $this->_req->hasQuery('start') && $this->_req->hasQuery('u'))
		{
			$u = $this->_req->getQuery('u', 'intval', 0);
			$start = $this->_req->getQuery('start', 'intval', 0);
			redirectexit('action=profile;u=' . $u . ';area=showposts;start=' . $start);
		}
		// Back to the board if the topic was removed
		elseif ($full_topic)
		{
			redirectexit('board=' . $board . '.0');
		}
		// Back to the topic where the message was removed
		else
		{
			$start = $this->_req->getQuery('start', 'intval', 0);
			redirectexit('topic=' . $topic . '.' . $start);
		}
	}

	/**
	 * Move back a topic or post from the recycle board to its original board.
	 *
	 * What it does:
	 *
	 * - Merges back the posts to the original as necessary.
	 * - Accessed by ?action=restoretopic
	 */
	public function action_restoretopic(): void
	{
		global $modSettings;

		// Check session.
		checkSession('get');

		// Is recycled board enabled?
		if (empty($modSettings['recycle_enable']))
		{
			throw new Exception('restored_disabled', 'critical');
		}

		// Can we be in here?
		isAllowedTo('move_any', $modSettings['recycle_board']);

		$restorer = new MessagesDelete($modSettings['recycle_enable'], $modSettings['recycle_board']);

		// Restoring messages?
		$msgs_param = $this->_req->getQuery('msgs', 'trim|strval', '');
		if ($msgs_param !== '')
		{
			$actioned_messages = $restorer->restoreMessages(array_map('intval', explode(',', $msgs_param)));
		}

		// Now any topics?
		$topics_param = $this->_req->getQuery('topics', 'trim|strval', '');
		if ($topics_param !== '')
		{
			$topics_to_restore = array_map('intval', explode(',', $topics_param));
			$restorer->restoreTopics($topics_to_restore);
		}

		$restorer->doRestore();

		// Didn't find some things?
		if ($restorer->unfoundRestoreMessages())
		{
			throw new Exception('restore_not_found', false, ['<ul><li>' . implode('</li><li>', $restorer->unfoundRestoreMessages(true)) . '</li></ul>']);
		}

		// Let's send them back somewhere that may make sense
		if (isset($actioned_messages) && count($actioned_messages) === 1 && empty($topics_to_restore))
		{
			redirectexit('topic=' . array_key_first($actioned_messages));
		}
		elseif (count($topics_to_restore) === 1)
		{
			redirectexit('topic=' . $topics_to_restore[0]);
		}
		else
		{
			redirectexit();
		}
	}
}
