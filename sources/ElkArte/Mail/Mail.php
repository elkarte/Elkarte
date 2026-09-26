<?php

/**
 * This class deals with the actual sending of your sites emails
 *
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * @version 2.0 Beta 1
 *
 */

namespace ElkArte\Mail;

use ElkArte\Errors\Errors;
use Throwable;

/**
 * Deals with the sending of email via mail() or SMTP functions
 */
class Mail extends BaseMail
{
	/** @var resource|null Active SMTP socket connection */
	protected $socket;

	/** @var bool Whether to keep the SMTP socket open across multiple emails */
	protected bool $keepAlive = false;

	/**
	 * Returns whether keep-alive is enabled.
	 *
	 * @return bool
	 */
	public function isKeepAlive(): bool
	{
		return $this->keepAlive;
	}

	/**
	 * Sets whether to keep the SMTP socket connection open for multiple emails.
	 *
	 * @param bool $keepAlive
	 * @return self
	 */
	public function setKeepAlive(bool $keepAlive): self
	{
		$this->keepAlive = $keepAlive;

		return $this;
	}

	/**
	 * Returns whether an SMTP socket is currently connected.
	 *
	 * @return bool
	 */
	public function isConnected(): bool
	{
		return is_resource($this->socket) && !feof($this->socket);
	}

	/**
	 * Destructor to ensure any open socket is closed.
	 */
	public function __destruct()
	{
		$this->closeSMTP();
	}

