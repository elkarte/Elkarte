<?php

/**
 * This file handles tasks related to mail.
 * The functions in this file do NOT check permissions.
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * This file contains code covered by:
 * copyright:	2011 Simple Machines (http://www.simplemachines.org)
 * license:  	BSD, See included LICENSE.TXT for terms and conditions.
 *
 * @version 1.1 beta 4
 *
 */

/**
 * This function sends an email to the specified recipient(s).
 *
 * It uses the mail_type settings and webmaster_email variable.
 *
 * @package Mail
 * @param string[]|string $to - the email(s) to send to
 * @param string $subject - email subject, expected to have entities, and slashes, but not be parsed
 * @param string $message - email body, expected to have slashes, no htmlentities
 * @param string|null $from = null - the address to use for replies
 * @param string|null $message_id = null - if specified, it will be used as local part of the Message-ID header.
 * @param bool $send_html = false, whether or not the message is HTML vs. plain text
 * @param int $priority = 3
 * @param bool|null $hotmail_fix = null
 * @param bool $is_private
 * @param string|null $from_wrapper - used to provide envelope from wrapper based on if we sharing a users display name
 * @param int|null $reference - The parent topic id for use in a References header
 * @return boolean whether or not the email was accepted properly.
 */
function sendmail($to, $subject, $message, $from = null, $message_id = null, $send_html = false, $priority = 3, $hotmail_fix = null, $is_private = false, $from_wrapper = null, $reference = null)
{
	global $webmaster_email, $context, $modSettings, $txt, $scripturl, $boardurl;

	// Use sendmail if it's set or if no SMTP server is set.
	$use_sendmail = empty($modSettings['mail_type']) || $modSettings['smtp_host'] == '';

	// Using maillist styles and this message qualifies (priority 3 and below only (4 = digest, 5 = newsletter))
	$maillist = !empty($modSettings['maillist_enabled']) && $from_wrapper !== null && $message_id !== null && $priority < 4 && empty($modSettings['mail_no_message_id']);

	// Line breaks need to be \r\n only in windows or for SMTP.
	$line_break = detectServer()->is('windows') || !$use_sendmail ? "\r\n" : "\n";

	if ($message_id !== null && isset($message_id[0]) && in_array($message_id[0], array('m', 'p', 't')))
	{
		$message_type = $message_id[0];
		$message_id = substr($message_id, 1);
	}
	else
	{
		$message_type = 'm';
	}

	// So far so good.
	$mail_result = true;

	// If the recipient list isn't an array, make it one.
	$to_array = is_array($to) ? $to : array($to);

	// Once upon a time, Hotmail could not interpret non-ASCII mails.
	// In honour of those days, it's still called the 'hotmail fix'.
	if ($hotmail_fix === null)
	{
		$hotmail_to = array();
		foreach ($to_array as $i => $to_address)
		{
			if (preg_match('~@(att|comcast|bellsouth)\.[a-zA-Z\.]{2,6}$~i', $to_address) === 1)
			{
				$hotmail_to[] = $to_address;
				$to_array = array_diff($to_array, array($to_address));
			}
		}

		// Call this function recursively for the hotmail addresses.
		if (!empty($hotmail_to))
			$mail_result = sendmail($hotmail_to, $subject, $message, $from, $message_type . $message_id, $send_html, $priority, true, $is_private, $from_wrapper, $reference);

		// The remaining addresses no longer need the fix.
		$hotmail_fix = false;

		// No other addresses left? Return instantly.
		if (empty($to_array))
			return $mail_result;
	}

	// Get rid of entities.
	$subject = un_htmlspecialchars($subject);

	// Make the message use the proper line breaks.
	$message = str_replace(array("\r", "\n"), array('', $line_break), $message);

	// Make sure hotmail mails are sent as HTML so that HTML entities work.
	if ($hotmail_fix && !$send_html)
	{
		$send_html = true;
		$message = strtr($message, array($line_break => '<br />' . $line_break));
		$message = preg_replace('~(' . preg_quote($scripturl, '~') . '(?:[?/][\w\-_%\.,\?&;=#]+)?)~', '<a href="$1">$1</a>', $message);
	}

	// Requirements (draft) for MLM to Support Basic DMARC Compliance
	// http://www.dmarc.org/supplemental/mailman-project-mlm-dmarc-reqs.html
	if ($maillist && $from !== null && $from_wrapper !== null)
	{
		// Be sure there is never an email in the from name if using maillist styles
		$dmarc_from = $from;
		if (filter_var($dmarc_from, FILTER_VALIDATE_EMAIL))
			$dmarc_from = str_replace(strstr($dmarc_from, '@'), '', $dmarc_from);

		// Add in the 'via' if desired, helps prevent email clients from learning/replacing legit names/emails
		if (!empty($modSettings['maillist_sitename']) && empty($modSettings['dmarc_spec_standard']))
			// @memo (2014) "via" is still a draft, and it's not yet clear if it will be localized or not.
			// To play safe, we are keeping it hard-coded, but the string is available for translation.
			$from = $dmarc_from . ' ' . /* $txt['via'] */ 'via' . ' ' . $modSettings['maillist_sitename'];
		else
			$from = $dmarc_from;
	}

	// Take care of from / subject encodings
	list (, $from_name, $from_encoding) = mimespecialchars(addcslashes($from !== null ? $from : (!empty($modSettings['maillist_sitename']) ? $modSettings['maillist_sitename'] : $context['forum_name']), '<>()\'\\"'), true, $hotmail_fix, $line_break);
	list (, $subject) = mimespecialchars($subject, true, $hotmail_fix, $line_break);
	if ($from_encoding !== 'base64')
		$from_name = '"' . $from_name . '"';

	// Construct the from / replyTo mail headers, based on if we showing a users name
	if ($from_wrapper !== null)
	{
		$headers = 'From: ' . $from_name . ' <' . $from_wrapper . '>' . $line_break;

		// If they reply where is it going to be sent?
		$headers .= 'Reply-To: "' . (!empty($modSettings['maillist_sitename']) ? $modSettings['maillist_sitename'] : $context['forum_name']) . '" <' . (!empty($modSettings['maillist_sitename_address']) ? $modSettings['maillist_sitename_address'] : (empty($modSettings['maillist_mail_from']) ? $webmaster_email : $modSettings['maillist_mail_from'])) . '>' . $line_break;
		if ($reference !== null)
			$headers .= 'References: <' . $reference . strstr(empty($modSettings['maillist_mail_from']) ? $webmaster_email : $modSettings['maillist_mail_from'], '@') . '>' . $line_break;
	}
	else
	{
		// Standard ElkArte headers
		$headers = 'From: ' . $from_name . ' <' . (empty($modSettings['maillist_mail_from']) ? $webmaster_email : $modSettings['maillist_mail_from']) . '>' . $line_break;
		$headers .= ($from !== null && strpos($from, '@') !== false) ? 'Reply-To: <' . $from . '>' . $line_break : '';
	}

	// We'll need this later for the envelope fix, too, so keep it
	$return_path = (!empty($modSettings['maillist_sitename_address']) ? $modSettings['maillist_sitename_address'] : (empty($modSettings['maillist_mail_from']) ? $webmaster_email : $modSettings['maillist_mail_from']));

	// Return path, date, mailer
	$headers .= 'Return-Path: ' . $return_path . $line_break;
	$headers .= 'Date: ' . gmdate('D, d M Y H:i:s') . ' -0000' . $line_break;
	$headers .= 'X-Mailer: ELK' . $line_break;

	// For maillist, digests or newsletters we include a few more headers for compliance
	if ($maillist || $priority > 3)
	{
		// Lets try to avoid auto replies
		$headers .= 'X-Auto-Response-Suppress: All' . $line_break;
		$headers .= 'Auto-Submitted: auto-generated' . $line_break;

		// Indicate its a list server to avoid spam tagging and to help client filters
		// http://www.ietf.org/rfc/rfc2369.txt
		$headers .= 'List-Id: <' . (!empty($modSettings['maillist_sitename_address']) ? $modSettings['maillist_sitename_address'] : (empty($modSettings['maillist_mail_from']) ? $webmaster_email : $modSettings['maillist_mail_from'])) . '>' . $line_break;
		$headers .= 'List-Unsubscribe: <' . $boardurl . '/index.php?action=profile;area=notification>' . $line_break;
		$headers .= 'List-Owner: <mailto:' . (!empty($modSettings['maillist_sitename_help']) ? $modSettings['maillist_sitename_help'] : (empty($modSettings['maillist_mail_from']) ? $webmaster_email : $modSettings['maillist_mail_from'])) . '> (' . (!empty($modSettings['maillist_sitename']) ? $modSettings['maillist_sitename'] : $context['forum_name']) . ')' . $line_break;
	}

	// Pass this to the integration before we start modifying the output -- it'll make it easier later.
	if (in_array(false, call_integration_hook('integrate_outgoing_email', array(&$subject, &$message, &$headers)), true))
		return false;

	// Save the original message...
	$orig_message = $message;

	// The mime boundary separates the different alternative versions.
	$mime_boundary = 'ELK-' . md5($message . time());

	// Using mime, as it allows to send a plain unencoded alternative.
	$headers .= 'Mime-Version: 1.0' . $line_break;
	$headers .= 'Content-Type: multipart/alternative; boundary="' . $mime_boundary . '"' . $line_break;
	$headers .= 'Content-Transfer-Encoding: 7bit' . $line_break;

	// Sending HTML?  Let's plop in some basic stuff, then.
	if ($send_html)
	{
		$no_html_message = un_htmlspecialchars(strip_tags(strtr($orig_message, array('</title>' => $line_break))));

		// But, then, dump it and use a plain one for dinosaur clients.
		list (, $plain_message) = mimespecialchars($no_html_message, false, true, $line_break);
		$message = $plain_message . $line_break . '--' . $mime_boundary . $line_break;

		// This is the plain text version.  Even if no one sees it, we need it for spam checkers.
		list ($charset, $plain_charset_message, $encoding) = mimespecialchars($no_html_message, false, false, $line_break);
		$message .= 'Content-Type: text/plain; charset=' . $charset . $line_break;
		$message .= 'Content-Transfer-Encoding: ' . $encoding . $line_break . $line_break;
		$message .= $plain_charset_message . $line_break . '--' . $mime_boundary . $line_break;

		// This is the actual HTML message, prim and proper.  If we wanted images, they could be inlined here (with multipart/related, etc.)
		list ($charset, $html_message, $encoding) = mimespecialchars($orig_message, false, $hotmail_fix, $line_break);
		$message .= 'Content-Type: text/html; charset=' . $charset . $line_break;
		$message .= 'Content-Transfer-Encoding: ' . ($encoding == '' ? '7bit' : $encoding) . $line_break . $line_break;
		$message .= $html_message . $line_break . '--' . $mime_boundary . '--';
	}
	// Text is good too.
	else
	{
		// Send a plain message first, for the older web clients.
		list (, $plain_message) = mimespecialchars($orig_message, false, true, $line_break);
		$message = $plain_message . $line_break . '--' . $mime_boundary . $line_break;

		// Now add an encoded message using the forum's character set.
		list ($charset, $encoded_message, $encoding) = mimespecialchars($orig_message, false, false, $line_break);
		$message .= 'Content-Type: text/plain; charset=' . $charset . $line_break;
		$message .= 'Content-Transfer-Encoding: ' . $encoding . $line_break . $line_break;
		$message .= $encoded_message . $line_break . '--' . $mime_boundary . '--';
	}

	// Are we using the mail queue, if so this is where we butt in...
	if (!empty($modSettings['mail_queue']) && $priority != 0)
		return AddMailQueue(false, $to_array, $subject, $message, $headers, $send_html, $priority, $is_private, $message_type . $message_id);
	// If it's a priority mail, send it now - note though that this should NOT be used for sending many at once.
	elseif (!empty($modSettings['mail_queue']) && !empty($modSettings['mail_period_limit']))
	{
		list ($last_mail_time, $mails_this_minute) = @explode('|', $modSettings['mail_recent']);
		if (empty($mails_this_minute) || time() > $last_mail_time + 60)
			$new_queue_stat = time() . '|' . 1;
		else
			$new_queue_stat = $last_mail_time . '|' . ((int) $mails_this_minute + 1);

		updateSettings(array('mail_recent' => $new_queue_stat));
	}

	// SMTP or sendmail?
	if ($use_sendmail)
	{
		$subject = strtr($subject, array("\r" => '', "\n" => ''));
		if (!empty($modSettings['mail_strip_carriage']))
		{
			$message = strtr($message, array("\r" => ''));
			$headers = strtr($headers, array("\r" => ''));
		}
		$sent = array();
		$need_break = substr($headers, -1) === "\n" || substr($headers, -1) === "\r" ? false : true;

		foreach ($to_array as $key => $to)
		{
			$unq_id = '';
			$unq_head = '';
			$unq_head_array = array();

			// If we are using the post by email functions, then we generate "reply to mail" security keys
			if ($maillist)
			{
				$unq_head_array[0] = md5($boardurl . microtime() . rand());
				$unq_head_array[1] = $message_type;
				$unq_head_array[2] = $message_id;
				$unq_head = $unq_head_array[0] . '-' . $unq_head_array[2];
				$encoded_unq_head = base64_encode($line_break . $line_break . '[' . $unq_head . ']' . $line_break);
				$unq_id = ($need_break ? $line_break : '') . 'Message-ID: <' . $unq_head . strstr(empty($modSettings['maillist_mail_from']) ? $webmaster_email : $modSettings['maillist_mail_from'], '@') . '>';
				$message = mail_insert_key($message, $unq_head, $encoded_unq_head, $line_break);
			}
			elseif (empty($modSettings['mail_no_message_id']))
				$unq_id = ($need_break ? $line_break : '') . 'Message-ID: <' . md5($boardurl . microtime()) . '-' . $message_id . strstr(empty($modSettings['maillist_mail_from']) ? $webmaster_email : $modSettings['maillist_mail_from'], '@') . '>';

			// This is frequently not set, or not set according to the needs of PBE and bounce detection
			// We have to use ini_set, since "-f <address>" doesn't work on windows systems, so we need both
			$old_return = ini_set('sendmail_from', $return_path);
			if (!mail(strtr($to, array("\r" => '', "\n" => '')), $subject, $message, $headers . $unq_id, '-f ' . $return_path))
			{
				Errors::instance()->log_error(sprintf($txt['mail_send_unable'], $to));
				$mail_result = false;
			}
			else
			{
				// Keep our post via email log
				if (!empty($unq_head))
				{
					$unq_head_array[] = time();
					$unq_head_array[] = $to;
					$sent[] = $unq_head_array;
				}

				// Track total emails sent
				if (!empty($modSettings['trackStats']))
					trackStats(array('email' => '+'));
			}

			// Put it back
			ini_set('sendmail_from', $old_return);

			// Wait, wait, I'm still sending here!
			detectServer()->setTimeLimit(300);
		}

		// Log each email that we sent so they can be replied to
		if (!empty($sent))
		{
			require_once(SUBSDIR . '/Maillist.subs.php');
			log_email($sent);
		}
	}
	else
		// SMTP protocol it is
		$mail_result = $mail_result && smtp_mail($to_array, $subject, $message, $headers, $priority, $message_type . $message_id);

	// Clear out the stat cache.
	trackStats();

// debug
file_put_contents (BOARDDIR . '/test_email.txt', $to . "\n" . $subject . "\n" . $message . "\n" . $headers . $unq_id . "\n" . $return_path);
	// Everything go smoothly?
	return $mail_result;
}

