<?php

/**
 *
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * @version 2.0 dev
 *
 */

namespace ElkArte\Maillist;

use ElkArte\AbstractController;
use ElkArte\EmailParse;
use ElkArte\Languages\Loader;
use ElkArte\Languages\Txt;

/**
 * Handles items pertaining to posting or PM an item that was received by email
 *
 * @package Maillist
 */
class MaillistPost extends AbstractController
{
	/** @var array holds user information based on found email address */
	public $pbeUser;

	/** @var array holds information on the topic being replyed to */
	public $topicInfo = [];

	/** @var array holds information on the PM being replied to */
	public $pmInfo = [];

	/** @var string easy to read constants */
	private const TOPIC_REPLY = 't';
	private const MESSAGE_REPLY = 'm';
	private const PM_REPLY = 'p';

	/**
	 * The action_index method is responsible for executing the functionality for the "index" action.
	 * It calls the action_pbe_post method, should it ever get here.
	 *
	 * @return void
	 */
	public function action_index()
	{
		$this->action_pbe_post();
	}

	/**
	 * Main email posting controller, reads, parses, checks and posts an email message or PM
	 *
	 * What it does:
	 *
	 * - Allows a user to reply to a topic on the board by emailing a reply to a notification message.
	 * - It must have the security key in the email, or it will be rejected
	 * - It must be from the email of a registered user
	 * - The key must have been sent to that user
	 * - Keys are used once and then discarded
	 * - Accessed by email imap cron script (emailpost.php), and ManageMaillist.controller.php.
	 *
	 * @param string|null $data used to supply a full headers+body email
	 * @param bool $force used to override common failure errors
	 * @param string|null $key used to supply a lost key
	 *
	 * @return bool
	 */
	public function action_pbe_post($data = null, $force = false, $key = null)
	{
		global $maintenance;

		// The function is not even on ...
		if (!$this->isMailListEnabled())
		{
			return false;
		}

		// Prepare
		require_once(SUBSDIR . '/Maillist.subs.php');
		Txt::load('Maillist');
		detectServer()->setMemoryLimit('128M');

		$email_message = $this->loadEmailMessage($data, $key);

		// Is the email valid, not spam, not a bounced message
		if ($this->isValidEmail($email_message, $force) !== true)
		{
			return false;
		}

		// Good we have a key, is it legit?
		if ($this->isValidMessageKey($email_message, $force) !== true)
		{
			return false;
		}

		// In maintenance mode, just log it for now
		if (!empty($maintenance) && $maintenance !== 2
			&& !$this->pbeUser['user_info']['is_admin'] && $this->getUser()->is_admin === false)
		{
			pbe_emailError('error_in_maintenance_mode', $email_message);
			return 'error_in_maintenance_mode';
		}

		// The email looks valid, now on to check the actual user trying to make the post/pm
		if ($email_message->message_type === self::TOPIC_REPLY || $email_message->message_type === self::MESSAGE_REPLY)
		{
			if ($this->loadTopicPermissions($email_message) === false)
			{
				return false;
			}
		}
		elseif ($this->loadPmPermissions($email_message) === false)
		{
			return false;
		}

		// Account for moderation actions
		pbe_check_moderation($this->pbeUser);

		// Maybe an addons wants to do additional spam / security checking
		call_integration_hook('integrate_mailist_checks_before', [$email_message, $this->pbeUser]);

		// Load in the correct Re: for the language
		$this->adjustForUserLanguage();

		// Allow for new topics to be started via an email subject change
		if ($this->makeNewTopicBySubjectChange($email_message) === true)
		{
			$board_info = query_load_board_details($this->topicInfo['id_board'], $this->pbeUser);

			$result = pbe_create_topic($this->pbeUser, $email_message, $board_info);
		}
		// Time to make a Post or a PM, first up topic and message replies
		elseif ($email_message->message_type === self::TOPIC_REPLY || $email_message->message_type === self::MESSAGE_REPLY)
		{
			$result = pbe_create_post($this->pbeUser, $email_message, $this->topicInfo);
		}
		// Must be a PM then
		elseif ($email_message->message_type === self::PM_REPLY)
		{
			$result = pbe_create_pm($this->pbeUser, $email_message, $this->pmInfo);
		}

		// We have now posted or PM'ed .. lets do some database maintenance cause maintenance is fun :'(
		if (!empty($result))
		{
			query_key_maintenance($email_message);

			// Update this user so the log shows they were/are active, no lurking in the email ether
			query_update_member_stats($this->pbeUser, $email_message, $email_message->message_type === self::PM_REPLY ? $this->pmInfo : $this->topicInfo);

			return true;
		}

		return false;
	}

