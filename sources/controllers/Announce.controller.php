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
 * Handles announce topic functionality.
 *
 */

if (!defined('ELKARTE'))
	die('No access...');

class Announce_Controller
{
	/**
	 * Default (sub)action for ?action=announce
	 */
	function action_index()
	{
		loadLanguage('Post');
		// default for action=announce: action_selectgroup() function.
		$this->action_selectgroup();
	}

	/**
	 * Set up the context for the announce topic function (action=announce).
	 * This function is called before the flow is redirected to action_selectgroup() or action_send().
	 *
	 * checks the topic announcement permissions and loads the announcement template.
	 * requires the announce_topic permission.
	 * uses the ManageMembers template and Post language file.
	 */
	function pre_announce()
	{
		global $context, $txt, $topic;

		isAllowedTo('announce_topic');

		validateSession();

		if (empty($topic))
			fatal_lang_error('topic_gone', false);

		loadLanguage('Post');
		loadTemplate('Announce');

		$context['page_title'] = $txt['announce_topic'];
	}

	/**
	 * Allow a user to chose the membergroups to send the announcement to.
	 * Lets the user select the membergroups that will receive the topic announcement.
	 * Accessed by action=announce;sa=selectgroup
	 */
	function action_selectgroup()
	{
		global $txt, $context, $topic, $board, $board_info;

		$db = database();

		$groups = array_merge($board_info['groups'], array(1));
		foreach ($groups as $id => $group)
			$groups[$id] = (int) $group;

		require_once(SUBSDIR . '/Membergroups.subs.php');
		require_once(SUBSDIR . '/Topic.subs.php');
		loadTemplate('Announce');

		$context['groups'] = getGroups($groups);

		// Get the subject of the topic we're about to announce.
		$topic_info = getTopicInfo($topic, 'message');
		$context['topic_subject'] = $topic_info['subject'];

		censorText($context['announce_topic']['subject']);

		$context['move'] = isset($_REQUEST['move']) ? 1 : 0;
		$context['go_back'] = isset($_REQUEST['goback']) ? 1 : 0;

		$context['sub_template'] = 'announce';
	}

	/**
	 * Send the announcement in chunks.
	 *
	 * splits the members to be sent a topic announcement into chunks.
	 * composes notification messages in all languages needed.
	 * does the actual sending of the topic announcements in chunks.
	 * calculates a rough estimate of the percentage items sent.
	 * Accessed by action=announce;sa=send
	 */
	function action_send()
	{
		global $topic, $board, $board_info, $context, $modSettings;
		global $language, $scripturl, $txt, $user_info;

		$db = database();

		checkSession();

		$context['start'] = empty($_REQUEST['start']) ? 0 : (int) $_REQUEST['start'];
		$groups = array_merge($board_info['groups'], array(1));

		if (isset($_POST['membergroups']))
			$who = explode(',', $_POST['membergroups']);

		// Check whether at least one membergroup was selected.
		if (empty($who))
			fatal_lang_error('no_membergroup_selected');

		// Make sure all membergroups are integers and can access the board of the announcement.
		foreach ($who as $id => $mg)
			$who[$id] = in_array((int) $mg, $groups) ? (int) $mg : 0;

		require_once(SUBSDIR . '/Topic.subs.php');

		// Get the topic subject and body and censor them.
		$topic_info = getTopicInfo($topic, 'message');
		$context['topic_subject'] = $topic_info['subject'];

		censorText($context['topic_subject']);
		censorText($topic_info['body']);

		$topic_info['body'] = trim(un_htmlspecialchars(strip_tags(strtr(parse_bbc($topic_info['body'], false, $topic_info['id_first_msg']), array('<br />' => "\n", '</div>' => "\n", '</li>' => "\n", '&#91;' => '[', '&#93;' => ']')))));

		// We need this in order to be able send emails.
		require_once(SUBSDIR . '/Mail.subs.php');
		require_once(SUBSDIR . '/Members.subs.php');

		// Select the email addresses for this batch.
		$conditions = array(
			'activated_status' => 1,
			'member_greater' => $context['start'],
			'group_list' => $who,
			'order_by' => 'id_member',
			// @todo Might need an interface?
			'limit' => empty($modSettings['mail_queue']) ? 50 : 500,
		);
		if (!empty($modSettings['allow_disableAnnounce']))
			$conditions['notify_announcements'] = 1;

		$data = retrieveMemberData($conditions);

		// All members have received a mail. Go to the next screen.
		if (empty($data))
		{
			logAction('announce_topic', array('topic' => $topic), 'user');
			if (!empty($_REQUEST['move']) && allowedTo('move_any'))
				redirectexit('action=movetopic;topic=' . $topic . '.0' . (empty($_REQUEST['goback']) ? '' : ';goback'));
			elseif (!empty($_REQUEST['goback']))
				redirectexit('topic=' . $topic . '.new;boardseen#new', isBrowser('ie'));
			else
				redirectexit('board=' . $board . '.0');
		}

		$announcements = array();
		// Loop through all members that'll receive an announcement in this batch.
		foreach ($data as $row)
		{
			$cur_language = empty($row['lngfile']) || empty($modSettings['userLanguage']) ? $language : $row['lngfile'];

			// If the language wasn't defined yet, load it and compose a notification message.
			if (!isset($announcements[$cur_language]))
			{
				$replacements = array(
					'TOPICSUBJECT' => $context['topic_subject'],
					'MESSAGE' => $topic_info['body'],
					'TOPICLINK' => $scripturl . '?topic=' . $topic . '.0',
				);

				$emaildata = loadEmailTemplate('new_announcement', $replacements, $cur_language);

				$announcements[$cur_language] = array(
					'subject' => $emaildata['subject'],
					'body' => $emaildata['body'],
					'recipients' => array(),
				);
			}

			$announcements[$cur_language]['recipients'][$row['id_member']] = $row['email_address'];
			$context['start'] = $row['id_member'];
		}

		// For each language send a different mail - low priority...
		foreach ($announcements as $lang => $mail)
			sendmail($mail['recipients'], $mail['subject'], $mail['body'], null, null, false, 5);

		$context['percentage_done'] = round(100 * $context['start'] / $modSettings['latestMember'], 1);

		$context['move'] = empty($_REQUEST['move']) ? 0 : 1;
		$context['go_back'] = empty($_REQUEST['goback']) ? 0 : 1;
		$context['membergroups'] = implode(',', $who);
		$context['sub_template'] = 'announcement_send';

		// Go back to the correct language for the user ;).
		if (!empty($modSettings['userLanguage']))
			loadLanguage('Post');
	}
}
