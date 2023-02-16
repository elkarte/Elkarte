<?php

/**
 * This class deals with the actual sending of your sites emails
 *
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * @version 2.0 dev
 *
 */

namespace ElkArte\Mail;

use ElkArte\Errors\Errors;

/**
 * Deals with the sending of email via mail() or SMTP functions
 */
class Mail extends BaseMail
{
	/**
	 * This function dispatches to PHP mail or SMTP mail to send email to the specified recipient(s).
	 *
	 * It uses the mail_type settings and webmaster_email variable.
	 *
	 * @param string[]|string $to - the email(s) to send to
	 * @param string $subject - email subject as prepared by buildEmail()
	 * @param string $message - email body as processed by buildEmail()
	 * @param string|null $message_id = null - if specified, it will be used as local part of the Message-ID header.
	 * @return bool whether the email was accepted properly.
	 * @package Mail
	 */
	public function sendMail($to, $subject, $headers, $message, $message_id = null)
	{
		$message_id = $this->setMessageType($message_id);

		$to = is_array($to) ? $to : [$to];

		if ($this->useSendmail)
		{
			return $this->sendPHP($to, $subject, $message, $headers, $message_id);
		}

		return $this->SMTP($to, $subject, $message, $headers, $message_id);
	}

	/**
	 * Sends an email using PHP mail() function
	 *
	 * @param string[] $mail_to_array
	 * @param string $subject
	 * @param string $message
	 * @param string $headers
	 * @param string $message_id
	 * @return bool if the mail was accepted by the system
	 */
	public function sendPHP($mail_to_array, $subject, $message, $headers, $message_id)
	{
		global $webmaster_email, $modSettings, $txt;

		$mail_result = true;
		$subject = strtr($subject, ["\r" => '', "\n" => '']);

		// Looks like another hidden beauty here
		if (!empty($modSettings['mail_strip_carriage']))
		{
			$message = strtr($message, ["\r" => '']);
			$headers = strtr($headers, ["\r" => '']);
		}

		$mid = strstr(empty($modSettings['maillist_mail_from']) ? $webmaster_email : $modSettings['maillist_mail_from'], '@');
		$this->setReturnPath();

		// This is frequently not set, or not set according to the needs of PBE and bounce detection
		// We have to use ini_set, since "-f <address>" doesn't work on Windows systems, so we need both
		$old_return = ini_set('sendmail_from', $this->returnPath);

		$sent = [];
		foreach ($mail_to_array as $key => $sendTo)
		{
			// Every message sent gets a unique Message-ID header
			$unq_head = $this->getUniqueMessageID($message_id);
			$messageHeader = 'Message-ID: <' . $unq_head . $mid . '>';

			// Using PBE, we also insert keys in the message as a safety net of sorts
			if ($this->mailList)
			{
				$message = mail_insert_key($message, $unq_head, $this->lineBreak);
			}

			$sendTo = strtr($sendTo, ["\r" => '', "\n" => '']);
			if (!mail($sendTo, $subject, $message, $headers . $this->lineBreak . $messageHeader, '-f ' . $this->returnPath))
			{
				Errors::instance()->log_error(sprintf($txt['mail_send_unable'], $sendTo));
				$mail_result = false;
			}
			else
			{
				// Keep our post via email log
				if ($this->mailList)
				{
					$this->unqPBEHead[] = time();
					$this->unqPBEHead[] = $sendTo;
					$sent[] = $this->unqPBEHead;
				}

				// Track total emails sent
				if (!empty($modSettings['trackStats']))
				{
					trackStats(['email' => '+']);
				}
			}

			// Wait, wait, I'm still sending here!
			detectServer()->setTimeLimit(300);
		}

		// Put it back
		ini_set('sendmail_from', $old_return);

		// Log each email that we sent, such that they can be replied to
		if (!empty($sent))
		{
			require_once(SUBSDIR . '/Maillist.subs.php');
			log_email($sent);
		}

		return $mail_result;
	}