/**
 * Add an email to the mail queue.
 *
 * @package Mail
 * @param bool $flush = false
 * @param string[] $to_array = array()
 * @param string $subject = ''
 * @param string $message = ''
 * @param string $headers = ''
 * @param bool $send_html = false
 * @param int $priority = 3
 * @param boolean $is_private
 * @param string|null $message_id
 * @return boolean
 */
function AddMailQueue($flush = false, $to_array = array(), $subject = '', $message = '', $headers = '', $send_html = false, $priority = 3, $is_private = false, $message_id = '')
{
	global $context;

	$db = database();

	static $cur_insert = array();
	static $cur_insert_len = 0;

	if ($cur_insert_len == 0)
		$cur_insert = array();

	// If we're flushing, make the final inserts - also if we're near the MySQL length limit!
	if (($flush || $cur_insert_len > 800000) && !empty($cur_insert))
	{
		// Only do these once.
		$cur_insert_len = 0;

		// Dump the data...
		$db->insert('',
			'{db_prefix}mail_queue',
			array(
				'time_sent' => 'int', 'recipient' => 'string-255', 'body' => 'string', 'subject' => 'string-255',
				'headers' => 'string-65534', 'send_html' => 'int', 'priority' => 'int', 'private' => 'int', 'message_id' => 'string-255',
			),
			$cur_insert,
			array('id_mail')
		);

		$cur_insert = array();
		$context['flush_mail'] = false;
	}

	// If we're flushing we're done.
	if ($flush)
	{
		$nextSendTime = time() + 10;

		$db->query('', '
			UPDATE {db_prefix}settings
			SET value = {string:nextSendTime}
			WHERE variable = {string:mail_next_send}
				AND value = {string:no_outstanding}',
			array(
				'nextSendTime' => $nextSendTime,
				'mail_next_send' => 'mail_next_send',
				'no_outstanding' => '0',
			)
		);

		return true;
	}

	// Ensure we tell obExit to flush.
	$context['flush_mail'] = true;

	foreach ($to_array as $to)
	{
		// Will this insert go over MySQL's limit?
		$this_insert_len = strlen($to) + strlen($message) + strlen($headers) + 700;

		// Insert limit of 1M (just under the safety) is reached?
		if ($this_insert_len + $cur_insert_len > 1000000)
		{
			// Flush out what we have so far.
			$db->insert('',
				'{db_prefix}mail_queue',
				array(
					'time_sent' => 'int', 'recipient' => 'string-255', 'body' => 'string', 'subject' => 'string-255',
					'headers' => 'string-65534', 'send_html' => 'int', 'priority' => 'int', 'private' => 'int', 'message_id' => 'string-255',
				),
				$cur_insert,
				array('id_mail')
			);

			// Clear this out.
			$cur_insert = array();
			$cur_insert_len = 0;
		}

		// Now add the current insert to the array...
		$cur_insert[] = array(time(), (string) $to, (string) $message, (string) $subject, (string) $headers, ($send_html ? 1 : 0), $priority, (int) $is_private, (string) $message_id);
		$cur_insert_len += $this_insert_len;
	}

	// If they are using SSI there is a good chance obExit will never be called.  So lets be nice and flush it for them.
	if (ELK === 'SSI')
		return AddMailQueue(true);

	return true;
}

