<?php

/**
 * This file/class handles known scheduled tasks
 *
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
 * @version 1.0 Release Candidate 2
 *
 */

if (!defined('ELK'))
	die('No access...');

/**
 * This class handles known scheduled tasks.
 *
 * - Each method implements a task, and
 * - it's called automatically for the task to run.
 *
 * @package ScheduledTasks
 */
class Scheduled_Task
{
	/**
	 * Function to sending out approval notices to moderators.
	 *
	 * - It checks who needs to receive approvals notifications and sends emails.
	 */
	public function approval_notification()
	{
		global $scripturl, $txt;

		$db = database();

		// Grab all the items awaiting approval and sort type then board - clear up any things that are no longer relevant.
		$request = $db->query('', '
			SELECT aq.id_msg, aq.id_attach, aq.id_event, m.id_topic, m.id_board, m.subject, t.id_first_msg,
				b.id_profile
			FROM {db_prefix}approval_queue AS aq
				INNER JOIN {db_prefix}messages AS m ON (m.id_msg = aq.id_msg)
				INNER JOIN {db_prefix}topics AS t ON (t.id_topic = m.id_topic)
				INNER JOIN {db_prefix}boards AS b ON (b.id_board = m.id_board)',
			array(
			)
		);
		$notices = array();
		$profiles = array();
		while ($row = $db->fetch_assoc($request))
		{
			// If this is no longer around we'll ignore it.
			if (empty($row['id_topic']))
				continue;

			// What type is it?
			if ($row['id_first_msg'] && $row['id_first_msg'] == $row['id_msg'])
				$type = 'topic';
			elseif ($row['id_attach'])
				$type = 'attach';
			else
				$type = 'msg';

			// Add it to the array otherwise.
			$notices[$row['id_board']][$type][] = array(
				'subject' => $row['subject'],
				'href' => $scripturl . '?topic=' . $row['id_topic'] . '.msg' . $row['id_msg'] . '#msg' . $row['id_msg'],
			);

			// Store the profile for a bit later.
			$profiles[$row['id_board']] = $row['id_profile'];
		}
		$db->free_result($request);

		// Delete it all!
		$db->query('', '
			DELETE FROM {db_prefix}approval_queue',
			array(
			)
		);

		// If nothing quit now.
		if (empty($notices))
			return true;

		// Now we need to think about finding out *who* can approve - this is hard!
		// First off, get all the groups with this permission and sort by board.
		$request = $db->query('', '
			SELECT id_group, id_profile, add_deny
			FROM {db_prefix}board_permissions
			WHERE permission = {string:approve_posts}
				AND id_profile IN ({array_int:profile_list})',
			array(
				'profile_list' => $profiles,
				'approve_posts' => 'approve_posts',
			)
		);
		$perms = array();
		$addGroups = array(1);
		while ($row = $db->fetch_assoc($request))
		{
			// Sorry guys, but we have to ignore guests AND members - it would be too many otherwise.
			if ($row['id_group'] < 2)
				continue;

			$perms[$row['id_profile']][$row['add_deny'] ? 'add' : 'deny'][] = $row['id_group'];

			// Anyone who can access has to be considered.
			if ($row['add_deny'])
				$addGroups[] = $row['id_group'];
		}
		$db->free_result($request);

		// Grab the moderators if they have permission!
		$mods = array();
		$members = array();
		if (in_array(2, $addGroups))
		{
			require_once(SUBSDIR . '/Boards.subs.php');
			$all_mods = allBoardModerators(true);

			// Make sure they get included in the big loop.
			$members = array_keys($all_mods);
			foreach ($all_mods as $row)
				$mods[$row['id_member']][$row['id_board']] = true;
		}

		// Come along one and all... until we reject you ;)
		$request = $db->query('', '
			SELECT id_member, real_name, email_address, lngfile, id_group, additional_groups, mod_prefs
			FROM {db_prefix}members
			WHERE id_group IN ({array_int:additional_group_list})
				OR FIND_IN_SET({raw:additional_group_list_implode}, additional_groups) != 0' . (empty($members) ? '' : '
				OR id_member IN ({array_int:member_list})') . '
			ORDER BY lngfile',
			array(
				'additional_group_list' => $addGroups,
				'member_list' => $members,
				'additional_group_list_implode' => implode(', additional_groups) != 0 OR FIND_IN_SET(', $addGroups),
			)
		);
		$members = array();
		while ($row = $db->fetch_assoc($request))
		{
			// Check whether they are interested.
			if (!empty($row['mod_prefs']))
			{
				list (,, $pref_binary) = explode('|', $row['mod_prefs']);
				if (!($pref_binary & 4))
					continue;
			}

			$members[$row['id_member']] = array(
				'id' => $row['id_member'],
				'groups' => array_merge(explode(',', $row['additional_groups']), array($row['id_group'])),
				'language' => $row['lngfile'],
				'email' => $row['email_address'],
				'name' => $row['real_name'],
			);
		}
		$db->free_result($request);

		// Get the mailing stuff.
		require_once(SUBSDIR . '/Mail.subs.php');

		// Need the below for loadLanguage to work!
		loadEssentialThemeData();

		$current_language = '';

		// Finally, loop through each member, work out what they can do, and send it.
		foreach ($members as $id => $member)
		{
			$emailbody = '';

			// Load the language file as required.
			if (empty($current_language) || $current_language != $member['language'])
				$current_language = loadLanguage('EmailTemplates', $member['language'], false);

			// Loop through each notice...
			foreach ($notices as $board => $notice)
			{
				$access = false;

				// Can they mod in this board?
				if (isset($mods[$id][$board]))
					$access = true;

				// Do the group check...
				if (!$access && isset($perms[$profiles[$board]]['add']))
				{
					// They can access?!
					if (array_intersect($perms[$profiles[$board]]['add'], $member['groups']))
						$access = true;

					// If they have deny rights don't consider them!
					if (isset($perms[$profiles[$board]]['deny']))
						if (array_intersect($perms[$profiles[$board]]['deny'], $member['groups']))
							$access = false;
				}

				// Finally, fix it for admins!
				if (in_array(1, $member['groups']))
					$access = true;

				// If they can't access it then give it a break!
				if (!$access)
					continue;

				foreach ($notice as $type => $items)
				{
					// Build up the top of this section.
					$emailbody .= $txt['scheduled_approval_email_' . $type] . "\n" .
						'------------------------------------------------------' . "\n";

					foreach ($items as $item)
						$emailbody .= $item['subject'] . ' - ' . $item['href'] . "\n";

					$emailbody .= "\n";
				}
			}

			if ($emailbody == '')
				continue;

			$replacements = array(
				'REALNAME' => $member['name'],
				'BODY' => $emailbody,
			);

			$emaildata = loadEmailTemplate('scheduled_approval', $replacements, $current_language);

			// Send the actual email.
			sendmail($member['email'], $emaildata['subject'], $emaildata['body'], null, null, false, 2);
		}

		// All went well!
		return true;
	}

