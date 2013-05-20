<?php

/**
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * This file contains code covered by:
 * copyright:	2011 Simple Machines (http://www.simplemachines.org)
 * license:  	BSD, See included LICENSE.TXT for terms and conditions.
 *
 * @version 1.0 Alpha
 *
 * Handle notifications
 *
 */

if (!defined('ELKARTE'))
	die('No access...');

/**
 * Sends a notification to members who have elected to receive emails
 * when things happen to a topic, such as replies are posted.
 * The function automatically finds the subject and its board, and
 * checks permissions for each member who is "signed up" for notifications.
 * It will not send 'reply' notifications more than once in a row.
 *
 * @param array $topics - represents the topics the action is happening to.
 * @param string $type - can be any of reply, sticky, lock, unlock, remove,
 *  move, merge, and split.  An appropriate message will be sent for each.
 * @param array $exclude = array() - members in the exclude array will not be
 *  processed for the topic with the same key.
 * @param array $members_only = array() - are the only ones that will be sent the notification if they have it on.
 * @uses Post language file
 */
function sendNotifications($topics, $type, $exclude = array(), $members_only = array(), $pbe = array())
{
	global $txt, $scripturl, $language, $user_info, $webmaster_email, $mbname;
	global $modSettings, $smcFunc;

	// Coming in from emailpost or emailtopic, if so pbe values will be set to the credentials of the emailer
	$user_id = (!empty($pbe['user_info']['id']) && !empty($modSettings['maillist_enabled'])) ? $pbe['user_info']['id'] : $user_info['id'];
	$user_language = (!empty($pbe['user_info']['language']) && !empty($modSettings['maillist_enabled'])) ? $pbe['user_info']['language'] : $user_info['language'];

	// Load in our dependencies
	$maillist = !empty($modSettings['maillist_enabled']) && !empty($modSettings['pbe_post_enabled']);
	if ($maillist)
		require_once(SUBSDIR . '/Emailpost.subs.php');
	require_once(SUBSDIR . '/Mail.subs.php');

	// Can't do it if there's no topics.
	if (empty($topics))
		return;

	// It must be an array - it must!
	if (!is_array($topics))
		$topics = array($topics);

	// I hope we are not sending one of silly moderation notices
	if ($type !== 'reply' && !empty($maillist) && !empty($modSettings['pbe_no_mod_notices']))
		return;

	// Get the subject, body and basic poster details
	$result = $smcFunc['db_query']('', '
		SELECT mf.subject, ml.body, ml.id_member, t.id_last_msg, t.id_topic, t.id_board, mem.signature,
			IFNULL(mem.real_name, ml.poster_name) AS poster_name, COUNT(a.id_attach) as num_attach, t.id_member_started,
			b.member_groups as board_groups, b.name as board_name, b.id_profile
		FROM {db_prefix}topics AS t
			INNER JOIN {db_prefix}messages AS mf ON (mf.id_msg = t.id_first_msg)
			INNER JOIN {db_prefix}messages AS ml ON (ml.id_msg = t.id_last_msg)
			INNER JOIN {db_prefix}boards AS b ON (t.id_board = b.id_board)
			LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = ml.id_member)
			LEFT JOIN {db_prefix}attachments AS a ON(a.attachment_type = {int:attachment_type} AND a.id_msg = t.id_last_msg)
		WHERE t.id_topic IN ({array_int:topic_list})
		GROUP BY t.id_topic',
		array(
			'topic_list' => $topics,
			'attachment_type' => 0,
		)
	);
	$topicData = array();
	$boards_index = array();
	while ($row = $smcFunc['db_fetch_assoc']($result))
	{
		// Using the maillist function or the standard style
		if ($maillist)
		{
			// Convert to markdown markup e.g. text ;)
			pbe_prepare_text($row['body'], $row['subject'], $row['signature']);
		}
		else
		{
			// Clean it up.
			censorText($row['subject']);
			censorText($row['body']);
			censorText($row['signature']);
			$row['subject'] = un_htmlspecialchars($row['subject']);
			$row['body'] = trim(un_htmlspecialchars(strip_tags(strtr(parse_bbc($row['body'], false, $row['id_last_msg']), array('<br />' => "\n", '</div>' => "\n", '</li>' => "\n", '&#91;' => '[', '&#93;' => ']')))));
		}

		// all the boards for these topics, used to find all the members to be notified
		$boards_index[$row['id_board']] = array(
			'id' => $row['id_board'],
			'name' => $row['board_name'],
			'groups' => $row['board_groups'],
			'profile' => $row['id_profile'],
		);

		$topicData[$row['id_topic']] = array(
			'subject' => $row['subject'],
			'body' => $row['body'],
			'last_id' => $row['id_last_msg'],
			'topic' => $row['id_topic'],
			'board' => $boards_index[$row['id_board']],
			'name' => $row['poster_name'],
			'exclude' => '',
			'signature' => $row['signature'],
			'attachments' => $row['num_attach'],
			'started_by' => $row['id_member_started'],
		);
	}
	$smcFunc['db_free_result']($result);

	// Work out any exclusions...
	foreach ($topics as $key => $id)
		if (isset($topicData[$id]) && !empty($exclude[$key]))
			$topicData[$id]['exclude'] = (int) $exclude[$key];

	// Nada?
	if (empty($topicData))
		trigger_error('sendNotifications(): topics not found', E_USER_NOTICE);

	$topics = array_keys($topicData);

	// Just in case they've gone walkies.
	if (empty($topics))
		return;

	// Insert all of these items into the digest log for those who want notifications later.
	$digest_insert = array();
	foreach ($topicData as $id => $data)
		$digest_insert[] = array($data['topic'], $data['last_id'], $type, (int) $data['exclude']);
	
	$smcFunc['db_insert']('',
		'{db_prefix}log_digest',
		array(
			'id_topic' => 'int', 'id_msg' => 'int', 'note_type' => 'string', 'exclude' => 'int',
		),
		$digest_insert,
		array()
	);

	// Using the posting email function in either group or list mode
	if ($maillist)
	{
		// Find the members with *board* notifications on.
		$members = $smcFunc['db_query']('', '
			SELECT
				mem.id_member, mem.email_address, mem.notify_regularity, mem.notify_types, mem.notify_send_body, mem.lngfile,
				mem.id_group, mem.additional_groups, mem.id_post_group, ln.id_board, ln.sent
			FROM {db_prefix}log_notify AS ln
				INNER JOIN {db_prefix}members AS mem ON (mem.id_member = ln.id_member)
			WHERE ln.id_board IN ({array_int:board_list})
				AND mem.notify_types != {int:notify_types}
				AND mem.notify_regularity < {int:notify_regularity}
				AND mem.is_activated = {int:is_activated}
				AND ln.id_member != {int:current_member}' .
				(empty($members_only) ? '' : ' AND ln.id_member IN ({array_int:members_only})') . '
			ORDER BY mem.lngfile',
			array(
				'current_member' => $user_id,
				'board_list' => $boards_index,
				'notify_types' => $type === 'reply' ? 4 : 3,
				'notify_regularity' => 2,
				'is_activated' => 1,
				'members_only' => is_array($members_only) ? $members_only : array($members_only),
			)
		);
		$boards = array();
		$sent = 0;
		while ($row = $smcFunc['db_fetch_assoc']($members))
		{
			// If they are not the poster do they want to know?
			// @todo maybe if they posted via email?  
			if ($type !== 'reply' && $row['notify_types'] == 2)
				continue;

			// for this member/board, loop through the topics and see if we should send it
			foreach ($topicData as $id => &$data) 
			{
				// Don't send it if its not from the right board
				if ($data['board']['id'] !== $row['id_board'])
					continue;

				// Don't do the excluded...
				if ($data['exclude'] === $row['id_member'])
					continue;

				$email_perm = true;
				if (validateAccess($row, $maillist, $email_perm) === false)
					continue;

				$needed_language = empty($row['lngfile']) || empty($modSettings['userLanguage']) ? $language : $row['lngfile'];
				if (empty($current_language) || $current_language != $needed_language)
					$current_language = loadLanguage('Post', $needed_language, false);

				$message_type = 'notification_' . $type;
				$replacements = array(
					'TOPICSUBJECT' => $data['subject'],
					'POSTERNAME' => un_htmlspecialchars($data['name']),
					'TOPICLINKNEW' => $scripturl . '?topic=' . $id . '.new;topicseen#new',
					'TOPICLINK' => $scripturl . '?topic=' . $id . '.msg' . $data['last_id'] . '#msg' . $data['last_id'],
					'UNSUBSCRIBELINK' => $scripturl . '?action=notifyboard;board=' . $boards_index[$data['id_board']]['name'] . '.0',
					'SIGNATURE' => $data['signature'],
					'BOARDNAME' => $data['board_name'],
				);

				if ($type === 'remove')
					unset($replacements['TOPICLINK'], $replacements['UNSUBSCRIBELINK']);

				// Do they want the body of the message sent too?
				if (!empty($row['notify_send_body']) && $type === 'reply')
				{
					$message_type .= '_body';
					$replacements['MESSAGE'] = $data['body'];

					// Any attachments? if so lets make a big deal about them!
					if ($data['attachments'] != 0)
						$replacements['MESSAGE'] .=  "\n\n" . sprintf($txt['message_attachments'], $data['attachments'], $replacements['TOPICLINK']);
				}

				if (!empty($row['notify_regularity']) && $type === 'reply')
					$message_type .= '_once';

				// Send only if once is off or it's on and it hasn't been sent.
				if ($type !== 'reply' || empty($row['notify_regularity']) || empty($row['sent']))
				{
					$emaildata = loadEmailTemplate((($maillist && $email_perm && $type === 'reply') ? 'pbe_' : '') . $message_type, $replacements, $needed_language);

					if ($maillist && $email_perm && $type === 'reply')
					{
						// In group mode like google group or yahoo group, the mail is from the poster
						// Otherwise in maillist mode, it is from the site
						$emailfrom = !empty($modSettings['maillist_group_mode']) ? un_htmlspecialchars($data['name']) : (!empty($modSettings['maillist_sitename']) ? un_htmlspecialchars($modSettings['maillist_sitename']) : $mbname);
						
						// The email address of the sender, irrespective of the envelope name above
						$from_wrapper = !empty($modSettings['maillist_sitename_address']) ? $modSettings['maillist_sitename_address'] : (empty($modSettings['maillist_mail_from']) ? $webmaster_email : $modSettings['maillist_mail_from']);
						sendmail($row['email_address'], $emaildata['subject'], $emaildata['body'], $emailfrom, 'm' . $data['last_id'], false, 3, null, false, $from_wrapper, $id);
					}
					else
						sendmail($row['email_address'], $emaildata['subject'], $emaildata['body'], null, 'm' . $data['last_id']);
					
					$sent++;

					// make a note that this member was sent this topic
					$boards[$row['id_member']][$id] = 1;
				}
			}
		}
		$smcFunc['db_free_result']($members);
	}

	// Find the members with notification on for this topic.
	$members = $smcFunc['db_query']('', '
		SELECT
			mem.id_member, mem.email_address, mem.notify_regularity, mem.notify_types, mem.notify_send_body, mem.lngfile,
			mem.id_group, mem.additional_groups, mem.id_post_group, ln.id_topic, ln.sent
		FROM {db_prefix}log_notify AS ln
			INNER JOIN {db_prefix}members AS mem ON (mem.id_member = ln.id_member)
		WHERE ln.id_topic IN ({array_int:topic_list})
			AND mem.notify_types < {int:notify_types}
			AND mem.notify_regularity < {int:notify_regularity}
			AND mem.is_activated = {int:is_activated}
			AND ln.id_member != {int:current_member}' .
			(empty($members_only) ? '' : ' AND ln.id_member IN ({array_int:members_only})') . '
		ORDER BY mem.lngfile',
		array(
			'current_member' => $user_id,
			'topic_list' => $topics,
			'notify_types' => $type == 'reply' ? 4 : 3,
			'notify_regularity' => 2,
			'is_activated' => 1,
			'members_only' => is_array($members_only) ? $members_only : array($members_only),
		)
	);

	while ($row = $smcFunc['db_fetch_assoc']($members))
	{
		// Don't do the excluded...
		if ($topicData[$row['id_topic']]['exclude'] == $row['id_member'])
			continue;

		// Don't do the ones that were sent via board notification, you only get one notice
		if (isset($boards[$row['id_member']][$row['id_topic']]))
			continue;

		// Easier to check this here... if they aren't the topic poster do they really want to know?
		// @todo prehaps just if they posted by email?
		if ($type != 'reply' && $row['notify_types'] == 2 && $row['id_member'] != $topicData[$row['id_topic']]['started_by'])
			continue;

		$email_perm = true;
		if (validateAccess($row, $maillist, $email_perm) === false)
			continue;

		$needed_language = empty($row['lngfile']) || empty($modSettings['userLanguage']) ? $language : $row['lngfile'];
		if (empty($current_language) || $current_language != $needed_language)
			$current_language = loadLanguage('Post', $needed_language, false);

		$message_type = 'notification_' . $type;
		$replacements = array(
			'TOPICSUBJECT' => $topicData[$row['id_topic']]['subject'],
			'POSTERNAME' => un_htmlspecialchars($topicData[$row['id_topic']]['name']),
			'TOPICLINKNEW' => $scripturl . '?topic=' . $row['id_topic'] . '.new;topicseen#new',
			'TOPICLINK' => $scripturl . '?topic=' . $row['id_topic'] . '.msg' . $data['last_id'] . '#msg' . $data['last_id'],
			'UNSUBSCRIBELINK' => $scripturl . '?action=notify;topic=' . $row['id_topic'] . '.0',
			'SIGNATURE' => $topicData[$row['id_topic']]['signature'],
			'BOARDNAME' => $topicData[$row['id_topic']]['board']['name'],
		);

		if ($type == 'remove')
			unset($replacements['TOPICLINK'], $replacements['UNSUBSCRIBELINK']);

		// Do they want the body of the message sent too?
		if (!empty($row['notify_send_body']) && $type == 'reply')
		{
			$message_type .= '_body';
			$replacements['MESSAGE'] = $topicData[$row['id_topic']]['body'];
		}
		if (!empty($row['notify_regularity']) && $type == 'reply')
			$message_type .= '_once';

		call_integration_hook('integrate_notification_replacements', array(&$replacements, $row, $type, $current_language, $boards_index, $topicData));

		// Send only if once is off or it's on and it hasn't been sent.
		if ($type != 'reply' || empty($row['notify_regularity']) || empty($row['sent']))
		{
			$emaildata = loadEmailTemplate((($maillist && $email_perm && $type === 'reply') ? 'pbe_' : '') . $message_type, $replacements, $needed_language);

			// Using the maillist functions?
			if ($maillist && $email_perm && $type === 'reply')
			{
				// Set the from name base on group or maillist mode
				$emailfrom = !empty($modSettings['maillist_group_mode']) ? un_htmlspecialchars($topicData[$row['id_topic']]['name']) : un_htmlspecialchars($modSettings['maillist_sitename']);
				$from_wrapper = !empty($modSettings['maillist_sitename_address']) ? $modSettings['maillist_sitename_address'] : (empty($modSettings['maillist_mail_from']) ? $webmaster_email : $modSettings['maillist_mail_from']);
				sendmail($row['email_address'], $emaildata['subject'], $emaildata['body'], $emailfrom, 'm' . $data['last_id'], false, 3, null, false, $from_wrapper, $row['id_topic']);
			}
			else
				sendmail($row['email_address'], $emaildata['subject'], $emaildata['body'], null, 'm' . $topicData[$row['id_topic']]['last_id']);

			$sent++;
		}
	}
	$smcFunc['db_free_result']($members);

	if (isset($current_language) && $current_language != $user_language)
		loadLanguage('Post');

	// Sent!
	if ($type == 'reply' && !empty($sent))
		$smcFunc['db_query']('', '
			UPDATE {db_prefix}log_notify
			SET sent = {int:is_sent}
			WHERE id_topic IN ({array_int:topic_list})
				AND id_member != {int:current_member}',
			array(
				'current_member' => $user_id,
				'topic_list' => $topics,
				'is_sent' => 1,
			)
		);

	// For approvals we need to unsend the exclusions (This *is* the quickest way!)
	if (!empty($sent) && !empty($exclude))
	{
		foreach ($topicData as $id => $data)
		{
			if ($data['exclude'])
			{
				$smcFunc['db_query']('', '
					UPDATE {db_prefix}log_notify
					SET sent = {int:not_sent}
					WHERE id_topic = {int:id_topic}
						AND id_member = {int:id_member}',
					array(
						'not_sent' => 0,
						'id_topic' => $id,
						'id_member' => $data['exclude'],
					)
				);
			}
		}
	}
}


