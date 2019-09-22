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
 * copyright:	2011 Simple Machines (http://www.simplemachines.org)
 *
 * @version 2.0 dev
 *
 */

namespace ElkArte\Controller;

/**
 * Handles the deletion of topics, posts
 */
class RemoveTopic extends \ElkArte\AbstractController
{
	/**
	 * Hold topic information for supplied message
	 * @var array
	 */
	private $_topic_info;

	/**
	 * Pre Dispatch, called before other methods.
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
	 * @see \ElkArte\AbstractController::action_index()
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
	public function action_removetopic2()
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

		// Check if its been recycled
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
			logAction('remove', array(
				(empty($modSettings['recycle_enable']) || $modSettings['recycle_board'] != $board ? 'topic' : 'old_topic_id') => $topic,
				'subject' => $this->_topic_info['subject'],
				'member' => $this->_topic_info['id_member_started'],
				'board' => $board)
			);
		}

		// Back to the board where the topic was removed from
		redirectexit('board=' . $board . '.0');
	}

	/**
	 * Remove just a single post.
	 *
	 * What it does:
	 *  - On completion redirect to the topic or to the board.
	 *  - Accessed by ?action=deletemsg
	 *  - Verifies the message exists and that they can see the message
	 */
	public function action_deletemsg()
	{
		global $topic, $modSettings;

		checkSession('get');

		// This has some handy functions for topics
		require_once(SUBSDIR . '/Messages.subs.php');

		// Need a message to remove
		$_msg = $this->_req->getQuery('msg', 'intval', null);

		// Is $topic set?
		if (empty($topic) && isset($this->_req->query->topic))
		{
			$topic = (int) $this->_req->query->topic;
		}

		// Trying to mess around are we?
		if (empty($_msg))
		{
			redirectexit();
		}

		// Permanently removing from the recycle bin?
		removeDeleteConcurrence();

		// Load the message details
		$this->_topic_info = loadMessageDetails(
			array('t.id_member_started'),
			array('LEFT JOIN {db_prefix}topics AS t ON (m.id_topic = t.id_topic)'),
			array('message_list' => $_msg)
		);

		// Can they see the message to remove it?
		$this->_checkApproval();

		// Ensure they can do this
		$this->_verifyDeletePermissions();

		// Do the removal, track if we removed the entire topic so we redirect back to the board.
		$remover = new \ElkArte\MessagesDelete($modSettings['recycle_enable'], $modSettings['recycle_board']);
		$full_topic = $remover->removeMessage($_msg);

		$this->_redirectBack($full_topic);
	}

	/**
	 * Move back a topic or post from the recycle board to its original board.
	 *
	 * What it does:
	 *
	 * - Merges back the posts to the original as necessary.
	 * - Accessed by ?action=restoretopic
	 */
	public function action_restoretopic()
	{
		global $modSettings;

		// Check session.
		checkSession('get');

		// Is recycled board enabled?
		if (empty($modSettings['recycle_enable']))
		{
			throw new \ElkArte\Exceptions\Exception('restored_disabled', 'critical');
		}

		// Can we be in here?
		isAllowedTo('move_any', $modSettings['recycle_board']);

		$restorer = new \ElkArte\MessagesDelete($modSettings['recycle_enable'], $modSettings['recycle_board']);

		// Restoring messages?
		if (!empty($this->_req->query->msgs))
		{
			$actioned_messages = $restorer->restoreMessages(array_map('intval', explode(',', $this->_req->query->msgs)));
		}

		// Now any topics?
		if (!empty($this->_req->query->topics))
		{
			$topics_to_restore = array_map('intval', explode(',', $this->_req->query->topics));
			$restorer->restoreTopics($topics_to_restore);
		}

		$restorer->doRestore();

		// Didn't find some things?
		if ($restorer->unfoundRestoreMessages())
		{
			throw new \ElkArte\Exceptions\Exception('restore_not_found', false, array('<ul><li>' . implode('</li><li>', $restorer->unfoundRestoreMessages(true)) . '</li></ul>'));
		}

		// Lets send them back somewhere that may make sense
		if (isset($actioned_messages) && count($actioned_messages) === 1 && empty($topics_to_restore))
		{
			reset($actioned_messages);
			redirectexit('topic=' . key($actioned_messages));
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

	/**
	 * Verifies the user has the permissions needed to remove a message
	 *
	 * - @uses isAllowedTo() which will end processing if user lacks proper permissions.
	 */
	private function _verifyDeletePermissions()
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
				throw new \ElkArte\Exceptions\Exception('modify_post_time_passed', false);
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
	 * @throws \ElkArte\Exceptions\Exception
	 */
	private function _redirectBack($full_topic)
	{
		global $topic, $board;

		// We want to redirect back to recent action.
		if (isset($this->_req->query->recent))
		{
			redirectexit('action=recent');
		}
		// Back to profile
		elseif (isset($this->_req->query->profile, $this->_req->query->start, $this->_req->query->u))
		{
			redirectexit('action=profile;u=' . $this->_req->query->u . ';area=showposts;start=' . $this->_req->query->start);
		}
		// Back to the board if the topic was removed
		elseif ($full_topic)
		{
			redirectexit('board=' . $board . '.0');
		}
		// Back to the topic where the message was removed
		else
		{
			redirectexit('topic=' . $topic . '.' . $this->_req->query->start);
		}
	}

	/**
	 * Verifies the user has permissions to remove an unapproved message/topic
	 */
	private function _checkApproval()
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
}