	/**
	 * This function does daily cleaning up:
	 *
	 * - decrements warning levels if it's enabled
	 * - consolidate spider statistics
	 * - fix MySQL version
	 * - regenerate Diffie-Hellman keys for OpenID
	 * - remove obsolete login history logs
	 */
	public function daily_maintenance()
	{
		global $modSettings, $db_type;

		$db = database();

		// First clean out the cache.
		clean_cache();

		// If warning decrement is enabled and we have people who have not had a new warning in 24 hours, lower their warning level.
		list (, , $modSettings['warning_decrement']) = explode(',', $modSettings['warning_settings']);
		if ($modSettings['warning_decrement'])
		{
			// Find every member who has a warning level...
			$request = $db->query('', '
				SELECT id_member, warning
				FROM {db_prefix}members
				WHERE warning > {int:no_warning}',
				array(
					'no_warning' => 0,
				)
			);
			$members = array();
			while ($row = $db->fetch_assoc($request))
				$members[$row['id_member']] = $row['warning'];
			$db->free_result($request);

			// Have some members to check?
			if (!empty($members))
			{
				// Find out when they were last warned.
				$request = $db->query('', '
					SELECT id_recipient, MAX(log_time) AS last_warning
					FROM {db_prefix}log_comments
					WHERE id_recipient IN ({array_int:member_list})
						AND comment_type = {string:warning}
					GROUP BY id_recipient',
					array(
						'member_list' => array_keys($members),
						'warning' => 'warning',
					)
				);
				$member_changes = array();
				while ($row = $db->fetch_assoc($request))
				{
					// More than 24 hours ago?
					if ($row['last_warning'] <= time() - 86400)
						$member_changes[] = array(
							'id' => $row['id_recipient'],
							'warning' => $members[$row['id_recipient']] >= $modSettings['warning_decrement'] ? $members[$row['id_recipient']] - $modSettings['warning_decrement'] : 0,
						);
				}
				$db->free_result($request);

				// Have some members to change?
				if (!empty($member_changes))
					foreach ($member_changes as $change)
						updateMemberData($change['id'], array('warning' => $change['warning']));
			}
		}

		// Do any spider stuff.
		if (!empty($modSettings['spider_mode']) && $modSettings['spider_mode'] > 1)
		{
			// We'll need this.
			require_once(SUBSDIR . '/SearchEngines.subs.php');
			consolidateSpiderStats();
		}

		// Check the database version - for some buggy MySQL version.
		$server_version = $db->db_server_info();
		if ($db_type == 'mysql' && in_array(substr($server_version, 0, 6), array('5.0.50', '5.0.51')))
			updateSettings(array('db_mysql_group_by_fix' => '1'));
		elseif (!empty($modSettings['db_mysql_group_by_fix']))
			$db->query('', '
				DELETE FROM {db_prefix}settings
				WHERE variable = {string:mysql_fix}',
				array(
					'mysql_fix' => 'db_mysql_group_by_fix',
				)
			);

		// Regenerate the Diffie-Hellman keys if OpenID is enabled.
		if (!empty($modSettings['enableOpenID']))
		{
			require_once(SUBSDIR . '/OpenID.subs.php');
			$openID = new OpenID();
			$openID->setup_DH(true);
		}
		elseif (!empty($modSettings['dh_keys']))
			$db->query('', '
				DELETE FROM {db_prefix}settings
				WHERE variable = {string:dh_keys}',
				array(
					'dh_keys' => 'dh_keys',
				)
			);

		// Clean up some old login history information.
		$db->query('', '
			DELETE FROM {db_prefix}member_logins
			WHERE time > {int:oldLogins}',
			array(
				'oldLogins' => !empty($modSettings['loginHistoryDays']) ? 60 * 60 * $modSettings['loginHistoryDays'] : 108000,
		));

		// Log we've done it...
		return true;
	}

	/**
	 * Auto optimize the database.
	 */
	public function auto_optimize()
	{
		global $modSettings, $db_prefix;

		// we're working with them databases but we shouldn't :P
		$db = database();

		// By default do it now!
		$delay = false;

		// As a kind of hack, if the server load is too great delay, but only by a bit!
		if (!empty($modSettings['load_average']) && !empty($modSettings['loadavg_auto_opt']) && $modSettings['load_average'] >= $modSettings['loadavg_auto_opt'])
			$delay = true;

		// Otherwise are we restricting the number of people online for this?
		if (!empty($modSettings['autoOptMaxOnline']))
		{
			$request = $db->query('', '
				SELECT COUNT(*)
				FROM {db_prefix}log_online',
				array(
				)
			);
			list ($dont_do_it) = $db->fetch_row($request);
			$db->free_result($request);

			if ($dont_do_it > $modSettings['autoOptMaxOnline'])
				$delay = true;
		}

		// If we are gonna delay, do so now!
		if ($delay)
			return false;

		// Get all the tables.
		$tables = $db->db_list_tables(false, $db_prefix . '%');

		foreach ($tables as $table)
			$db->db_optimize_table($table);

		// Return for the log...
		return true;
	}

