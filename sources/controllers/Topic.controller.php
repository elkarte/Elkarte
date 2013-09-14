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
		global $topic, $txt, $scripturl, $context, $user_info;
		global $board_info, $modSettings, $settings;

		// Redirect to the boardindex if no valid topic id is provided.
		if (empty($topic))
			redirectexit();

		if (!empty($modSettings['disable_print_topic']))
		{
			unset($_REQUEST['action']);
			$context['theme_loaded'] = false;
			fatal_lang_error('feature_disabled', false);
		}

		// Whatever happens don't index this.
		$context['robot_no_index'] = true;

		// Get the topic starter information.
		$row = pollStarters($topic);
		// Redirect to the boardindex if no valid topic id is provided.
		if (empty($row))
			redirectexit();

		if (!empty($row['id_poll']))
		{
			loadLanguage('Post');
			require_once(SUBSDIR . '/Poll.subs.php');

			// Get the question and if it's locked.
			$pollinfo = pollInfo($row['id_poll']);

			// Get all the options, and calculate the total votes.
			$pollOptions = pollOptionsForMember();
			$realtotal = 0;
			$pollinfo['has_voted'] = false;
			foreach ($pollOptions as $row)
			{
				$realtotal += $row['votes'];
				$pollinfo['has_voted'] |= $row['voted_this'] != -1;
			}

			// If this is a guest we need to do our best to work out if they have voted, and what they voted for.
			if ($user_info['is_guest'] && $pollinfo['guest_vote'] && allowedTo('poll_vote'))
			{
				if (!empty($_COOKIE['guest_poll_vote']) && preg_match('~^[0-9,;]+$~', $_COOKIE['guest_poll_vote']) && strpos($_COOKIE['guest_poll_vote'], ';' . $row['id_poll'] . ',') !== false)
				{
					// ;id,timestamp,[vote,vote...]; etc
					$guestinfo = explode(';', $_COOKIE['guest_poll_vote']);

					// Find the poll we're after.
					foreach ($guestinfo as $i => $guestvoted)
					{
						$guestvoted = explode(',', $guestvoted);
						if ($guestvoted[0] == $row['id_poll'])
							break;
					}

					// Has the poll been reset since guest voted?
					if ($pollinfo['reset_poll'] > $guestvoted[1])
					{
						// Remove the poll info from the cookie to allow guest to vote again
						unset($guestinfo[$i]);
						if (!empty($guestinfo))
							$_COOKIE['guest_poll_vote'] = ';' . implode(';', $guestinfo);
						else
							unset($_COOKIE['guest_poll_vote']);
					}
					else
					{
						// What did they vote for?
						unset($guestvoted[0], $guestvoted[1]);
						foreach ($pollOptions as $choice => $details)
						{
							$pollOptions[$choice]['voted_this'] = in_array($choice, $guestvoted) ? 1 : -1;
							$pollinfo['has_voted'] |= $pollOptions[$choice]['voted_this'] != -1;
						}
						unset($choice, $details, $guestvoted);
					}
					unset($guestinfo, $guestvoted, $i);
				}
			}

			$context['user']['started'] = $user_info['id'] == $row['id_member'] && !$user_info['is_guest'];

			// Set up the basic poll information.
			$context['poll'] = array(
				'id' => $row['id_poll'],
				'image' => 'normal_' . (empty($pollinfo['voting_locked']) ? 'poll' : 'locked_poll'),
				'question' => parse_bbc($pollinfo['question']),
				'total_votes' => $pollinfo['total'],
				'change_vote' => !empty($pollinfo['change_vote']),
				'is_locked' => !empty($pollinfo['voting_locked']),
				'options' => array(),
				'lock' => allowedTo('poll_lock_any') || ($context['user']['started'] && allowedTo('poll_lock_own')),
				'edit' => allowedTo('poll_edit_any') || ($context['user']['started'] && allowedTo('poll_edit_own')),
				'allowed_warning' => $pollinfo['max_votes'] > 1 ? sprintf($txt['poll_options6'], min(count($pollOptions), $pollinfo['max_votes'])) : '',
				'is_expired' => !empty($pollinfo['expire_time']) && $pollinfo['expire_time'] < time(),
				'expire_time' => !empty($pollinfo['expire_time']) ? standardTime($pollinfo['expire_time']) : 0,
				'has_voted' => !empty($pollinfo['has_voted']),
				'starter' => array(
					'id' => $pollinfo['id_member'],
					'name' => $row['poster_name'],
					'href' => $pollinfo['id_member'] == 0 ? '' : $scripturl . '?action=profile;u=' . $pollinfo['id_member'],
					'link' => $pollinfo['id_member'] == 0 ? $row['poster_name'] : '<a href="' . $scripturl . '?action=profile;u=' . $pollinfo['id_member'] . '">' . $row['poster_name'] . '</a>'
				)
			);

			// Make the lock and edit permissions defined above more directly accessible.
			$context['allow_lock_poll'] = $context['poll']['lock'];
			$context['allow_edit_poll'] = $context['poll']['edit'];

			// You're allowed to view the results if:
			// 1. you're just a super-nice-guy, or
			// 2. anyone can see them (hide_results == 0), or
			// 3. you can see them after you voted (hide_results == 1), or
			// 4. you've waited long enough for the poll to expire. (whether hide_results is 1 or 2.)
			$context['allow_poll_view'] = allowedTo('moderate_board') || $pollinfo['hide_results'] == 0 || ($pollinfo['hide_results'] == 1 && $context['poll']['has_voted']) || $context['poll']['is_expired'];

			// Calculate the percentages and bar lengths...
			$divisor = $realtotal == 0 ? 1 : $realtotal;

			// Determine if a decimal point is needed in order for the options to add to 100%.
			$precision = $realtotal == 100 ? 0 : 1;

			// Now look through each option, and...
			foreach ($pollOptions as $i => $option)
			{
				// First calculate the percentage, and then the width of the bar...
				$bar = round(($option['votes'] * 100) / $divisor, $precision);
				$barWide = $bar == 0 ? 1 : floor(($bar * 8) / 3);

				// Now add it to the poll's contextual theme data.
				$context['poll']['options'][$i] = array(
					'id' => 'options-' . $i,
					'percent' => $bar,
					'votes' => $option['votes'],
					'voted_this' => $option['voted_this'] != -1,
					'bar' => '<span style="white-space: nowrap;"><img src="' . $settings['images_url'] . '/poll_' . ($context['right_to_left'] ? 'right' : 'left') . '.png" alt="" /><img src="' . $settings['images_url'] . '/poll_middle.png" style="width:' . $barWide . 'px; height: 12px" alt="-" /><img src="' . $settings['images_url'] . '/poll_' . ($context['right_to_left'] ? 'left' : 'right') . '.png" alt="" /></span>',
					// Note: IE < 8 requires us to set a width on the container, too.
					'bar_ndt' => $bar > 0 ? '<div class="bar" style="width: ' . ($bar * 3.5 + 4) . 'px;"><div style="width: ' . $bar * 3.5 . 'px;"></div></div>' : '',
					'bar_width' => $barWide,
					'option' => parse_bbc($option['label']),
					'vote_button' => '<input type="' . ($pollinfo['max_votes'] > 1 ? 'checkbox' : 'radio') . '" name="options[]" id="options-' . $i . '" value="' . $i . '" class="input_' . ($pollinfo['max_votes'] > 1 ? 'check' : 'radio') . '" />'
				);
			}
		}

		// Lets "output" all that info.
		loadTemplate('Printpage');
		Template_Layers::getInstance()->removeAll();
		Template_Layers::getInstance()->add('print');
		$context['board_name'] = $board_info['name'];
		$context['category_name'] = $board_info['cat']['name'];
		$context['poster_name'] = $row['poster_name'];
		$context['post_time'] = relativeTime($row['poster_time'], false);
		$context['parent_boards'] = array();
		foreach ($board_info['parent_boards'] as $parent)
			$context['parent_boards'][] = $parent['name'];

		// Split the topics up so we can print them.
		$context['posts'] = topicMessages($topic);

		if (!isset($context['topic_subject']))
			$context['topic_subject'] = $context['posts'][count($context['posts']) - 1]['subject'];

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