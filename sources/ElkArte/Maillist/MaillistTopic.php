<?php

/**
 *
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * @version 2.0 Beta 2
 *
 */

namespace ElkArte\Maillist;

use ElkArte\AbstractController;
use ElkArte\Helper\Util;

/**
 * Handles items pertaining to posting or PM an item that was received by email
 *
 * @package Maillist
 */
class MaillistTopic extends AbstractController
{
	use MaillistEmailTrait;

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
	public function action_pbe_topic($data = null): bool
	{
		// The function is not even on ...
		if (!$this->isMailListEnabled())
		{
			return false;
		}

		// Init
		$this->initPbe();

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
		if ($this->isBlockedByMaintenance())
		{
			return pbe_emailError('error_in_maintenance_mode', $email_message);
		}

		// Any additional spam / security checking that an addon may want
		$this->runPreChecksHook($email_message);

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
		$this->applyModerationLimits();

		// Create the topic, send notifications
		return pbe_create_topic($this->pbeUser, $email_message, $this->boardInfo);
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
		if (($result = $this->checkDsn($email_message)) !== true)
		{
			return $result;
		}

		// If the feature is on but the post/pm function is not enabled, just log the message.
		if (empty($modSettings['pbe_post_enabled']))
		{
			pbe_emailError('error_email_notenabled', $email_message);
			return 'error_email_notenabled';
		}

		// Load the user from the database based on the sending email address
		if (($result = $this->loadSender($email_message)) !== true)
		{
			return $result;
		}

		// Getting hammy with it?
		if ($email_message->load_spam())
		{
			pbe_emailError('error_found_spam', $email_message);
			return 'error_found_spam';
		}

		// The board that this email address corresponds to
		$this->boardNumber = $this->findBoardNumber($email_message);
		if (empty($this->boardNumber))
		{
			pbe_emailError('error_not_find_board', $email_message);
			return 'error_not_find_board';
		}

		return true;
	}

	/**
	 * Finds the board id that the address(es) an email was sent to is assigned to in the ACP
	 *
	 * @param EmailParse $email_message
	 *
	 * @return int the board number in which the new topic must go, 0 if none
	 */
	private function findBoardNumber($email_message): int
	{
		global $modSettings;

		$valid_address = [];
		$data = empty($modSettings['maillist_receiving_address']) ? [] : Util::unserialize($modSettings['maillist_receiving_address']);
		foreach ($data as $addr)
		{
			$valid_address[$addr[0]] = $addr[1];
		}

		// May have been sent to multiple addresses, so check each one
		foreach ($email_message->email['to'] as $to_email)
		{
			if (isset($valid_address[$to_email]))
			{
				return (int) $valid_address[$to_email];
			}
		}

		return 0;
	}
}