	/**
	 * Send out a daily email of all subscribed topics, to members.
	 *
	 * - It sends notifications about replies or new topics,
	 * and moderation actions.
	 */
	public function daily_digest()
	{
		global $is_weekly, $txt, $mbname, $scripturl, $modSettings, $boardurl;

		$db = database();

		// We'll want this...
		require_once(SUBSDIR . '/Mail.subs.php');
		loadEssentialThemeData();

		// If the maillist function is on then so is the enhanced digest
		$maillist = !empty($modSettings['maillist_enabled']) && !empty($modSettings['pbe_digest_enabled']);
		if ($maillist)
			require_once(SUBSDIR . '/Emailpost.subs.php');

		$is_weekly = !empty($is_weekly) ? 1 : 0;

		// Right - get all the notification data FIRST.
		$request = $db->query('', '
			SELECT ln.id_topic, COALESCE(t.id_board, ln.id_board) AS id_board, mem.email_address, mem.member_name, mem.real_name, mem.notify_types,
				mem.lngfile, mem.id_member
			FROM {db_prefix}log_notify AS ln
				INNER JOIN {db_prefix}members AS mem ON (mem.id_member = ln.id_member)
				LEFT JOIN {db_prefix}topics AS t ON (ln.id_topic != {int:empty_topic} AND t.id_topic = ln.id_topic)
			WHERE mem.notify_regularity = {int:notify_regularity}
				AND mem.is_activated = {int:is_activated}',
			array(
				'empty_topic' => 0,
				'notify_regularity' => $is_weekly ? '3' : '2',
				'is_activated' => 1,
			)
		);
		$members = array();
		$langs = array();
		$notify = array();
		$boards = array();
		while ($row = $db->fetch_assoc($request))
		{
			if (!isset($members[$row['id_member']]))
			{
				$members[$row['id_member']] = array(
					'email' => $row['email_address'],
					'name' => ($row['real_name'] == '') ? $row['member_name'] : un_htmlspecialchars($row['real_name']),
					'id' => $row['id_member'],
					'notifyMod' => $row['notify_types'] < 3 ? true : false,
					'lang' => $row['lngfile'],
				);
				$langs[$row['lngfile']] = $row['lngfile'];
			}

			// Store this useful data!
			$boards[$row['id_board']] = $row['id_board'];
			if ($row['id_topic'])
				$notify['topics'][$row['id_topic']][] = $row['id_member'];
			else
				$notify['boards'][$row['id_board']][] = $row['id_member'];
		}
		$db->free_result($request);

		if (empty($boards))
			return true;

		// Just get the board names.
		require_once(SUBSDIR . '/Boards.subs.php');
		$boards = fetchBoardsInfo(array('boards' => $boards), array('override_permissions' => true));

		if (empty($boards))
			return true;

		// Get the actual topics...
		$request = $db->query('', '
			SELECT ld.note_type, t.id_topic, t.id_board, t.id_member_started, m.id_msg, m.subject, m.body, ld.id_msg AS last_reply,
				b.name AS board_name, ml.body as last_body
			FROM {db_prefix}log_digest AS ld
				INNER JOIN {db_prefix}topics AS t ON (t.id_topic = ld.id_topic
					AND t.id_board IN ({array_int:board_list}))
				INNER JOIN {db_prefix}messages AS m ON (m.id_msg = t.id_first_msg)
				INNER JOIN {db_prefix}messages AS ml ON (ml.id_msg = ld.id_msg)
				INNER JOIN {db_prefix}boards AS b ON (b.id_board = t.id_board)
			WHERE ' . ($is_weekly ? 'ld.daily != {int:daily_value}' : 'ld.daily IN (0, 2)'),
			array(
				'board_list' => array_keys($boards),
				'daily_value' => 2,
			)
		);
		$types = array();
		while ($row = $db->fetch_assoc($request))
		{
			if (!isset($types[$row['note_type']][$row['id_board']]))
				$types[$row['note_type']][$row['id_board']] = array(
					'lines' => array(),
					'name' => un_htmlspecialchars($row['board_name']),
					'id' => $row['id_board'],
				);

			// A reply has been made
			if ($row['note_type'] === 'reply')
			{
				// More than one reply to this topic?
				if (isset($types[$row['note_type']][$row['id_board']]['lines'][$row['id_topic']]))
				{
					$types[$row['note_type']][$row['id_board']]['lines'][$row['id_topic']]['count']++;

					// keep track of the highest numbered reply and body text for this topic ...
					if ($types[$row['note_type']][$row['id_board']]['lines'][$row['id_topic']]['body_id'] < $row['last_reply'])
					{
						$types[$row['note_type']][$row['id_board']]['lines'][$row['id_topic']]['body_id'] = $row['last_reply'];
						$types[$row['note_type']][$row['id_board']]['lines'][$row['id_topic']]['body_text'] = $row['last_body'];
					}
				}
				else
				{
					// First time we have seen a reply to this topic, so load our array
					$types[$row['note_type']][$row['id_board']]['lines'][$row['id_topic']] = array(
						'id' => $row['id_topic'],
						'subject' => un_htmlspecialchars($row['subject']),
						'link' => $scripturl . '?topic=' . $row['id_topic'] . '.new;topicseen#new',
						'count' => 1,
						'body_id' => $row['last_reply'],
						'body_text' => $row['last_body'],
					);
				}
			}
			// New topics are good too
			elseif ($row['note_type'] === 'topic')
			{
				if ($maillist)
				{
					// Convert to markdown markup e.g. text ;)
					pbe_prepare_text($row['body']);
					$row['body'] = Util::shorten_text($row['body'], !empty($modSettings['digest_preview_length']) ? $modSettings['digest_preview_length'] : 375, true);
					$row['body'] = preg_replace("~\n~s", "\n  ", $row['body']);
				}

				// Topics are simple since we are only concerned with the first post
				if (!isset($types[$row['note_type']][$row['id_board']]['lines'][$row['id_topic']]))
					$types[$row['note_type']][$row['id_board']]['lines'][$row['id_topic']] = array(
						'id' => $row['id_topic'],
						'link' => $scripturl . '?topic=' . $row['id_topic'] . '.new;topicseen#new',
						'subject' => un_htmlspecialchars($row['subject']),
						'body' => $row['body'],
					);
			}
			elseif ($maillist && empty($modSettings['pbe_no_mod_notices']))
			{
				if (!isset($types[$row['note_type']][$row['id_board']]['lines'][$row['id_topic']]))
					$types[$row['note_type']][$row['id_board']]['lines'][$row['id_topic']] = array(
						'id' => $row['id_topic'],
						'subject' => un_htmlspecialchars($row['subject']),
						'starter' => $row['id_member_started'],
					);
			}

			$types[$row['note_type']][$row['id_board']]['lines'][$row['id_topic']]['members'] = array();

			if (!empty($notify['topics'][$row['id_topic']]))
				$types[$row['note_type']][$row['id_board']]['lines'][$row['id_topic']]['members'] = array_merge($types[$row['note_type']][$row['id_board']]['lines'][$row['id_topic']]['members'], $notify['topics'][$row['id_topic']]);

			if (!empty($notify['boards'][$row['id_board']]))
				$types[$row['note_type']][$row['id_board']]['lines'][$row['id_topic']]['members'] = array_merge($types[$row['note_type']][$row['id_board']]['lines'][$row['id_topic']]['members'], $notify['boards'][$row['id_board']]);
		}
		$db->free_result($request);

		if (empty($types))
			return true;

		// Fix the last reply message so its suitable for previewing
		if ($maillist)
		{
			foreach ($types['reply'] as $id => $board)
			{
				foreach ($board['lines'] as $topic)
				{
					// Replace the body array with the appropriate preview message
					$body = $types['reply'][$id]['lines'][$topic['id']]['body_text'];
					pbe_prepare_text($body);
					$body = Util::shorten_text($body, !empty($modSettings['digest_preview_length']) ? $modSettings['digest_preview_length'] : 375, true);
					$body = preg_replace("~\n~s", "\n  ", $body);
					$types['reply'][$id]['lines'][$topic['id']]['body'] = $body;

					unset($types['reply'][$id]['lines'][$topic['id']]['body_text'], $body);
				}
			}
		}

		// Let's load all the languages into a cache thingy.
		$langtxt = array();
		foreach ($langs as $lang)
		{
			loadLanguage('Post', $lang);
			loadLanguage('index', $lang);
			loadLanguage('Maillist', $lang);
			loadLanguage('EmailTemplates', $lang);

			$langtxt[$lang] = array(
				'subject' => $txt['digest_subject_' . ($is_weekly ? 'weekly' : 'daily')],
				'char_set' => 'UTF-8',
				'intro' => sprintf($txt['digest_intro_' . ($is_weekly ? 'weekly' : 'daily')], $mbname),
				'new_topics' => $txt['digest_new_topics'],
				'topic_lines' => $txt['digest_new_topics_line'],
				'new_replies' => $txt['digest_new_replies'],
				'mod_actions' => $txt['digest_mod_actions'],
				'replies_one' => $txt['digest_new_replies_one'],
				'replies_many' => $txt['digest_new_replies_many'],
				'sticky' => $txt['digest_mod_act_sticky'],
				'lock' => $txt['digest_mod_act_lock'],
				'unlock' => $txt['digest_mod_act_unlock'],
				'remove' => $txt['digest_mod_act_remove'],
				'move' => $txt['digest_mod_act_move'],
				'merge' => $txt['digest_mod_act_merge'],
				'split' => $txt['digest_mod_act_split'],
				'bye' => (!empty($modSettings['maillist_sitename_regards']) ? $modSettings['maillist_sitename_regards'] : '') . "\n" . $boardurl,
				'preview' => $txt['digest_preview'],
				'see_full' => $txt['digest_see_full'],
				'reply_preview' => $txt['digest_reply_preview'],
				'unread_reply_link' => $txt['digest_unread_reply_link'],
				);
		}

		// Right - send out the silly things - this will take quite some space!
		foreach ($members as $mid => $member)
		{
			// Do the start stuff!
			$email = array(
				'subject' => $mbname . ' - ' . $langtxt[$lang]['subject'],
				'body' => $member['name'] . ',' . "\n\n" . $langtxt[$lang]['intro'] . "\n" . $scripturl . '?action=profile;area=notification;u=' . $member['id'] . "\n",
				'email' => $member['email'],
			);

			// All the new topics
			if (isset($types['topic']))
			{
				$titled = false;

				// Each type contains a board ID and then a topic number
				foreach ($types['topic'] as $id => $board)
				{
					foreach ($board['lines'] as $topic)
					{
						// They have requested notification for new topics in this board
						if (in_array($mid, $topic['members']))
						{
							// Start of the new topics with a heading bar
							if (!$titled)
							{
								$email['body'] .= "\n" . $langtxt[$lang]['new_topics'] . ':' . "\n" . str_repeat('-', 78);
								$titled = true;
							}

							$email['body'] .= "\n" . sprintf($langtxt[$lang]['topic_lines'], $topic['subject'], $board['name']);
							if ($maillist)
								$email['body'] .= $langtxt[$lang]['preview'] . $topic['body'] . $langtxt[$lang]['see_full'] . $topic['link'] . "\n";
						}
					}
				}

				if ($titled)
					$email['body'] .= "\n";
			}

			// What about replies?
			if (isset($types['reply']))
			{
				$titled = false;

				// Each reply will have a board id and then a topic ID
				foreach ($types['reply'] as $id => $board)
				{
					foreach ($board['lines'] as $topic)
					{
						// This member wants notices on replys to this topic
						if (in_array($mid, $topic['members']))
						{
							// First one in the section gets a nice heading
							if (!$titled)
							{
								$email['body'] .= "\n" . $langtxt[$lang]['new_replies'] . ':' . "\n" . str_repeat('-', 78);
								$titled = true;
							}

							$email['body'] .= "\n" . ($topic['count'] === 1 ? sprintf($langtxt[$lang]['replies_one'], $topic['subject']) : sprintf($langtxt[$lang]['replies_many'], $topic['count'], $topic['subject']));
							if ($maillist)
								$email['body'] .= $langtxt[$lang]['reply_preview'] . $topic['body'] . $langtxt[$lang]['unread_reply_link'] . $topic['link'] . "\n";
						}
					}
				}

				if ($titled)
					$email['body'] .= "\n";
			}

			// Finally, moderation actions!
			$titled = false;
			foreach ($types as $note_type => $type)
			{
				if ($note_type === 'topic' || $note_type === 'reply')
					continue;

				foreach ($type as $id => $board)
				{
					foreach ($board['lines'] as $topic)
					{
						if (in_array($mid, $topic['members']))
						{
							if (!$titled)
							{
								$email['body'] .= "\n" . $langtxt[$lang]['mod_actions'] . ':' . "\n" . str_repeat('-', 47);
								$titled = true;
							}

							$email['body'] .= "\n" . sprintf($langtxt[$lang][$note_type], $topic['subject']);
						}
					}
				}
			}

			if ($titled)
				$email['body'] .= "\n";

			// Then just say our goodbyes!
			$email['body'] .= "\n\n" .$langtxt[$lang]['bye'];

			// Send it - low priority!
			sendmail($email['email'], $email['subject'], $email['body'], null, null, false, 4);
		}

		// Using the queue, do a final flush before we say thats all folks
		if (!empty($modSettings['mail_queue']))
			AddMailQueue(true);

		// Clean up...
		if ($is_weekly)
		{
			$db->query('', '
				DELETE FROM {db_prefix}log_digest
				WHERE daily != {int:not_daily}',
				array(
					'not_daily' => 0,
				)
			);
			$db->query('', '
				UPDATE {db_prefix}log_digest
				SET daily = {int:daily_value}
				WHERE daily = {int:not_daily}',
				array(
					'daily_value' => 2,
					'not_daily' => 0,
				)
			);
		}
		else
		{
			// Clear any only weekly ones, and stop us from sending daily again.
			$db->query('', '
				DELETE FROM {db_prefix}log_digest
				WHERE daily = {int:daily_value}',
				array(
					'daily_value' => 2,
				)
			);
			$db->query('', '
				UPDATE {db_prefix}log_digest
				SET daily = {int:both_value}
				WHERE daily = {int:no_value}',
				array(
					'both_value' => 1,
					'no_value' => 0,
				)
			);
		}

		// Just in case the member changes their settings mark this as sent.
		$members = array_keys($members);
		$db->query('', '
			UPDATE {db_prefix}log_notify
			SET sent = {int:is_sent}
			WHERE id_member IN ({array_int:member_list})',
			array(
				'member_list' => $members,
				'is_sent' => 1,
			)
		);

		// Log we've done it...
		return true;
	}