	/**
	 * Sends mail, like mail() but using Simple Mail Transfer Protocol (SMTP).
	 *
	 * - It expects no slashes or entities.
	 *
	 * @param string[] $mail_to_array - array of strings (email addresses)
	 * @param string $subject email subject
	 * @param string $message email message
	 * @param string $headers
	 * @param string|null $message_id
	 * @return bool whether it sent or not.
	 * @package Mail
	 */
	public function SMTP($mail_to_array, $subject, $message, $headers, $message_id = null)
	{
		global $modSettings, $webmaster_email;

		// This should already be set in the ACP
		if (empty($modSettings['smtp_client']))
		{
			$modSettings['smtp_client'] = detectServer()->getFQDN(empty($modSettings['smtp_host']) ? '' : $modSettings['smtp_host']);
			updateSettings(['smtp_client' => $modSettings['smtp_client']]);
		}

		// Shortcuts
		$smtp_client = $modSettings['smtp_client'];
		$smtp_port = empty($modSettings['smtp_port']) ? 25 : (int) $modSettings['smtp_port'];
		$smtp_host = trim($modSettings['smtp_host']);

		// Try to connect to the SMTP server...
		$socket = $this->_getSMTPSocket($smtp_host, $smtp_port);
		if (!is_resource($socket))
		{
			return false;
		}

		// The server responded, now login our client
		$login = $this->_loginSMTPClient($socket, $smtp_client);
		if ($login === false)
		{
			return false;
		}

		// Fix the message for any lines beginning with a period! (the first is ignored, you see.)
		$message = strtr($message, ["\r\n" . '.' => "\r\n" . '..']);

		$mid = strstr(empty($modSettings['maillist_mail_from']) ? $webmaster_email : $modSettings['maillist_mail_from'], '@');
		$this->setReturnPath();
		$mail_to_array = array_values($mail_to_array);
		$sent = [];

		// Time to send these, so they can be trapped in a SPAM filter :P
		foreach ($mail_to_array as $i => $mail_to)
		{
			$this_message = $message;
			$unq_head = $this->getUniqueMessageID($message_id);
			$messageHeader = 'Message-ID: <' . $unq_head . $mid . '>';

			// Reset the connection to send another email.
			if (($i !== 0) && !$this->_server_parse('RSET', $socket, '250'))
			{
				return false;
			}

			// From, to, and then start the data...
			if (!$this->_server_parse('MAIL FROM: <' . $this->returnPath . '>', $socket, '250'))
			{
				return false;
			}

			if (!$this->_server_parse('RCPT TO: <' . $mail_to . '>', $socket, '250'))
			{
				return false;
			}

			if (!$this->_server_parse('DATA', $socket, '354'))
			{
				return false;
			}

			// Using PBE, we also insert keys in the message to overcome clients that act badly
			if ($this->mailList)
			{
				$this_message = mail_insert_key($this_message, $unq_head, $this->lineBreak);
			}

			fwrite($socket, 'Subject: ' . $subject . $this->lineBreak);
			if ($mail_to !== '')
			{
				fwrite($socket, 'To: <' . $mail_to . '>' . $this->lineBreak);
			}
			fwrite($socket, $headers . $this->lineBreak . $messageHeader . $this->lineBreak . $this->lineBreak);
			fwrite($socket, $this_message . $this->lineBreak);

			// Send a ., or in other words "end of data".
			if (!$this->_server_parse('.', $socket, '250'))
			{
				return false;
			}

			// track the number of emails sent
			if (!empty($modSettings['trackStats']))
			{
				trackStats(['email' => '+']);
			}

			// Keep our post via email log
			if ($this->mailList)
			{
				$this->unqPBEHead[] = time();
				$this->unqPBEHead[] = $mail_to;
				$sent[] = $this->unqPBEHead;
			}

			// Almost done, almost done... don't stop me just yet!
			detectServer()->setTimeLimit(300);
		}

		// say our goodbyes
		fwrite($socket, 'QUIT' . $this->lineBreak);
		fclose($socket);

		// Log each email if using PBE
		if (!empty($sent))
		{
			require_once(SUBSDIR . '/Maillist.subs.php');
			log_email($sent);
		}

		return true;
	}

