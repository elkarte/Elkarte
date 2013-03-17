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
 * The functions in this file deal with sending topics to a friend or moderator,
 * and email to a user.
 *
 */

if (!defined('ELKARTE'))
	die('No access...');

/**
 * This function initializes or sets up the necessary, for the other actions
 */
function pre_emailuser()
{
	global $context;

	// Don't index anything here.
	$context['robot_no_index'] = true;

	// Load the template.
	loadTemplate('Emailuser');
}

/**
 * Default action handler (when no ;sa is specified)
 */
function action_emailuser()
{
	// default action: action_sendtopic()
	action_sendtopic();
}

/**
 * Send a topic to a friend.
 * Uses the Emailuser template, with the main sub template.
 * Requires the send_topic permission.
 * Redirects back to the first page of the topic when done.
 * Is accessed via ?action=emailuser;sa=sendtopic.
 */
function action_sendtopic()
{
	global $topic, $txt, $context, $scripturl, $smcFunc, $modSettings;

	// Check permissions...
	isAllowedTo('send_topic');

	// We need at least a topic... go away if you don't have one.
	if (empty($topic))
		fatal_lang_error('not_a_topic', false);

	require_once(SUBSDIR . '/Topic.subs.php');

	$row = getTopicInfo($topic, 'message');
	if (empty($row))
		fatal_lang_error('not_a_topic', false);

	// Can't send topic if its unapproved and using post moderation.
	if ($modSettings['postmod_active'] && !$row['approved'])
		fatal_lang_error('not_approved_topic', false);

	// Censor the subject....
	censorText($row['subject']);

	// Sending yet, or just getting prepped?
	if (empty($_POST['send']))
	{
		$context['page_title'] = sprintf($txt['sendtopic_title'], $row['subject']);
		$context['start'] = $_REQUEST['start'];

		return;
	}

	// Actually send the message...
	checkSession();
	spamProtection('sendtopic');

	// This is needed for sendmail().
	require_once(SUBSDIR . '/Mail.subs.php');

	// Trim the names..
	$_POST['y_name'] = trim($_POST['y_name']);
	$_POST['r_name'] = trim($_POST['r_name']);

	// Make sure they aren't playing "let's use a fake email".
	if ($_POST['y_name'] == '_' || !isset($_POST['y_name']) || $_POST['y_name'] == '')
		fatal_lang_error('no_name', false);
	if (!isset($_POST['y_email']) || $_POST['y_email'] == '')
		fatal_lang_error('no_email', false);
	if (preg_match('~^[0-9A-Za-z=_+\-/][0-9A-Za-z=_\'+\-/\.]*@[\w\-]+(\.[\w\-]+)*(\.[\w]{2,6})$~', $_POST['y_email']) == 0)
		fatal_lang_error('email_invalid_character', false);

	// The receiver should be valid to.
	if ($_POST['r_name'] == '_' || !isset($_POST['r_name']) || $_POST['r_name'] == '')
		fatal_lang_error('no_name', false);
	if (!isset($_POST['r_email']) || $_POST['r_email'] == '')
		fatal_lang_error('no_email', false);
	if (preg_match('~^[0-9A-Za-z=_+\-/][0-9A-Za-z=_\'+\-/\.]*@[\w\-]+(\.[\w\-]+)*(\.[\w]{2,6})$~', $_POST['r_email']) == 0)
		fatal_lang_error('email_invalid_character', false);

	// Emails don't like entities...
	$row['subject'] = un_htmlspecialchars($row['subject']);

	$replacements = array(
		'TOPICSUBJECT' => $row['subject'],
		'SENDERNAME' => $_POST['y_name'],
		'RECPNAME' => $_POST['r_name'],
		'TOPICLINK' => $scripturl . '?topic=' . $topic . '.0',
	);

	$emailtemplate = 'send_topic';

	if (!empty($_POST['comment']))
	{
		$emailtemplate .= '_comment';
		$replacements['COMMENT'] = $_POST['comment'];
	}

	$emaildata = loadEmailTemplate($emailtemplate, $replacements);
	// And off we go!
	sendmail($_POST['r_email'], $emaildata['subject'], $emaildata['body'], $_POST['y_email']);

	// Back to the topic!
	redirectexit('topic=' . $topic . '.0');
}

/**
 * Allow a user to send an email.
 * Send an email to the user - allow the sender to write the message.
 * Can either be passed a user ID as uid or a message id as msg.
 * Does not check permissions for a message ID as there is no information disclosed.
 * ?action=emailuser;sa=email
 */