/**
 * Notifies members who have requested notification for new topics posted on a board of said posts.
 *
 * receives data on the topics to send out notifications to by the passed in array.
 * only sends notifications to those who can *currently* see the topic (it doesn't matter if they could when they requested notification.)
 * loads the Post language file multiple times for each language if the userLanguage setting is set.
 * @param array &$topicData
 */
function notifyMembersBoard(&$topicData)
{
	global $scripturl, $language, $user_info, $modSettings, $smcFunc, $webmaster_email;

	require_once(SUBSDIR . '/Mail.subs.php');

	// Do we have one or lots of topics?
	if (isset($topicData['body']))
		$topicData = array($topicData);

	// Using the post to email functions?
	$maillist = !empty($modSettings['maillist_enabled']) && !empty($modSettings['pbe_post_enabled']);
	if ($maillist)
		require_once(SUBSDIR . '/Emailpost.subs.php');

	// Find out what boards we have... and clear out any rubbish!
	$boards = array();
	foreach ($topicData as $key => $topic)
	{
		if (!empty($topic['board']))
			$boards[$topic['board']][] = $key;
		else
		{
			unset($topic[$key]);
			continue;
		}

		// Using maillist functionality?
		if ($maillist)
		{
			// Convert to markdown markup e.g. styled plain text
			pbe_prepare_text($topicData[$key]['body'], $topicData[$key]['subject'], $topicData[$key]['signature']);
		}
		else
		{
			// Censor the subject and body...
			censorText($topicData[$key]['subject']);
			censorText($topicData[$key]['body']);

			$topicData[$key]['subject'] = un_htmlspecialchars($topicData[$key]['subject']);
			$topicData[$key]['body'] = trim(un_htmlspecialchars(strip_tags(strtr(parse_bbc($topicData[$key]['body'], false), array('<br />' => "\n", '</div>' => "\n", '</li>' => "\n", '&#91;' => '[', '&#93;' => ']')))));
		}
	}

	// Just the board numbers.
	$board_index = array_unique(array_keys($boards));
	if (empty($board_index))
		return;

	// Load the actual board names
	$board_names = array();
	$request = $smcFunc['db_query']('', '
		SELECT id_board, name
		FROM {db_prefix}boards
		WHERE id_board IN ({array_int:board_list})',
		array(
			'board_list' => $board_index,
		)
	);
	while ($row = $smcFunc['db_fetch_assoc']($request))
		$board_names[$row['id_board']] = $row['name'];
	$smcFunc['db_free_result']($request);

	// Yea, we need to add this to the digest queue.
	$digest_insert = array();
	foreach ($topicData as $id => $data)
		$digest_insert[] = array($data['topic'], $data['msg'], 'topic', $user_info['id']);
	$smcFunc['db_insert']('',
		'{db_prefix}log_digest',
		array(
			'id_topic' => 'int', 'id_msg' => 'int', 'note_type' => 'string', 'exclude' => 'int',
		),
		$digest_insert,
		array()
	);

	// Find the members with notification on for these boards.
	$members = $smcFunc['db_query']('', '
		SELECT
			mem.id_member, mem.email_address, mem.notify_regularity, mem.notify_send_body, mem.lngfile, mem.warning,
			ln.sent, ln.id_board, mem.id_group, mem.additional_groups, b.member_groups, b.id_profile,
			mem.id_post_group
		FROM {db_prefix}log_notify AS ln
			INNER JOIN {db_prefix}boards AS b ON (b.id_board = ln.id_board)
			INNER JOIN {db_prefix}members AS mem ON (mem.id_member = ln.id_member)
		WHERE ln.id_board IN ({array_int:board_list})
			AND mem.id_member != {int:current_member}
			AND mem.is_activated = {int:is_activated}
			AND mem.notify_types != {int:notify_types}
			AND mem.notify_regularity < {int:notify_regularity}
		ORDER BY mem.lngfile',
		array(
			'current_member' => $user_info['id'],
			'board_list' => $board_index,
			'is_activated' => 1,
			'notify_types' => 4,
			'notify_regularity' => 2,
		)
	);
	// While we have members with board notifications 
	while ($rowmember = $smcFunc['db_fetch_assoc']($members))
	{
		$email_perm = true;
		if (validateAccess($rowmember, $maillist, $email_perm) === false)
			continue;

		$langloaded = loadLanguage('index', empty($rowmember['lngfile']) || empty($modSettings['userLanguage']) ? $language : $rowmember['lngfile'], false);

		// Now loop through all the notifications to send for this board.
		if (empty($boards[$rowmember['id_board']]))
			continue;

		$sentOnceAlready = 0;

		// For each message we need to send (from this board to this member)
		foreach ($boards[$rowmember['id_board']] as $key)
		{
			// Don't notify the guy who started the topic!
			// @todo In this case actually send them a "it's approved hooray" email :P
			if ($topicData[$key]['poster'] == $rowmember['id_member'])
				continue;

			// Setup the string for adding the body to the message, if a user wants it.
			$send_body = $maillist || (empty($modSettings['disallow_sendBody']) && !empty($rowmember['notify_send_body']));

			$replacements = array(
				'TOPICSUBJECT' => $topicData[$key]['subject'],
				'POSTERNAME' => un_htmlspecialchars($topicData[$key]['name']),
				'TOPICLINK' => $scripturl . '?topic=' . $topicData[$key]['topic'] . '.new#new',
				'TOPICLINKNEW' => $scripturl . '?topic=' . $topicData[$key]['topic'] . '.new#new',
				'MESSAGE' => $send_body ? $topicData[$key]['body'] : '',
				'UNSUBSCRIBELINK' => $scripturl . '?action=notifyboard;board=' . $topicData[$key]['board'] . '.0',
				'SIGNATURE' => !empty($topicData[$key]['signature']) ? $topicData[$key]['signature'] : '',
				'BOARDNAME' => $board_names[$topicData[$key]['board']],
			);

			// Figure out which email to send
			$emailtype = '';

			// Send only if once is off or it's on and it hasn't been sent.
			if (!empty($rowmember['notify_regularity']) && !$sentOnceAlready && empty($rowmember['sent']))
				$emailtype = 'notify_boards_once';
			elseif (empty($rowmember['notify_regularity']))
				$emailtype = 'notify_boards';

			if (!empty($emailtype))
			{
				$emailtype .= $send_body ? '_body' : '';
				$emaildata = loadEmailTemplate((($maillist && $email_perm) ? 'pbe_' : '') . $emailtype, $replacements, $langloaded);
				$emailname = (!empty($topicData[$key]['name'])) ? un_htmlspecialchars($topicData[$key]['name']) : null;
				
				// Maillist style?
				if ($maillist && $email_perm)
				{
					// Add in the from wrapper and trigger sendmail to add in a security key
					$from_wrapper = !empty($modSettings['maillist_sitename_address']) ? $modSettings['maillist_sitename_address'] : (empty($modSettings['maillist_mail_from']) ? $webmaster_email : $modSettings['maillist_mail_from']);
					sendmail($rowmember['email_address'], $emaildata['subject'], $emaildata['body'], $emailname, 't' . $topicData[$key]['topic'], false, 3, null, false, $from_wrapper, $topicData[$key]['topic']);
				}
				else
					sendmail($rowmember['email_address'], $emaildata['subject'], $emaildata['body'], null, null, false, 3);
			}

			$sentOnceAlready = 1;
		}
	}
	$smcFunc['db_free_result']($members);

	loadLanguage('index', $user_info['language']);

	// Sent!
	$smcFunc['db_query']('', '
		UPDATE {db_prefix}log_notify
		SET sent = {int:is_sent}
		WHERE id_board IN ({array_int:board_list})
			AND id_member != {int:current_member}',
		array(
			'current_member' => $user_info['id'],
			'board_list' => $board_index,
			'is_sent' => 1,
		)
	);
}