	/**
	 * Make a connection to the SMTP server
	 *
	 * @param string $smtp_host
	 * @param int $smtp_port
	 * @return false|resource
	 */
	private function _getSMTPSocket($smtp_host, $smtp_port)
	{
		global $txt;

		// Try to connect to the SMTP server... if it doesn't exist, only wait three seconds.
		set_error_handler(static function () { /* ignore errors */ });
		try
		{
			$socket = fsockopen($smtp_host, $smtp_port, $errno, $errstr, 3);
		}
		catch (\Exception $e)
		{
			$socket = false;
		}
		finally
		{
			restore_error_handler();
		}

		if (!is_resource($socket))
		{
			// Maybe we can still save this?  The port might be wrong.
			if ($smtp_port === 25 && substr($smtp_host, 0, 4) === 'ssl:')
			{
				$socket = fsockopen($smtp_host, 465, $errno, $errstr, 3);
				if (is_resource($socket))
				{
					updateSettings(['smtp_port' => 465]);
					Errors::instance()->log_error($txt['smtp_port_ssl']);
				}
			}

			// Unable to connect!
			if (!is_resource($socket))
			{
				Errors::instance()->log_error($txt['smtp_no_connect'] . ': ' . $errno . ' : ' . $errstr);
			}
		}

		// Wait for a response of 220, without "-" continue.
		if (!is_resource($socket) || !$this->_server_parse(null, $socket, '220'))
		{
			return false;
		}

		return $socket;
	}

	/**
	 * Parse a message to the SMTP server.
	 *
	 * - Sends the specified message to the server, and checks for the expected response.
	 *
	 * @param string $message - the message to send
	 * @param resource $socket - socket to send on
	 * @param string $response - the expected response code
	 * @return string|bool it responded as such.
	 * @package Mail
	 */
	private function _server_parse($message, $socket, $response)
	{
		global $txt;

		if ($message !== null)
		{
			fwrite($socket, $message . "\r\n");
		}

		// No response yet.
		$server_response = '';

		while (substr($server_response, 3, 1) !== ' ')
		{
			if (!($server_response = fgets($socket, 256)))
			{
				// @todo Change this message to reflect that it may mean bad user/password/server issues/etc.
				Errors::instance()->log_error($txt['smtp_bad_response']);

				return false;
			}
		}

		if ($response === null)
		{
			return substr($server_response, 0, 3);
		}

		if (strpos($server_response, $response) !== 0)
		{
			Errors::instance()->log_error($txt['smtp_error'] . $server_response);

			return false;
		}

		return true;
	}

	/**
	 * Logs a 'user' on to the SMTP server
	 *
	 * If it fails and suspects TLS is required, will attempt that as well.
	 *
	 * @param resource $socket
	 * @param string $smtp_client
	 * @return bool
	 */
	private function _loginSMTPClient($socket, $smtp_client)
	{
		global $modSettings;

		$smtp_username = trim($modSettings['smtp_username']);
		$smtp_password = trim($modSettings['smtp_password']);
		$smtp_starttls = !empty($modSettings['smtp_starttls']);

		if ($smtp_username !== '' && $smtp_password !== '')
		{
			// EHLO could be understood to mean encrypted hello...
			if ($this->_server_parse('EHLO ' . $smtp_client, $socket, null) === '250')
			{
				if ($smtp_starttls)
				{
					$this->_server_parse('STARTTLS', $socket, null);
					stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
					$this->_server_parse('EHLO ' . $smtp_client, $socket, null);
				}

				if (!$this->_server_parse('AUTH LOGIN', $socket, '334'))
				{
					return false;
				}

				// Send the username and password, encoded.
				if (!$this->_server_parse(base64_encode($smtp_username), $socket, '334'))
				{
					return false;
				}

				// The password is already encoded ;)
				if (!$this->_server_parse($smtp_password, $socket, '235'))
				{
					return false;
				}

				return true;
			}

			if ($this->_server_parse('HELO ' . $smtp_client, $socket, '250'))
			{
				return true;
			}

			return false;
		}

		// Just say "helo".
		if ($this->_server_parse('HELO ' . $smtp_client, $socket, '250'))
		{
			return true;
		}

		return false;
	}
}