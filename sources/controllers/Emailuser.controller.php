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

if (!defined('ELK'))
	die('No access...');

class Emailuser_Controller extends Action_Controller
{
	/**
	 * This function initializes or sets up the necessary, for the other actions
	 */
	public function pre_dispatch()
	{
		global $context;

		// Don't index anything here.
		$context['robot_no_index'] = true;

		// Load the template.
		loadTemplate('Emailuser');
	}

	/**
	 * Default action handler
	 *
	 * @see Action_Controller::action_index()
	 */
	public function action_index()
	{
		// just accept we haz a default action: action_sendtopic()
		$this->action_sendtopic();
	}

	/**
	 * Send a topic to a friend.
	 * Uses the Emailuser template, with the main sub template.
	 * Requires the send_topic permission.
	 * Redirects back to the first page of the topic when done.
	 * Is accessed via ?action=emailuser;sa=sendtopic.
	 */
	public function action_sendtopic()
	{
		global $topic, $txt, $context, $modSettings;

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

		$result = $this->_sendTopic($row);
		if ($result !== true)
			$context['sendtopic_error'] = $result;

		// Back to the topic!
		redirectexit('topic=' . $topic . '.0');
	}

	public function action_sendtopic_api()
	{
		global $topic, $modSettings, $txt, $context, $scripturl;

		loadTemplate('Xml');

		Template_Layers::getInstance()->removeAll();
		$context['sub_template'] = 'generic_xml_buttons';

		if (empty($_POST['send']))
			die();

		// We need at least a topic... go away if you don't have one.
		// Guests can't mark things.
		if (empty($topic))
		{
			loadLanguage('Errors');
			$context['xml_data'] = array(
				'error' => 1,
				'text' => $txt['not_a_topic']
			);
			return;
		}

		// Is the session valid?
		if (checkSession('post', '', false))
		{
			loadLanguage('Errors');
			$context['xml_data'] = array(
				'error' => 1,
				'url' => $scripturl . '?action=emailuser;sa=sendtopic;topic=' . $topic . '.0',
			);
			return;
		}

		require_once(SUBSDIR . '/Topic.subs.php');

		$row = getTopicInfo($topic, 'message');
		if (empty($row))
		{
			loadLanguage('Errors');
			$context['xml_data'] = array(
				'error' => 1,
				'text' => $txt['not_a_topic']
			);
			return;
		}

		// Can't send topic if its unapproved and using post moderation.
		if ($modSettings['postmod_active'] && !$row['approved'])
		{
			loadLanguage('Errors');
			$context['xml_data'] = array(
				'error' => 1,
				'text' => $txt['not_approved_topic']
			);
			return;
		}

		$is_spam = spamProtection('sendtopic', false);
		if ($is_spam !== false)
		{
			loadLanguage('Errors');
			$context['xml_data'] = array(
				'error' => 1,
				'text' => sprintf($txt['sendtopic_WaitTime_broken'], $is_spam)
			);
			return;
		}

		// Censor the subject....
		censorText($row['subject']);

		$result = $this->_sendTopic($row);
		if ($result !== true)
		{
			loadLanguage('Errors');
			$context['xml_data'] = $result;
			return;
		}

		$context['xml_data'] = array(
			'text' => $txt['topic_sent'],
		);
	}

