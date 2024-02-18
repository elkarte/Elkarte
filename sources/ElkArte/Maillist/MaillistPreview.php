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
use ElkArte\Languages\Txt;

/**
 * Handles items pertaining to posting or PM an item that was received by email
 *
 * @package Maillist
 */
class MaillistPreview extends AbstractController
{
	/**
	 * The action_index method is responsible for executing the functionality for the "index" action.
	 * It calls the action_pbe_preview method, should it ever get here.
	 *
	 * @return void
	 */
	public function action_index()
	{
		$this->action_pbe_preview();
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
	 * @return string[]|bool
	 */
	public function action_pbe_preview($data = '')
	{
		// Our mail parser and our main subs
		require_once(SUBSDIR . '/Maillist.subs.php');

		// Init
		Txt::load('Maillist');

		// Load the email parser and get some data to work with
		$email_message = $this->loadEmailMessage($data);
		if (empty($email_message->raw_message))
		{
			return false;
		}

		// Convert to BBC and Format for the preview
		$text = pbe_load_text($email_message, []);

		// If there are attachments, just get the count
		$text .= $this->getAttachmentCount($email_message);

		// Return the parsed and formatted body and who it was sent to for the template
		return [
			'body' => $text,
			'to' => implode(' & ', $email_message->email['to']) . (empty($email_message->email['cc']) ? '' : ', ' . implode(' & ', $email_message->email['cc']))
		];
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
		$email_message->load_key();

		return $email_message;
	}

	/**
	 * Retrieves the count of attachments in the given email message.
	 *
	 * @param EmailParse $email_message The email message object.
	 * @return string The count of attachments as a string. If there are no attachments, an empty string is returned.
	 */
	private function getAttachmentCount($email_message)
	{
		global $modSettings, $txt;

		$attachment_count = 0;
		$text = '';

		if (!empty($email_message->attachments) && !empty($modSettings['maillist_allow_attachments']) && !empty($modSettings['attachmentEnable']) && $modSettings['attachmentEnable'] == 1)
		{
			if ($email_message->message_type === 'p')
			{
				$text .= "\n\n" . $txt['error_no_pm_attach'] . "\n";
			}
			else
			{
				$attachment_count = count($email_message->attachments);
			}
		}

		if ($attachment_count !== 0)
		{
			$text .= "\n\n" . sprintf($txt['email_attachments'], $attachment_count);
		}

		return $text;
	}
}