/**
 * A special function for handling the hell which is sending approval notifications.
 *
 * @param $topicData
 */
function sendApprovalNotifications(&$topicData)
{
	global $scripturl, $language, $user_info, $modSettings, $smcFunc;

	// Clean up the data...
	if (!is_array($topicData) || empty($topicData))
		return;

	// Email ahoy
	require_once(SUBSDIR . '/Mail.subs.php');

	// Maillist format?
	$maillist = !empty($modSettings['maillist_enabled']) && !empty($modSettings['pbe_post_enabled']);
	if ($maillist)
		require_once(SUBSDIR . '/Emailpost.subs.php');

	$topics = array();
	$digest_insert = array();
	foreach ($topicData as $topic => $msgs)
	{
		foreach ($msgs as $msgKey => $msg)
		{
			if ($maillist)
			{
				// Convert it to markdown for sending
				pbe_prepare_text($topicData[$topic][$msgKey]['body'], $topicData[$topic][$msgKey]['subject'], '');
			}
			else
			{
				censorText($topicData[$topic][$msgKey]['subject']);
				censorText($topicData[$topic][$msgKey]['body']);
				$topicData[$topic][$msgKey]['subject'] = un_htmlspecialchars($topicData[$topic][$msgKey]['subject']);
				$topicData[$topic][$msgKey]['body'] = trim(un_htmlspecialchars(strip_tags(strtr(parse_bbc($topicData[$topic][$msgKey]['body'], false), array('<br />' => "\n", '</div>' => "\n", '</li>' => "\n", '&#91;' => '[', '&#93;' => ']')))));
			}

			$topics[] = $msg['id'];
			$digest_insert[] = array($msg['topic'], $msg['id'], 'reply', $user_info['id']);
		}
	}

	// These need to go into the digest too...
	$smcFunc['db_insert']('',
		'{db_prefix}log_digest',
		array(
			'id_topic' => 'int', 'id_msg' => 'int', 'note_type' => 'string', 'exclude' => 'int',
		),
		$digest_insert,
		array()
	);

	// Find everyone who needs to know about this.
	$members = $smcFunc['db_query']('', '
		SELECT
			mem.id_member, mem.email_address, mem.notify_regularity, mem.notify_types, mem.notify_send_body, mem.lngfile,
			ln.sent, mem.id_group, mem.additional_groups, b.member_groups, mem.id_post_group, t.id_member_started,
			ln.id_topic
		FROM {db_prefix}log_notify AS ln
			INNER JOIN {db_prefix}members AS mem ON (mem.id_member = ln.id_member)
			INNER JOIN {db_prefix}topics AS t ON (t.id_topic = ln.id_topic)
			INNER JOIN {db_prefix}boards AS b ON (b.id_board = t.id_board)
		WHERE ln.id_topic IN ({array_int:topic_list})
			AND mem.is_activated = {int:is_activated}
			AND mem.notify_types < {int:notify_types}
			AND mem.notify_regularity < {int:notify_regularity}
		GROUP BY mem.id_member, ln.id_topic, mem.email_address, mem.notify_regularity, mem.notify_types, mem.notify_send_body, mem.lngfile, ln.sent, mem.id_group, mem.additional_groups, b.member_groups, mem.id_post_group, t.id_member_started
		ORDER BY mem.lngfile',
		array(
			'topic_list' => $topics,
			'is_activated' => 1,
			'notify_types' => 4,
			'notify_regularity' => 2,
		)
	);
	$sent = 0;

	$current_language = $user_info['language'];
	while ($row = $smcFunc['db_fetch_assoc']($members))
	{
		if ($row['id_group'] != 1)
		{
			$allowed = explode(',', $row['member_groups']);
			$row['additional_groups'] = explode(',', $row['additional_groups']);
			$row['additional_groups'][] = $row['id_group'];
			$row['additional_groups'][] = $row['id_post_group'];

			if (count(array_intersect($allowed, $row['additional_groups'])) == 0)
				continue;
		}

		$needed_language = empty($row['lngfile']) || empty($modSettings['userLanguage']) ? $language : $row['lngfile'];
		if (empty($current_language) || $current_language != $needed_language)
			$current_language = loadLanguage('Post', $needed_language, false);

		$sent_this_time = false;
		// Now loop through all the messages to send.
		foreach ($topicData[$row['id_topic']] as $msg)
		{
			$replacements = array(
				'TOPICSUBJECT' => $topicData[$row['id_topic']]['subject'],
				'POSTERNAME' => un_htmlspecialchars($topicData[$row['id_topic']]['name']),
				'TOPICLINK' => $scripturl . '?topic=' . $row['id_topic'] . '.new;topicseen#new',
				'UNSUBSCRIBELINK' => $scripturl . '?action=notify;topic=' . $row['id_topic'] . '.0',
			);

			$message_type = 'notification_reply';
			// Do they want the body of the message sent too?
			if (!empty($row['notify_send_body']) && empty($modSettings['disallow_sendBody']))
			{
				$message_type .= '_body';
				$replacements['BODY'] = $topicData[$row['id_topic']]['body'];
			}
			if (!empty($row['notify_regularity']))
				$message_type .= '_once';

			// Send only if once is off or it's on and it hasn't been sent.
			if (empty($row['notify_regularity']) || (empty($row['sent']) && !$sent_this_time))
			{
				$emaildata = loadEmailTemplate($message_type, $replacements, $needed_language);
				sendmail($row['email_address'], $emaildata['subject'], $emaildata['body'], null, 'm' . $topicData[$row['id_topic']]['last_id']);
				$sent++;
			}

			$sent_this_time = true;
		}
	}
	$smcFunc['db_free_result']($members);

	if (isset($current_language) && $current_language != $user_info['language'])
		loadLanguage('Post');

	// Sent!
	if (!empty($sent))
		$smcFunc['db_query']('', '
			UPDATE {db_prefix}log_notify
			SET sent = {int:is_sent}
			WHERE id_topic IN ({array_int:topic_list})
				AND id_member != {int:current_member}',
			array(
				'current_member' => $user_info['id'],
				'topic_list' => $topics,
				'is_sent' => 1,
			)
		);
}

