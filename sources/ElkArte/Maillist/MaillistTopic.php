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
use ElkArte\Languages\Txt;

/**
 * Handles items pertaining to posting or PM an item that was received by email
 *
 * @package Maillist
 */
class MaillistTopic extends AbstractController
{
	/** @var array holds user information based on found email address */
	public $pbeUser;

	/** @var array holds information on the PM being replied to */
	public $boardInfo = [];

	/** @var int the board id we are posting to */
	public $boardNumber;

	/**
	 * action_index
	 *
	 * This method is used to perform the action for the index page.
	 *
	 * @return void
	 */
	public function action_index()
	{
		$this->action_pbe_topic();
	}

	/**
	 * New Topic posting controller, reads, parses, checks and posts a new topic
	 *
	 * What it does:
	 *
	 * - New topics do not have security keys in them, so they are subject to spoofing
	 * - It must be from the email of a registered user
	 * - It must have been sent to an email ID that has been set to post new topics
	 * - Accessed through emailtopic.
	 *
	 * @param string|null $data used to supply a full body+headers email
	 *
	 * @return bool
	 */
	public function action_pbe_topic($data = null)
	{
		global $maintenance;

		// The function is not even on ...
		if (!$this->isMailListEnabled())
		{
			return false;
		}

		// Init
		require_once(SUBSDIR . '/MaillistPost.subs.php');
		Txt::load('Maillist');
		detectServer()->setMemoryLimit('128M');

		// Get the data from one of our email data
		$email_message = $this->loadEmailMessage($data);

		// No key for a new topic, so set some blanks for the error function (if needed)
		$email_message->message_type = 'x';
		$email_message->message_key_id = '';
		$email_message->message_key = '';
		$email_message->message_id = 0;

		// Is the email valid, not spam, not a bounced message
		if ($this->isValidEmail($email_message) !== true)
		{
			return false;
		}

		// In maintenance mode so just save it for the moderators to deal with
		if (!empty($maintenance) && $maintenance !== 2 && !$this->pbeUser['user_info']['is_admin'] && $this->emailpost->getUser()->is_admin === false)
		{
			return pbe_emailError('error_in_maintenance_mode', $email_message);
		}

		// Any additional spam / security checking that an addon may want
		call_integration_hook('integrate_mailist_checks_before', [$email_message, $this->pbeUser]);

		// To post a NEW topic, we need some board details for where it goes
		$this->boardInfo = query_load_board_details($this->boardNumber, $this->pbeUser);
		if (empty($this->boardInfo))
		{
			pbe_emailError('error_board_gone', $email_message);
			return false;
		}

		// Load up the user permissions for the board
		query_load_permissions('board', $this->pbeUser, $this->boardInfo);

		// Account for any moderation they may be under
		pbe_check_moderation($this->pbeUser);

		// Create the topic, send notifications
		return pbe_create_topic($this->pbeUser, $email_message, $this->boardInfo);
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
	 *
	 * @return EmailParse The parsed email message.
	 */
	private function loadEmailMessage($data)
	{
		// Load the email parser and set some data to work with
		$email_message = new EmailParse();
		$email_message->read_data($data, BOARDDIR);

		// Ask for an HTML version (if available) and some needed details
		$email_message->read_email(true, $email_message->raw_message);
		$email_message->load_address();

		return $email_message;
	}

	/**
	 * The isValidEmail method is responsible for validating an email message.
	 *
	 * @param EmailParse $email_message The email message object to validate.
	 *
	 * @return bool|string Returns true if the email is valid, and a string with the appropriate error message if the email is invalid.
	 */
	private function isValidEmail($email_message)
	{
		global $modSettings;

		// Check if it's a DSN
		if ($email_message->_is_dsn)
		{
			if (!empty($modSettings['pbe_bounce_detect']))
			{
				pbe_disable_user_notify($email_message);

				// @todo Notify the user
				if (!empty($modSettings['pbe_bounce_record']))
				{
					// They can record the message anyway, if they so wish
					pbe_emailError('error_bounced', $email_message);
					return 'error_bounced';
				}

				// If they don't wish, then return false like recording the failure
				return 'error_bounced';
			}

			// When the auto-disable function is not turned on, record the DSN
			// In the failed email table for the admins to handle however
			pbe_emailError('error_bounced', $email_message);
			return 'error_bounced';
		}

		// If the feature is on but the post/pm function is not enabled, just log the message.
		if (empty($modSettings['pbe_post_enabled']))
		{
			pbe_emailError('error_email_notenabled', $email_message);
			return 'error_email_notenabled';
		}

		// Load the user from the database based on the sending email address
		$email_message->email['from'] = empty($email_message->email['from']) ? '' : strtolower($email_message->email['from']);
		$this->pbeUser = query_load_user_info($email_message->email['from']);

		// Can't find this email as one of our users?
		if (empty($this->pbeUser))
		{
			pbe_emailError('error_not_find_member', $email_message);
			return 'error_not_find_member';
		}

		// Getting hammy with it?
		if ($email_message->load_spam())
		{
			pbe_emailError('error_found_spam', $email_message);
			return 'error_found_spam';
		}

		// The board that this email address corresponds to
		$this->boardNumber = pbe_find_board_number($email_message);
		if (empty($this->boardNumber))
		{
			pbe_emailError('error_not_find_board', $email_message);
			return 'error_not_find_board';
		}

		return true;
	}
}