	/**
	 * Checks if the mail list is enabled.
	 *
	 * @return bool Returns true if the mail list is enabled, false otherwise.
	 * @global array $modSettings The settings array.
	 */
	private function isMailListEnabled()
	{
		global $modSettings;

		return !empty($modSettings['maillist_enabled']);
	}

	/**
	 * The loadEmailMessage method is responsible for loading and parsing an email message.
	 *
	 * @param mixed $data The data to be passed to the EmailParse class for reading.
	 * @param string $key The key to be passed to the EmailParse class for loading data.
	 *
	 * @return EmailParse The parsed email message.
	 */
	private function loadEmailMessage($data, $key)
	{
		// Load the email parser and set some data to work with
		$email_message = new EmailParse();
		$email_message->read_data($data, BOARDDIR);

		// Ask for an HTML version (if available) and some needed details
		$email_message->read_email(true, $email_message->raw_message);
		$email_message->load_address();
		$email_message->load_key($key);

		return $email_message;
	}

	/**
	 * The isValidEmail method is responsible for validating an email message.
	 *
	 * @param EmailParse $email_message The email message object to validate.
	 * @param bool $force If set to true, the validation will be forced even if it's a DSN or spam.
	 *
	 * @return bool|string Returns true if the email is valid, and a string with the appropriate error message if the email is invalid.
	 */
	private function isValidEmail($email_message, $force)
	{
		global $modSettings;

		// Check if it's a DSN, and handle it
		if ($email_message->_is_dsn)
		{
			if (!empty($modSettings['pbe_bounce_detect']))
			{
				pbe_disable_user_notify($email_message);

				if (!empty($modSettings['pbe_bounce_record']))
				{
					// They can record the message anyway, if they so wish
					pbe_emailError('error_bounced', $email_message);
					return 'error_bounced';
				}

				return 'error_bounced';
			}

			// When the auto-disable function is not turned on, record the DSN
			// In the failed email table for the admins to handle however
			pbe_emailError('error_bounced', $email_message);
			return 'error_bounced';
		}

		// If the feature is on but the post/pm function is not enabled, just log the message.
		if (empty($modSettings['pbe_post_enabled']) && empty($modSettings['pbe_pm_enabled']))
		{
			pbe_emailError('error_email_notenabled', $email_message);
			return 'error_email_notenabled';
		}

		// Spam I am?
		if ($email_message->load_spam() && !$force)
		{
			pbe_emailError('error_found_spam', $email_message);
			return 'error_found_spam';
		}

		// Load the user from the database based on the sending email address
		$email_message->email['from'] = empty($email_message->email['from']) ? '' : strtolower($email_message->email['from']);
		$this->pbeUser = query_load_user_info($email_message->email['from']);

		// Can't find this email in our database, a non-user, a spammer, a looser, a poser or even worse?
		if (empty($this->pbeUser))
		{
			pbe_emailError('error_not_find_member', $email_message);
			return 'error_not_find_member';
		}

		// Find the message security key, without it, we are not going anywhere ever
		if (empty($email_message->message_key_id))
		{
			pbe_emailError('error_missing_key', $email_message);
			return 'error_missing_key';
		}

		return true;
	}