function action_email()
{
	global $context, $modSettings, $user_info, $smcFunc, $txt, $scripturl;

	// Can the user even see this information?
	if ($user_info['is_guest'] && !empty($modSettings['guest_hideContacts']))
		fatal_lang_error('no_access', false);

	isAllowedTo('send_email_to_members');

	// Are we sending to a user?
	$context['form_hidden_vars'] = array();
	if (isset($_REQUEST['uid']))
	{
		$request = $smcFunc['db_query']('', '
			SELECT email_address AS email, real_name AS name, id_member, hide_email
			FROM {db_prefix}members
			WHERE id_member = {int:id_member}',
			array(
				'id_member' => (int) $_REQUEST['uid'],
			)
		);

		$context['form_hidden_vars']['uid'] = (int) $_REQUEST['uid'];
	}
	elseif (isset($_REQUEST['msg']))
	{
		$request = $smcFunc['db_query']('', '
			SELECT IFNULL(mem.email_address, m.poster_email) AS email, IFNULL(mem.real_name, m.poster_name) AS name, IFNULL(mem.id_member, 0) AS id_member, hide_email
			FROM {db_prefix}messages AS m
				LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = m.id_member)
			WHERE m.id_msg = {int:id_msg}',
			array(
				'id_msg' => (int) $_REQUEST['msg'],
			)
		);

		$context['form_hidden_vars']['msg'] = (int) $_REQUEST['msg'];
	}

	if (empty($request) || $smcFunc['db_num_rows']($request) == 0)
		fatal_lang_error('cant_find_user_email');

	$row = $smcFunc['db_fetch_assoc']($request);
	$smcFunc['db_free_result']($request);

	// Are you sure you got the address?
	if (empty($row['email']))
		fatal_lang_error('cant_find_user_email');

	// Can they actually do this?
	$context['show_email_address'] = showEmailAddress(!empty($row['hide_email']), $row['id_member']);
	if ($context['show_email_address'] === 'no')
		fatal_lang_error('no_access', false);

	// Setup the context!
	$context['recipient'] = array(
		'id' => $row['id_member'],
		'name' => $row['name'],
		'email' => $row['email'],
		'email_link' => ($context['show_email_address'] == 'yes_permission_override' ? '<em>' : '') . '<a href="mailto:' . $row['email'] . '">' . $row['email'] . '</a>' . ($context['show_email_address'] == 'yes_permission_override' ? '</em>' : ''),
		'link' => $row['id_member'] ? '<a href="' . $scripturl . '?action=profile;u=' . $row['id_member'] . '">' . $row['name'] . '</a>' : $row['name'],
	);

	// Can we see this person's email address?
	$context['can_view_receipient_email'] = $context['show_email_address'] == 'yes' || $context['show_email_address'] == 'yes_permission_override';

	// Are we actually sending it?
	if (isset($_POST['send']) && isset($_POST['email_body']))
	{
		require_once(SUBSDIR . '/Mail.subs.php');

		checkSession();

		// If it's a guest sort out their names.
		if ($user_info['is_guest'])
		{
			if (empty($_POST['y_name']) || $_POST['y_name'] == '_' || trim($_POST['y_name']) == '')
				fatal_lang_error('no_name', false);
			if (empty($_POST['y_email']))
				fatal_lang_error('no_email', false);
			if (preg_match('~^[0-9A-Za-z=_+\-/][0-9A-Za-z=_\'+\-/\.]*@[\w\-]+(\.[\w\-]+)*(\.[\w]{2,6})$~', $_POST['y_email']) == 0)
				fatal_lang_error('email_invalid_character', false);

			$from_name = trim($_POST['y_name']);
			$from_email = trim($_POST['y_email']);
		}
		else
		{
			$from_name = $user_info['name'];
			$from_email = $user_info['email'];
		}

		// Check we have a body (etc).
		if (trim($_POST['email_body']) == '' || trim($_POST['email_subject']) == '')
			fatal_lang_error('email_missing_data');

		// We use a template in case they want to customise!
		$replacements = array(
			'EMAILSUBJECT' => $_POST['email_subject'],
			'EMAILBODY' => $_POST['email_body'],
			'SENDERNAME' => $from_name,
			'RECPNAME' => $context['recipient']['name'],
		);

		// Don't let them send too many!
		spamProtection('sendmail');

		// Get the template and get out!
		$emaildata = loadEmailTemplate('send_email', $replacements);
		sendmail($context['recipient']['email'], $emaildata['subject'], $emaildata['body'], $from_email, null, false, 1, null, true);

		// Now work out where to go!
		if (isset($_REQUEST['uid']))
			redirectexit('action=profile;u=' . (int) $_REQUEST['uid']);
		elseif (isset($_REQUEST['msg']))
			redirectexit('msg=' . (int) $_REQUEST['msg']);
		else
			redirectexit();
	}

	$context['sub_template'] = 'custom_email';
	$context['page_title'] = $txt['send_email'];
}