	/**
	 * Sends out email notifications for new/updated topics.
	 *
	 * - Like the daily stuff - just seven times less regular ;)
	 * - This method forwards to daily_digest()
	 */
	public function weekly_digest()
	{
		global $is_weekly;

		// We just pass through to the daily function - avoid duplication!
		$is_weekly = true;
		return $this->daily_digest();
	}

	/**
	 * This task retrieved files from the official server.
	 * This task is no longer used and the method remains only to avoid
	 * "last minute" problems, it will be removed from 1.1 version
	 *
	 * @deprecated since 1.0 - will be removed in 1.1
	 */
	public function fetchFiles()
	{
		return true;
	}

	/**
	 * Schedule birthday emails.
	 * (aka "Happy birthday!!")
	 */
	public function birthdayemails()
	{
		global $modSettings, $txt, $txtBirthdayEmails;

		$db = database();

		// Need this in order to load the language files.
		loadEssentialThemeData();

		// Going to need this to send the emails.
		require_once(SUBSDIR . '/Mail.subs.php');

		$greeting = isset($modSettings['birthday_email']) ? $modSettings['birthday_email'] : 'happy_birthday';

		// Get the month and day of today.
		$month = date('n'); // Month without leading zeros.
		$day = date('j'); // Day without leading zeros.

		// So who are the lucky ones?  Don't include those who are banned and those who don't want them.
		$result = $db->query('', '
			SELECT id_member, real_name, lngfile, email_address
			FROM {db_prefix}members
			WHERE is_activated < 10
				AND MONTH(birthdate) = {int:month}
				AND DAYOFMONTH(birthdate) = {int:day}
				AND notify_announcements = {int:notify_announcements}
				AND YEAR(birthdate) > {int:year}',
			array(
				'notify_announcements' => 1,
				'year' => 1,
				'month' => $month,
				'day' => $day,
			)
		);

		// Group them by languages.
		$birthdays = array();
		while ($row = $db->fetch_assoc($result))
		{
			if (!isset($birthdays[$row['lngfile']]))
				$birthdays[$row['lngfile']] = array();
			$birthdays[$row['lngfile']][$row['id_member']] = array(
				'name' => $row['real_name'],
				'email' => $row['email_address']
			);
		}
		$db->free_result($result);

		// Send out the greetings!
		foreach ($birthdays as $lang => $recps)
		{
			// We need to do some shuffling to make this work properly.
			loadLanguage('EmailTemplates', $lang);
			$txt['emails']['happy_birthday']['subject'] = $txtBirthdayEmails[$greeting . '_subject'];
			$txt['emails']['happy_birthday']['body'] = $txtBirthdayEmails[$greeting . '_body'];

			foreach ($recps as $recp)
			{
				$replacements = array(
					'REALNAME' => $recp['name'],
				);

				$emaildata = loadEmailTemplate('happy_birthday', $replacements, $lang, false);

				sendmail($recp['email'], $emaildata['subject'], $emaildata['body'], null, null, false, 4);

				// Try to stop a timeout, this would be bad...
				@set_time_limit(300);
				if (function_exists('apache_reset_timeout'))
					@apache_reset_timeout();

			}
		}

		// Flush the mail queue, just in case.
		AddMailQueue(true);

		return true;
	}

