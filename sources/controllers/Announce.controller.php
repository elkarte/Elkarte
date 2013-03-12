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

/**
 * Default (sub)action for ?action=announce
 */
function action_announce()
{
	// default for action=announce: action_selectgroup() function.
	action_selectgroup();
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
	global $txt, $context, $topic, $board, $board_info, $smcFunc;

	$groups = array_merge($board_info['groups'], array(1));
	foreach ($groups as $id => $group)
		$groups[$id] = (int) $group;

	require_once(SUBSDIR . '/Membergroups.subs.php');
	require_once(SUBSDIR . '/Topic.subs.php');

	$context['groups'] = array();
	if (in_array(0, $groups))
	{
		$context['groups'][0] = array(
			'id' => 0,
			'name' => $txt['announce_regular_members'],
			'member_count' => 'n/a',
		);
	}

	// Get all membergroups that have access to the board the announcement was made on.
	$request = $smcFunc['db_query']('', '
		SELECT mg.id_group, COUNT(mem.id_member) AS num_members
		FROM {db_prefix}membergroups AS mg
			LEFT JOIN {db_prefix}members AS mem ON (mem.id_group = mg.id_group OR FIND_IN_SET(mg.id_group, mem.additional_groups) != 0 OR mg.id_group = mem.id_post_group)
		WHERE mg.id_group IN ({array_int:group_list})
		GROUP BY mg.id_group',
		array(
			'group_list' => $groups,
			'newbie_id_group' => 4,
		)
	);
	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		$context['groups'][$row['id_group']] = array(
			'id' => $row['id_group'],
			'name' => '',
			'member_count' => $row['num_members'],
		);
	}
	$smcFunc['db_free_result']($request);

	// Now get the membergroup names.
	$groups_info = membergroupsById($groups, 0);
	foreach ($groups_info as $id_group => $group_info)
		$context['groups'][$id_group]['name'] = $group_info['group_name'];

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
	global $language, $scripturl, $txt, $user_info, $smcFunc;

	checkSession();

	$context['start'] = empty($_REQUEST['start']) ? 0 : (int) $_REQUEST['start'];
	$groups = array_merge($board_info['groups'], array(1));

	if (isset($_POST['membergroups']))
		$_POST['who'] = explode(',', $_POST['membergroups']);

	// Check whether at least one membergroup was selected.
	if (empty($_POST['who']))
		fatal_lang_error('no_membergroup_selected');

	// Make sure all membergroups are integers and can access the board of the announcement.
	foreach ($_POST['who'] as $id => $mg)
		$_POST['who'][$id] = in_array((int) $mg, $groups) ? (int) $mg : 0;

	require_once(SUBSDIR . '/Topic.subs.php');

	// Get the topic subject and body and censor them.
	$topic_info = getTopicInfo($topic, 'message');
	$context['topic_subject'] = $topic_info['subject'];

	censorText($context['topic_subject']);
	censorText($topic_info['body']);

	$topic_info['body'] = trim(un_htmlspecialchars(strip_tags(strtr(parse_bbc($topic_info['body'], false, $topic_info['id_first_msg']), array('<br />' => "\n", '</div>' => "\n", '</li>' => "\n", '&#91;' => '[', '&#93;' => ']')))));

	// We need this in order to be able send emails.
	require_once(SUBSDIR . '/Mail.subs.php');

	// Select the email addresses for this batch.
	$request = $smcFunc['db_query']('', '
		SELECT mem.id_member, mem.email_address, mem.lngfile
		FROM {db_prefix}members AS mem
		WHERE (mem.id_group IN ({array_int:group_list}) OR mem.id_post_group IN ({array_int:group_list}) OR FIND_IN_SET({raw:additional_group_list}, mem.additional_groups) != 0)' . (!empty($modSettings['allow_disableAnnounce']) ? '
			AND mem.notify_announcements = {int:notify_announcements}' : '') . '
			AND mem.is_activated = {int:is_activated}
			AND mem.id_member > {int:start}
		ORDER BY mem.id_member
		LIMIT {int:chunk_size}',
		array(
			'group_list' => $_POST['who'],
			'notify_announcements' => 1,
			'is_activated' => 1,
			'start' => $context['start'],
			'additional_group_list' => implode(', mem.additional_groups) != 0 OR FIND_IN_SET(', $_POST['who']),
			// @todo Might need an interface?
			'chunk_size' => empty($modSettings['mail_queue']) ? 50 : 500,
		)
	);

	// All members have received a mail. Go to the next screen.
	if ($smcFunc['db_num_rows']($request) == 0)
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
	while ($row = $smcFunc['db_fetch_assoc']($request))
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
	$smcFunc['db_free_result']($request);

	// For each language send a different mail - low priority...
	foreach ($announcements as $lang => $mail)
		sendmail($mail['recipients'], $mail['subject'], $mail['body'], null, null, false, 5);

	$context['percentage_done'] = round(100 * $context['start'] / $modSettings['latestMember'], 1);

	$context['move'] = empty($_REQUEST['move']) ? 0 : 1;
	$context['go_back'] = empty($_REQUEST['goback']) ? 0 : 1;
	$context['membergroups'] = implode(',', $_POST['who']);
	$context['sub_template'] = 'announcement_send';

	// Go back to the correct language for the user ;).
	if (!empty($modSettings['userLanguage']))
		loadLanguage('Post');
}