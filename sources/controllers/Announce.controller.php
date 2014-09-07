<?php

/**
 * Handles all announce topic functionality
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
 * @version 1.0
 *
 */

if (!defined('ELK'))
	die('No access...');

/**
 * Class to handle announce topic functionality.
 */
class Announce_Controller extends Action_Controller
{
	/**
	 * Default (sub)action for ?action=announce
	 *
	 * @see Action_Controller::action_index()
	 */
	public function action_index()
	{
		// Default for action=announce: action_selectgroup() function.
		$this->action_selectgroup();
	}

	/**
	 * Set up the context for the announce topic function (action=announce).
	 * This function is called before the flow is redirected to action_selectgroup() or action_send().
	 *
	 * checks the topic announcement permissions and loads the announcement template.
	 * requires the announce_topic permission.
	 * uses the Announce template and Post language file.
	 */
	public function pre_dispatch()
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
	 * @uses Announce template announce sub template
	 */
	public function action_selectgroup()
	{
		global $context, $topic, $board_info;

		require_once(SUBSDIR . '/Membergroups.subs.php');
		require_once(SUBSDIR . '/Topic.subs.php');

		// Build a list of groups that can see this board
		$groups = array_merge($board_info['groups'], array(1));
		foreach ($groups as $id => $group)
			$groups[$id] = (int) $group;

		// Prepare for a group selection list in the template
		$context['groups'] = getGroups($groups);

		// Get the subject of the topic we're about to announce.
		$topic_info = getTopicInfo($topic, 'message');
		$context['topic_subject'] = $topic_info['subject'];
		censorText($context['announce_topic']['subject']);

		// Prepare for the template
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
	 * @uses announcement template announcement_send sub template
	 */
	public function action_send()
	{
		global $topic, $board, $board_info, $context, $modSettings, $language, $scripturl;

		checkSession();

		$context['start'] = empty($_REQUEST['start']) ? 0 : (int) $_REQUEST['start'];
		$groups = array_merge($board_info['groups'], array(1));
		$who = array();

		// Load any supplied membergroups (from announcement_send template pause loop)
		if (isset($_POST['membergroups']))
			$_POST['who'] = explode(',', $_POST['membergroups']);

		// Check that at least one membergroup was selected (set from announce sub template)
		if (empty($_POST['who']))
			fatal_lang_error('no_membergroup_selected');

		// Make sure all membergroups are integers and can access the board of the announcement.
		foreach ($_POST['who'] as $id => $mg)
			$who[$id] = in_array((int) $mg, $groups) ? (int) $mg : 0;

		// Get the topic details that we are going to send
		require_once(SUBSDIR . '/Topic.subs.php');
		$topic_info = getTopicInfo($topic, 'message');

		// Prepare a plain text (markdown) body for email use, does the censoring as well
		require_once(SUBSDIR . '/Emailpost.subs.php');
		pbe_prepare_text($topic_info['body'], $topic_info['subject']);

		// We need this in order to be able send emails.
		require_once(SUBSDIR . '/Mail.subs.php');
		require_once(SUBSDIR . '/Members.subs.php');

		// Select the email addresses for this batch.
		$conditions = array(
			'activated_status' => 1,
			'member_greater' => $context['start'],
			'group_list' => $who,
			'order_by' => 'id_member',
			// @todo interface for this
			'limit' => empty($modSettings['mail_queue']) ? 25 : 500,
		);

		// Have we allowed members to opt out of announcements?
		if (!empty($modSettings['allow_disableAnnounce']))
			$conditions['notify_announcements'] = 1;

		$data = retrieveMemberData($conditions);

		// All members have received a mail. Go to the next screen.
		if (empty($data) || $data['member_count'] === 0)
		{
			logAction('announce_topic', array('topic' => $topic), 'user');

			if (!empty($_REQUEST['move']) && allowedTo('move_any'))
				redirectexit('action=movetopic;topic=' . $topic . '.0' . (empty($_REQUEST['goback']) ? '' : ';goback'));
			elseif (!empty($_REQUEST['goback']))
				redirectexit('topic=' . $topic . '.new;boardseen#new', isBrowser('ie'));
			else
				redirectexit('board=' . $board . '.0');
		}

		// Loop through all members that'll receive an announcement in this batch.
		$announcements = array();
		foreach ($data['member_info'] as $row)
		{
			$cur_language = empty($row['language']) || empty($modSettings['userLanguage']) ? $language : $row['language'];

			// If the language wasn't defined yet, load it and compose a notification message.
			if (!isset($announcements[$cur_language]))
			{
				$replacements = array(
					'TOPICSUBJECT' => $topic_info['subject'],
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

			$announcements[$cur_language]['recipients'][$row['id']] = $row['email'];
			$context['start'] = $row['id'];
		}

		// For each language send a different mail - low priority...
		foreach ($announcements as $lang => $mail)
			sendmail($mail['recipients'], $mail['subject'], $mail['body'], null, null, false, 5);

		// Provide an overall indication of progress, this is not strictly correct
		if ($data['member_count'] < $conditions['limit'])
			$context['percentage_done'] = 100;
		else
			$context['percentage_done'] = round(100 * $context['start'] / $modSettings['latestMember'], 1);

		// Prepare for the template
		$context['move'] = empty($_REQUEST['move']) ? 0 : 1;
		$context['go_back'] = empty($_REQUEST['goback']) ? 0 : 1;
		$context['membergroups'] = implode(',', $who);
		$context['topic_subject'] = $topic_info['subject'];
		$context['sub_template'] = 'announcement_send';

		// Go back to the correct language for the user ;)
		if (!empty($modSettings['userLanguage']))
			loadLanguage('Post');
	}
}