/**
 * Prepare text strings for sending as email body or header.
 *
 * What it does:
 * - In case there are higher ASCII characters in the given string, this
 * function will attempt the transport method 'quoted-printable'.
 * - Otherwise the transport method '7bit' is used.
 *
 * @package Mail
 * @param string $string
 * @param bool $with_charset = true
 * @param bool $hotmail_fix = false, with hotmail_fix set all higher ASCII
 * characters are converted to HTML entities to assure proper display of the mail
 * @param string $line_break
 * @param string|null $custom_charset = null, if set, it uses this character set
 * @return string[] an array containing the character set, the converted string and the transport method.
 */
function mimespecialchars($string, $with_charset = true, $hotmail_fix = false, $line_break = "\r\n", $custom_charset = null)
{
	$charset = $custom_charset !== null ? $custom_charset : 'UTF-8';

	// This is the fun part....
	if (preg_match_all('~&#(\d{3,8});~', $string, $matches) !== 0 && !$hotmail_fix)
	{
		// Let's, for now, assume there are only &#021;'ish characters.
		$simple = true;

		foreach ($matches[1] as $entity)
			if ($entity > 128)
				$simple = false;
		unset($matches);

		if ($simple)
			$string = preg_replace_callback('~&#(\d{3,7});~', 'mimespecialchars_callback', $string);
		else
		{
			$string = preg_replace_callback('~&#(\d{3,7});~', 'fixchar__callback', $string);

			// Unicode, baby.
			$charset = 'UTF-8';
		}
	}

	// Convert all special characters to HTML entities...just for Hotmail :-\
	if ($hotmail_fix)
	{
		// Convert all 'special' characters to HTML entities.
		return array($charset, preg_replace_callback('~([\x80-\x{10FFFF}])~u', 'entityConvert', $string), '7bit');
	}
	// We don't need to mess with the line if no special characters were in it..
	elseif (!$hotmail_fix && preg_match('~([^\x09\x0A\x0D\x20-\x7F])~', $string) === 1)
	{
		// Base64 encode.
		$string = base64_encode($string);

		// Show the characterset and the transfer-encoding for header strings.
		if ($with_charset)
			$string = '=?' . $charset . '?B?' . $string . '?=';

		// Break it up in lines (mail body).
		else
			$string = chunk_split($string, 76, $line_break);

		return array($charset, $string, 'base64');
	}
	else
		return array($charset, $string, '7bit');
}