	/**
	 * Checks if the given message key is valid.
	 *
	 * @param object $email_message The email message object.
	 * @param bool $force Flag indicating whether to force the message validation.
	 *
	 * @return bool|string Returns true if the message key is valid, or an error code if the key is invalid and $force is false.
	 */
	private function isValidMessageKey($email_message, $force)
	{
		// Good we have a key, who was it sent to?
		$key_owner = query_key_owner($email_message);

		// Can't find this key in the database, either
		// a) spam attempt or b) replying with an expired/consumed key
		if (empty($key_owner) && !$force)
		{
			pbe_emailError('error_' . ($email_message->message_type === self::PM_REPLY ? 'pm_' : '') . 'not_find_entry', $email_message);
			return 'error_' . ($email_message->message_type === self::PM_REPLY ? 'pm_' : '') . 'not_find_entry';
		}

		// The key received was not sent to this member ... how we love those email aggregators
		if (strtolower($key_owner) !== $email_message->email['from'] && !$force)
		{
			pbe_emailError('error_key_sender_match', $email_message);
			return 'error_key_sender_match';
		}

		return true;
	}

	/**
	 * Load the topic permissions and message details.
	 *
	 * @param object $email_message The email message object.
	 * @return array|bool The topic info or false if topic is not found.
	 */
	private function loadTopicPermissions($email_message)
	{
		// let's load the topic/message info and any additional permissions we need
		// Load the message/topic details
		$this->topicInfo = query_load_message($email_message->message_type, $email_message->message_id, $this->pbeUser);
		if (empty($this->topicInfo))
		{
			pbe_emailError('error_topic_gone', $email_message);
			return false;
		}

		// Load board permissions
		query_load_permissions('board', $this->pbeUser, $this->topicInfo);

		return $this->topicInfo;
	}

	/**
	 * Load the private message permissions and details.
	 *
	 * @param object $email_message The email message object.
	 * @return array|bool The private message info or false if the message is not found.
	 */
	private function loadPmPermissions($email_message)
	{
		// Load the PM details
		$this->pmInfo = query_load_message($email_message->message_type, $email_message->message_id, $this->pbeUser);
		if (empty($this->pmInfo))
		{
			// Duh oh ... likely they deleted the PM on the site and are now
			// replying to the PM by email, the agony!
			pbe_emailError('error_pm_not_found', $email_message);
			return false;
		}

		return $this->pmInfo;
	}

	/**
	 * Adjust the response prefix based on the user's language.
	 *
	 * @return void
	 */
	private function adjustForUserLanguage()
	{
		global $txt, $language;

		if ($language === $this->pbeUser['user_info']['language'])
		{
			$this->pbeUser['response_prefix'] = $txt['response_prefix'];
		}
		else
		{
			$mtxt = [];
			$lang = new Loader($language, $mtxt, database());
			$lang->load('index', false, true);
			$this->pbeUser['response_prefix'] = $mtxt['response_prefix'];
			unset($mtxt);
		}
	}

	/**
	 * Check if a new topic needs to be created based on the subject change in the email message.
	 *
	 * @param object $email_message The email message object.
	 * @return bool Whether a new topic needs to be created or not.
	 */
	private function makeNewTopicBySubjectChange($email_message)
	{
		global $modSettings;

		if (!empty($modSettings['maillist_newtopic_change']) && ($email_message->message_type === self::MESSAGE_REPLY))
		{
			$subject = str_replace($this->pbeUser['response_prefix'], '', pbe_clean_email_subject($email_message->subject));
			$current_subject = str_replace($this->pbeUser['response_prefix'], '', $this->topicInfo['subject']);

			// If it does not match, then we go to make a new topic instead
			if (trim($subject) !== trim($current_subject))
			{
				return true;
			}
		}

		return false;
	}
}