/**
 * Report a post to the moderator... ask for a comment.
 * Gathers data from the user to report abuse to the moderator(s).
 * Uses the ReportToModerator template, main sub template.
 * Requires the report_any permission.
 * Uses action_reporttm2() if post data was sent.
 * Accessed through ?action=reporttm.
 */
function action_reporttm()
{
	global $txt, $topic, $modSettings, $user_info, $context, $smcFunc;

	$context['robot_no_index'] = true;

	// You can't use this if it's off or you are not allowed to do it.
	isAllowedTo('report_any');

	// No errors, yet.
	$report_errors = error_context::context('report', 1);
	// ...or maybe some.
	$context['report_error'] = array(
		'errors' => $report_errors->prepareErrors(),
		'type' => $report_errors->getErrorType() == 0 ? 'minor' : 'serious',
	);
	// If they're posting, it should be processed by action_reporttm2.
	if ((isset($_POST[$context['session_var']]) || isset($_POST['save'])) && !$report_errors->hasErrors())
		action_reporttm2();

	// We need a message ID to check!
	if (empty($_REQUEST['msg']) && empty($_REQUEST['mid']))
		fatal_lang_error('no_access', false);

	// For compatibility, accept mid, but we should be using msg. (not the flavor kind!)
	$_REQUEST['msg'] = empty($_REQUEST['msg']) ? (int) $_REQUEST['mid'] : (int) $_REQUEST['msg'];

	// Check the message's ID - don't want anyone reporting a post they can't even see!
	$result = $smcFunc['db_query']('', '
		SELECT m.id_msg, m.id_member, t.id_member_started
		FROM {db_prefix}messages AS m
			INNER JOIN {db_prefix}topics AS t ON (t.id_topic = {int:current_topic})
		WHERE m.id_msg = {int:id_msg}
			AND m.id_topic = {int:current_topic}
		LIMIT 1',
		array(
			'current_topic' => $topic,
			'id_msg' => $_REQUEST['msg'],
		)
	);
	if ($smcFunc['db_num_rows']($result) == 0)
		fatal_lang_error('no_board', false);
	list ($_REQUEST['msg'], $member, $starter) = $smcFunc['db_fetch_row']($result);
	$smcFunc['db_free_result']($result);

	// Do we need to show the visual verification image?
	$context['require_verification'] = $user_info['is_guest'] && !empty($modSettings['guests_report_require_captcha']);
	if ($context['require_verification'])
	{
		require_once(SUBSDIR . '/Editor.subs.php');
		$verificationOptions = array(
			'id' => 'report',
		);
		$context['require_verification'] = create_control_verification($verificationOptions);
		$context['visual_verification_id'] = $verificationOptions['id'];
	}

	// Show the inputs for the comment, etc.
	loadLanguage('Post');
	loadLanguage('Errors');
	loadTemplate('Emailuser');

	addInlineJavascript('
	error_txts[\'post_too_long\'] = ' . JavaScriptEscape($txt['error_post_too_long']) . ';

	var report_errors = new errorbox_handler({
		self: \'report_errors\',
		error_box_id: \'report_error\',
		error_checks: [{
			code: \'post_too_long\',
			efunction: function(box_value) {
				if (box_value.length > 254)
					return true;
				else
					return false;
			}
		}],
		check_id: "report_comment"
	});', true);

	$context['comment_body'] = !isset($_POST['comment']) ? '' : trim($_POST['comment']);
	$context['email_address'] = !isset($_POST['email']) ? '' : trim($_POST['email']);

	// This is here so that the user could, in theory, be redirected back to the topic.
	$context['start'] = $_REQUEST['start'];
	$context['message_id'] = $_REQUEST['msg'];

	$context['page_title'] = $txt['report_to_mod'];
	$context['sub_template'] = 'report';
}

/**
 * Send the emails.
 * Sends off emails to all the moderators.
 * Sends to administrators and global moderators. (1 and 2)
 * Called by action_reporttm(), and thus has the same permission and setting requirements as it does.
 * Accessed through ?action=reporttm when posting.
 */
function action_reporttm2()
{
	global $txt, $scripturl, $topic, $board, $user_info, $modSettings, $language, $context, $smcFunc;

	// You must have the proper permissions!
	isAllowedTo('report_any');

	// Make sure they aren't spamming.
	spamProtection('reporttm');

	require_once(SUBSDIR . '/Mail.subs.php');

	// No errors, yet.
	$report_errors = error_context::context('report', 1);

	// Check their session.
	if (checkSession('post', '', false) != '')
		$report_errors->addError('session_timeout');

	// Make sure we have a comment and it's clean.
	if (!isset($_POST['comment']) || $smcFunc['htmltrim']($_POST['comment']) === '')
		$report_errors->addError('no_comment');
	$poster_comment = strtr($smcFunc['htmlspecialchars']($_POST['comment']), array("\r" => '', "\t" => ''));

	if ($smcFunc['strlen']($poster_comment) > 254)
		$report_errors->addError('post_too_long');

	// Guests need to provide their address!
	if ($user_info['is_guest'])
	{
		$_POST['email'] = !isset($_POST['email']) ? '' : trim($_POST['email']);
		if ($_POST['email'] === '')
			$report_errors->addError('no_email');
		elseif (preg_match('~^[0-9A-Za-z=_+\-/][0-9A-Za-z=_\'+\-/\.]*@[\w\-]+(\.[\w\-]+)*(\.[\w]{2,6})$~', $_POST['email']) == 0)
			$report_errors->addError('bad_email');

		isBannedEmail($_POST['email'], 'cannot_post', sprintf($txt['you_are_post_banned'], $txt['guest_title']));

		$user_info['email'] = htmlspecialchars($_POST['email']);
	}

	// Could they get the right verification code?
	if ($user_info['is_guest'] && !empty($modSettings['guests_report_require_captcha']))
	{
		require_once(SUBSDIR . '/Editor.subs.php');
		$verificationOptions = array(
			'id' => 'report',
		);
		$context['require_verification'] = create_control_verification($verificationOptions, true);
	}

	// Any errors?
	if ($report_errors->hasErrors())
		return action_reporttm();

	// Get the basic topic information, and make sure they can see it.
	$_POST['msg'] = (int) $_POST['msg'];

	$request = $smcFunc['db_query']('', '
		SELECT m.id_topic, m.id_board, m.subject, m.body, m.id_member AS id_poster, m.poster_name, mem.real_name
		FROM {db_prefix}messages AS m
			LEFT JOIN {db_prefix}members AS mem ON (m.id_member = mem.id_member)
		WHERE m.id_msg = {int:id_msg}
			AND m.id_topic = {int:current_topic}
		LIMIT 1',
		array(
			'current_topic' => $topic,
			'id_msg' => $_POST['msg'],
		)
	);
	if ($smcFunc['db_num_rows']($request) == 0)
		fatal_lang_error('no_board', false);
	$message = $smcFunc['db_fetch_assoc']($request);
	$smcFunc['db_free_result']($request);

	$poster_name = un_htmlspecialchars($message['real_name']) . ($message['real_name'] != $message['poster_name'] ? ' (' . $message['poster_name'] . ')' : '');
	$reporterName = un_htmlspecialchars($user_info['name']) . ($user_info['name'] != $user_info['username'] && $user_info['username'] != '' ? ' (' . $user_info['username'] . ')' : '');
	$subject = un_htmlspecialchars($message['subject']);

	// Get a list of members with the moderate_board permission.
	require_once(SUBSDIR . '/Members.subs.php');
	$moderators = membersAllowedTo('moderate_board', $board);

	$request = $smcFunc['db_query']('', '
		SELECT id_member, email_address, lngfile, mod_prefs
		FROM {db_prefix}members
		WHERE id_member IN ({array_int:moderator_list})
			AND notify_types != {int:notify_types}
		ORDER BY lngfile',
		array(
			'moderator_list' => $moderators,
			'notify_types' => 4,
		)
	);

	// Check that moderators do exist!
	if ($smcFunc['db_num_rows']($request) == 0)
		fatal_lang_error('no_mods', false);

	// If we get here, I believe we should make a record of this, for historical significance, yabber.
	if (empty($modSettings['disable_log_report']))
	{
		$request2 = $smcFunc['db_query']('', '
			SELECT id_report, ignore_all
			FROM {db_prefix}log_reported
			WHERE id_msg = {int:id_msg}
				AND (closed = {int:not_closed} OR ignore_all = {int:ignored})
			ORDER BY ignore_all DESC',
			array(
				'id_msg' => $_POST['msg'],
				'not_closed' => 0,
				'ignored' => 1,
			)
		);
		if ($smcFunc['db_num_rows']($request2) != 0)
			list ($id_report, $ignore) = $smcFunc['db_fetch_row']($request2);
		$smcFunc['db_free_result']($request2);

		// If we're just going to ignore these, then who gives a monkeys...
		if (!empty($ignore))
			redirectexit('topic=' . $topic . '.msg' . $_POST['msg'] . '#msg' . $_POST['msg']);

		// Already reported? My god, we could be dealing with a real rogue here...
		if (!empty($id_report))
			$smcFunc['db_query']('', '
				UPDATE {db_prefix}log_reported
				SET num_reports = num_reports + 1, time_updated = {int:current_time}
				WHERE id_report = {int:id_report}',
				array(
					'current_time' => time(),
					'id_report' => $id_report,
				)
			);
		// Otherwise, we shall make one!
		else
		{
			if (empty($message['real_name']))
				$message['real_name'] = $message['poster_name'];

			$smcFunc['db_insert']('',
				'{db_prefix}log_reported',
				array(
					'id_msg' => 'int', 'id_topic' => 'int', 'id_board' => 'int', 'id_member' => 'int', 'membername' => 'string',
					'subject' => 'string', 'body' => 'string', 'time_started' => 'int', 'time_updated' => 'int',
					'num_reports' => 'int', 'closed' => 'int',
				),
				array(
					$_POST['msg'], $message['id_topic'], $message['id_board'], $message['id_poster'], $message['real_name'],
					$message['subject'], $message['body'] , time(), time(), 1, 0,
				),
				array('id_report')
			);
			$id_report = $smcFunc['db_insert_id']('{db_prefix}log_reported', 'id_report');
		}

		// Now just add our report...
		if ($id_report)
		{
			$smcFunc['db_insert']('',
				'{db_prefix}log_reported_comments',
				array(
					'id_report' => 'int', 'id_member' => 'int', 'membername' => 'string', 'email_address' => 'string',
					'member_ip' => 'string', 'comment' => 'string', 'time_sent' => 'int',
				),
				array(
					$id_report, $user_info['id'], $user_info['name'], $user_info['email'],
					$user_info['ip'], $poster_comment, time(),
				),
				array('id_comment')
			);
		}
	}

	// Find out who the real moderators are - for mod preferences.
	$request2 = $smcFunc['db_query']('', '
		SELECT id_member
		FROM {db_prefix}moderators
		WHERE id_board = {int:current_board}',
		array(
			'current_board' => $board,
		)
	);
	$real_mods = array();
	while ($row = $smcFunc['db_fetch_assoc']($request2))
		$real_mods[] = $row['id_member'];
	$smcFunc['db_free_result']($request2);

	// Send every moderator an email.
	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		// Maybe they don't want to know?!
		if (!empty($row['mod_prefs']))
		{
			list(,, $pref_binary) = explode('|', $row['mod_prefs']);
			if (!($pref_binary & 1) && (!($pref_binary & 2) || !in_array($row['id_member'], $real_mods)))
				continue;
		}

		$replacements = array(
			'TOPICSUBJECT' => $subject,
			'POSTERNAME' => $poster_name,
			'REPORTERNAME' => $reporterName,
			'TOPICLINK' => $scripturl . '?topic=' . $topic . '.msg' . $_POST['msg'] . '#msg' . $_POST['msg'],
			'REPORTLINK' => !empty($id_report) ? $scripturl . '?action=moderate;area=reports;report=' . $id_report : '',
			'COMMENT' => $_POST['comment'],
		);

		$emaildata = loadEmailTemplate('report_to_moderator', $replacements, empty($row['lngfile']) || empty($modSettings['userLanguage']) ? $language : $row['lngfile']);

		// Send it to the moderator.
		sendmail($row['email_address'], $emaildata['subject'], $emaildata['body'], $user_info['email'], null, false, 2);
	}
	$smcFunc['db_free_result']($request);

	// Keep track of when the mod reports get updated, that way we know when we need to look again.
	updateSettings(array('last_mod_report_action' => time()));

	// Back to the post we reported!
	redirectexit('reportsent;topic=' . $topic . '.msg' . $_POST['msg'] . '#msg' . $_POST['msg']);
}
