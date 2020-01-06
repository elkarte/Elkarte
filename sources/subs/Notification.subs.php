<?php

/**
 * Functions to support the sending of notifications (new posts, replys, topics)
 *
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * @version 2.0 dev
 *
 */

use ElkArte\User;

/**
 * Sends a notification to members who have elected to receive emails
 * when things happen to a topic, such as replies are posted.
 * The function automatically finds the subject and its board, and
 * checks permissions for each member who is "signed up" for notifications.
 * It will not send 'reply' notifications more than once in a row.
 *
 * @param int[]|int $topics - represents the topics the action is happening to.
 * @param string $type - can be any of reply, sticky, lock, unlock, remove,
 *                       move, merge, and split.  An appropriate message will be sent for each.
 * @param int[]|int $exclude = array() - members in the exclude array will not be
 *                                   processed for the topic with the same key.
 * @param int[]|int $members_only = array() - are the only ones that will be sent the notification if they have it on.
 * @param mixed[] $pbe = array() - array containing user_info if this is being run as a result of an email posting
 * @throws \ElkArte\Exceptions\Exception
 * @uses Post language file
 */
function sendNotifications($topics, $type, $exclude = array(), $members_only = array(), $pbe = array())
{
	global $txt, $scripturl, $language, $webmaster_email, $mbname, $modSettings;

	$db = database();

	// Coming in from emailpost or emailtopic, if so pbe values will be set to the credentials of the emailer
	$user_id = (!empty($pbe['user_info']['id']) && !empty($modSettings['maillist_enabled'])) ? $pbe['user_info']['id'] : User::$info->id;
	$user_language = (!empty($pbe['user_info']['language']) && !empty($modSettings['maillist_enabled'])) ? $pbe['user_info']['language'] : User::$info->language;

	// Can't do it if there's no topics.
	if (empty($topics))
	{
		return;
	}

	// It must be an array - it must!
	if (!is_array($topics))
	{
		$topics = array($topics);
	}

	// I hope we are not sending one of those silly moderation notices
	$maillist = !empty($modSettings['maillist_enabled']) && !empty($modSettings['pbe_post_enabled']);
	if ($type !== 'reply' && !empty($maillist) && !empty($modSettings['pbe_no_mod_notices']))
	{
		return;
	}

	// Load in our dependencies
	require_once(SUBSDIR . '/Emailpost.subs.php');
	require_once(SUBSDIR . '/Mail.subs.php');

	// Get the subject, body and basic poster details, number of attachments if any
	$topicData = array();
	$boards_index = array();
	$db->fetchQuery('
		SELECT 
			mf.subject, ml.body, ml.id_member, t.id_last_msg, t.id_topic, t.id_board, mem.signature,
			COALESCE(mem.real_name, ml.poster_name) AS poster_name, COUNT(a.id_attach) as num_attach
		FROM {db_prefix}topics AS t
			INNER JOIN {db_prefix}messages AS mf ON (mf.id_msg = t.id_first_msg)
			INNER JOIN {db_prefix}messages AS ml ON (ml.id_msg = t.id_last_msg)
			LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = ml.id_member)
			LEFT JOIN {db_prefix}attachments AS a ON(a.attachment_type = {int:attachment_type} AND a.id_msg = t.id_last_msg)
		WHERE t.id_topic IN ({array_int:topic_list})
		GROUP BY t.id_topic, mf.subject, ml.body, ml.id_member, mem.signature, mem.real_name, ml.poster_name',
		array(
			'topic_list' => $topics,
			'attachment_type' => 0,
		)
	)->fetch_callback(
		function ($row) use (&$topicData, &$boards_index, $type) {
			// Convert to markdown e.g. text ;) and clean it up
			pbe_prepare_text($row['body'], $row['subject'], $row['signature']);

			// all the boards for these topics, used to find all the members to be notified
			$boards_index[] = $row['id_board'];

			// And the information we are going to tell them about
			$topicData[$row['id_topic']] = array(
				'subject' => $row['subject'],
				'body' => $row['body'],
				'last_id' => $row['id_last_msg'],
				'topic' => $row['id_topic'],
				'board' => $row['id_board'],
				'name' => $type === 'reply' ? $row['poster_name'] : User::$info->name,
				'exclude' => '',
				'signature' => $row['signature'],
				'attachments' => $row['num_attach'],
			);
		}
	);

	// Work out any exclusions...
	foreach ($topics as $key => $id)
	{
		if (isset($topicData[$id]) && !empty($exclude[$key]))
		{
			$topicData[$id]['exclude'] = (int) $exclude[$key];
		}
	}

	// Nada?
	if (empty($topicData))
	{
		trigger_error('sendNotifications(): topics not found', E_USER_NOTICE);
	}

	$topics = array_keys($topicData);

	// Just in case they've gone walkies.
	if (empty($topics))
	{
		return;
	}

	// Insert all of these items into the digest log for those who want notifications later.
	$digest_insert = array();
	foreach ($topicData as $id => $data)
	{
		$digest_insert[] = array($data['topic'], $data['last_id'], $type, (int) $data['exclude']);
	}

	$db->insert('',
		'{db_prefix}log_digest',
		array(
			'id_topic' => 'int', 'id_msg' => 'int', 'note_type' => 'string', 'exclude' => 'int',
		),
		$digest_insert,
		array()
	);

	// Are we doing anything here?
	$sent = 0;

	// Using the posting email function in either group or list mode
	if ($maillist)
	{
		// Find the members with *board* notifications on.
		$boards = array();
		$members = $db->fetchQuery('
			SELECT
				mem.id_member, mem.email_address, mem.notify_regularity, mem.notify_types, mem.notify_send_body, mem.lngfile, mem.warning,
				ln.sent, mem.id_group, mem.additional_groups, b.member_groups, mem.id_post_group, b.name, b.id_profile,
				ln.id_board, mem.password_salt
			FROM {db_prefix}log_notify AS ln
				INNER JOIN {db_prefix}boards AS b ON (b.id_board = ln.id_board)
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
		while (($row = $members->fetch_assoc()))
		{
			// If they are not the poster do they want to know?
			// @todo maybe if they posted via email?
			if ($type !== 'reply' && $row['notify_types'] == 2)
			{
				continue;
			}

			// for this member/board, loop through the topics and see if we should send it
			foreach ($topicData as $id => $data)
			{
				// Don't send it if its not from the right board
				if ($data['board'] !== $row['id_board'])
				{
					continue;
				}
				else
				{
					$data['board_name'] = $row['name'];
				}

				// Don't do the excluded...
				if ($data['exclude'] === $row['id_member'])
				{
					continue;
				}

				$email_perm = true;
				if (!validateNotificationAccess($row, $maillist, $email_perm))
				{
					continue;
				}

				$needed_language = empty($row['lngfile']) || empty($modSettings['userLanguage']) ? $language : $row['lngfile'];
				if (empty($current_language) || $current_language !== $needed_language)
				{
					$current_language = theme()->getTemplates()->loadLanguageFile('Post', $needed_language, false);
				}

				$message_type = 'notification_' . $type;
				$replacements = array(
					'TOPICSUBJECT' => $data['subject'],
					'POSTERNAME' => un_htmlspecialchars($data['name']),
					'TOPICLINKNEW' => $scripturl . '?topic=' . $id . '.new;topicseen#new',
					'TOPICLINK' => $scripturl . '?topic=' . $id . '.msg' . $data['last_id'] . '#msg' . $data['last_id'],
					'UNSUBSCRIBELINK' => replaceBasicActionUrl('{script_url}?action=notify;sa=unsubscribe;token=' .
						getNotifierToken($row['id_member'], $row['email_address'], $row['password_salt'], 'board_' . $data['board'])),
					'SIGNATURE' => $data['signature'],
					'BOARDNAME' => $data['board_name'],
					'SUBSCRIPTION' => $txt['board'],
				);

				if ($type === 'remove')
				{
					unset($replacements['TOPICLINK'], $replacements['UNSUBSCRIBELINK']);
				}

				// Do they want the body of the message sent too?
				if (!empty($row['notify_send_body']) && $type === 'reply')
				{
					$message_type .= '_body';
					$replacements['MESSAGE'] = $data['body'];

					// Any attachments? if so lets make a big deal about them!
					if ($data['attachments'] != 0)
					{
						$replacements['MESSAGE'] .= "\n\n" . sprintf($txt['message_attachments'], $data['attachments'], $replacements['TOPICLINK']);
					}
				}

				if (!empty($row['notify_regularity']) && $type === 'reply')
				{
					$message_type .= '_once';
				}

				// Give them a way to add in their own replacements
				call_integration_hook('integrate_notification_replacements', array(&$replacements, $row, $type, $current_language));

				// Send only if once is off or it's on and it hasn't been sent.
				if ($type !== 'reply' || empty($row['notify_regularity']) || empty($row['sent']))
				{
					$emaildata = loadEmailTemplate((($maillist && $email_perm && $type === 'reply' && !empty($row['notify_send_body'])) ? 'pbe_' : '') . $message_type, $replacements, $needed_language);

					// If using the maillist functions, we adjust who this is coming from
					if ($maillist && $email_perm && $type === 'reply' && !empty($row['notify_send_body']))
					{
						// In group mode like google group or yahoo group, the mail is from the poster
						// Otherwise in maillist mode, it is from the site
						$emailfrom = !empty($modSettings['maillist_group_mode']) ? un_htmlspecialchars($data['name']) : (!empty($modSettings['maillist_sitename']) ? un_htmlspecialchars($modSettings['maillist_sitename']) : $mbname);

						// The email address of the sender, irrespective of the envelope name above
						$from_wrapper = !empty($modSettings['maillist_mail_from']) ? $modSettings['maillist_mail_from'] : (empty($modSettings['maillist_sitename_address']) ? $webmaster_email : $modSettings['maillist_sitename_address']);
						sendmail($row['email_address'], $emaildata['subject'], $emaildata['body'], $emailfrom, 'm' . $data['last_id'], false, 3, null, false, $from_wrapper, $id);
					}
					else
					{
						sendmail($row['email_address'], $emaildata['subject'], $emaildata['body'], null, 'm' . $data['last_id']);
					}

					$sent++;

					// Make a note that this member was sent this topic
					$boards[$row['id_member']][$id] = 1;
				}
			}
		}
		$members->free_result();
	}

	// Find the members with notification on for this topic.
	$members = $db->fetchQuery('
		SELECT
			mem.id_member, mem.email_address, mem.notify_regularity, mem.notify_types, mem.warning,
			mem.notify_send_body, mem.lngfile, mem.id_group, mem.additional_groups,mem.id_post_group,
			t.id_member_started, b.member_groups, b.name, b.id_profile, b.id_board,
			ln.id_topic, ln.sent, mem.password_salt
		FROM {db_prefix}log_notify AS ln
			INNER JOIN {db_prefix}members AS mem ON (mem.id_member = ln.id_member)
			INNER JOIN {db_prefix}topics AS t ON (t.id_topic = ln.id_topic)
			INNER JOIN {db_prefix}boards AS b ON (b.id_board = t.id_board)
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
			'notify_types' => $type === 'reply' ? 4 : 3,
			'notify_regularity' => 2,
			'is_activated' => 1,
			'members_only' => is_array($members_only) ? $members_only : array($members_only),
		)
	);
	while (($row = $members->fetch_assoc()))
	{
		// Don't do the excluded...
		if ($topicData[$row['id_topic']]['exclude'] == $row['id_member'])
		{
			continue;
		}

		// Don't do the ones that were sent via board notification, you only get one notice
		if (isset($boards[$row['id_member']][$row['id_topic']]))
		{
			continue;
		}

		// Easier to check this here... if they aren't the topic poster do they really want to know?
		// @todo perhaps just if they posted by email?
		if ($type !== 'reply' && $row['notify_types'] == 2 && $row['id_member'] != $row['id_member_started'])
		{
			continue;
		}

		$email_perm = true;
		if (!validateNotificationAccess($row, $maillist, $email_perm))
		{
			continue;
		}

		$needed_language = empty($row['lngfile']) || empty($modSettings['userLanguage']) ? $language : $row['lngfile'];
		if (empty($current_language) || $current_language !== $needed_language)
		{
			$current_language = theme()->getTemplates()->loadLanguageFile('Post', $needed_language, false);
		}

		$message_type = 'notification_' . $type;
		$replacements = array(
			'TOPICSUBJECT' => $topicData[$row['id_topic']]['subject'],
			'POSTERNAME' => un_htmlspecialchars($topicData[$row['id_topic']]['name']),
			'TOPICLINKNEW' => $scripturl . '?topic=' . $row['id_topic'] . '.new;topicseen#new',
			'TOPICLINK' => $scripturl . '?topic=' . $row['id_topic'] . '.msg' . $data['last_id'] . '#msg' . $data['last_id'],
			'UNSUBSCRIBELINK' => replaceBasicActionUrl('{script_url}?action=notify;sa=unsubscribe;token=' .
				getNotifierToken($row['id_member'], $row['email_address'], $row['password_salt'], 'topic_' . $row['id_topic'])),
			'SIGNATURE' => $topicData[$row['id_topic']]['signature'],
			'BOARDNAME' => $row['name'],
			'SUBSCRIPTION' => $txt['topic'],
		);

		if ($type === 'remove')
		{
			unset($replacements['TOPICLINK'], $replacements['UNSUBSCRIBELINK']);
		}

		// Do they want the body of the message sent too?
		if (!empty($row['notify_send_body']) && $type === 'reply')
		{
			$message_type .= '_body';
			$replacements['MESSAGE'] = $topicData[$row['id_topic']]['body'];
		}
		if (!empty($row['notify_regularity']) && $type === 'reply')
		{
			$message_type .= '_once';
		}

		// Send only if once is off or it's on and it hasn't been sent.
		if ($type !== 'reply' || empty($row['notify_regularity']) || empty($row['sent']))
		{
			$emaildata = loadEmailTemplate((($maillist && $email_perm && $type === 'reply' && !empty($row['notify_send_body'])) ? 'pbe_' : '') . $message_type, $replacements, $needed_language);

			// Using the maillist functions? Then adjust the from wrapper
			if ($maillist && $email_perm && $type === 'reply' && !empty($row['notify_send_body']))
			{
				// Set the from name base on group or maillist mode
				$emailfrom = !empty($modSettings['maillist_group_mode']) ? un_htmlspecialchars($topicData[$row['id_topic']]['name']) : un_htmlspecialchars($modSettings['maillist_sitename']);
				$from_wrapper = !empty($modSettings['maillist_mail_from']) ? $modSettings['maillist_mail_from'] : (empty($modSettings['maillist_sitename_address']) ? $webmaster_email : $modSettings['maillist_sitename_address']);
				sendmail($row['email_address'], $emaildata['subject'], $emaildata['body'], $emailfrom, 'm' . $data['last_id'], false, 3, null, false, $from_wrapper, $row['id_topic']);
			}
			else
			{
				sendmail($row['email_address'], $emaildata['subject'], $emaildata['body'], null, 'm' . $topicData[$row['id_topic']]['last_id']);
			}

			$sent++;
		}
	}
	$members->free_result();

	if (isset($current_language) && $current_language !== $user_language)
	{
		theme()->getTemplates()->loadLanguageFile('Post');
	}

	// Sent!
	if ($type === 'reply' && !empty($sent))
	{
		$db->query('', '
			UPDATE {db_prefix}log_notify
			SET 
				sent = {int:is_sent}
			WHERE id_topic IN ({array_int:topic_list})
				AND id_member != {int:current_member}',
			array(
				'current_member' => $user_id,
				'topic_list' => $topics,
				'is_sent' => 1,
			)
		);
	}

	// For approvals we need to unsend the exclusions (This *is* the quickest way!)
	if (!empty($sent) && !empty($exclude))
	{
		foreach ($topicData as $id => $data)
		{
			if ($data['exclude'])
			{
				$db->query('', '
					UPDATE {db_prefix}log_notify
					SET 
						sent = {int:not_sent}
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
 *
 * @param mixed[] $topicData
 * @throws \ElkArte\Exceptions\Exception
 */
function sendBoardNotifications(&$topicData)
{
	global $scripturl, $language, $modSettings, $webmaster_email;

	$db = database();

	require_once(SUBSDIR . '/Mail.subs.php');
	require_once(SUBSDIR . '/Emailpost.subs.php');

	// Do we have one or lots of topics?
	if (isset($topicData['body']))
	{
		$topicData = array($topicData);
	}

	// Using the post to email functions?
	$maillist = !empty($modSettings['maillist_enabled']) && !empty($modSettings['pbe_post_enabled']);

	// Find out what boards we have... and clear out any rubbish!
	$boards = array();
	foreach ($topicData as $key => $topic)
	{
		if (!empty($topic['board']))
		{
			$boards[$topic['board']][] = $key;
		}
		else
		{
			unset($topic[$key]);
			continue;
		}

		// Convert to markdown markup e.g. styled plain text, while doing the censoring
		pbe_prepare_text($topicData[$key]['body'], $topicData[$key]['subject'], $topicData[$key]['signature']);
	}

	// Just the board numbers.
	$board_index = array_unique(array_keys($boards));
	if (empty($board_index))
	{
		return;
	}

	// Load the actual board names
	require_once(SUBSDIR . '/Boards.subs.php');
	$board_names = fetchBoardsInfo(array('boards' => $board_index, 'override_permissions' => true));

	// Yea, we need to add this to the digest queue.
	$digest_insert = array();
	foreach ($topicData as $id => $data)
	{
		$digest_insert[] = array($data['topic'], $data['msg'], 'topic', User::$info->id);
	}
	$db->insert('',
		'{db_prefix}log_digest',
		array(
			'id_topic' => 'int', 'id_msg' => 'int', 'note_type' => 'string', 'exclude' => 'int',
		),
		$digest_insert,
		array()
	);

	// Find the members with notification on for these boards.
	$members = $db->fetchQuery('
		SELECT
			mem.id_member, mem.email_address, mem.notify_regularity, mem.notify_send_body, mem.lngfile, mem.warning,
			ln.sent, ln.id_board, mem.id_group, mem.additional_groups, b.member_groups, b.id_profile,
			mem.id_post_group, mem.password_salt
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
			'current_member' => User::$info->id,
			'board_list' => $board_index,
			'is_activated' => 1,
			'notify_types' => 4,
			'notify_regularity' => 2,
		)
	);
	// While we have members with board notifications
	while (($rowmember = $members->fetch_assoc()))
	{
		$email_perm = true;
		if (!validateNotificationAccess($rowmember, $maillist, $email_perm))
		{
			continue;
		}

		$langloaded = theme()->getTemplates()->loadLanguageFile('index', empty($rowmember['lngfile']) || empty($modSettings['userLanguage']) ? $language : $rowmember['lngfile'], false);

		// Now loop through all the notifications to send for this board.
		if (empty($boards[$rowmember['id_board']]))
		{
			continue;
		}

		$sentOnceAlready = 0;

		// For each message we need to send (from this board to this member)
		foreach ($boards[$rowmember['id_board']] as $key)
		{
			// Don't notify the guy who started the topic!
			// @todo In this case actually send them a "it's approved hooray" email :P
			if ($topicData[$key]['poster'] == $rowmember['id_member'])
			{
				continue;
			}

			// Setup the string for adding the body to the message, if a user wants it.
			$send_body = $maillist || (empty($modSettings['disallow_sendBody']) && !empty($rowmember['notify_send_body']));

			$replacements = array(
				'TOPICSUBJECT' => $topicData[$key]['subject'],
				'POSTERNAME' => un_htmlspecialchars($topicData[$key]['name']),
				'TOPICLINK' => $scripturl . '?topic=' . $topicData[$key]['topic'] . '.new#new',
				'TOPICLINKNEW' => $scripturl . '?topic=' . $topicData[$key]['topic'] . '.new#new',
				'MESSAGE' => $send_body ? $topicData[$key]['body'] : '',
				'UNSUBSCRIBELINK' => replaceBasicActionUrl('{script_url}?action=notify;sa=unsubscribe;token=' .
					getNotifierToken($rowmember['id_member'], $rowmember['email_address'], $rowmember['password_salt'], 'board_' . $topicData[$key]['board'])),
				'SIGNATURE' => !empty($topicData[$key]['signature']) ? $topicData[$key]['signature'] : '',
				'BOARDNAME' => $board_names[$topicData[$key]['board']]['name'],
			);

			// Figure out which email to send
			$emailtype = '';

			// Send only if once is off or it's on and it hasn't been sent.
			if (!empty($rowmember['notify_regularity']) && !$sentOnceAlready && empty($rowmember['sent']))
			{
				$emailtype = 'notify_boards_once';
			}
			elseif (empty($rowmember['notify_regularity']))
			{
				$emailtype = 'notify_boards';
			}

			if (!empty($emailtype))
			{
				$emailtype .= $send_body ? '_body' : '';
				$emaildata = loadEmailTemplate((($maillist && $email_perm && $send_body) ? 'pbe_' : '') . $emailtype, $replacements, $langloaded);
				$emailname = (!empty($topicData[$key]['name'])) ? un_htmlspecialchars($topicData[$key]['name']) : null;

				// Maillist style?
				if ($maillist && $email_perm && $send_body)
				{
					// Add in the from wrapper and trigger sendmail to add in a security key
					$from_wrapper = !empty($modSettings['maillist_mail_from']) ? $modSettings['maillist_mail_from'] : (empty($modSettings['maillist_sitename_address']) ? $webmaster_email : $modSettings['maillist_sitename_address']);
					sendmail($rowmember['email_address'], $emaildata['subject'], $emaildata['body'], $emailname, 't' . $topicData[$key]['topic'], false, 3, null, false, $from_wrapper, $topicData[$key]['topic']);
				}
				else
				{
					sendmail($rowmember['email_address'], $emaildata['subject'], $emaildata['body'], null, null, false, 3);
				}
			}

			$sentOnceAlready = 1;
		}
	}
	$members->free_result();

	theme()->getTemplates()->loadLanguageFile('index', User::$info->language);

	// Sent!
	$db->query('', '
		UPDATE {db_prefix}log_notify
		SET 
			sent = {int:is_sent}
		WHERE id_board IN ({array_int:board_list})
			AND id_member != {int:current_member}',
		array(
			'current_member' => User::$info->id,
			'board_list' => $board_index,
			'is_sent' => 1,
		)
	);
}

/**
 * A special function for handling the hell which is sending approval notifications.
 *
 * @param mixed[] $topicData
 * @throws \ElkArte\Exceptions\Exception
 */
function sendApprovalNotifications(&$topicData)
{
	global $scripturl, $language, $modSettings;

	$db = database();

	// Clean up the data...
	if (!is_array($topicData) || empty($topicData))
	{
		return;
	}

	// Email ahoy
	require_once(SUBSDIR . '/Mail.subs.php');
	require_once(SUBSDIR . '/Emailpost.subs.php');

	$topics = array();
	$digest_insert = array();
	foreach ($topicData as $topic => $msgs)
	{
		foreach ($msgs as $msgKey => $msg)
		{
			// Convert it to markdown for sending, censor is done as well
			pbe_prepare_text($topicData[$topic][$msgKey]['body'], $topicData[$topic][$msgKey]['subject']);

			$topics[] = $msg['id'];
			$digest_insert[] = array($msg['topic'], $msg['id'], 'reply', User::$info->id);
		}
	}

	// These need to go into the digest too...
	$db->insert('',
		'{db_prefix}log_digest',
		array(
			'id_topic' => 'int', 'id_msg' => 'int', 'note_type' => 'string', 'exclude' => 'int',
		),
		$digest_insert,
		array()
	);

	// Find everyone who needs to know about this.
	$members = $db->fetchQuery('
		SELECT
			mem.id_member, mem.email_address, mem.notify_regularity, mem.notify_types, mem.notify_send_body, mem.lngfile,
			ln.sent, mem.id_group, mem.additional_groups, b.member_groups, mem.id_post_group, t.id_member_started,
			ln.id_topic, mem.password_salt
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
	$current_language = User::$info->language;
	while (($row = $members->fetch_assoc()))
	{
		if ($row['id_group'] != 1)
		{
			$allowed = explode(',', $row['member_groups']);
			$row['additional_groups'] = explode(',', $row['additional_groups']);
			$row['additional_groups'][] = $row['id_group'];
			$row['additional_groups'][] = $row['id_post_group'];

			if (count(array_intersect($allowed, $row['additional_groups'])) === 0)
			{
				continue;
			}
		}

		$needed_language = empty($row['lngfile']) || empty($modSettings['userLanguage']) ? $language : $row['lngfile'];
		if (empty($current_language) || $current_language !== $needed_language)
		{
			$current_language = theme()->getTemplates()->loadLanguageFile('Post', $needed_language, false);
		}

		$sent_this_time = false;
		$replacements = array(
			'TOPICLINK' => $scripturl . '?topic=' . $row['id_topic'] . '.new;topicseen#new',
			'UNSUBSCRIBELINK' => replaceBasicActionUrl('{script_url}?action=notify;sa=unsubscribe;token=' .
				getNotifierToken($row['id_member'], $row['email_address'], $row['password_salt'], 'topic_' . $row['id_topic'])),
		);

		// Now loop through all the messages to send.
		foreach ($topicData[$row['id_topic']] as $msg)
		{
			$replacements += array(
				'TOPICSUBJECT' => $msg['subject'],
				'POSTERNAME' => un_htmlspecialchars($msg['name']),
			);

			$message_type = 'notification_reply';

			// Do they want the body of the message sent too?
			if (!empty($row['notify_send_body']) && empty($modSettings['disallow_sendBody']))
			{
				$message_type .= '_body';
				$replacements['MESSAGE'] = $msg['body'];
			}

			if (!empty($row['notify_regularity']))
			{
				$message_type .= '_once';
			}

			// Send only if once is off or it's on and it hasn't been sent.
			if (empty($row['notify_regularity']) || (empty($row['sent']) && !$sent_this_time))
			{
				$emaildata = loadEmailTemplate($message_type, $replacements, $needed_language);
				sendmail($row['email_address'], $emaildata['subject'], $emaildata['body'], null, 'm' . $msg['last_id']);
				$sent++;
			}

			$sent_this_time = true;
		}
	}
	$members->free_result();

	if (isset($current_language) && $current_language !== User::$info->language)
	{
		theme()->getTemplates()->loadLanguageFile('Post');
	}

	// Sent!
	if (!empty($sent))
	{
		$db->query('', '
			UPDATE {db_prefix}log_notify
			SET 
				sent = {int:is_sent}
			WHERE id_topic IN ({array_int:topic_list})
				AND id_member != {int:current_member}',
			array(
				'current_member' => User::$info->id,
				'topic_list' => $topics,
				'is_sent' => 1,
			)
		);
	}
}

/**
 * This simple function gets a list of all administrators and sends them an email
 * to let them know a new member has joined.
 * Called by registerMember() function in subs/Members.subs.php.
 * Email is sent to all groups that have the moderate_forum permission.
 * The language set by each member is being used (if available).
 *
 * @param string $type types supported are 'approval', 'activation', and 'standard'.
 * @param int $memberID
 * @param string|null $member_name = null
 * @throws \ElkArte\Exceptions\Exception
 * @uses the Login language file.
 */
function sendAdminNotifications($type, $memberID, $member_name = null)
{
	global $modSettings, $language;

	$db = database();

	// If the setting isn't enabled then just exit.
	if (empty($modSettings['notify_new_registration']))
	{
		return;
	}

	// Needed to notify admins, or anyone
	require_once(SUBSDIR . '/Mail.subs.php');

	if ($member_name === null)
	{
		require_once(SUBSDIR . '/Members.subs.php');

		// Get the new user's name....
		$member_info = getBasicMemberData($memberID);
		$member_name = $member_info['real_name'];
	}

	// All membergroups who can approve members.
	$groups = array();
	$db->fetchQuery('
		SELECT 
			id_group
		FROM {db_prefix}permissions
		WHERE permission = {string:moderate_forum}
			AND add_deny = {int:add_deny}
			AND id_group != {int:id_group}',
		array(
			'add_deny' => 1,
			'id_group' => 0,
			'moderate_forum' => 'moderate_forum',
		)
	)->fetch_callback(
		function ($row) use (&$groups) {
			$groups[] = $row['id_group'];
		}
	);

	// Add administrators too...
	$groups[] = 1;
	$groups = array_unique($groups);

	// Get a list of all members who have ability to approve accounts - these are the people who we inform.
	$current_language = User::$info->language;
	$db->query('', '
		SELECT 
			id_member, lngfile, email_address
		FROM {db_prefix}members
		WHERE (id_group IN ({array_int:group_list}) OR FIND_IN_SET({raw:group_array_implode}, additional_groups) != 0)
			AND notify_types != {int:notify_types}
		ORDER BY lngfile',
		array(
			'group_list' => $groups,
			'notify_types' => 4,
			'group_array_implode' => implode(', additional_groups) != 0 OR FIND_IN_SET(', $groups),
		)
	)->fetch_callback(
		function ($row) use ($type, $member_name, $memberID, $language) {
			global $scripturl, $modSettings;

			$replacements = array(
				'USERNAME' => $member_name,
				'PROFILELINK' => $scripturl . '?action=profile;u=' . $memberID
			);
			$emailtype = 'admin_notify';

			// If they need to be approved add more info...
			if ($type === 'approval')
			{
				$replacements['APPROVALLINK'] = $scripturl . '?action=admin;area=viewmembers;sa=browse;type=approve';
				$emailtype .= '_approval';
			}

			$emaildata = loadEmailTemplate($emailtype, $replacements, empty($row['lngfile']) || empty($modSettings['userLanguage']) ? $language : $row['lngfile']);

			// And do the actual sending...
			sendmail($row['email_address'], $emaildata['subject'], $emaildata['body'], null, null, false, 0);
		}
	);

	if (isset($current_language) && $current_language !== User::$info->language)
	{
		theme()->getTemplates()->loadLanguageFile('Login');
	}
}

/**
 * Checks if a user has the correct access to get notifications
 * - validates they have proper group access to a board
 * - if using the maillist, checks if they should get a reply-able message
 *     - not muted
 *     - has postby_email permission on the board
 *
 * Returns false if they do not have the proper group access to a board
 * Sets email_perm to false if they should not get a reply-able message
 *
 * @param mixed[] $row
 * @param bool $maillist
 * @param bool $email_perm
 *
 * @return bool
 * @throws \ElkArte\Exceptions\Exception
 */
function validateNotificationAccess($row, $maillist, &$email_perm = true)
{
	global $modSettings;

	static $board_profile = array();

	$allowed = explode(',', $row['member_groups']);
	$row['additional_groups'] = !empty($row['additional_groups']) ? explode(',', $row['additional_groups']) : array();
	$row['additional_groups'][] = $row['id_group'];
	$row['additional_groups'][] = $row['id_post_group'];

	// No need to check for you ;)
	if ($row['id_group'] == 1 || in_array('1', $row['additional_groups']))
	{
		return $email_perm;
	}

	// They do have access to this board?
	if (count(array_intersect($allowed, $row['additional_groups'])) === 0)
	{
		return false;
	}

	// If using maillist, see if they should get a reply-able message
	if ($maillist)
	{
		// Perhaps they don't require a security key in the message
		if (!empty($modSettings['postmod_active']) && !empty($modSettings['warning_mute']) && $modSettings['warning_mute'] <= $row['warning'])
		{
			$email_perm = false;
		}
		else
		{
			if (!isset($board_profile[$row['id_board']]))
			{
				require_once(SUBSDIR . '/Members.subs.php');
				$board_profile[$row['id_board']] = groupsAllowedTo('postby_email', $row['id_board']);
			}

			// In a group that has email posting permissions on this board
			if (count(array_intersect($board_profile[$row['id_board']]['allowed'], $row['additional_groups'])) === 0)
			{
				$email_perm = false;
			}

			// And not specifically denied?
			if ($email_perm && !empty($modSettings['permission_enable_deny'])
				&& count(array_intersect($row['additional_groups'], $board_profile[$row['id_board']]['denied'])) !== 0)
			{
				$email_perm = false;
			}
		}
	}

	return $email_perm;
}

/**
 * Queries the database for notification preferences of a set of members.
 *
 * @param string[]|string $notification_types
 * @param int[]|int $members
 *
 * @return array
 * @throws \Exception
 */
function getUsersNotificationsPreferences($notification_types, $members)
{
	$db = database();

	$notification_types = (array) $notification_types;
	$query_members = (array) $members;
	$defaults = array_map(function($vals) {
		$return = [];
		foreach ($vals as $k => $level)
		{
			if ($level == \ElkArte\Notifications::DEFAULT_LEVEL)
			{
				$return[] = $k;
			}
		}

		return $return;
	}, getConfiguredNotificationMethods('*'));

	$results = array();
	$db->fetchQuery('
		SELECT 
			id_member, notification_type, mention_type
		FROM {db_prefix}notifications_pref
		WHERE id_member IN ({array_int:members_to})
			AND mention_type IN ({array_string:mention_types})',
		array(
			'members_to' => $query_members,
			'mention_types' => $notification_types,
		)
	)->fetch_callback(
		function ($row) use (&$results, $defaults) {
			if (!isset($results[$row['id_member']]))
			{
				$results[$row['id_member']] = [];
			}
			if (!isset($results[$row['id_member']][$row['mention_type']]))
			{
				$results[$row['id_member']][$row['mention_type']] = [];
			}

			$results[$row['id_member']][$row['mention_type']][] = $row['notification_type'];
		}
	);

	// Set the defaults
	foreach ($query_members as $member)
	{
		foreach ($notification_types as $type)
		{
			if (empty($results[$member]) && !empty($defaults[$type]))
			{
				if (!isset($results[$member]))
				{
					$results[$member] = [];
				}
				if (!isset($results[$member][$type]))
				{
					$results[$member][$type] = [];
				}
				$results[$member][$type] = $defaults[$type];
			}
		}
	}

	return $results;
}

/**
 * Saves into the database the notification preferences of a certain member.
 *
 * @param int $member The member id
 * @param int[] $notification_data The array of notifications ('type' => 'level')
 * @throws \ElkArte\Exceptions\Exception
 */
function saveUserNotificationsPreferences($member, $notification_data)
{
	$db = database();

	// First drop the existing settings
	$db->query('', '
		DELETE FROM {db_prefix}notifications_pref
		WHERE id_member = {int:member}
			AND mention_type IN ({array_string:mention_types})',
		array(
			'member' => $member,
			'mention_types' => array_keys($notification_data),
		)
	);

	$inserts = array();
	foreach ($notification_data as $type => $level)
	{
		$inserts[] = array(
			$member,
			$type,
			$level,
		);
	}

	if (empty($inserts))
	{
		return;
	}

	$db->insert('',
		'{db_prefix}notifications_pref',
		array(
			'id_member' => 'int',
			'mention_type' => 'string-12',
			'notification_level' => 'int',
		),
		$inserts,
		array('id_member', 'mention_type')
	);
}

/**
 * From the list of all possible notification methods available, only those
 * enabled are returned.
 *
 * @param string[] $possible_methods The array of notifications ('type' => 'level')
 * @param string $type The type of notification (mentionmem, likemsg, etc.)
 *
 * @return array
 */
function filterNotificationMethods($possible_methods, $type)
{
	$unserialized = getConfiguredNotificationMethods($type);

	if (empty($unserialized))
	{
		return array();
	}

	$allowed = array();
	foreach ($possible_methods as $class)
	{
		$class = strtolower($class);
		if (!empty($unserialized[$class]))
		{
			$allowed[] = $class;
		}
	}

	return $allowed;
}

/**
 * Returns all the enabled methods of notification for a specific
 * type of notification.
 *
 * @param string $type The type of notification (mentionmem, likemsg, etc.)
 *
 * @return array
 */
function getConfiguredNotificationMethods($type = '*')
{
	global $modSettings;

	$unserialized = unserialize($modSettings['notification_methods']);

	if (isset($unserialized[$type]))
	{
		return $unserialized[$type];
	}

	if ($type === '*')
	{
		return $unserialized;
	}

	return array();
}

/**
 * Creates a hash code using the notification details and our secret key
 *
 * - If no salt (secret key) has been set, creates a random one and saves it
 * in modSettings for future use
 *
 * @param string $memID member id
 * @param string $memEmail member email address
 * @param string $memSalt member salt
 * @param string $area area to unsubscribe
 * @return string the token for the unsubscribe link
 */
function getNotifierToken($memID, $memEmail, $memSalt, $area)
{
	global $modSettings;

	// Generate a 22 digit random code suitable for Blowfish crypt.
	$tokenizer = new \Token_Hash();
	$unsubscribe_salt = '$2a$10$' . $tokenizer->generate_hash(22);
	$now = time();

	// Ideally we would just have a larger -per user- password_salt
	if (empty($modSettings['unsubscribe_site_salt']))
	{
		// extra 10 digits of salt
		$modSettings['unsubscribe_site_salt'] = $tokenizer->generate_hash();
		updateSettings(array('unsubscribe_site_salt' => $modSettings['unsubscribe_site_salt']));
	}

	// Add member salt + site salt to the otherwise deterministic data
	$hash = crypt($area . $now . $memEmail . $memSalt . $modSettings['unsubscribe_site_salt'], $unsubscribe_salt);

	return urlencode(implode('_',
		array(
			$memID,
			substr($hash, 7),
			$area,
			$now
		)
	));
}

/**
 * Creates a hash code using the notification details and our secret key
 *
 * - If no salt (secret key) has been set, creates a random one and saves it
 * in modSettings for future use
 *
 * @param string $memEmail member email address
 * @param string $memSalt member salt
 * @param string $area area to unsubscribe
 * @param string $hash the sent hash
 * @return string|bool
 */
function validateNotifierToken($memEmail, $memSalt, $area, $hash)
{
	global $modSettings;

	if (empty($modSettings['unsubscribe_site_salt']))
	{
		return false;
	}

	$expected = '$2a$10$' . $hash;
	$check = crypt($area . $memEmail . $memSalt . $modSettings['unsubscribe_site_salt'], $expected);

	// Basic safe compare as hash_equals is PHP 5.6+
	if (function_exists('hash_equals'))
	{
		return hash_equals($expected, $check);
	}

	$ret = strlen($expected) ^ strlen($check);
	$ret |= array_sum(unpack("C*", $expected ^ $check));

	return !$ret;
}
