<?php

/**
 * Base abstract class for mail functions
 *
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * @version 2.0 Beta 1
 *
 */

namespace ElkArte\Mail;

/**
 * BaseMail class provides constructor and common code for other mail functions
 */
abstract class BaseMail
{
	/** @var bool If to use PBE/Mailist processing */
	public $mailList = false;

	/** @var bool If to use mail or SMTP to send */
	public $useSendmail = true;

	/** @var string \r\n or \n based on transport and OS */
	public $lineBreak = "\n";

	/** @var string m, p or t */
	public $messageType;

	/** @var array collection of data for saving in DB to allow reply to email */
	public $unqPBEHead = [];

	/** @var string Used to help bounce detection */
	public $returnPath;

	/** @var string The language to use for the email templates */
	public $language;

	/**
	 * Constructor, use to set the transport and linebreak
	 */
	public function __construct()
	{
		$this->setMailTransport();
		$this->setLineBreak();
		$this->setLanguage();

		require_once(SUBSDIR . '/Mail.subs.php');
	}

	/**
	 * Sets the language for the current instance. If no language is provided, the global default language is used.
	 *
	 * @param string $language The language to set. If empty, the global $language will be used.
	 * @return void
	 */
	public function setLanguage($language = ''): void
	{
		if (empty($language))
		{
			$language = $GLOBALS['language'];
		}

		$this->language = $language;
	}

	/**
	 * Sets if we use php mail or smtp mail transport
	 */
	public function setMailTransport(): void
	{
		global $modSettings;

		$this->useSendmail = empty($modSettings['mail_type']) || $modSettings['smtp_host'] === '';
	}

	/**
	 * Based on OS or mail transport, sets the needed linebreak value
	 */
	public function setLineBreak(): void
	{
		// If messages are not received while not using SMTP, then try using a LF (\n) only. Some Unix
		// mail transfer agents (notably qmail) replace LF by CRLF automatically (which leads to doubling
		// CR if CRLF is used). That should be a last resort.
		$this->lineBreak = "\r\n";

		// Line breaks need to be \r\n only in windows or for SMTP.
		// $this->lineBreak = detectServer()->is('windows') || !$this->useSendmail ? "\r\n" : "\n";
	}

	/**
	 * Sets a flag if we are using maillist functionality
	 *
	 * @param string $from_wrapper
	 * @param string $message_id
	 * @param int $priority
	 */
	public function setMailList($from_wrapper, $message_id, $priority): void
	{
		global $modSettings;

		// Using maillist styles and this message qualifies (priority 3 and below only (4 = digest, 5 = newsletter))
		$this->mailList = !empty($modSettings['maillist_enabled'])
			&& $from_wrapper !== null
			&& $message_id !== null
			&& $priority < 4
			&& empty($modSettings['mail_no_message_id']);
	}

	/**
	 * Message type is one of m = message, t = topic, p = private
	 *
	 * @param string $message_id
	 * @return string|null cleaned message id
	 */
	public function setMessageType($message_id): ?string
	{
		$this->messageType = 'm';
		if ($message_id !== null && isset($message_id[0]) && in_array($message_id[0], ['m', 'p', 't']))
		{
			$this->messageType = $message_id[0];
			$message_id = substr($message_id, 1);
		}

		return $message_id;
	}

	/**
	 * Sets the unique ID for the message id header and PBE emails
	 *
	 * If using maillist functions it will also insert the ID into the message body's as
	 * some email clients strip, or do not return, proper headers to show what they are in replying to.
	 * PBE functions depend on finding this key to match up reply's to a message and ensure the reply
	 * was from a valid recipient.
	 *
	 * @param $message_id
	 * @return string
	 */
	public function getUniqueMessageID($message_id): string
	{
		global $boardurl, $modSettings;

		$unq_head = '';

		// If we are using the post by email functions, then we generate "reply to mail" security keys
		if ($this->mailList)
		{
			$this->unqPBEHead[0] = md5($boardurl . microtime() . mt_rand());
			$this->unqPBEHead[1] = $this->messageType;
			$this->unqPBEHead[2] = $message_id;

			$unq_head = $this->unqPBEHead[0] . '-' . $this->unqPBEHead[1] . $this->unqPBEHead[2];
		}
		elseif (empty($modSettings['mail_no_message_id']))
		{
			$unq_head = md5($boardurl . microtime()) . '-' . $message_id;
		}

		return $unq_head;
	}

	/**
	 * Sets a return path, mainly used in PHP mail() function to help in bounce detection
	 *
	 * @return void
	 */
	public function setReturnPath(): void
	{
		global $modSettings, $webmaster_email;

		$this->returnPath = '';
		if ($this->mailList === true)
		{
			$this->returnPath = empty($modSettings['maillist_sitename_address']) ? '' : $modSettings['maillist_sitename_address'];
		}

		if ($this->returnPath === '')
		{
			$this->returnPath = empty($modSettings['maillist_mail_from']) ? $webmaster_email : $modSettings['maillist_mail_from'];
		}
	}
}