/**
 * Converts out of ascii range characters in to HTML entities
 *
 * - Character codes <= 128 are left as is
 * - Callback function of preg_replace_callback, used just for hotmail address
 *
 * @package Mail
 * @param mixed[] $match
 */
function entityConvert($match)
{
	$c = $match[1];
	$c_strlen = strlen($c);
	$c_ord = ord($c[0]);

	if ($c_strlen === 1 && $c_ord <= 0x7F)
		return $c;
	elseif ($c_strlen === 2 && $c_ord >= 0xC0 && $c_ord <= 0xDF)
		return '&#' . ((($c_ord ^ 0xC0) << 6) + (ord($c[1]) ^ 0x80)) . ';';
	elseif ($c_strlen === 3 && $c_ord >= 0xE0 && $c_ord <= 0xEF)
		return '&#' . ((($c_ord ^ 0xE0) << 12) + ((ord($c[1]) ^ 0x80) << 6) + (ord($c[2]) ^ 0x80)) . ';';
	elseif ($c_strlen === 4 && $c_ord >= 0xF0 && $c_ord <= 0xF7)
		return '&#' . ((($c_ord ^ 0xF0) << 18) + ((ord($c[1]) ^ 0x80) << 12) + ((ord($c[2]) ^ 0x80) << 6) + (ord($c[3]) ^ 0x80)) . ';';
	else
		return '';
}

/**
 * Callback for the preg_replace in mimespecialchars
 *
 * @package Mail
 * @param mixed[] $match
 */
function mimespecialchars_callback($match)
{
	return chr($match[1]);
}

/**
 * Sends mail, like mail() but over SMTP.
 *
 * - It expects no slashes or entities.
 *
 * @package Mail
 * @internal
 * @param string[] $mail_to_array - array of strings (email addresses)
 * @param string $subject email subject
 * @param string $message email message
 * @param string $headers
 * @param int $priority
 * @param string|null $message_id
 * @return boolean whether it sent or not.
 */
function smtp_mail($mail_to_array, $subject, $message, $headers, $priority, $message_id = null)
{
	global $modSettings, $webmaster_email, $txt, $scripturl;

	$modSettings['smtp_host'] = trim($modSettings['smtp_host']);

	if ($message_id !== null && isset($message_id[0]) && in_array($message_id[0], array('m', 'p', 't')))
	{
		$message_type = $message_id[0];
		$message_id = substr($message_id, 1);
	}
	else
	{
		$message_type = 'm';
	}

	// Try POP3 before SMTP?
	// @todo There's no interface for this yet.
	if ($modSettings['mail_type'] == 2 && $modSettings['smtp_username'] != '' && $modSettings['smtp_password'] != '')
	{
		$socket = fsockopen($modSettings['smtp_host'], 110, $errno, $errstr, 2);
		if (!$socket && (substr($modSettings['smtp_host'], 0, 5) == 'smtp.' || substr($modSettings['smtp_host'], 0, 11) == 'ssl://smtp.'))
			$socket = fsockopen(strtr($modSettings['smtp_host'], array('smtp.' => 'pop.')), 110, $errno, $errstr, 2);

		if ($socket)
		{
			fgets($socket, 256);
			fputs($socket, 'USER ' . $modSettings['smtp_username'] . "\r\n");
			fgets($socket, 256);
			fputs($socket, 'PASS ' . base64_decode($modSettings['smtp_password']) . "\r\n");
			fgets($socket, 256);
			fputs($socket, 'QUIT' . "\r\n");

			fclose($socket);
		}
	}

	// Try to connect to the SMTP server... if it doesn't exist, only wait three seconds.
	if (!$socket = fsockopen($modSettings['smtp_host'], empty($modSettings['smtp_port']) ? 25 : $modSettings['smtp_port'], $errno, $errstr, 3))
	{
		// Maybe we can still save this?  The port might be wrong.
		if (substr($modSettings['smtp_host'], 0, 4) == 'ssl:' && (empty($modSettings['smtp_port']) || $modSettings['smtp_port'] == 25))
		{
			if ($socket = fsockopen($modSettings['smtp_host'], 465, $errno, $errstr, 3))
				Errors::instance()->log_error($txt['smtp_port_ssl']);
		}

		// Unable to connect!  Don't show any error message, but just log one and try to continue anyway.
		if (!$socket)
		{
			Errors::instance()->log_error($txt['smtp_no_connect'] . ': ' . $errno . ' : ' . $errstr);
			return false;
		}
	}

	// Wait for a response of 220, without "-" continue.
	if (!server_parse(null, $socket, '220'))
		return false;

	if ($modSettings['mail_type'] == 1 && $modSettings['smtp_username'] != '' && $modSettings['smtp_password'] != '')
	{
		// @todo These should send the CURRENT server's name, not the mail server's!

		// EHLO could be understood to mean encrypted hello...
		if (server_parse('EHLO ' . $modSettings['smtp_host'], $socket, null) == '250')
		{
			if (!empty($modSettings['smtp_starttls']))
			{
				server_parse('STARTTLS', $socket, null);
				stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
				server_parse('EHLO ' . $modSettings['smtp_host'], $socket, null);
			}

			if (!server_parse('AUTH LOGIN', $socket, '334'))
				return false;
			// Send the username and password, encoded.
			if (!server_parse(base64_encode($modSettings['smtp_username']), $socket, '334'))
				return false;
			// The password is already encoded ;)
			if (!server_parse($modSettings['smtp_password'], $socket, '235'))
				return false;
		}
		elseif (!server_parse('HELO ' . $modSettings['smtp_host'], $socket, '250'))
			return false;
	}
	else
	{
		// Just say "helo".
		if (!server_parse('HELO ' . $modSettings['smtp_host'], $socket, '250'))
			return false;
	}

	// Fix the message for any lines beginning with a period! (the first is ignored, you see.)
	$message = strtr($message, array("\r\n" . '.' => "\r\n" . '..'));

	$sent = array();
	$need_break = substr($headers, -1) === "\n" || substr($headers, -1) === "\r" ? false : true;
	$real_headers = $headers;
	$line_break = "\r\n";

	// !! Theoretically, we should be able to just loop the RCPT TO.
	$mail_to_array = array_values($mail_to_array);
	foreach ($mail_to_array as $i => $mail_to)
	{
		// the keys are must unique for every mail you see
		$unq_id = '';
		$unq_head = '';
		$unq_head_array = array();

		// Using the post by email functions, and not a digest (priority 4)
		// then generate "reply to mail" keys and place them in the message
		if (!empty($modSettings['maillist_enabled']) && $message_id !== null && $priority != 4)
		{
			$unq_head_array[0] = md5($scripturl . microtime() . rand());
			$unq_head_array[1] = $message_type;
			$unq_head_array[2] = $message_id;
			$unq_head = $unq_head_array[0] . '-' . $unq_head_array[1] . $unq_head_array[2];
			$encoded_unq_head = base64_encode($line_break . $line_break . '[' . $unq_head . ']' . $line_break);
			$unq_id = ($need_break ? $line_break : '') . 'Message-ID: <' . $unq_head . strstr(empty($modSettings['maillist_mail_from']) ? $webmaster_email : $modSettings['maillist_mail_from'], '@') . '>';
			$message = mail_insert_key($message, $unq_head, $encoded_unq_head, $line_break);
		}

		// Fix up the headers for this email!
		$headers = $real_headers . $unq_id;

		// Reset the connection to send another email.
		if ($i != 0)
		{
			if (!server_parse('RSET', $socket, '250'))
				return false;
		}

		// From, to, and then start the data...
		if (!server_parse('MAIL FROM: <' . (empty($modSettings['maillist_mail_from']) ? $webmaster_email : $modSettings['maillist_mail_from']) . '>', $socket, '250'))
			return false;
		if (!server_parse('RCPT TO: <' . $mail_to . '>', $socket, '250'))
			return false;
		if (!server_parse('DATA', $socket, '354'))
			return false;

		fputs($socket, 'Subject: ' . $subject . $line_break);
		if (strlen($mail_to) > 0)
			fputs($socket, 'To: <' . $mail_to . '>' . $line_break);
		fputs($socket, $headers . $line_break . $line_break);
		fputs($socket, $message . $line_break);

		// Send a ., or in other words "end of data".
		if (!server_parse('.', $socket, '250'))
			return false;

		// track the number of emails sent
		if (!empty($modSettings['trackStats']))
			trackStats(array('email' => '+'));

		// Keep our post via email log
		if (!empty($unq_head))
		{
			$unq_head_array[] = time();
			$unq_head_array[] = $mail_to;
			$sent[] = $unq_head_array;
		}

		// Almost done, almost done... don't stop me just yet!
		detectServer()->setTimeLimit(300);
	}

	// say our goodbyes
	fputs($socket, 'QUIT' . $line_break);
	fclose($socket);

	// Log each email
	if (!empty($sent))
	{
		require_once(SUBSDIR . '/Maillist.subs.php');
		log_email($sent);
	}

	return true;
}