/**
 * Checks if a user has the correct access to get notifications
 * - validates they have proper group access to a board 
 * - if using the maillist, checks if they should get a reply-able message
 * 		- not muted
 * 		- has postby_email permission on the board
 * 
 * Returns false if they do not have the proper group access to a board
 * Sets email_perm to false if they should not get a reply-able message
 *
 * @param array $row
 * @param boolean $maillist
 * @param boolean $email_perm
 */
function validateAccess($row, $maillist, &$email_perm = true)
{
	global $smcFunc, $modSettings;
	static $board_profile = array();

	// No need to check for you ;)
	if ($row['id_group'] == 1)
		return;

	$allowed = explode(',', $row['member_groups']);
	$row['additional_groups'] = !empty( $row['additional_groups']) ? explode(',', $row['additional_groups']) : array();
	$row['additional_groups'][] = $row['id_group'];
	$row['additional_groups'][] = $row['id_post_group'];

	// They do have access to this board?
	if (count(array_intersect($allowed, $row['additional_groups'])) === 0)
		return false;

	// If using maillist, see if they should get a reply-able message
	if ($maillist)
	{
		// Perhaps they don't require a security key in the message
		if (!empty($modSettings['postmod_active']) && !empty($modSettings['warning_mute']) && $modSettings['warning_mute'] <= $row['warning'])
			$email_perm = false;
		else
		{
			// In a group that has email posting permissions on this board
			if (!isset($board_profile[$row['id_profile']]))
			{
				$request = $smcFunc['db_query']('', '
					SELECT permission, add_deny, id_group
					FROM {db_prefix}board_permissions
					WHERE id_profile = {int:id_profile}
						AND permission = {string:permission}',
					array(
						'id_profile' => $row['id_profile'],
						'permission' => 'postby_email',
					)
				);
				while ($row_perm = $smcFunc['db_fetch_assoc']($request))
					$board_profile[$row['id_profile']][] = $row_perm['id_group'];
				$smcFunc['db_free_result']($request);
			}

			// Get the email permission for this board / posting group
			if (count(array_intersect($board_profile[$row['id_profile']], $row['additional_groups'])) === 0)
				$email_perm = false;
		}
	}
}
