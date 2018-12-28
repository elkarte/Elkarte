<?php

/**
 * This file takes care of actions on topics including
 * - lock/unlock a topic,
 * - sticky (pin) /unsticky (unpin) it
 * - printing
 *
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * This file contains code covered by:
 * copyright:	2011 Simple Machines (http://www.simplemachines.org)
 * license:		BSD, See included LICENSE.TXT for terms and conditions.
 *
 * @version 2.0 dev
 *
 */

namespace ElkArte\Controller;

/**
 * Handles various topic actions, lock/unlock, sticky (pin) /unsticky (unpin), printing
 */
class Topic extends \ElkArte\AbstractController
{
	/**
	 * Entry point for this class (by default).
	 *
	 * @see \ElkArte\AbstractController::action_index()
	 */
	public function action_index()
	{
		global $topic;

		// Call the right method, if it is not done yet.
		//
		// This is done by the dispatcher, so lets leave it alone...
		// We don't want to assume what it means if the user doesn't
		// send us a ?sa=, do we? (lock topics out of nowhere?)
		// Unless... we can printpage()

		// Without anything it throws an error, so redirect somewhere
		if (!empty($topic))
			redirectexit('topic=' . $topic . '.0');
		else
			redirectexit();
	}

	/**
	 * Locks a topic... either by way of a moderator or the topic starter.
	 *
	 * What this does:
	 *  - Locks a topic, toggles between locked/unlocked/admin locked.
	 *  - Only admins can unlock topics locked by other admins.
	 *  - Requires the lock_own or lock_any permission.
	 *  - Logs the action to the moderator log.
	 *  - Returns to the topic after it is done.
	 *  - It is accessed via ?action=topic;sa=lock.
	 */
	public function action_lock()
	{
		global $topic, $user_info, $board;

		// Just quit if there's no topic to lock.
		if (empty($topic))
			throw new \ElkArte\Exceptions\Exception('not_a_topic', false);

		checkSession('get');

		// Load up the helpers
		require_once(SUBSDIR . '/Notification.subs.php');
		require_once(SUBSDIR . '/Topic.subs.php');

		// Find out who started the topic and its current lock status
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
			throw new \ElkArte\Exceptions\Exception('locked_by_admin', 'user');

		// Lock the topic!
		setTopicAttribute($topic, array('locked' => $locked));

		// If they are allowed a "moderator" permission, log it in the moderator log.
		if (!$user_lock)
			logAction($locked ? 'lock' : 'unlock', array('topic' => $topic, 'board' => $board));

		// Notify people that this topic has been locked?
		sendNotifications($topic, empty($locked) ? 'unlock' : 'lock');

		// Back to the topic!
		redirectexit('topic=' . $topic . '.' . $this->_req->post->start);
	}

	/**
	 * Sticky a topic.
	 *
	 * Can't be done by topic starters - that would be annoying!
	 *
	 * What this does:
	 *  - Stickies a topic - toggles between sticky and normal.
	 *  - Requires the make_sticky permission.
	 *  - Adds an entry to the moderator log.
	 *  - When done, sends the user back to the topic.
	 *  - Accessed via ?action=topic;sa=sticky.
	 */
	public function action_sticky()
	{
		global $topic, $board;

		// Make sure the user can sticky it, and they are stickying *something*.
		isAllowedTo('make_sticky');

		// You can't sticky a board or something!
		if (empty($topic))
			throw new \ElkArte\Exceptions\Exception('not_a_topic', false);

		checkSession('get');

		// We need this for the sendNotifications() function.
		require_once(SUBSDIR . '/Notification.subs.php');

		// And Topic subs for topic attributes.
		require_once(SUBSDIR . '/Topic.subs.php');

		// Is this topic already stickied, or no?
		$sticky = topicAttribute($topic, 'is_sticky');
		$is_sticky = $sticky['is_sticky'];

		// Toggle the sticky value.
		setTopicAttribute($topic, array('is_sticky' => (empty($is_sticky) ? 1 : 0)));

		// Log this sticky action - always a moderator thing.
		logAction(empty($is_sticky) ? 'sticky' : 'unsticky', array('topic' => $topic, 'board' => $board));

		// Notify people that this topic has been stickied?
		if (empty($is_sticky))
			sendNotifications($topic, 'sticky');

		// Take them back to the now stickied topic.
		redirectexit('topic=' . $topic . '.' . $this->_req->post->start);
	}