/**
 * Parse a message to the SMTP server.
 *
 * - Sends the specified message to the server, and checks for the expected response.
 *
 * @package Mail
 * @internal
 * @param string $message - the message to send
 * @param resource $socket - socket to send on
 * @param string $response - the expected response code
 * @return string|boolean it responded as such.
 */
function server_parse($message, $socket, $response)
{
	global $txt;

	if ($message !== null)
		fputs($socket, $message . "\r\n");

	// No response yet.
	$server_response = '';

	while (substr($server_response, 3, 1) != ' ')
		if (!($server_response = fgets($socket, 256)))
		{
			// @todo Change this message to reflect that it may mean bad user/password/server issues/etc.
			Errors::instance()->log_error($txt['smtp_bad_response']);
			return false;
		}

	if ($response === null)
		return substr($server_response, 0, 3);

	if (substr($server_response, 0, 3) != $response)
	{
		Errors::instance()->log_error($txt['smtp_error'] . $server_response);
		return false;
	}

	return true;
}

/**
 * Adds the unique security key in to an email
 *
 * - adds the key in to (each) message body section
 * - safety net for clients that strip out the message-id and in-reply-to headers
 *
 * @package Mail
 * @param string $message
 * @param string $unq_head
 * @param string $encoded_unq_head
 * @param string $line_break
 */
function mail_insert_key($message, $unq_head, $encoded_unq_head, $line_break)
{
	// Append the key to the bottom of each message section, plain, html, encoded, etc
	$message = preg_replace('~^(.*?)(' . $line_break . '--ELK-[a-z0-9]{32})~s', "$1{$line_break}{$line_break}[{$unq_head}]{$line_break}$2", $message);
	$message = preg_replace('~(Content-Type: text/plain;.*?Content-Transfer-Encoding: 7bit' . $line_break . $line_break . ')(.*?)(' . $line_break . '--ELK-[a-z0-9]{32})~s', "$1$2{$line_break}{$line_break}[{$unq_head}]{$line_break}$3", $message);
	$message = preg_replace('~(Content-Type: text/html;.*?Content-Transfer-Encoding: 7bit' . $line_break . $line_break . ')(.*?)(' . $line_break . '--ELK-[a-z0-9]{32})~s', "$1$2<br /><br />[{$unq_head}]<br />$3", $message);

	// base64 the harder one to insert our key
	// Find the sections, un-do the chunk_split, add in the new key, and re chunky it
	if (preg_match('~(Content-Transfer-Encoding: base64' . $line_break . $line_break . ')(.*?)(' . $line_break . '--ELK-[a-z0-9]{32})~s', $message, $match))
	{
		// un-chunk, add in our encoded key header, and re chunk, all so we match RFC 2045 semantics.
		$encoded_message = str_replace($line_break, '', $match[2]);
		$encoded_message .= $encoded_unq_head;
		$encoded_message = chunk_split($encoded_message, 76, $line_break);
		$message = str_replace($match[2], $encoded_message, $message);
	}

	return $message;
}

/**
 * Load a template from EmailTemplates language file.
 *
 * @package Mail
 *
 * @param string   $template
 * @param mixed[]  $replacements
 * @param string   $lang = ''
 * @param bool     $loadLang = true
 * @param string[] $suffixes - Additional suffixes to find and return
 * @param string[] $additional_files - Additional language files to load
 *
 * @return array
 * @throws Elk_Exception email_no_template
 */
function loadEmailTemplate($template, $replacements = array(), $lang = '', $loadLang = true, $suffixes = array(), $additional_files = array())
{
	global $txt, $mbname, $scripturl, $settings, $boardurl, $modSettings;

	// First things first, load up the email templates language file, if we need to.
	if ($loadLang)
	{
		loadLanguage('EmailTemplates', $lang);
		if (!empty($modSettings['maillist_enabled']))
			loadLanguage('MaillistTemplates', $lang);

		if (!empty($additional_files))
		{
			foreach ($additional_files as $file)
				loadLanguage($file, $lang);
		}
	}

	if (!isset($txt[$template . '_subject']) || !isset($txt[$template . '_body']))
		throw new Elk_Exception('email_no_template', 'template', array($template));

	$ret = array(
		'subject' => $txt[$template . '_subject'],
		'body' => $txt[$template . '_body'],
	);
	if (!empty($suffixes))
	{
		foreach ($suffixes as $key)
			$ret[$key] = $txt[$template . '_' . $key];
	}

	// Add in the default replacements.
	$replacements += array(
		'FORUMNAME' => $mbname,
		'FORUMNAMESHORT' => (!empty($modSettings['maillist_sitename']) ? $modSettings['maillist_sitename'] : $mbname),
		'EMAILREGARDS' => (!empty($modSettings['maillist_sitename_regards']) ? $modSettings['maillist_sitename_regards'] : ''),
		'FORUMURL' => $boardurl,
		'SCRIPTURL' => $scripturl,
		'THEMEURL' => $settings['theme_url'],
		'IMAGESURL' => $settings['images_url'],
		'DEFAULT_THEMEURL' => $settings['default_theme_url'],
		'REGARDS' => replaceBasicActionUrl($txt['regards_team']),
	);

	// Split the replacements up into two arrays, for use with str_replace
	$find = array();
	$replace = array();

	foreach ($replacements as $f => $r)
	{
		$find[] = '{' . $f . '}';
		$replace[] = $r;
	}

	// Do the variable replacements.
	foreach ($ret as $key => $val)
	{
		$val = str_replace($find, $replace, $val);
		// Now deal with the {USER.variable} items.
		$ret[$key] = preg_replace_callback('~{USER.([^}]+)}~', 'user_info_callback', $val);
	}

	// Finally return the email to the caller so they can send it out.
	return $ret;
}