	/**
	 * Weekly maintenance taks
	 *
	 * What it does:
	 * - remove empty or temporary settings
	 * - prune logs
	 * - obsolete paid subscriptions
	 * - clear sessions table
	 */
	public function weekly_maintenance()
	{
		global $modSettings;

		$db = database();

		// Delete some settings that needn't be set if they are otherwise empty.
		$emptySettings = array(
			'warning_mute', 'warning_moderate', 'warning_watch', 'warning_show', 'disableCustomPerPage', 'spider_mode', 'spider_group',
			'paid_currency_code', 'paid_currency_symbol', 'paid_email_to', 'paid_email', 'paid_enabled', 'paypal_email',
			'search_enable_captcha', 'search_floodcontrol_time', 'show_spider_online',
		);

		$db->query('', '
			DELETE FROM {db_prefix}settings
			WHERE variable IN ({array_string:setting_list})
				AND (value = {string:zero_value} OR value = {string:blank_value})',
			array(
				'zero_value' => '0',
				'blank_value' => '',
				'setting_list' => $emptySettings,
			)
		);

		// Some settings we never want to keep - they are just there for temporary purposes.
		$deleteAnywaySettings = array(
			'attachment_full_notified',
		);

		$db->query('', '
			DELETE FROM {db_prefix}settings
			WHERE variable IN ({array_string:setting_list})',
			array(
				'setting_list' => $deleteAnywaySettings,
			)
		);

		// Ok should we prune the logs?
		if (!empty($modSettings['pruningOptions']))
		{
			if (!empty($modSettings['pruningOptions']) && strpos($modSettings['pruningOptions'], ',') !== false)
				list ($modSettings['pruneErrorLog'], $modSettings['pruneModLog'], $modSettings['pruneBanLog'], $modSettings['pruneReportLog'], $modSettings['pruneScheduledTaskLog'], $modSettings['pruneSpiderHitLog']) = explode(',', $modSettings['pruningOptions']);

			if (!empty($modSettings['pruneErrorLog']))
			{
				// Figure out when our cutoff time is.  1 day = 86400 seconds.
				$t = time() - $modSettings['pruneErrorLog'] * 86400;

				$db->query('', '
					DELETE FROM {db_prefix}log_errors
					WHERE log_time < {int:log_time}',
					array(
						'log_time' => $t,
					)
				);
			}

			if (!empty($modSettings['pruneModLog']))
			{
				// Figure out when our cutoff time is.  1 day = 86400 seconds.
				$t = time() - $modSettings['pruneModLog'] * 86400;

				$db->query('', '
					DELETE FROM {db_prefix}log_actions
					WHERE log_time < {int:log_time}
						AND id_log = {int:moderation_log}',
					array(
						'log_time' => $t,
						'moderation_log' => 1,
					)
				);
			}

			if (!empty($modSettings['pruneBanLog']))
			{
				// Figure out when our cutoff time is.  1 day = 86400 seconds.
				$t = time() - $modSettings['pruneBanLog'] * 86400;

				$db->query('', '
					DELETE FROM {db_prefix}log_banned
					WHERE log_time < {int:log_time}',
					array(
						'log_time' => $t,
					)
				);
			}

			if (!empty($modSettings['pruneBadbehaviorLog']))
			{
				// Figure out when our cutoff time is.  1 day = 86400 seconds.
				$t = time() - $modSettings['pruneBadbehaviorLog'] * 86400;

				$db->query('', '
					DELETE FROM {db_prefix}log_badbehavior
					WHERE log_time < {int:log_time}',
					array(
						'log_time' => $t,
					)
				);
			}

			if (!empty($modSettings['pruneReportLog']))
			{
				// Figure out when our cutoff time is.  1 day = 86400 seconds.
				$t = time() - $modSettings['pruneReportLog'] * 86400;

				// This one is more complex then the other logs.  First we need to figure out which reports are too old.
				$reports = array();
				$result = $db->query('', '
					SELECT id_report
					FROM {db_prefix}log_reported
					WHERE time_started < {int:time_started}
						AND closed = {int:not_closed}
						AND ignore_all = {int:not_ignored}',
					array(
						'time_started' => $t,
						'not_closed' => 0,
						'not_ignored' => 0,
					)
				);
				while ($row = $db->fetch_row($result))
					$reports[] = $row[0];
				$db->free_result($result);

				if (!empty($reports))
				{
					// Now delete the reports...
					$db->query('', '
						DELETE FROM {db_prefix}log_reported
						WHERE id_report IN ({array_int:report_list})',
						array(
							'report_list' => $reports,
						)
					);
					// And delete the comments for those reports...
					$db->query('', '
						DELETE FROM {db_prefix}log_reported_comments
						WHERE id_report IN ({array_int:report_list})',
						array(
							'report_list' => $reports,
						)
					);
				}
			}

			if (!empty($modSettings['pruneScheduledTaskLog']))
			{
				// Figure out when our cutoff time is.  1 day = 86400 seconds.
				$t = time() - $modSettings['pruneScheduledTaskLog'] * 86400;

				$db->query('', '
					DELETE FROM {db_prefix}log_scheduled_tasks
					WHERE time_run < {int:time_run}',
					array(
						'time_run' => $t,
					)
				);
			}

			if (!empty($modSettings['pruneSpiderHitLog']))
			{
				// Figure out when our cutoff time is.  1 day = 86400 seconds.
				$t = time() - $modSettings['pruneSpiderHitLog'] * 86400;

				require_once(SUBSDIR . '/SearchEngines.subs.php');
				removeSpiderOldLogs($t);
			}
		}

		// Get rid of any paid subscriptions that were never actioned.
		$db->query('', '
			DELETE FROM {db_prefix}log_subscribed
			WHERE end_time = {int:no_end_time}
				AND status = {int:not_active}
				AND start_time < {int:start_time}
				AND payments_pending < {int:payments_pending}',
			array(
				'no_end_time' => 0,
				'not_active' => 0,
				'start_time' => time() - 60,
				'payments_pending' => 1,
			)
		);

		// Some OS's don't seem to clean out their sessions.
		$db->query('', '
			DELETE FROM {db_prefix}sessions
			WHERE last_update < {int:last_update}',
			array(
				'last_update' => time() - 86400,
			)
		);

		return true;
	}