	/**
	 * Format a topic to be printer friendly.
	 *
	 * What id does:
	 * - Must be called with a topic specified.
	 * - Accessed via ?action=topic;sa=printpage.
	 *
	 * @uses template_print_page() in Printpage.template,
	 * @uses template_print_above() later without the main layer.
	 * @uses template_print_below() without the main layer
	 */
	public function action_printpage()
	{
		global $topic, $scripturl, $context, $user_info, $board_info, $modSettings;

		// Redirect to the boardindex if no valid topic id is provided.
		if (empty($topic))
			redirectexit();

		// Its not enabled, give them the boot
		if (!empty($modSettings['disable_print_topic']))
		{
			unset($this->_req->query->action);
			$context['theme_loaded'] = false;
			throw new \ElkArte\Exceptions\Exception('feature_disabled', false);
		}

		// Clean out the template layers
		$template_layers = theme()->getLayers();
		$template_layers->removeAll();

		// Get the topic starter information.
		require_once(SUBSDIR . '/Topic.subs.php');
		$topicinfo = getTopicInfo($topic, 'starter');

		// Redirect to the boardindex if no valid topic id is provided.
		if (empty($topicinfo))
			redirectexit();

		$context['user']['started'] = $user_info['id'] == $topicinfo['id_member'] && !$user_info['is_guest'];

		// Whatever happens don't index this.
		$context['robot_no_index'] = true;

		// @todo this code is almost the same as the one in Display.controller.php
		if ($topicinfo['id_poll'] > 0 && !empty($modSettings['pollMode']) && allowedTo('poll_view'))
		{
			theme()->getTemplates()->loadLanguageFile('Post');
			require_once(SUBSDIR . '/Poll.subs.php');

			loadPollContext($topicinfo['id_poll']);
			$template_layers->addAfter('print_poll', 'print');
		}

		// Lets "output" all that info.
		theme()->getTemplates()->load('Printpage');
		$template_layers->add('print');
		$context['sub_template'] = 'print_page';
		$context['board_name'] = $board_info['name'];
		$context['category_name'] = $board_info['cat']['name'];
		$context['poster_name'] = $topicinfo['poster_name'];
		$context['post_time'] = standardTime($topicinfo['poster_time'], false);
		$context['parent_boards'] = array();

		foreach ($board_info['parent_boards'] as $parent)
		{
			$context['parent_boards'][] = $parent['name'];
		}

		// Split the topics up so we can print them.
		$context['posts'] = topicMessages($topic);
		$posts_id = array_keys($context['posts']);

		if (!isset($context['topic_subject']))
			$context['topic_subject'] = $context['posts'][min($posts_id)]['subject'];

		// Fetch attachments so we can print them if asked, enabled and allowed
		if (isset($this->_req->query->images) && !empty($modSettings['attachmentEnable']) && allowedTo('view_attachments'))
		{
			require_once(SUBSDIR . '/Topic.subs.php');
			$context['printattach'] = messagesAttachments(array_keys($context['posts']));
			$context['viewing_attach'] = true;
		}

		// Set a canonical URL for this page.
		$context['canonical_url'] = $scripturl . '?topic=' . $topic . '.0';
		$context['view_attach_mode'] = array(
			'text' => $scripturl . '?action=topic;sa=printpage;topic=' . $topic . '.0',
			'images' => $scripturl . '?action=topic;sa=printpage;topic=' . $topic . '.0;images',
		);
	}
}