/**
 * Prepare subject and message of an email for the preview box
 *
 * Used in action_mailingcompose and RetrievePreview (Xml.controller.php)
 *
 * @package Mail
 */
function prepareMailingForPreview()
{
	global $context, $modSettings, $scripturl, $user_info, $txt;

	loadLanguage('Errors');
	require_once(SUBSDIR . '/Post.subs.php');

	$processing = array(
		'preview_subject' => 'subject',
		'preview_message' => 'message'
	);

	// Use the default time format.
	$user_info['time_format'] = $modSettings['time_format'];

	$variables = array(
		'{$board_url}',
		'{$current_time}',
		'{$latest_member.link}',
		'{$latest_member.id}',
		'{$latest_member.name}'
	);

	$html = $context['send_html'];

	// We might need this in a bit
	$cleanLatestMember = empty($context['send_html']) || $context['send_pm'] ? un_htmlspecialchars($modSettings['latestRealName']) : $modSettings['latestRealName'];

	$bbc_parser = \BBC\ParserWrapper::getInstance();

	foreach ($processing as $key => $post)
	{
		$context[$key] = !empty($_REQUEST[$post]) ? $_REQUEST[$post] : '';

		if (empty($context[$key]) && empty($_REQUEST['xml']))
			$context['post_error']['messages'][] = $txt['error_no_' . $post];
		elseif (!empty($_REQUEST['xml']))
			continue;

		preparsecode($context[$key]);

		// Sending as html then we convert any bbc
		if ($html)
		{
			$enablePostHTML = $modSettings['enablePostHTML'];
			$modSettings['enablePostHTML'] = $context['send_html'];
			$context[$key] = $bbc_parser->parseEmail($context[$key]);
			$modSettings['enablePostHTML'] = $enablePostHTML;
		}

		// Replace in all the standard things.
		$context[$key] = str_replace($variables,
			array(
				!empty($context['send_html']) ? '<a href="' . $scripturl . '">' . $scripturl . '</a>' : $scripturl,
				standardTime(forum_time(), false),
				!empty($context['send_html']) ? '<a href="' . $scripturl . '?action=profile;u=' . $modSettings['latestMember'] . '">' . $cleanLatestMember . '</a>' : ($context['send_pm'] ? '[url=' . $scripturl . '?action=profile;u=' . $modSettings['latestMember'] . ']' . $cleanLatestMember . '[/url]' : $cleanLatestMember),
				$modSettings['latestMember'],
				$cleanLatestMember
			), $context[$key]);
	}
}

/**
 * Callback function for load email template on subject and body
 * Uses capture group 1 in array
 *
 * @package Mail
 * @param mixed[] $matches
 * @return string
 */
function user_info_callback($matches)
{
	global $user_info;

	if (empty($matches[1]))
		return '';

	$use_ref = true;
	$ref = &$user_info;

	foreach (explode('.', $matches[1]) as $index)
	{
		if ($use_ref && isset($ref[$index]))
			$ref = &$ref[$index];
		else
		{
			$use_ref = false;
			break;
		}
	}

	return $use_ref ? $ref : $matches[0];
}

/**
 * This function grabs the mail queue items from the database, according to the params given.
 *
 * @package Mail
 * @param int $start The item to start with (for pagination purposes)
 * @param int $items_per_page  The number of items to show per page
 * @param string $sort A string indicating how to sort the results
 * @return array
 */
function list_getMailQueue($start, $items_per_page, $sort)
{
	global $txt;

	$db = database();

	return $db->fetchQueryCallback('
		SELECT
			id_mail, time_sent, recipient, priority, private, subject
		FROM {db_prefix}mail_queue
		ORDER BY {raw:sort}
		LIMIT {int:start}, {int:items_per_page}',
		array(
			'start' => $start,
			'sort' => $sort,
			'items_per_page' => $items_per_page,
		),
		function ($row) use ($txt)
		{
			// Private PM/email subjects and similar shouldn't be shown in the mailbox area.
			if (!empty($row['private']))
				$row['subject'] = $txt['personal_message'];

			return $row;
		}
	);
}

/**
 * Returns the total count of items in the mail queue.
 *
 * @package Mail
 * @return int
 */
function list_getMailQueueSize()
{
	$db = database();

	// How many items do we have?
	$request = $db->query('', '
		SELECT COUNT(*) AS queue_size
		FROM {db_prefix}mail_queue',
		array(
		)
	);
	list ($mailQueueSize) = $db->fetch_row($request);
	$db->free_result($request);

	return $mailQueueSize;
}

/**
 * Deletes items from the mail queue
 *
 * @package Mail
 * @param int[] $items
 */
function deleteMailQueueItems($items)
{
	$db = database();

	$db->query('', '
		DELETE FROM {db_prefix}mail_queue
		WHERE id_mail IN ({array_int:mail_ids})',
		array(
			'mail_ids' => $items,
		)
	);
}

/**
 * Get the current mail queue status
 *
 * @package Mail
 */
function list_MailQueueStatus()
{
	$db = database();

	$items = array();

	// How many items do we have?
	$request = $db->query('', '
		SELECT COUNT(*) AS queue_size, MIN(time_sent) AS oldest
		FROM {db_prefix}mail_queue',
		array(
		)
	);
	list ($items['mailQueueSize'], $items['mailOldest']) = $db->fetch_row($request);
	$db->free_result($request);

	return $items;
}

/**
 * This function handles updates to account for failed emails.
 *
 * - It is used to keep track of failed emails attempts and next try.
 *
 * @package Mail
 * @param mixed[] $failed_emails
 */
function updateFailedQueue($failed_emails)
{
	global $modSettings;

	$db = database();

	// Update the failed attempts check.
	$db->insert('replace',
		'{db_prefix}settings',
		array('variable' => 'string', 'value' => 'string'),
		array('mail_failed_attempts', empty($modSettings['mail_failed_attempts']) ? 1 : ++$modSettings['mail_failed_attempts']),
		array('variable')
	);

	// If we have failed to many times, tell mail to wait a bit and try again.
	if ($modSettings['mail_failed_attempts'] > 5)
		$db->query('', '
			UPDATE {db_prefix}settings
			SET value = {string:next_mail_send}
			WHERE variable = {string:mail_next_send}
				AND value = {string:last_send}',
			array(
				'next_mail_send' => time() + 60,
				'mail_next_send' => 'mail_next_send',
				'last_send' => $modSettings['mail_next_send'],
			)
		);

	// Add our email back to the queue, manually.
	$db->insert('insert',
		'{db_prefix}mail_queue',
		array('time_sent' => 'int', 'recipient' => 'string', 'body' => 'string', 'subject' => 'string', 'headers' => 'string', 'send_html' => 'int', 'priority' => 'int', 'private' => 'int', 'message_id' => 'string-255'),
		$failed_emails,
		array('id_mail')
	);
}