	private function _sendTopic($row)
	{
		global $scripturl, $topic, $txt;

		// This is needed for sendmail().
		require_once(SUBSDIR . '/Mail.subs.php');

		// Time to check and clean what was placed in the form
		require_once(SUBSDIR . '/DataValidator.class.php');
		$validator = new Data_Validator();
		$validator->sanitation_rules(array(
			'y_name' => 'trim',
			'r_name' => 'trim'
		));
		$validator->validation_rules(array(
			'y_name' => 'required|notequal[_]',
			'y_email' => 'required|valid_email',
			'r_name' => 'required|notequal[_]',
			'r_email' => 'required|valid_email'
		));
		$validator->text_replacements(array(
			'y_name' => $txt['sendtopic_sender_name'],
			'y_email' => $txt['sendtopic_sender_email'],
			'r_name' => $txt['sendtopic_receiver_name'],
			'r_email' => $txt['sendtopic_receiver_email']
		));

		// Any errors or are we good to go?
		if (!$validator->validate($_POST))
		{
			$errors = $validator->validation_errors();

			return $context['sendtopic_error'] = array(
				'errors' => $errors,
				'type' => 'minor',
				'title' => $txt['validation_failure'],
				// And something for ajax
				'error' => 1,
				'text' => $errors[0],
			);
		}

		// Emails don't like entities...
		$row['subject'] = un_htmlspecialchars($row['subject']);

		$replacements = array(
			'TOPICSUBJECT' => $row['subject'],
			'SENDERNAME' => $validator->y_name,
			'RECPNAME' => $validator->r_name,
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
		sendmail($validator->r_email, $emaildata['subject'], $emaildata['body'], $validator->y_email);

		return true;
	}

	/**
	 * Allow a user to send an email.
	 * Send an email to the user - allow the sender to write the message.
	 * Can either be passed a user ID as uid or a message id as msg.
	 * Does not check permissions for a message ID as there is no information disclosed.
	 * ?action=emailuser;sa=email
	 */
	public function action_email()
	{
		global $context, $modSettings, $user_info, $txt, $scripturl;

		// Can the user even see this information?
		if ($user_info['is_guest'] && !empty($modSettings['guest_hideContacts']))
			fatal_lang_error('no_access', false);

		isAllowedTo('send_email_to_members');

		// Are we sending to a user?
		$context['form_hidden_vars'] = array();
		if (isset($_REQUEST['uid']))
		{
			require_once(SUBSDIR . '/Members.subs.php');
			// Get the latest activated member's display name.
			$row = getBasicMemberData((int) $_REQUEST['uid']);

			$context['form_hidden_vars']['uid'] = (int) $_REQUEST['uid'];
		}
		elseif (isset($_REQUEST['msg']))
		{
			require_once(SUBSDIR . '/Messages.subs.php');
			$row = mailFromMesasge((int) $_REQUEST['msg']);

			$context['form_hidden_vars']['msg'] = (int) $_REQUEST['msg'];
		}

		// Are you sure you got the address?
		if (empty($row['email_address']))
			fatal_lang_error('cant_find_user_email');

		// Can they actually do this?
		$context['show_email_address'] = showEmailAddress(!empty($row['hide_email']), $row['id_member']);
		if ($context['show_email_address'] === 'no')
			fatal_lang_error('no_access', false);

		// Setup the context!
		$context['recipient'] = array(
			'id' => $row['id_member'],
			'name' => $row['real_name'],
			'email' => $row['email_address'],
			'email_link' => ($context['show_email_address'] == 'yes_permission_override' ? '<em>' : '') . '<a href="mailto:' . $row['email_address'] . '">' . $row['email_address'] . '</a>' . ($context['show_email_address'] == 'yes_permission_override' ? '</em>' : ''),
			'link' => $row['id_member'] ? '<a href="' . $scripturl . '?action=profile;u=' . $row['id_member'] . '">' . $row['real_name'] . '</a>' : $row['real_name'],
		);

		// Can we see this person's email address?
		$context['can_view_recipient_email'] = $context['show_email_address'] == 'yes' || $context['show_email_address'] == 'yes_permission_override';

		// Template
		$context['sub_template'] = 'custom_email';
		$context['page_title'] = $txt['send_email'];

		// Are we actually sending it?
		if (isset($_POST['send']) && isset($_POST['email_body']))
		{
			checkSession();

			// Don't let them send too many!
			spamProtection('sendmail');

			require_once(SUBSDIR . '/Mail.subs.php');
			require_once(SUBSDIR . '/DataValidator.class.php');

			// We will need to do some data checking
			$validator = new Data_Validator();
			$validator->sanitation_rules(array(
				'y_name' => 'trim',
				'email_body' => 'trim',
				'email_subject' => 'trim'
			));
			$validator->validation_rules(array(
				'y_name' => 'required|notequal[_]',
				'y_email' => 'required|valid_email',
				'email_body' => 'required',
				'email_subject' => 'required'
			));
			$validator->text_replacements(array(
				'y_name' => $txt['sendtopic_sender_name'],
				'y_email' => $txt['sendtopic_sender_email'],
				'email_body' => $txt['message'],
				'email_subject' => $txt['send_email_subject']
			));
			$validator->validate($_POST);

			// If it's a guest sort out their names.
			if ($user_info['is_guest'])
			{
				$errors = $validator->validation_errors(array('y_name', 'y_email'));
				if ($errors)
				{
					$context['sendemail_error'] = array(
						'errors' => $errors,
						'type' => 'minor',
						'title' => $txt['validation_failure'],
					);
					return;
				}

				$from_name = $validator->y_name;
				$from_email = $validator->y_email;
			}
			else
			{
				$from_name = $user_info['name'];
				$from_email = $user_info['email'];
			}

			// Check we have a body (etc).
			$errors = $validator->validation_errors(array('email_body', 'email_subject'));
			if (!empty($errors))
			{
				$context['sendemail_error'] = array(
					'errors' => $errors,
					'type' => 'minor',
					'title' => $txt['validation_failure'],
				);
				return;
			}

			// We use a template in case they want to customise!
			$replacements = array(
				'EMAILSUBJECT' => $validator->email_subject,
				'EMAILBODY' => $validator->email_body,
				'SENDERNAME' => $from_name,
				'RECPNAME' => $context['recipient']['name'],
			);

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
	}

	/**
	 * Report a post to the moderator... ask for a comment.
	 * Gathers data from the user to report abuse to the moderator(s).
	 * Uses the ReportToModerator template, main sub template.
	 * Requires the report_any permission.
	 * Uses action_reporttm2() if post data was sent.
	 * Accessed through ?action=reporttm.
	 */
	public function action_reporttm()
	{
		global $txt, $topic, $modSettings, $user_info, $context;

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
			$this->action_reporttm2();

		// We need a message ID to check!
		if (empty($_REQUEST['msg']) && empty($_REQUEST['mid']))
			fatal_lang_error('no_access', false);

		// For compatibility, accept mid, but we should be using msg. (not the flavor kind!)
		$message_id = empty($_REQUEST['msg']) ? (int) $_REQUEST['mid'] : (int) $_REQUEST['msg'];

		// Check the message's ID - don't want anyone reporting a post they can't even see!
		require_once(SUBSDIR . '/Topic.subs.php');
		$message_info = messageTopicDetails($topic, $message_id);
		if (empty($message_info ))
			fatal_lang_error('no_board', false);

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
		$context['message_id'] = $message_id;

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
	public function action_reporttm2()
	{
		global $txt, $scripturl, $topic, $board, $user_info, $modSettings, $language, $context;

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
		if (!isset($_POST['comment']) || Util::htmltrim($_POST['comment']) === '')
			$report_errors->addError('no_comment');
		$poster_comment = strtr(Util::htmlspecialchars($_POST['comment']), array("\r" => '', "\t" => ''));

		if (Util::strlen($poster_comment) > 254)
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

			if (is_array($context['require_verification']))
			{
				foreach ($context['require_verification'] as $error)
					$report_errors->addError($error, 0);
			}
		}

		// Any errors?
		if ($report_errors->hasErrors())
			return action_reporttm();

		// Get the basic topic information, and make sure they can see it.
		$msg_id = (int) $_POST['msg']*0;
		$message = posterDetails($msg_id, $topic);

		if (empty($message))
			fatal_lang_error('no_board', false);

		$poster_name = un_htmlspecialchars($message['real_name']) . ($message['real_name'] != $message['poster_name'] ? ' (' . $message['poster_name'] . ')' : '');
		$reporterName = un_htmlspecialchars($user_info['name']) . ($user_info['name'] != $user_info['username'] && $user_info['username'] != '' ? ' (' . $user_info['username'] . ')' : '');
		$subject = un_htmlspecialchars($message['subject']);

		// Get a list of members with the moderate_board permission.
		require_once(SUBSDIR . '/Members.subs.php');
		$moderators = membersAllowedTo('moderate_board', $board);
		$result = getBasicMemberData($moderators, array('preferences' => true, 'sort' => 'lngfile'));
		$mod_to_notify = array();
		foreach ($result as $row)
		{
			if ($row['notify_types'] != 4)
				$mod_to_notify[] = $row;
		}

		// Check that moderators do exist!
		if (empty($mod_to_notify))
			fatal_lang_error('no_mods', false);

		// If we get here, I believe we should make a record of this, for historical significance, yabber.
		if (empty($modSettings['disable_log_report']))
		{
			require_once(SUBSDIR . '/Messages.subs.php');
			$id_report = recordReport($message, $poster_comment);

			// If we're just going to ignore these, then who gives a monkeys...
			if ($id_report === false)
				redirectexit('topic=' . $topic . '.msg' . $msg_id . '#msg' . $msg_id);
		}

		// Find out who the real moderators are - for mod preferences.
		require_once(SUBSDIR . '/Boards.subs.php');
		$real_mods = getBoardModerators($board, true);

		// Send every moderator an email.
		foreach ($mod_to_notify as $row)
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
				'TOPICLINK' => $scripturl . '?topic=' . $topic . '.msg' . $msg_id . '#msg' . $msg_id,
				'REPORTLINK' => !empty($id_report) ? $scripturl . '?action=moderate;area=reports;report=' . $id_report : '',
				'COMMENT' => $_POST['comment'],
			);

			$emaildata = loadEmailTemplate('report_to_moderator', $replacements, empty($row['lngfile']) || empty($modSettings['userLanguage']) ? $language : $row['lngfile']);

			// Send it to the moderator.
			sendmail($row['email_address'], $emaildata['subject'], $emaildata['body'], $user_info['email'], null, false, 2);
		}

		// Keep track of when the mod reports get updated, that way we know when we need to look again.
		updateSettings(array('last_mod_report_action' => time()));

		// Back to the post we reported!
		redirectexit('reportsent;topic=' . $topic . '.msg' . $msg_id . '#msg' . $msg_id);
	}
}
