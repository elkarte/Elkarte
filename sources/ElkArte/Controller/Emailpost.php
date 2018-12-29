<?php

/**
 * All the functions that validate and then save an email as a post or pm
 *
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * @version 2.0 dev
 *
 */

namespace ElkArte\Controller;

/**
 * Handles items pertaining to posting or PM an item that was received by email
 *
 * @package Maillist
 */
class Emailpost extends \ElkArte\AbstractController
{
	/**
	 * Default entry point, it forwards to a worker method,
	 * if we ever get here.
	 * @see \ElkArte\AbstractController::action_index()
	 */
	public function action_index()
	{
		// By default we go to preview
		$this->action_pbe_preview();
	}

	/**
	 * Main email posting controller, reads, parses, checks and posts an email message or PM
	 *
	 * What it does:
	 *
	 * - Allows a user to reply to a topic on the board by emailing a reply to a
	 * notification message.
	 * - It must have the security key in the email or it will be rejected
	 * - It must be from the email of a registered user
	 * - The key must have been sent to that user
	 * - Keys are used once and then discarded
	 * - Accessed by email imap cron script, and ManageMaillist.controller.php.
	 *
	 * @param string|null $data used to supply a full headers+body email
	 * @param boolean $force used to override common failure errors
	 * @param string|null $key used to supply a lost key
	 *
	 * @return bool
	 * @throws \ElkArte\Exceptions\Exception
	 */
	public function action_pbe_post($data = null, $force = false, $key = null)
	{
		global $txt, $modSettings, $language, $user_info, $maintenance;

		// The function is not even on ...
		if (empty($modSettings['maillist_enabled']))
			return false;

		// Our mail parser and our main subs
		require_once(SUBSDIR . '/Emailpost.subs.php');

		// Init
		theme()->getTemplates()->loadLanguageFile('Maillist');
		detectServer()->setMemoryLimit('128M');

		// Load the email parser and get some data to work with
		$email_message = new \ElkArte\EmailParse();
		$email_message->read_data($data, BOARDDIR);
		if (!$email_message->raw_message)
			return false;

		// Ask for an html version (if available) and some needed details
		$email_message->read_email(true, $email_message->raw_message);
		$email_message->load_address();
		$email_message->load_key($key);

		// Check if it's a DSN, and handle it
		if ($email_message->_is_dsn)
		{
			if (!empty($modSettings['pbe_bounce_detect']))
			{
				pbe_disable_user_notify($email_message);

				// @todo Notify the user
				if (!empty($modSettings['pbe_bounce_record']))
				{
					// They can record the message anyway, if they so wish
					return pbe_emailError('error_bounced', $email_message);
				}

				// If they don't wish, then return false like recording the failure would do
				return false;
			}
			else
			{
				// When the auto-disable function is not turned on, record the DSN
				// In the failed email table for the admins to handle however
				return pbe_emailError('error_bounced', $email_message);
			}
		}

		// If the feature is on but the post/pm function is not enabled, just log the message.
		if (empty($modSettings['pbe_post_enabled']) && empty($modSettings['pbe_pm_enabled']))
			return pbe_emailError('error_email_notenabled', $email_message);

		// Spam I am?
		if ($email_message->load_spam() && !$force)
			return pbe_emailError('error_found_spam', $email_message);

		// Load the user from the database based on the sending email address
		$email_message->email['from'] = !empty($email_message->email['from']) ? strtolower($email_message->email['from']) : '';
		$pbe = query_load_user_info($email_message->email['from']);

		// Can't find this email in our database, a non-user, a spammer, a looser, a poser or even worse?
		if (empty($pbe))
			return pbe_emailError('error_not_find_member', $email_message);

		// Find the message security key, without it we are not going anywhere ever
		if (empty($email_message->message_key_id))
			return pbe_emailError('error_missing_key', $email_message);

		require_once(SUBSDIR . '/Emailpost.subs.php');
		// Good we have a key, who was it sent to?
		$key_owner = query_key_owner($email_message);

		// Can't find this key in the database, either
		// a) spam attempt or b) replying with an expired/consumed key
		if (empty($key_owner) && !$force)
			return pbe_emailError('error_' . ($email_message->message_type === 'p' ? 'pm_' : '') . 'not_find_entry', $email_message);

		// The key received was not sent to this member ... how we love those email aggregators
		if (strtolower($key_owner) !== $email_message->email['from'] && !$force)
			return pbe_emailError('error_key_sender_match', $email_message);

		// In maintenance mode, just log it for now
		if (!empty($maintenance) && $maintenance !== 2 && !$pbe['user_info']['is_admin'] && !$user_info['is_admin'])
			return pbe_emailError('error_in_maintenance_mode', $email_message);

		// The email looks valid, now on to check the actual user trying to make the post/pm
		// lets load the topic/message info and any additional permissions we need
		$topic_info = array();
		$pm_info = array();
		if ($email_message->message_type === 't' || $email_message->message_type === 'm')
		{
			// Load the message/topic details
			$topic_info = query_load_message($email_message->message_type, $email_message->message_id, $pbe);
			if (empty($topic_info))
				return pbe_emailError('error_topic_gone', $email_message);

			// Load board permissions
			query_load_permissions('board', $pbe, $topic_info);
		}
		else
		{
			// Load the PM details
			$pm_info = query_load_message($email_message->message_type, $email_message->message_id, $pbe);
			if (empty($pm_info))
			{
				// Duh oh ... likely they deleted the PM on the site and are now
				// replying to the PM by email, the agony!
				return pbe_emailError('error_pm_not_found', $email_message);
			}
		}

		// Account for moderation actions
		pbe_check_moderation($pbe);

		// Maybe they want to do additional spam / security checking
		call_integration_hook('integrate_mailist_checks_before', array($email_message, $pbe));

		// Load in the correct Re: for the language
		if ($language === $pbe['user_info']['language'])
			$pbe['response_prefix'] = $txt['response_prefix'];
		else
		{
			theme()->getTemplates()->loadLanguageFile('index', $language, false);
			$pbe['response_prefix'] = $txt['response_prefix'];
			theme()->getTemplates()->loadLanguageFile('index');
		}

		// Allow for new topics to be started via a email subject change
		if (!empty($modSettings['maillist_newtopic_change']) && ($email_message->message_type === 'm'))
		{
			$subject = str_replace($pbe['response_prefix'], '', pbe_clean_email_subject($email_message->subject));
			$current_subject = str_replace($pbe['response_prefix'], '', $topic_info['subject']);

			// If it does not match, then we go to make a new topic instead
			if (trim($subject) != trim($current_subject))
			{
				$board_info = query_load_board_details($topic_info['id_board'], $pbe);
				return pbe_create_topic($pbe, $email_message, $board_info);
			}
		}

		// Time to make a Post or a PM, first up topic and message replies
		if ($email_message->message_type === 't' || $email_message->message_type === 'm')
			$result = pbe_create_post($pbe, $email_message, $topic_info);
		// Must be a PM then
		elseif ($email_message->message_type === 'p')
			$result = pbe_create_pm($pbe, $email_message, $pm_info);

		if (!empty($result))
		{
			// We have now posted or PM'ed .. lets do some database maintenance cause maintenance is fun :'(
			query_key_maintenance($email_message);

			// Update this user so the log shows they were/are active, no lurking in the email ether
			query_update_member_stats($pbe, $email_message, $email_message->message_type === 'p' ? $pm_info : $topic_info);
		}

		return !empty($result);
	}

