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

if (!defined('ELKARTE'))
	die('No access...');

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
function action_lock()
{
	global $topic, $user_info, $board, $smcFunc;

	// Just quit if there's no topic to lock.
	if (empty($topic))
		fatal_lang_error('not_a_topic', false);

	checkSession('get');

	// Get subs/Post.subs.php for sendNotifications.
	require_once(SUBSDIR . '/Post.subs.php');

	// Find out who started the topic - in case User Topic Locking is enabled.
	$request = $smcFunc['db_query']('', '
		SELECT id_member_started, locked
		FROM {db_prefix}topics
		WHERE id_topic = {int:current_topic}
		LIMIT 1',
		array(
			'current_topic' => $topic,
		)
	);
	list ($starter, $locked) = $smcFunc['db_fetch_row']($request);
	$smcFunc['db_free_result']($request);

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

	// Actually lock the topic in the database with the new value.
	$smcFunc['db_query']('', '
		UPDATE {db_prefix}topics
		SET locked = {int:locked}
		WHERE id_topic = {int:current_topic}',
		array(
			'current_topic' => $topic,
			'locked' => $locked,
		)
	);

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
function action_sticky()
{
	global $modSettings, $topic, $board, $smcFunc;

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

	// Is this topic already stickied, or no?
	$request = $smcFunc['db_query']('', '
		SELECT is_sticky
		FROM {db_prefix}topics
		WHERE id_topic = {int:current_topic}
		LIMIT 1',
		array(
			'current_topic' => $topic,
		)
	);
	list ($is_sticky) = $smcFunc['db_fetch_row']($request);
	$smcFunc['db_free_result']($request);

	// Toggle the sticky value.... pretty simple ;).
	$smcFunc['db_query']('', '
		UPDATE {db_prefix}topics
		SET is_sticky = {int:is_sticky}
		WHERE id_topic = {int:current_topic}',
		array(
			'current_topic' => $topic,
			'is_sticky' => empty($is_sticky) ? 1 : 0,
		)
	);

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
function action_printpage()
{
	global $topic, $txt, $scripturl, $context, $user_info;
	global $board_info, $smcFunc, $modSettings, $settings;

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
	$request = $smcFunc['db_query']('', '
		SELECT mem.id_member, m.poster_time, IFNULL(mem.real_name, m.poster_name) AS poster_name, t.id_poll
		FROM {db_prefix}messages AS m
			LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = m.id_member)
			LEFT JOIN {db_prefix}topics as t ON (t.id_first_msg = m.id_msg)
		WHERE m.id_topic = {int:current_topic}
		ORDER BY m.id_msg
		LIMIT 1',
		array(
			'current_topic' => $topic,
		)
	);
	// Redirect to the boardindex if no valid topic id is provided.
	if ($smcFunc['db_num_rows']($request) == 0)
		redirectexit();
	$row = $smcFunc['db_fetch_assoc']($request);
	$smcFunc['db_free_result']($request);

	if (!empty($row['id_poll']))
	{
		loadLanguage('Post');
		// Get the question and if it's locked.
		$request = $smcFunc['db_query']('', '
			SELECT
				p.question, p.voting_locked, p.hide_results, p.expire_time, p.max_votes, p.change_vote,
				p.guest_vote, p.id_member, IFNULL(mem.real_name, p.poster_name) AS poster_name, p.num_guest_voters, p.reset_poll
			FROM {db_prefix}polls AS p
				LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = p.id_member)
			WHERE p.id_poll = {int:id_poll}
			LIMIT 1',
			array(
				'id_poll' => $row['id_poll'],
			)
		);
		$pollinfo = $smcFunc['db_fetch_assoc']($request);
		$smcFunc['db_free_result']($request);

		$request = $smcFunc['db_query']('', '
			SELECT COUNT(DISTINCT id_member) AS total
			FROM {db_prefix}log_polls
			WHERE id_poll = {int:id_poll}
				AND id_member != {int:not_guest}',
			array(
				'id_poll' => $row['id_poll'],
				'not_guest' => 0,
			)
		);
		list ($pollinfo['total']) = $smcFunc['db_fetch_row']($request);
		$smcFunc['db_free_result']($request);

		// Total voters needs to include guest voters
		$pollinfo['total'] += $pollinfo['num_guest_voters'];

		// Get all the options, and calculate the total votes.
		$request = $smcFunc['db_query']('', '
			SELECT pc.id_choice, pc.label, pc.votes, IFNULL(lp.id_choice, -1) AS voted_this
			FROM {db_prefix}poll_choices AS pc
				LEFT JOIN {db_prefix}log_polls AS lp ON (lp.id_choice = pc.id_choice AND lp.id_poll = {int:id_poll} AND lp.id_member = {int:current_member} AND lp.id_member != {int:not_guest})
			WHERE pc.id_poll = {int:id_poll}',
			array(
				'current_member' => $user_info['id'],
				'id_poll' => $row['id_poll'],
				'not_guest' => 0,
			)
		);
		$pollOptions = array();
		$realtotal = 0;
		$pollinfo['has_voted'] = false;
		while ($row = $smcFunc['db_fetch_assoc']($request))
		{
			censorText($row['label']);
			$pollOptions[$row['id_choice']] = $row;
			$realtotal += $row['votes'];
			$pollinfo['has_voted'] |= $row['voted_this'] != -1;
		}
		$smcFunc['db_free_result']($request);

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
			'expire_time' => !empty($pollinfo['expire_time']) ? timeformat($pollinfo['expire_time']) : 0,
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
				'bar' => '<span style="white-space: nowrap;"><img src="' . $settings['images_url'] . '/poll_' . ($context['right_to_left'] ? 'right' : 'left') . '.png" alt="" /><img src="' . $settings['images_url'] . '/poll_middle.png" width="' . $barWide . '" height="12" alt="-" /><img src="' . $settings['images_url'] . '/poll_' . ($context['right_to_left'] ? 'left' : 'right') . '.png" alt="" /></span>',
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
	$context['template_layers'] = array('print');
	$context['board_name'] = $board_info['name'];
	$context['category_name'] = $board_info['cat']['name'];
	$context['poster_name'] = $row['poster_name'];
	$context['post_time'] = timeformat($row['poster_time'], false);
	$context['parent_boards'] = array();
	foreach ($board_info['parent_boards'] as $parent)
		$context['parent_boards'][] = $parent['name'];

	// Split the topics up so we can print them.
	$request = $smcFunc['db_query']('', '
		SELECT subject, poster_time, body, IFNULL(mem.real_name, poster_name) AS poster_name, id_msg
		FROM {db_prefix}messages AS m
			LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = m.id_member)
		WHERE m.id_topic = {int:current_topic}' . ($modSettings['postmod_active'] && !allowedTo('approve_posts') ? '
			AND (m.approved = {int:is_approved}' . ($user_info['is_guest'] ? '' : ' OR m.id_member = {int:current_member}') . ')' : '') . '
		ORDER BY m.id_msg',
		array(
			'current_topic' => $topic,
			'is_approved' => 1,
			'current_member' => $user_info['id'],
		)
	);
	$context['posts'] = array();
	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		// Censor the subject and message.
		censorText($row['subject']);
		censorText($row['body']);

		$context['posts'][] = array(
			'subject' => $row['subject'],
			'member' => $row['poster_name'],
			'time' => timeformat($row['poster_time'], false),
			'timestamp' => forum_time(true, $row['poster_time']),
			'body' => parse_bbc($row['body'], 'print'),
			'id_msg' => $row['id_msg'],
		);

		if (!isset($context['topic_subject']))
			$context['topic_subject'] = $row['subject'];
	}
	$smcFunc['db_free_result']($request);

	// Fetch attachments so we can print them if asked, enabled and allowed
	if (isset($_REQUEST['images']) && !empty($modSettings['attachmentEnable']) && allowedTo('view_attachments'))
	{
		$messages = array();
		foreach ($context['posts'] as $temp)
			$messages[] = $temp['id_msg'];

		// build the request
		$request = $smcFunc['db_query']('', '
			SELECT
				a.id_attach, a.id_msg, a.approved, a.width, a.height, a.file_hash, a.filename, a.id_folder, a.mime_type
			FROM {db_prefix}attachments AS a
			WHERE a.id_msg IN ({array_int:message_list})
				AND a.attachment_type = {int:attachment_type}',
			array(
				'message_list' => $messages,
				'attachment_type' => 0,
				'is_approved' => 1,
			)
		);
		$temp = array();
		while ($row = $smcFunc['db_fetch_assoc']($request))
		{
			$temp[$row['id_attach']] = $row;
			if (!isset($context['printattach'][$row['id_msg']]))
				$context['printattach'][$row['id_msg']] = array();
		}
		$smcFunc['db_free_result']($request);
		ksort($temp);

		// load them into $context so the template can use them
		foreach ($temp as $row)
		{
			if (!empty($row['width']) && !empty($row['height']))
			{
				if (!empty($modSettings['max_image_width']) && (empty($modSettings['max_image_height']) || $row['height'] * ($modSettings['max_image_width'] / $row['width']) <= $modSettings['max_image_height']))
				{
					if ($row['width'] > $modSettings['max_image_width'])
					{
						$row['height'] = floor($row['height'] * ($modSettings['max_image_width'] / $row['width']));
						$row['width'] = $modSettings['max_image_width'];
					}
				}
				elseif (!empty($modSettings['max_image_width']))
				{
					if ($row['height'] > $modSettings['max_image_height'])
					{
						$row['width'] = floor($row['width'] * $modSettings['max_image_height'] / $row['height']);
						$row['height'] = $modSettings['max_image_height'];
					}
				}

				$row['filename'] = getAttachmentFilename($row['filename'], $row['id_attach'], $row['id_folder'], false, $row['file_hash']);

				// save for the template
				$context['printattach'][$row['id_msg']][] = $row;
			}
		}
	}

	// Set a canonical URL for this page.
	$context['canonical_url'] = $scripturl . '?topic=' . $topic . '.0';
}