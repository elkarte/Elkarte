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

namespace ElkArte\Mail;

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

	/**
	 * Constructor, use to set the transport and linebreak
	 */
	public function __construct()
	{
		$this->setMailTransport();
		$this->setLineBreak();

		require_once(SUBSDIR . '/Mail.subs.php');
	}

	/**
	 * Sets if we use php mail or smtp mail transport
	 */
	public function setMailTransport()
	{
		global $modSettings;

		$this->useSendmail = empty($modSettings['mail_type']) || $modSettings['smtp_host'] === '';
	}

	/**
	 * Based on OS or mail transport, sets the needed linebreak value
	 */
	public function setLineBreak()
	{
		// Line breaks need to be \r\n only in windows or for SMTP.
		$this->lineBreak = detectServer()->is('windows') || !$this->useSendmail ? "\r\n" : "\n";
	}

	/**
	 * Sets flag if we are using maillist functionality
	 *
	 * @param string $from_wrapper
	 * @param string $message_id
	 * @param int $priority
	 */
	public function setMailList($from_wrapper, $message_id, $priority)
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
	 * @return string cleaned message id
	 */
	public function setMessageType($message_id)
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
	public function getUniqueMessageID($message_id)
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
	public function setReturnPath()
	{
		global $modSettings, $webmaster_email;

		$this->returnPath = !empty($modSettings['maillist_sitename_address']) ? $modSettings['maillist_sitename_address'] : '';

		if ($this->returnPath === '')
		{
			$this->returnPath = empty($modSettings['maillist_mail_from']) ? $webmaster_email : $modSettings['maillist_mail_from'];
		}
	}
}