	/**
	 * New Topic posting controller, reads, parses, checks and posts a new topic
	 *
	 * What it does:
	 *
	 * - New topics do not have security keys in them so they are subject to spoofing
	 * - It must be from the email of a registered user
	 * - It must have been sent to an email ID that has been set to post new topics
	 * - Accessed through emailtopic.
	 *
	 * @param string|null $data used to supply a full body+headers email
	 *
	 * @return bool
	 * @throws \ElkArte\Exceptions\Exception
	 */
	public function action_pbe_topic($data = null)
	{
		global $modSettings, $user_info, $maintenance;

		// The function is not even on ...
		if (empty($modSettings['maillist_enabled']))
			return false;

		// Our mail parser and our main subs
		require_once(SUBSDIR . '/Emailpost.subs.php');

		// Init
		theme()->getTemplates()->loadLanguageFile('Maillist');
		detectServer()->setMemoryLimit('256M');

		// Get the data from one of our sources
		$email_message = new \ElkArte\EmailParse();
		$email_message->read_data($data, BOARDDIR);
		if (!$email_message->raw_message)
			return false;

		// Parse the header and some needed details
		$email_message->read_email(true, $email_message->raw_message);
		$email_message->load_address();

		// No key for this, so set some blanks for the error function (if needed)
		$email_message->message_type = 'x';
		$email_message->message_key_id = '';
		$email_message->message_key = '';
		$email_message->message_id = 0;

		// Check if it's a DSN
		// Hopefully, this will eventually DO something but for now
		// we'll just add it with a more specific error reason
		if ($email_message->_is_dsn)
		{
			if (!empty($modSettings['pbe_bounce_detect']))
			{
				pbe_disable_user_notify($email_message);

				// @todo Notify the user
				if (!empty($modSettings['pbe_bounce_record']))
				{
					// They can record the message anyway, if they so wish
					return pbe_emailError('error_bounced', $email_message);
				}

				// If they don't wish, then return false like recording the failure
				return false;
			}
			else
			{
				// When the auto-disable function is not turned on, record the DSN
				// In the failed email table for the admins to handle however
				return pbe_emailError('error_bounced', $email_message);
			}
		}

		// If the feature is on but the post/pm function is not enabled, just log the message.
		if (empty($modSettings['pbe_post_enabled']))
			return pbe_emailError('error_email_notenabled', $email_message);

		// Load the user from the database based on the sending email address
		$email_message->email['from'] = !empty($email_message->email['from']) ? strtolower($email_message->email['from']) : '';
		$pbe = query_load_user_info($email_message->email['from']);

		// Can't find this email as one of our users?
		if (empty($pbe))
			return pbe_emailError('error_not_find_member', $email_message);

		// Getting hammy with it?
		if ($email_message->load_spam())
			return pbe_emailError('error_found_spam', $email_message);

		// The board that this email address corresponds to
		$board_number = pbe_find_board_number($email_message);
		if (empty($board_number))
			return pbe_emailError('error_not_find_board', $email_message);

		// In maintenance mode so just save it for the moderators to deal with
		if (!empty($maintenance) && $maintenance !== 2 && !$pbe['user_info']['is_admin'] && !$user_info['is_admin'])
			return pbe_emailError('error_in_maintenance_mode', $email_message);

		// Any additional spam / security checking
		call_integration_hook('integrate_mailist_checks_before', array($email_message, $pbe));

		// To post a NEW topic, we need some board details for where it goes
		$board_info = query_load_board_details($board_number, $pbe);
		if (empty($board_info))
			return pbe_emailError('error_board_gone', $email_message);

		// Load up this users permissions for that board
		query_load_permissions('board', $pbe, $board_info);

		// Account for any moderation they may be under
		pbe_check_moderation($pbe);

		// Create the topic, send notifications
		return pbe_create_topic($pbe, $email_message, $board_info);
	}

