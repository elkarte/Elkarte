<?php

/**
 * Send out emails of all subscribed topics, to members.
 *
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * This file contains code covered by:
 * copyright:	2011 Simple Machines (http://www.simplemachines.org)
 * license:  	BSD, See included LICENSE.TXT for terms and conditions.
 *
 * @version 2.0 dev
 *
 */

namespace ElkArte\ScheduledTasks\Tasks;

/**
 * Class DailyDigest - Send out a daily email of all subscribed topics, to members.
 *
 * - It sends notifications about replies or new topics, and moderation actions.
 *
 * @package ScheduledTasks
 */
class DailyDigest implements ScheduledTaskInterface
{
	/**
	 * Sends out the daily digest for all the DD subscribers
	 *
	 * @return bool
	 */
	public function run()
	{
		return $this->runDigest();
	}

	/**
	 * Send out a email of all subscribed topics, to members.
	 *
	 * - Builds email body's of topics and messages per user as defined by their
	 * notification settings
	 * - If weekly builds the weekly abridged digest
	 *
	 * @param bool $is_weekly
	 *
	 * @return bool
	 */
	public function runDigest($is_weekly = false)
	{
		global $txt, $mbname, $scripturl, $modSettings, $boardurl;

		$db = database();

		// We'll want this...
		require_once(SUBSDIR . '/Mail.subs.php');
		theme()->getTemplates()->loadEssentialThemeData();

		// If the maillist function is on then so is the enhanced digest
		$maillist = !empty($modSettings['maillist_enabled']) && !empty($modSettings['pbe_digest_enabled']);
		if ($maillist)
			require_once(SUBSDIR . '/Emailpost.subs.php');

		$is_weekly = !empty($is_weekly) ? 1 : 0;

		// Right - get all the notification data FIRST.
		$request = $db->query('', '
			SELECT 
				ln.id_topic, COALESCE(t.id_board, ln.id_board) AS id_board, 
				mem.email_address, mem.member_name, mem.real_name, mem.notify_types, mem.lngfile, mem.id_member
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
			SELECT 
				ld.note_type, ld.id_msg AS last_reply,
				t.id_topic, t.id_board, t.id_member_started, 
				m.id_msg, m.subject, m.body, 
				b.name AS board_name, 
				ml.body as last_body
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
					$row['body'] = \ElkArte\Util::shorten_text($row['body'], !empty($modSettings['digest_preview_length']) ? $modSettings['digest_preview_length'] : 375, true);
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
				{
					$types[$row['note_type']][$row['id_board']]['lines'][$row['id_topic']] = array(
						'id' => $row['id_topic'],
						'subject' => un_htmlspecialchars($row['subject']),
						'starter' => $row['id_member_started'],
					);
				}
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
					$body = \ElkArte\Util::shorten_text($body, !empty($modSettings['digest_preview_length']) ? $modSettings['digest_preview_length'] : 375, true);
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
			theme()->getTemplates()->loadLanguageFile('Post', $lang);
			theme()->getTemplates()->loadLanguageFile('index', $lang);
			theme()->getTemplates()->loadLanguageFile('Maillist', $lang);
			theme()->getTemplates()->loadLanguageFile('EmailTemplates', $lang);

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
						// This member wants notices on reply's to this topic
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
			$email['body'] .= "\n\n" . $langtxt[$lang]['bye'];

			// Send it - low priority!
			sendmail($email['email'], $email['subject'], $email['body'], null, null, false, 4);
		}

		// Using the queue, do a final flush before we say that's all folks
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
}