	/**
	 * Closes the active SMTP connection if open.
	 *
	 * @return void
	 */
	public function closeSMTP(): void
	{
		if (is_resource($this->socket))
		{
			set_error_handler(static function () { /* ignore errors */ });
			try
			{
				fwrite($this->socket, 'QUIT' . $this->lineBreak);
			}
			catch (Throwable)
			{
				// Ignore any errors during QUIT
			}
			finally
			{
				fclose($this->socket);
				$this->socket = null;
				restore_error_handler();
			}
		}
		else
		{
			$this->socket = null;
		}
	}

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
	public function sendMail($to, $subject, $headers, $message, $message_id = null): bool
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
	 * @return bool if the system accepted the mail
	 */
	public function sendPHP($mail_to_array, $subject, $message, $headers, $message_id): bool
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
		foreach ($mail_to_array as $sendTo)
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
					$this->unqPBEHead[3] = time();
					$this->unqPBEHead[4] = $sendTo;
					$sent[] = $this->unqPBEHead;
				}

				// Track total emails sent
				if (!empty($modSettings['trackStats']))
				{
					trackStats(['email' => '+']);
				}
			}

			// Wait, wait, I'm still sending it here!
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
	public function SMTP($mail_to_array, $subject, $message, $headers, $message_id = null): bool
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
		$smtp_host = trim($modSettings['smtp_host'] ?? '');

		// Check if we have an existing open connection or need a new one
		if (!is_resource($this->socket) || feof($this->socket))
		{
			$this->socket = $this->_getSMTPSocket($smtp_host, $smtp_port);
			if (!is_resource($this->socket))
			{
				return false;
			}

			// The server responded, now log in our client
			$login = $this->_loginSMTPClient($this->socket, $smtp_client);
			if ($login === false)
			{
				$this->closeSMTP();

				return false;
			}
		}
		elseif (!$this->_server_parse('RSET', $this->socket, '250'))
		{
			// Reusing existing connection: send RSET to reset state for this new message
			$this->closeSMTP();
			$this->socket = $this->_getSMTPSocket($smtp_host, $smtp_port);
			if (!is_resource($this->socket) || !$this->_loginSMTPClient($this->socket, $smtp_client))
			{
				$this->closeSMTP();

				return false;
			}
		}

		$socket = $this->socket;

		// Fix the message for any lines beginning with a period! (the first is ignored, you see.)
		$message = preg_replace('/^\./m', '..', $message);

		$mid = strstr(empty($modSettings['maillist_mail_from']) ? $webmaster_email : $modSettings['maillist_mail_from'], '@');
		$this->setReturnPath();
		$mail_to_array = array_values($mail_to_array);
		$sent = [];

		// Time to send these, so they can be trapped in a SPAM filter :P
		$send_ok = true;
		foreach ($mail_to_array as $i => $mail_to)
		{
			$this_message = $message;
			$unq_head = $this->getUniqueMessageID($message_id);
			$messageHeader = 'Message-ID: <' . $unq_head . $mid . '>';

			// Reset the connection to send another email if multiple recipients in single call.
			if (($i !== 0) && !$this->_server_parse('RSET', $socket, '250'))
			{
				$send_ok = false;
				break;
			}

			// From, to, and then start the data...
			if (!$this->_server_parse('MAIL FROM: <' . $this->returnPath . '>', $socket, '250'))
			{
				$send_ok = false;
				break;
			}

			if (!$this->_server_parse('RCPT TO: <' . $mail_to . '>', $socket, '250'))
			{
				$send_ok = false;
				break;
			}

			if (!$this->_server_parse('DATA', $socket, '354'))
			{
				$send_ok = false;
				break;
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
				$send_ok = false;
				break;
			}

			// track the number of emails sent
			if (!empty($modSettings['trackStats']))
			{
				trackStats(['email' => '+']);
			}

			// Keep our post via email log
			if ($this->mailList)
			{
				$this->unqPBEHead[3] = time();
				$this->unqPBEHead[4] = $mail_to;
				$sent[] = $this->unqPBEHead;
			}

			// Almost done, almost done... don't stop me just yet!
			detectServer()->setTimeLimit(300);
		}

		// Clean up the socket if the sending failed or keep-alive is disabled
		if (!$send_ok || !$this->keepAlive)
		{
			$this->closeSMTP();
		}

		// Log each email if using PBE
		if (!empty($sent))
		{
			require_once(SUBSDIR . '/Maillist.subs.php');
			log_email($sent);
		}

		return $send_ok;
	}

	/**
	 * Make a connection to the SMTP server using stream_socket_client
	 *
	 * @param string $smtp_host
	 * @param int $smtp_port
	 * @return false|resource
	 */
	private function _getSMTPSocket($smtp_host, $smtp_port)
	{
		global $txt, $modSettings;

		// @todo: This should be configurable in the ACP, but for now, let's use a default of 5 seconds.
		$timeout = empty($modSettings['smtp_timeout']) ? 5 : (int) $modSettings['smtp_timeout'];

		// Build context options with TLS settings (default to secure verification)
		$verify_ssl = !isset($modSettings['smtp_ssl_verify']) || !empty($modSettings['smtp_ssl_verify']);
		$context_options = [
			'ssl' => [
				'verify_peer' => $verify_ssl,
				'verify_peer_name' => $verify_ssl,
				'allow_self_signed' => !$verify_ssl,
				'crypto_method' => STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT | STREAM_CRYPTO_METHOD_TLS_CLIENT,
			],
		];
		$context = stream_context_create($context_options);

		// Format the remote socket address
		if (str_contains($smtp_host, '://'))
		{
			$remote_socket = $smtp_host . ':' . $smtp_port;
		}
		elseif (str_starts_with($smtp_host, 'ssl:'))
		{
			$remote_socket = 'ssl://' . substr($smtp_host, 4) . ':' . $smtp_port;
		}
		elseif (str_starts_with($smtp_host, 'tls:'))
		{
			$remote_socket = 'tls://' . substr($smtp_host, 4) . ':' . $smtp_port;
		}
		else
		{
			$remote_socket = 'tcp://' . $smtp_host . ':' . $smtp_port;
		}

		set_error_handler(static function () { /* ignore errors */ });
		try
		{
			$socket = stream_socket_client($remote_socket, $errno, $errstr, $timeout, STREAM_CLIENT_CONNECT, $context);
		}
		catch (Throwable)
		{
			$socket = false;
		}
		finally
		{
			restore_error_handler();
		}

		if (!is_resource($socket))
		{
			// Maybe we can still save this? The port might be wrong.
			if ($smtp_port === 25 && (str_starts_with($smtp_host, 'ssl:') || str_starts_with($smtp_host, 'ssl://')))
			{
				$fallback_host = str_starts_with($smtp_host, 'ssl://') ? $smtp_host : ('ssl://' . substr($smtp_host, 4));
				set_error_handler(static function () { /* ignore errors */ });
				try
				{
					$socket = stream_socket_client($fallback_host . ':465', $errno, $errstr, $timeout, STREAM_CLIENT_CONNECT, $context);
					if (is_resource($socket))
					{
						updateSettings(['smtp_port' => 465]);
						Errors::instance()->log_error($txt['smtp_port_ssl']);
					}
				}
				catch (Throwable)
				{
					$socket = false;
				}
				finally
				{
					restore_error_handler();
				}
			}

			// Unable to connect!
			if (!is_resource($socket))
			{
				Errors::instance()->log_error($txt['smtp_no_connect'] . ': ' . $errno . ' : ' . $errstr);

				return false;
			}
		}

		stream_set_timeout($socket, max($timeout, 10));

		// Wait for a response of 220, without "-" continue.
		if (!$this->_server_parse(null, $socket, '220'))
		{
			fclose($socket);

			return false;
		}

		return $socket;
	}

	/**
	 * Parse a message to the SMTP server.
	 *
	 * - Sends the specified message to the server and checks for the expected response.
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

		if (!str_starts_with($server_response, $response))
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
	private function _loginSMTPClient($socket, $smtp_client): bool
	{
		global $modSettings;

		$smtp_username = trim($modSettings['smtp_username'] ?? '');
		$smtp_password = trim($modSettings['smtp_password'] ?? '');
		$smtp_starttls = !empty($modSettings['smtp_starttls']);

		$ehlo_response = $this->_server_parse('EHLO ' . $smtp_client, $socket, null);
		if ($ehlo_response === '250')
		{
			if ($smtp_starttls)
			{
				if ($this->_server_parse('STARTTLS', $socket, '220'))
				{
					$crypto_method = STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT | STREAM_CRYPTO_METHOD_TLS_CLIENT;
					$crypto_res = stream_socket_enable_crypto($socket, true, $crypto_method);
					if ($crypto_res !== true)
					{
						Errors::instance()->log_error('Failed to establish TLS encryption with SMTP server');

						return false;
					}

					$ehlo_response = $this->_server_parse('EHLO ' . $smtp_client, $socket, null);
					if ($ehlo_response !== '250')
					{
						return false;
					}
				}
				else
				{
					return false;
				}
			}

			if ($smtp_username !== '' && $smtp_password !== '')
			{
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
				return (bool) $this->_server_parse($smtp_password, $socket, '235');
			}

			return true;
		}

		// Ancient servers: just say "helo".
		return (bool) $this->_server_parse('HELO ' . $smtp_client, $socket, '250');
	}
}