	/**
	 * Used to preview a failed email from the ACP
	 *
	 * What it does:
	 *
	 * - Called from ManageMaillist.controller, which checks topic/message permission for viewing
	 * - Calls pbe_load_text to prepare text for the preview
	 * - Returns an array of values for use in the template
	 *
	 * @param string $data raw email string, including headers
	 * @return string[]|boolean
	 * @throws \ElkArte\Exceptions\Exception
	 */
	public function action_pbe_preview($data = '')
	{
		global $txt, $modSettings;

		// Our mail parser and our main subs
		require_once(SUBSDIR . '/Emailpost.subs.php');

		// Init
		$pbe = array();
		theme()->getTemplates()->loadLanguageFile('Maillist');

		// Load the email parser and get some data to work with
		$email_message = new \ElkArte\EmailParse();
		$email_message->read_data($data, BOARDDIR);
		if (!$email_message->raw_message)
			return false;

		// Ask for an html version (if available) and some needed details
		$email_message->read_email(true, $email_message->raw_message);
		$html = $email_message->html_found;
		$email_message->load_address();
		$email_message->load_key();

		// Convert to BBC and Format for the preview
		$text = pbe_load_text($html, $email_message, $pbe);

		// If there are attachments, just get the count
		$attachment_count = 0;
		if (!empty($email_message->attachments) && !empty($modSettings['maillist_allow_attachments']) && !empty($modSettings['attachmentEnable']) && $modSettings['attachmentEnable'] == 1)
		{
			if ($email_message->message_type === 'p')
				$text .= "\n\n" . $txt['error_no_pm_attach'] . "\n";
			else
				$attachment_count = count($email_message->attachments);
		}

		if ($attachment_count)
			$text .= "\n\n" . sprintf($txt['email_attachments'], $attachment_count);

		// Return the parsed and formatted body and who it was sent to for the template
		return array('body' => $text, 'to' => implode(' & ', $email_message->email['to']) . (!empty($email_message->email['cc']) ? ', ' . implode(' & ', $email_message->email['cc']) : ''));
	}
}