	/**
	 * Perform the standard checks on expiring/near expiring subscriptions:
	 *
	 * - remove expired subscriptions
	 * - notify of subscriptions about to expire
	 */
	public function paid_subscriptions()
	{
		global $scripturl, $modSettings, $language;

		$db = database();

		// Start off by checking for removed subscriptions.
		$request = $db->query('', '
			SELECT id_subscribe, id_member
			FROM {db_prefix}log_subscribed
			WHERE status = {int:is_active}
				AND end_time < {int:time_now}',
			array(
				'is_active' => 1,
				'time_now' => time(),
			)
		);
		while ($row = $db->fetch_assoc($request))
		{
			require_once(ADMINDIR . '/ManagePaid.controller.php');
			removeSubscription($row['id_subscribe'], $row['id_member']);
		}
		$db->free_result($request);

		// Get all those about to expire that have not had a reminder sent.
		$request = $db->query('', '
			SELECT ls.id_sublog, m.id_member, m.member_name, m.email_address, m.lngfile, s.name, ls.end_time
			FROM {db_prefix}log_subscribed AS ls
				INNER JOIN {db_prefix}subscriptions AS s ON (s.id_subscribe = ls.id_subscribe)
				INNER JOIN {db_prefix}members AS m ON (m.id_member = ls.id_member)
			WHERE ls.status = {int:is_active}
				AND ls.reminder_sent = {int:reminder_sent}
				AND s.reminder > {int:reminder_wanted}
				AND ls.end_time < ({int:time_now} + s.reminder * 86400)',
			array(
				'is_active' => 1,
				'reminder_sent' => 0,
				'reminder_wanted' => 0,
				'time_now' => time(),
			)
		);
		$subs_reminded = array();
		while ($row = $db->fetch_assoc($request))
		{
			// If this is the first one load the important bits.
			if (empty($subs_reminded))
			{
				require_once(SUBSDIR . '/Mail.subs.php');
				// Need the below for loadLanguage to work!
				loadEssentialThemeData();
			}

			$subs_reminded[] = $row['id_sublog'];

			$replacements = array(
				'PROFILE_LINK' => $scripturl . '?action=profile;area=subscriptions;u=' . $row['id_member'],
				'REALNAME' => $row['member_name'],
				'SUBSCRIPTION' => $row['name'],
				'END_DATE' => strip_tags(standardTime($row['end_time'])),
			);

			$emaildata = loadEmailTemplate('paid_subscription_reminder', $replacements, empty($row['lngfile']) || empty($modSettings['userLanguage']) ? $language : $row['lngfile']);

			// Send the actual email.
			sendmail($row['email_address'], $emaildata['subject'], $emaildata['body'], null, null, false, 2);
		}
		$db->free_result($request);

		// Mark the reminder as sent.
		if (!empty($subs_reminded))
			$db->query('', '
				UPDATE {db_prefix}log_subscribed
				SET reminder_sent = {int:reminder_sent}
				WHERE id_sublog IN ({array_int:subscription_list})',
				array(
					'subscription_list' => $subs_reminded,
					'reminder_sent' => 1,
				)
			);

		return true;
	}

