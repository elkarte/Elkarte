<?php

/**
 * The functions in this file deal with sending random complaints to a moderator.
 *
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * This file contains code covered by:
 * copyright: 2011 Simple Machines (http://www.simplemachines.org)
 *
 * @version 2.0 dev
 *
 */

namespace ElkArte\Controller;

use ElkArte\AbstractController;
use ElkArte\Errors\ErrorContext;
use ElkArte\Exceptions\Exception;
use ElkArte\Helper\DataValidator;
use ElkArte\Helper\Util;
use ElkArte\Languages\Txt;
use ElkArte\VerificationControls\VerificationControlsIntegrate;

/**
 * Allows for sending topics via email
 */
class Emailmoderator extends AbstractController
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
		theme()->getTemplates()->load('Emailmoderator');
	}

	/**
	 * Default action handler
	 *
	 * @see AbstractController::action_index
	 */
	public function action_index()
	{
		// just accept we haz a default action: action_reporttm()
		$this->action_reporttm();
	}

	/**
	 * Report a post to the moderator... ask for a comment.
	 *
	 * what is does:
	 * - Gathers data from the user to report abuse to the moderator(s).
	 * - Uses the ReportToModerator template, main sub template.
	 * - Requires the report_any permission.
	 * - Uses action_reporttm2() if post data was sent.
	 * - Accessed through ?action=reporttm.
	 */
	public function action_reporttm(): void
	{
		global $txt, $modSettings, $context;

		$context['robot_no_index'] = true;

		// You can't use this if it's off or you are not allowed to do it.
		isAllowedTo('report_any');

		// No errors, yet.
		$report_errors = ErrorContext::context('report', 1);

		// ...or maybe some.
		$context['report_error'] = [
			'errors' => $report_errors->prepareErrors(),
			'type' => $report_errors->getErrorType() == 0 ? 'minor' : 'serious',
		];

		// If they're posting, it should be processed by action_reporttm2.
		if ((isset($this->_req->post->{$context['session_var']}) || isset($this->_req->post->save)) && !$report_errors->hasErrors())
		{
			$this->action_reporttm2();
		}

		// We need a message ID to check!
		if (empty($this->_req->query->msg) && empty($this->_req->post->msg))
		{
			throw new Exception('no_access', false);
		}

		// Check the message's ID - don't want anyone reporting a post that does not exist
		require_once(SUBSDIR . '/Messages.subs.php');
		$message_id = $this->_req->getPost('msg', 'intval', isset($this->_req->query->msg) ? (int) $this->_req->query->msg : 0);
		if (basicMessageInfo($message_id, true, true) === false)
		{
			throw new Exception('no_board', false);
		}

		// Do we need to show the visual verification image?
		$context['require_verification'] = $this->user->is_guest && !empty($modSettings['guests_report_require_captcha']);
		if ($context['require_verification'])
		{
			$verificationOptions = [
				'id' => 'report',
			];
			$context['require_verification'] = VerificationControlsIntegrate::create($verificationOptions);
			$context['visual_verification_id'] = $verificationOptions['id'];
		}

		// Show the inputs for the comment, etc.
		Txt::load('Post');
		Txt::load('Errors');

		$context['comment_body'] = $this->_req->getPost('comment', 'trim', '');
		$context['email_address'] = $this->_req->getPost('email', 'trim', '');

		// This is here so that the user could, in theory, be redirected back to the topic.
		$context['start'] = $this->_req->query->start;
		$context['message_id'] = $message_id;
		$context['page_title'] = $txt['report_to_mod'];
		$context['sub_template'] = 'report';
		theme()->getTemplates()->load('Emailmoderator');
	}

	/**
	 * Send the emails.
	 *
	 * - Sends off emails to all the moderators.
	 * - Sends to administrators and global moderators. (1 and 2)
	 * - Called by action_reporttm(), and thus has the same permission and setting requirements as it does.
	 * - Accessed through ?action=reporttm when posting.
	 */
	public function action_reporttm2(): ?bool
	{
		global $txt, $topic, $board, $modSettings, $language, $context;

		// You must have the proper permissions!
		isAllowedTo('report_any');

		// Make sure they aren't spamming.
		spamProtection('reporttm');

		require_once(SUBSDIR . '/Mail.subs.php');

		// No errors, yet.
		$report_errors = ErrorContext::context('report', 1);

		// Check their session.
		if (checkSession('post', '', false) != '')
		{
			$report_errors->addError('session_timeout');
		}

		// Make sure we have a comment and it's clean.
		if ($this->_req->getPost('comment', '\\ElkArte\\Helper\\Util::htmltrim', '') === '')
		{
			$report_errors->addError('no_comment');
		}

		$poster_comment = strtr(Util::htmlspecialchars($this->_req->post->comment), ["\r" => '', "\t" => '']);

		if (Util::strlen($poster_comment) > 254)
		{
			$report_errors->addError('post_too_long');
		}

		// Guests need to provide their address!
		if ($this->user->is_guest)
		{
			if (!DataValidator::is_valid($this->_req->post, ['email' => 'valid_email'], ['email' => 'trim']))
			{
				empty($this->_req->post->email) ? $report_errors->addError('no_email') : $report_errors->addError('bad_email');
			}

			isBannedEmail($this->_req->post->email, 'cannot_post', sprintf($txt['you_are_post_banned'], $txt['guest_title']));

			$this->user->email = htmlspecialchars($this->_req->post->email, ENT_COMPAT, 'UTF-8');
		}

		// Could they get the right verification code?
		if ($this->user->is_guest && !empty($modSettings['guests_report_require_captcha']))
		{
			$verificationOptions = [
				'id' => 'report',
			];
			$context['require_verification'] = VerificationControlsIntegrate::create($verificationOptions, true);

			if (is_array($context['require_verification']))
			{
				foreach ($context['require_verification'] as $error)
				{
					$report_errors->addError($error, 0);
				}
			}
		}

		// Any errors?
		if ($report_errors->hasErrors())
		{
			$this->action_reporttm();

			return true;
		}

		// Get the basic topic information, and make sure they can see it.
		$msg_id = (int) $this->_req->post->msg;
		$message = posterDetails($msg_id, $topic);

		if (empty($message))
		{
			throw new Exception('no_board', false);
		}

		$poster_name = un_htmlspecialchars($message['real_name']) . ($message['real_name'] !== $message['poster_name'] ? ' (' . $message['poster_name'] . ')' : '');
		$reporterName = un_htmlspecialchars($this->user->name) . ($this->user->name !== $this->user->username && $this->user->username != '' ? ' (' . $this->user->username . ')' : '');
		$subject = un_htmlspecialchars($message['subject']);

		// Get a list of members with the moderate_board permission.
		require_once(SUBSDIR . '/Members.subs.php');
		$moderators = membersAllowedTo('moderate_board', $board);
		$result = getBasicMemberData($moderators, ['preferences' => true, 'sort' => 'lngfile']);
		$mod_to_notify = [];
		foreach ($result as $row)
		{
			if ($row['notify_types'] !== 4)
			{
				$mod_to_notify[] = $row;
			}
		}

		// Check that moderators do exist!
		if (empty($mod_to_notify))
		{
			throw new Exception('no_mods', false);
		}

		// If we get here, I believe we should make a record of this, for historical significance, yabber.
		if (empty($modSettings['disable_log_report']))
		{
			require_once(SUBSDIR . '/Messages.subs.php');
			$message['type'] = 'msg';
			$id_report = recordReport($message, $poster_comment);

			// If we're just going to ignore these, then who gives a monkeys...
			if ($id_report === false)
			{
				redirectexit('topic=' . $topic . '.msg' . $msg_id . '#msg' . $msg_id);
			}
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
				[, , $pref_binary] = explode('|', $row['mod_prefs']);
				if (!($pref_binary & 1) && (!($pref_binary & 2) || !in_array($row['id_member'], $real_mods)))
				{
					continue;
				}
			}

			$replacements = [
				'TOPICSUBJECT' => $subject,
				'POSTERNAME' => $poster_name,
				'REPORTERNAME' => $reporterName,
				'TOPICLINK' => getUrl('topic', ['topic' => $topic, 'start' => 'msg' . $msg_id, 'subject' => $subject]) . '#msg' . $msg_id,
				'REPORTLINK' => empty($id_report) ? '' : getUrl('action', ['action' => 'moderate', 'area' => 'reports', 'report' => $id_report]),
				'COMMENT' => $this->_req->post->comment,
			];

			$emaildata = loadEmailTemplate('report_to_moderator', $replacements, empty($row['lngfile']) || empty($modSettings['userLanguage']) ? $language : $row['lngfile']);

			// Send it to the moderator.
			sendmail($row['email_address'], $emaildata['subject'], $emaildata['body'], $this->user->email, null, false, 2);
		}

		// Keep track of when the mod reports get updated, that way we know when we need to look again.
		updateSettings(['last_mod_report_action' => time()]);

		// Back to the post we reported!
		redirectexit('reportsent;topic=' . $topic . '.msg' . $msg_id . '#msg' . $msg_id);

		return null;
	}
}
