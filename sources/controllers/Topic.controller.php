<?php

/**
 * This file takes care of actions on topics lock/unlock a topic, sticky/unsticky it
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
 * @version 1.0 Beta
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
		// Unless... we can printpage()
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

		// Load up the helpers
		require_once(SUBSDIR . '/Notification.subs.php');
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

		// We need this for the sendNotifications() function.
		require_once(SUBSDIR . '/Notification.subs.php');

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

	/**
	 * Format a topic to be printer friendly.
	 * Must be called with a topic specified.
	 * Accessed via ?action=topic;sa=printpage.
	 *
	 * @uses Printpage template, main sub-template.
	 * @uses print_above/print_below later without the main layer.
	 */
	public function action_printpage()
	{
		global $topic, $scripturl, $context, $user_info, $board_info, $modSettings;

		// Redirect to the boardindex if no valid topic id is provided.
		if (empty($topic))
			redirectexit();

		if (!empty($modSettings['disable_print_topic']))
		{
			unset($_REQUEST['action']);
			$context['theme_loaded'] = false;
			fatal_lang_error('feature_disabled', false);
		}

		require_once(SUBSDIR . '/Topic.subs.php');

		// Get the topic starter information.
		$topicinfo = getTopicInfo($topic, 'starter');

		$context['user']['started'] = $user_info['id'] == $topicinfo['id_member'] && !$user_info['is_guest'];

		// Whatever happens don't index this.
		$context['robot_no_index'] = true;
		$is_poll = $topicinfo['id_poll'] > 0 && $modSettings['pollMode'] == '1' && allowedTo('poll_view');

		// Redirect to the boardindex if no valid topic id is provided.
		if (empty($topicinfo))
			redirectexit();

		// @todo this code is almost the same as the one in Display.controller.php
		if ($is_poll)
		{
			loadLanguage('Post');
			require_once(SUBSDIR . '/Poll.subs.php');

			loadPollContext($topicinfo['id_poll']);
		}

		// Lets "output" all that info.
		loadTemplate('Printpage');
		$template_layers = Template_Layers::getInstance();
		$template_layers->removeAll();
		$template_layers->add('print');
		$context['sub_template'] = 'print_page';
		$context['board_name'] = $board_info['name'];
		$context['category_name'] = $board_info['cat']['name'];
		$context['poster_name'] = $topicinfo['poster_name'];
		$context['post_time'] = standardTime($topicinfo['poster_time'], false);
		$context['parent_boards'] = array();

		foreach ($board_info['parent_boards'] as $parent)
			$context['parent_boards'][] = $parent['name'];

		// Split the topics up so we can print them.
		$context['posts'] = topicMessages($topic);
		$posts_id = array_keys($context['posts']);

		if (!isset($context['topic_subject']))
			$context['topic_subject'] = $context['posts'][min($posts_id)]['subject'];

		// Fetch attachments so we can print them if asked, enabled and allowed
		if (isset($_REQUEST['images']) && !empty($modSettings['attachmentEnable']) && allowedTo('view_attachments'))
		{
			require_once(SUBSDIR . '/Topic.subs.php');
			$context['printattach'] = messagesAttachments(array_keys($context['posts']));
		}

		// Set a canonical URL for this page.
		$context['canonical_url'] = $scripturl . '?topic=' . $topic . '.0';
	}
}