/**
 * Updates the failed attempts to email in the database.
 *
 * - It sets mail failed attempts value to 0.
 *
 * @package Mail
 */
function updateSuccessQueue()
{
	$db = database();

	$db->query('', '
		UPDATE {db_prefix}settings
		SET value = {string:zero}
		WHERE variable = {string:mail_failed_attempts}',
		array(
			'zero' => '0',
			'mail_failed_attempts' => 'mail_failed_attempts',
		)
	);
}

/**
 * Reset to 0 the next send time for emails queue.
 */
function resetNextSendTime()
{
	global $modSettings;

	$db = database();

	// Update the setting to zero, yay
	// ...unless someone else did.
	$db->query('', '
		UPDATE {db_prefix}settings
		SET value = {string:no_send}
		WHERE variable = {string:mail_next_send}
			AND value = {string:last_mail_send}',
		array(
			'no_send' => '0',
			'mail_next_send' => 'mail_next_send',
			'last_mail_send' => $modSettings['mail_next_send'],
		)
	);
}

/**
 * Update the next sending time for mail queue.
 *
 * - By default, move it 10 seconds for lower per mail_period_limits and 5 seconds for larger mail_period_limits
 * - Requires an affected row
 *
 * @package Mail
 * @return bool
 */
function updateNextSendTime()
{
	global $modSettings;

	$db = database();

	// Set a delay based on the per minute limit (mail_period_limit)
	$delay = !empty($modSettings['mail_queue_delay']) ? $modSettings['mail_queue_delay'] : (!empty($modSettings['mail_period_limit']) && $modSettings['mail_period_limit'] <= 5 ? 10 : 5);

	$db->query('', '
		UPDATE {db_prefix}settings
		SET value = {string:next_mail_send}
		WHERE variable = {string:mail_next_send}
			AND value = {string:last_send}',
		array(
			'next_mail_send' => time() + $delay,
			'mail_next_send' => 'mail_next_send',
			'last_send' => $modSettings['mail_next_send'],
		)
	);
	if ($db->affected_rows() == 0)
		return false;

	return $delay;
}

/**
 * Retrieve all details from the database on the next emails.
 *
 * @package Mail
 * @param int $number
 * @return array
 */
function emailsInfo($number)
{
	$db = database();
	$ids = array();
	$emails = array();

	// Get the next $number emails, with all that's to know about them and one more.
	$db->fetchQueryCallback('
		SELECT /*!40001 SQL_NO_CACHE */ id_mail, recipient, body, subject, headers, send_html, time_sent, priority, private, message_id
		FROM {db_prefix}mail_queue
		ORDER BY priority ASC, id_mail ASC
		LIMIT ' . $number,
		array(
		),
		function ($row) use(&$ids, &$emails)
		{
			// Just get the data and go.
			$ids[] = $row['id_mail'];
			$emails[] = array(
				'to' => $row['recipient'],
				'body' => $row['body'],
				'subject' => $row['subject'],
				'headers' => $row['headers'],
				'send_html' => $row['send_html'],
				'time_sent' => $row['time_sent'],
				'priority' => $row['priority'],
				'private' => $row['private'],
				'message_id' => $row['message_id'],
			);
		}
	);

	return array($ids, $emails);
}

/**
 * Sends a group of emails from the mail queue.
 *
 * - Allows a batch of emails to be released every 5 to 10 seconds (based on per period limits)
 * - If batch size is not set, will determine a size such that it sends in 1/2 the period (buffer)
 *
 * @package Mail
 * @param int|bool $batch_size = false the number to send each loop
 * @param boolean $override_limit = false bypassing our limit flaf
 * @param boolean $force_send = false
 * @return boolean
 */