	/**
	 * Check for un-posted attachments is something we can do once in a while :P
	 *
	 * - This function uses opendir cycling through all the attachments
	 */
	public function remove_temp_attachments()
	{
		global $context, $txt;

		// We need to know where this thing is going.
		require_once(SUBSDIR . '/ManageAttachments.subs.php');
		$attach_dirs = attachmentPaths();

		foreach ($attach_dirs as $attach_dir)
		{
			$dir = @opendir($attach_dir);
			if (!$dir)
			{
				loadEssentialThemeData();
				loadLanguage('Post');

				$context['scheduled_errors']['remove_temp_attachments'][] = $txt['cant_access_upload_path'] . ' (' . $attach_dir . ')';
				log_error($txt['cant_access_upload_path'] . ' (' . $attach_dir . ')', 'critical');

				return false;
			}

			while ($file = readdir($dir))
			{
				if ($file == '.' || $file == '..')
					continue;

				if (strpos($file, 'post_tmp_') !== false)
				{
					// Temp file is more than 5 hours old!
					if (filemtime($attach_dir . '/' . $file) < time() - 18000)
						@unlink($attach_dir . '/' . $file);
				}
			}
			closedir($dir);
		}

		return true;
	}

	/**
	 * Check for move topic notices that have past their best by date:
	 *
	 * - remove them if the time has expired.
	 */
	public function remove_topic_redirect()
	{
		$db = database();

		// Init
		$topics = array();

		// We will need this for lanaguage files
		loadEssentialThemeData();

		// Find all of the old MOVE topic notices that were set to expire
		$request = $db->query('', '
			SELECT id_topic
			FROM {db_prefix}topics
			WHERE redirect_expires <= {int:redirect_expires}
				AND redirect_expires <> 0',
			array(
				'redirect_expires' => time(),
			)
		);

		while ($row = $db->fetch_row($request))
			$topics[] = $row[0];
		$db->free_result($request);

		// Zap, you're gone
		if (count($topics) > 0)
		{
			require_once(SUBSDIR . '/Topic.subs.php');
			removeTopics($topics, false, true);
		}

		return true;
	}

	/**
	 * Check for old drafts and remove them
	 */
	public function remove_old_drafts()
	{
		global $modSettings;

		$db = database();

		if (empty($modSettings['drafts_keep_days']))
			return true;

		// init
		$drafts = array();

		// We need this for language items
		loadEssentialThemeData();

		// Find all of the old drafts
		$request = $db->query('', '
			SELECT id_draft
			FROM {db_prefix}user_drafts
			WHERE poster_time <= {int:poster_time_old}',
			array(
				'poster_time_old' => time() - (86400 * $modSettings['drafts_keep_days']),
			)
		);
		while ($row = $db->fetch_row($request))
			$drafts[] = (int) $row[0];
		$db->free_result($request);

		// If we have old one, remove them
		if (count($drafts) > 0)
		{
			require_once(SUBSDIR . '/Drafts.subs.php');
			deleteDrafts($drafts, -1, false);
		}

		return true;
	}

	/**
	 * Fetch emails from an imap box and process them
	 *
	 * - If we can't run this via cron, run it as a task instead
	 */
	public function maillist_fetch_IMAP()
	{
		// Only should be run if the user can't set up a proper cron job and can not pipe emails
		require_once(BOARDDIR . '/email_imap_cron.php');

		return true;
	}

	/**
	 * Check for followups from removed topics and remove them from the table
	 */
	public function remove_old_followups()
	{
		global $modSettings;

		if (empty($modSettings['enableFollowup']))
			return;

		$db = database();

		$request = $db->query('', '
			SELECT fu.derived_from
			FROM {db_prefix}follow_ups AS fu
				LEFT JOIN {db_prefix}messages AS m ON (fu.derived_from = m.id_msg)
			WHERE m.id_msg IS NULL
			LIMIT {int:limit}',
			array(
				'limit' => 100,
			)
		);

		$remove = array();
		while ($row = $db->fetch_assoc($request))
			$remove[] = $row['derived_from'];
		$db->free_result($request);

		if (empty($remove))
			return true;

		require_once(SUBSDIR . '/FollowUps.subs.php');
		removeFollowUpsByMessage($remove);

		return true;
	}

	/**
	 * Re-syncs if a user can access a mention,
	 *
	 * - for example if they loose or gain access to a board, this will correct
	 * the viewing of the mention table.  Since this can be a large job it is run
	 * as a scheduled immediate task
	 */
	public function user_access_mentions()
	{
		global $modSettings;

		$db = database();
		$user_access_mentions = @unserialize($modSettings['user_access_mentions']);

		// This should be set only because of an immediate scheduled task, so higher priority
		if (!empty($user_access_mentions))
		{
			foreach ($user_access_mentions as $member => $begin)
			{
				// Just to stay on the safe side...
				if (empty($member))
					continue;

				require_once(SUBSDIR . '/Boards.subs.php');
				require_once(SUBSDIR . '/Mentions.subs.php');
				require_once(SUBSDIR . '/Members.subs.php');

				$user_see_board = memberQuerySeeBoard($member);
				$limit = 100;

				// We need to repeat this twice: once to find the boards the user can access,
				// once for those he cannot access
				foreach (array('can', 'cannot') as $can)
				{
					// Let's always start from the begin
					$start = $begin;

					while (true)
					{
						// Find all the mentions that this user can or cannot see
						$request = $db->query('', '
							SELECT mnt.id_mention
							FROM {db_prefix}log_mentions as mnt
								LEFT JOIN {db_prefix}messages AS m ON (m.id_msg = mnt.id_msg)
								LEFT JOIN {db_prefix}boards AS b ON (b.id_board = m.id_board)
							WHERE mnt.id_member = {int:current_member}
								AND mnt.mention_type IN ({array_string:mention_types})
								AND {raw:user_see_board}
							LIMIT {int:start}, {int:limit}',
							array(
								'current_member' => $member,
								'mention_types' => array('men', 'like', 'rlike'),
								'user_see_board' => ($can == 'can' ? '' : 'NOT ') . $user_see_board,
								'start' => $start,
								'limit' => $limit,
							)
						);
						$mentions = array();
						while ($row = $db->fetch_assoc($request))
							$mentions[] = $row['id_mention'];
						$db->free_result($request);

						// If we found something toggle them and increment the start for the next round
						if (!empty($mentions))
							toggleMentionsAccessibility($mentions, $can == 'can');
						// Otherwise it means we have finished with this access level for this member
						else
							break;

						// Next batch
						$start += $limit;
					}
				}

				// Drop the member
				unset($user_access_mentions[$member]);

				// And save everything for the next run
				updateSettings(array('user_access_mentions' => serialize($user_access_mentions)));

				// Count helps keep things correct
				countUserMentions(false, '', $member);

				// Run this only once for each user, it may be quite heavy, let's split up the load
				break;
			}

			// If there are no more users, scheduleTaskImmediate can be stopped
			if (empty($user_access_mentions))
				removeScheduleTaskImmediate('user_access_mentions', false);

			return true;
		}
		else
		{
			// Checks 10 users at a time, the scheduled task is set to run once per hour, so 240 users a day
			// @todo <= I know you like it Spuds! :P It may be necessary to set it to something higher.
			$limit = 10;
			$current_check = !empty($modSettings['mentions_member_check']) ? $modSettings['mentions_member_check'] : 0;

			require_once(SUBSDIR . '/Members.subs.php');
			require_once(SUBSDIR . '/Mentions.subs.php');

			// Grab users with mentions
			$request = $db->query('', '
				SELECT COUNT(DISTINCT(id_member))
				FROM {db_prefix}log_mentions
				WHERE id_member > {int:last_id_member}
					AND mention_type IN ({array_string:mention_types})',
				array(
					'last_id_member' => $current_check,
					'mention_types' => array('men', 'like', 'rlike'),
				)
			);

			list ($remaining) = $db->fetch_row($request);
			$db->free_result($request);

			if ($remaining == 0)
				$current_check = 0;

			// Grab users with mentions
			$request = $db->query('', '
				SELECT DISTINCT(id_member) as id_member
				FROM {db_prefix}log_mentions
				WHERE id_member > {int:last_id_member}
					AND mention_type IN ({array_string:mention_types})
				LIMIT {int:limit}',
				array(
					'last_id_member' => $current_check,
					'mention_types' => array('men', 'like', 'rlike'),
					'limit' => $limit,
				)
			);

			// Remember where we are
			updateSettings(array('mentions_member_check' => $current_check + $limit));

			while ($row = $db->fetch_assoc($request))
			{
				// Rebuild 'query_see_board', a lot of code duplication... :(
				$user_see_board = memberQuerySeeBoard($row['id_member']);

				// Find out if this user cannot see something that was supposed to be able to see
				$request2 = $db->query('', '
					SELECT mnt.id_mention
					FROM {db_prefix}log_mentions as mnt
						LEFT JOIN {db_prefix}messages AS m ON (m.id_msg = mnt.id_msg)
						LEFT JOIN {db_prefix}boards AS b ON (b.id_board = m.id_board)
					WHERE mnt.id_member = {int:current_member}
						AND mnt.mention_type IN ({array_string:mention_types})
						AND {raw:user_see_board}
						AND status < 0
					LIMIT 1',
					array(
						'current_member' => $row['id_member'],
						'mention_types' => array('men', 'like', 'rlike'),
						'user_see_board' => 'NOT ' . $user_see_board,
					)
				);
				// One row of results is enough: scheduleTaskImmediate!
				if ($db->num_rows($request2) == 1)
				{
					if (!empty($modSettings['user_access_mentions']))
						$modSettings['user_access_mentions'] = @unserialize($modSettings['user_access_mentions']);
					else
						$modSettings['user_access_mentions'] = array();

					// But if the member is already on the list, let's skip it
					if (!isset($modSettings['user_access_mentions'][$row['id_member']]))
					{
						$modSettings['user_access_mentions'][$row['id_member']] = 0;
						updateSettings(array('user_access_mentions' => serialize(array_unique($modSettings['user_access_mentions']))));
						scheduleTaskImmediate('user_access_mentions');
					}
				}
				$db->free_result($request2);
			}
			$db->free_result($request);

			return true;
		}
	}
}