function reduceMailQueue($batch_size = false, $override_limit = false, $force_send = false)
{
	global $modSettings, $webmaster_email, $scripturl;

	// Do we have another script to send out the queue?
	if (!empty($modSettings['mail_queue_use_cron']) && empty($force_send))
		return false;

	// How many emails can we send each time we are called in a period
	if (!$batch_size)
	{
		// Batch size has been set in the ACP, use it
		if (!empty($modSettings['mail_batch_size']))
			$batch_size = $modSettings['mail_batch_size'];
		// No per period setting or batch size, set to send 5 every 5 seconds, or 60 per minute
		elseif (empty($modSettings['mail_period_limit']))
			$batch_size = 5;
		// A per period limit but no defined batch size, set a batch size
		else
		{
			// Based on the number of times we will potentially be called each minute
			$delay = !empty($modSettings['mail_queue_delay']) ? $modSettings['mail_queue_delay'] : (!empty($modSettings['mail_period_limit']) && $modSettings['mail_period_limit'] <= 5 ? 10 : 5);
			$batch_size = ceil($modSettings['mail_period_limit'] / ceil(60 / $delay));
			$batch_size = ($batch_size == 1 && $modSettings['mail_period_limit'] > 1) ? 2 : $batch_size;
		}
	}

	// If we came with a timestamp, and that doesn't match the next event, then someone else has beaten us.
	if (isset($_GET['ts']) && $_GET['ts'] != $modSettings['mail_next_send'] && empty($force_send))
		return false;

	// Prepare to send each email, and log that for future proof.
	require_once(SUBSDIR . '/Maillist.subs.php');

	// Set the delay for the next sending
	$delay = 0;
	if (!$override_limit)
	{
		// Update next send time for our mail queue, if there was something to update. Otherwise bail out :P
		$delay = updateNextSendTime();
		if ($delay === false)
			return false;

		$modSettings['mail_next_send'] = time() + $delay;
	}

	// If we're not overriding, do we have quota left in this mail period limit?
	if (!$override_limit && !empty($modSettings['mail_period_limit']))
	{
		// See if we have quota left to send another batch_size this minute or if we have to wait
		list ($mail_time, $mail_number) = isset($modSettings['mail_recent']) ? explode('|', $modSettings['mail_recent']) : array(0, 0);

		// Nothing worth noting...
		if (empty($mail_number) || $mail_time < time() - 60)
		{
			$mail_time = time();
			$mail_number = $batch_size;
		}
		// Otherwise we may still have quota to send a few more?
		elseif ($mail_number < $modSettings['mail_period_limit'])
		{
			// If this is likely one of the last cycles for this period, then send any remaining quota
			if (($mail_time - (time() - 60)) < $delay * 2)
				$batch_size = $modSettings['mail_period_limit'] - $mail_number;
			// Some batch sizes may need to be adjusted to fit as we approach the end
			elseif ($mail_number + $batch_size > $modSettings['mail_period_limit'])
				$batch_size = $modSettings['mail_period_limit'] - $mail_number;

			$mail_number += $batch_size;
		}
		// No more I'm afraid, return!
		else
			return false;

		// Reflect that we're about to send some, do it now to be safe.
		updateSettings(array('mail_recent' => $mail_time . '|' . $mail_number));
	}

	// Now we know how many we're sending, let's send them.
	list ($ids, $emails) = emailsInfo($batch_size);

	// Delete, delete, delete!!!
	if (!empty($ids))
		deleteMailQueueItems($ids);

	// Don't believe we have any left after this batch?
	if (count($ids) < $batch_size)
		resetNextSendTime();

	if (empty($ids))
		return false;

	// We have some to send, lets send them!
	$sent = array();
	$failed_emails = array();

	// Use sendmail or SMTP
	$use_sendmail = empty($modSettings['mail_type']) || $modSettings['smtp_host'] == '';

	// Line breaks need to be \r\n only in windows or for SMTP.
	$line_break = detectServer()->is('windows') || !$use_sendmail ? "\r\n" : "\n";

	foreach ($emails as $key => $email)
	{
		// So that we have it, just in case it's really needed - see #2245
		$email['body_fail'] = $email['body'];
		if ($email['message_id'] !== null && isset($email['message_id'][0]) && in_array($email['message_id'][0], array('m', 'p', 't')))
		{
			$email['message_type'] = $email['message_id'][0];
			$email['message_id'] = substr($email['message_id'], 1);
		}
		else
		{
			$email['message_type'] = 'm';
		}

		// Use the right mail resource
		if ($use_sendmail)
		{
			$email['subject'] = strtr($email['subject'], array("\r" => '', "\n" => ''));
			$email['body_fail'] = $email['body'];

			if (!empty($modSettings['mail_strip_carriage']))
			{
				$email['body'] = strtr($email['body'], array("\r" => ''));
				$email['headers'] = strtr($email['headers'], array("\r" => ''));
			}

			// See the assignment 10 lines before this and #2245 - repeated here for the strtr
			$email['body_fail'] = $email['body'];
			$need_break = substr($email['headers'], -1) === "\n" || substr($email['headers'], -1) === "\r" ? false : true;

			// Create our unique reply to email header if this message needs one
			$unq_id = '';
			$unq_head = '';
			$unq_head_array = array();
			if (!empty($modSettings['maillist_enabled']) && $email['message_id'] !== null && strpos($email['headers'], 'List-Id: <') !== false)
			{
				$unq_head_array[0] = md5($scripturl . microtime() . rand());
				$unq_head_array[1] = $email['message_type'];
				$unq_head_array[2] = $email['message_id'];
				$unq_head = $unq_head_array[0] . '-' . $unq_head_array[1] . $unq_head_array[2];
				$encoded_unq_head = base64_encode($line_break . $line_break . '[' . $unq_head . ']' . $line_break);
				$unq_id = ($need_break ? $line_break : '') . 'Message-ID: <' . $unq_head . strstr(empty($modSettings['maillist_mail_from']) ? $webmaster_email : $modSettings['maillist_mail_from'], '@') . '>';
				$email['body'] = mail_insert_key($email['body'], $unq_head, $encoded_unq_head, $line_break);
			}
			elseif ($email['message_id'] !== null && empty($modSettings['mail_no_message_id']))
				$unq_id = ($need_break ? $line_break : '') . 'Message-ID: <' . md5($scripturl . microtime()) . '-' . $email['message_id'] . strstr(empty($modSettings['maillist_mail_from']) ? $webmaster_email : $modSettings['maillist_mail_from'], '@') . '>';

			// No point logging a specific error here, as we have no language. PHP error is helpful anyway...
			$result = mail(strtr($email['to'], array("\r" => '', "\n" => '')), $email['subject'], $email['body'], $email['headers'] . $unq_id);

			// If it sent, keep a record so we can save it in our allowed to reply log
			if (!empty($unq_head) && $result)
				$sent[] = array($unq_head, time(), $email['to']);

			// Track total emails sent
			if ($result && !empty($modSettings['trackStats']))
				trackStats(array('email' => '+'));

			// Try to stop a timeout, this would be bad...
			detectServer()->setTimeLimit(300);
		}
		else
			$result = smtp_mail(array($email['to']), $email['subject'], $email['body'], $email['headers'], $email['priority'], $email['message_type'] . $email['message_id']);

		// Hopefully it sent?
		if (!$result)
			$failed_emails[] = array(time(), $email['to'], $email['body_fail'], $email['subject'], $email['headers'], $email['send_html'], $email['priority'], $email['private'], $email['message_id']);
	}

	// Clear out the stat cache.
	trackStats();

	// Log each of the sent emails.
	if (!empty($sent))
		log_email($sent);

	// Any emails that didn't send?
	if (!empty($failed_emails))
	{
		// If it failed, add it back to the queue
		updateFailedQueue($failed_emails);
		return false;
	}
	// We were able to send the email, clear our failed attempts.
	elseif (!empty($modSettings['mail_failed_attempts']))
		updateSuccessQueue();

	// Had something to send...
	return true;
}

/**
 * This function finds email address and few other details of the
 * poster of a certain message.
 *
 * @todo very similar to mailFromMessage
 * @package Mail
 * @param int $id_msg the id of a message
 * @param int $topic_id the topic the message belongs to
 * @return mixed[] the poster's details
 */
function posterDetails($id_msg, $topic_id)
{
	$db = database();

	$request = $db->query('', '
		SELECT m.id_msg, m.id_topic, m.id_board, m.subject, m.body, m.id_member AS id_poster, m.poster_name, mem.real_name
		FROM {db_prefix}messages AS m
			LEFT JOIN {db_prefix}members AS mem ON (m.id_member = mem.id_member)
		WHERE m.id_msg = {int:id_msg}
			AND m.id_topic = {int:current_topic}
		LIMIT 1',
		array(
			'current_topic' => $topic_id,
			'id_msg' => $id_msg,
		)
	);

	$message = $db->fetch_assoc($request);
	$db->free_result($request);

	return $message;
}

/**
 * Little utility function to calculate how long ago a time was.
 *
 * @package Mail
 * @param integer|double $time_diff
 * @return string
 */
function time_since($time_diff)
{
	global $txt;

	if ($time_diff < 0)
		$time_diff = 0;

	// Just do a bit of an if fest...
	if ($time_diff > 86400)
	{
		$days = round($time_diff / 86400, 1);
		return sprintf($days == 1 ? $txt['mq_day'] : $txt['mq_days'], $time_diff / 86400);
	}
	// Hours?
	elseif ($time_diff > 3600)
	{
		$hours = round($time_diff / 3600, 1);
		return sprintf($hours == 1 ? $txt['mq_hour'] : $txt['mq_hours'], $hours);
	}
	// Minutes?
	elseif ($time_diff > 60)
	{
		$minutes = (int) ($time_diff / 60);
		return sprintf($minutes == 1 ? $txt['mq_minute'] : $txt['mq_minutes'], $minutes);
	}
	// Otherwise must be second
	else
		return sprintf($time_diff == 1 ? $txt['mq_second'] : $txt['mq_seconds'], $time_diff);
}
