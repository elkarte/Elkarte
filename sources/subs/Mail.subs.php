<?php

/**
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * This software is a derived product, based on:
 *
 * Simple Machines Forum (SMF)
 * copyright:	2011 Simple Machines (http://www.simplemachines.org)
 * license:  	BSD, See included LICENSE.TXT for terms and conditions.
 *
 * @version 1.0 Alpha
 *
 * This file handles tasks related to mail.
 * The functions in this file do NOT check permissions.
 * @todo should not check permissions.
 *
 */

if (!defined('ELKARTE'))
	die('No access...');

/**
 * This function sends an email to the specified recipient(s).
 * It uses the mail_type settings and webmaster_email variable.
 *
 * @param array $to - the email(s) to send to
 * @param string $subject - email subject, expected to have entities, and slashes, but not be parsed
 * @param string $message - email body, expected to have slashes, no htmlentities
 * @param string $from = null - the address to use for replies
 * @param string $message_id = null - if specified, it will be used as local part of the Message-ID header.
 * @param bool $send_html = false, whether or not the message is HTML vs. plain text
 * @param int $priority = 3
 * @param bool $hotmail_fix = null
 * @param $is_private
 * @return boolean, whether ot not the email was sent properly.
 */
function sendmail($to, $subject, $message, $from = null, $message_id = null, $send_html = false, $priority = 3, $hotmail_fix = null, $is_private = false)
{
	global $webmaster_email, $context, $modSettings, $txt, $scripturl;
	global $smcFunc;

	// Use sendmail if it's set or if no SMTP server is set.
	$use_sendmail = empty($modSettings['mail_type']) || $modSettings['smtp_host'] == '';

	// Line breaks need to be \r\n only in windows or for SMTP.
	$line_break = $context['server']['is_windows'] || !$use_sendmail ? "\r\n" : "\n";

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
			$mail_result = sendmail($hotmail_to, $subject, $message, $from, $message_id, $send_html, $priority, true, $is_private);

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

	list (, $from_name) = mimespecialchars(addcslashes($from !== null ? $from : $context['forum_name'], '<>()\'\\"'), true, $hotmail_fix, $line_break);
	list (, $subject) = mimespecialchars($subject, true, $hotmail_fix, $line_break);

	// Construct the mail headers...
	$headers = 'From: "' . $from_name . '" <' . (empty($modSettings['mail_from']) ? $webmaster_email : $modSettings['mail_from']) . '>' . $line_break;
	$headers .= $from !== null ? 'Reply-To: <' . $from . '>' . $line_break : '';
	$headers .= 'Return-Path: ' . (empty($modSettings['mail_from']) ? $webmaster_email : $modSettings['mail_from']) . $line_break;
	$headers .= 'Date: ' . gmdate('D, d M Y H:i:s') . ' -0000' . $line_break;

	if ($message_id !== null && empty($modSettings['mail_no_message_id']))
		$headers .= 'Message-ID: <' . md5($scripturl . microtime()) . '-' . $message_id . strstr(empty($modSettings['mail_from']) ? $webmaster_email : $modSettings['mail_from'], '@') . '>' . $line_break;
	$headers .= 'X-Mailer: ELKARTE' . $line_break;

	// Pass this to the integration before we start modifying the output -- it'll make it easier later.
	if (in_array(false, call_integration_hook('integrate_outgoing_email', array(&$subject, &$message, &$headers)), true))
		return false;

	// Save the original message...
	$orig_message = $message;

	// The mime boundary separates the different alternative versions.
	$mime_boundary = 'ELKARTE-' . md5($message . time());

	// Using mime, as it allows to send a plain unencoded alternative.
	$headers .= 'Mime-Version: 1.0' . $line_break;
	$headers .= 'Content-Type: multipart/alternative; boundary="' . $mime_boundary . '"' . $line_break;
	$headers .= 'Content-Transfer-Encoding: 7bit' . $line_break;

	// Sending HTML?  Let's plop in some basic stuff, then.
	if ($send_html)
	{
		$no_html_message = un_htmlspecialchars(strip_tags(strtr($orig_message, array('</title>' => $line_break))));

		// But, then, dump it and use a plain one for dinosaur clients.
		list(, $plain_message) = mimespecialchars($no_html_message, false, true, $line_break);
		$message = $plain_message . $line_break . '--' . $mime_boundary . $line_break;

		// This is the plain text version.  Even if no one sees it, we need it for spam checkers.
		list($charset, $plain_charset_message, $encoding) = mimespecialchars($no_html_message, false, false, $line_break);
		$message .= 'Content-Type: text/plain; charset=' . $charset . $line_break;
		$message .= 'Content-Transfer-Encoding: ' . $encoding . $line_break . $line_break;
		$message .= $plain_charset_message . $line_break . '--' . $mime_boundary . $line_break;

		// This is the actual HTML message, prim and proper.  If we wanted images, they could be inlined here (with multipart/related, etc.)
		list($charset, $html_message, $encoding) = mimespecialchars($orig_message, false, $hotmail_fix, $line_break);
		$message .= 'Content-Type: text/html; charset=' . $charset . $line_break;
		$message .= 'Content-Transfer-Encoding: ' . ($encoding == '' ? '7bit' : $encoding) . $line_break . $line_break;
		$message .= $html_message . $line_break . '--' . $mime_boundary . '--';
	}
	// Text is good too.
	else
	{
		// Send a plain message first, for the older web clients.
		list(, $plain_message) = mimespecialchars($orig_message, false, true, $line_break);
		$message = $plain_message . $line_break . '--' . $mime_boundary . $line_break;

		// Now add an encoded message using the forum's character set.
		list ($charset, $encoded_message, $encoding) = mimespecialchars($orig_message, false, false, $line_break);
		$message .= 'Content-Type: text/plain; charset=' . $charset . $line_break;
		$message .= 'Content-Transfer-Encoding: ' . $encoding . $line_break . $line_break;
		$message .= $encoded_message . $line_break . '--' . $mime_boundary . '--';
	}

	// Are we using the mail queue, if so this is where we butt in...
	if (!empty($modSettings['mail_queue']) && $priority != 0)
		return AddMailQueue(false, $to_array, $subject, $message, $headers, $send_html, $priority, $is_private);

	// If it's a priority mail, send it now - note though that this should NOT be used for sending many at once.
	elseif (!empty($modSettings['mail_queue']) && !empty($modSettings['mail_limit']))
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

		foreach ($to_array as $to)
		{
			if (!mail(strtr($to, array("\r" => '', "\n" => '')), $subject, $message, $headers))
			{
				log_error(sprintf($txt['mail_send_unable'], $to));
				$mail_result = false;
			}

			// Wait, wait, I'm still sending here!
			@set_time_limit(300);
			if (function_exists('apache_reset_timeout'))
				@apache_reset_timeout();
		}
	}
	else
		$mail_result = $mail_result && smtp_mail($to_array, $subject, $message, $headers);

	// Everything go smoothly?
	return $mail_result;
}

/**
 * Add an email to the mail queue.
 *
 * @param bool $flush = false
 * @param array $to_array = array()
 * @param string $subject = ''
 * @param string $message = ''
 * @param string $headers = ''
 * @param bool $send_html = false
 * @param int $priority = 3
 * @param $is_private
 * @return boolean
 */
function AddMailQueue($flush = false, $to_array = array(), $subject = '', $message = '', $headers = '', $send_html = false, $priority = 3, $is_private = false)
{
	global $context, $modSettings, $smcFunc;

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
		$smcFunc['db_insert']('',
			'{db_prefix}mail_queue',
			array(
				'time_sent' => 'int', 'recipient' => 'string-255', 'body' => 'string', 'subject' => 'string-255',
				'headers' => 'string-65534', 'send_html' => 'int', 'priority' => 'int', 'private' => 'int',
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

		$smcFunc['db_query']('', '
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
			$smcFunc['db_insert']('',
				'{db_prefix}mail_queue',
				array(
					'time_sent' => 'int', 'recipient' => 'string-255', 'body' => 'string', 'subject' => 'string-255',
					'headers' => 'string-65534', 'send_html' => 'int', 'priority' => 'int', 'private' => 'int',
				),
				$cur_insert,
				array('id_mail')
			);

			// Clear this out.
			$cur_insert = array();
			$cur_insert_len = 0;
		}

		// Now add the current insert to the array...
		$cur_insert[] = array(time(), (string) $to, (string) $message, (string) $subject, (string) $headers, ($send_html ? 1 : 0), $priority, (int) $is_private);
		$cur_insert_len += $this_insert_len;
	}

	// If they are using SSI there is a good chance obExit will never be called.  So lets be nice and flush it for them.
	if (ELKARTE === 'SSI')
		return AddMailQueue(true);

	return true;
}

/**
 * Prepare text strings for sending as email body or header.
 * In case there are higher ASCII characters in the given string, this
 * function will attempt the transport method 'quoted-printable'.
 * Otherwise the transport method '7bit' is used.
 *
 * @param string $string
 * @param bool $with_charset = true
 * @param bool $hotmail_fix = false, with hotmail_fix set all higher ASCII
 *  characters are converted to HTML entities to assure proper display of the mail
 * @param $line_break
 * @param string $custom_charset = null, if set, it uses this character set
 * @return array an array containing the character set, the converted string and the transport method.
 */
function mimespecialchars($string, $with_charset = true, $hotmail_fix = false, $line_break = "\r\n", $custom_charset = null)
{
	global $context;

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
			$string = preg_replace('~&#(\d{3,8});~e', 'chr(\'$1\')', $string);
		else
		{
			$string = preg_replace_callback('~&#(\d{3,8});~', 'fixchar__callback', $string);

			// Unicode, baby.
			$charset = 'UTF-8';
		}
	}

	// Convert all special characters to HTML entities...just for Hotmail :-\
	if ($hotmail_fix)
	{
		//@todo ... another replaceEntities ?
		$entityConvert = create_function('$c', '
			if (strlen($c) === 1 && ord($c[0]) <= 0x7F)
				return $c;
			elseif (strlen($c) === 2 && ord($c[0]) >= 0xC0 && ord($c[0]) <= 0xDF)
				return "&#" . (((ord($c[0]) ^ 0xC0) << 6) + (ord($c[1]) ^ 0x80)) . ";";
			elseif (strlen($c) === 3 && ord($c[0]) >= 0xE0 && ord($c[0]) <= 0xEF)
				return "&#" . (((ord($c[0]) ^ 0xE0) << 12) + ((ord($c[1]) ^ 0x80) << 6) + (ord($c[2]) ^ 0x80)) . ";";
			elseif (strlen($c) === 4 && ord($c[0]) >= 0xF0 && ord($c[0]) <= 0xF7)
				return "&#" . (((ord($c[0]) ^ 0xF0) << 18) + ((ord($c[1]) ^ 0x80) << 12) + ((ord($c[2]) ^ 0x80) << 6) + (ord($c[3]) ^ 0x80)) . ";";
			else
				return "";');

		// Convert all 'special' characters to HTML entities.
		return array($charset, preg_replace('~([\x80-\x{10FFFF}])~eu', '$entityConvert(\'\1\')', $string), '7bit');
	}

	// We don't need to mess with the subject line if no special characters were in it..
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
 * Sends mail, like mail() but over SMTP.
 * It expects no slashes or entities.
 * @internal
 *
 * @param array $mail_to_array - array of strings (email addresses)
 * @param string $subject email subject
 * @param string $message email message
 * @param string $headers
 * @return boolean whether it sent or not.
 */
function smtp_mail($mail_to_array, $subject, $message, $headers)
{
	global $modSettings, $webmaster_email, $txt;

	$modSettings['smtp_host'] = trim($modSettings['smtp_host']);

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
				log_error($txt['smtp_port_ssl']);
		}

		// Unable to connect!  Don't show any error message, but just log one and try to continue anyway.
		if (!$socket)
		{
			log_error($txt['smtp_no_connect'] . ': ' . $errno . ' : ' . $errstr);
			return false;
		}
	}

	// Wait for a response of 220, without "-" continuer.
	if (!server_parse(null, $socket, '220'))
		return false;

	if ($modSettings['mail_type'] == 1 && $modSettings['smtp_username'] != '' && $modSettings['smtp_password'] != '')
	{
		// @todo These should send the CURRENT server's name, not the mail server's!

		// EHLO could be understood to mean encrypted hello...
		if (server_parse('EHLO ' . $modSettings['smtp_host'], $socket, null) == '250')
		{
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

	// !! Theoretically, we should be able to just loop the RCPT TO.
	$mail_to_array = array_values($mail_to_array);
	foreach ($mail_to_array as $i => $mail_to)
	{
		// Reset the connection to send another email.
		if ($i != 0)
		{
			if (!server_parse('RSET', $socket, '250'))
				return false;
		}

		// From, to, and then start the data...
		if (!server_parse('MAIL FROM: <' . (empty($modSettings['mail_from']) ? $webmaster_email : $modSettings['mail_from']) . '>', $socket, '250'))
			return false;
		if (!server_parse('RCPT TO: <' . $mail_to . '>', $socket, '250'))
			return false;
		if (!server_parse('DATA', $socket, '354'))
			return false;
		fputs($socket, 'Subject: ' . $subject . "\r\n");
		if (strlen($mail_to) > 0)
			fputs($socket, 'To: <' . $mail_to . '>' . "\r\n");
		fputs($socket, $headers . "\r\n\r\n");
		fputs($socket, $message . "\r\n");

		// Send a ., or in other words "end of data".
		if (!server_parse('.', $socket, '250'))
			return false;

		// Almost done, almost done... don't stop me just yet!
		@set_time_limit(300);
		if (function_exists('apache_reset_timeout'))
			@apache_reset_timeout();
	}
	fputs($socket, 'QUIT' . "\r\n");
	fclose($socket);

	return true;
}

/**
 * Parse a message to the SMTP server.
 * Sends the specified message to the server, and checks for the
 * expected response.
 * @internal
 *
 * @param string $message - the message to send
 * @param resource $socket - socket to send on
 * @param string $response - the expected response code
 * @return whether it responded as such.
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
			log_error($txt['smtp_bad_response']);
			return false;
		}

	if ($response === null)
		return substr($server_response, 0, 3);

	if (substr($server_response, 0, 3) != $response)
	{
		log_error($txt['smtp_error'] . $server_response);
		return false;
	}

	return true;
}

/**
 * Load a template from EmailTemplates language file.
 *
 * @param string $template
 * @param array $replacements = array()
 * @param string $lang = ''
 * @param bool $loadLang = true
 */
function loadEmailTemplate($template, $replacements = array(), $lang = '', $loadLang = true)
{
	global $txt, $mbname, $scripturl, $settings, $user_info;

	// First things first, load up the email templates language file, if we need to.
	if ($loadLang)
		loadLanguage('EmailTemplates', $lang);

	if (!isset($txt[$template . '_subject']) || !isset($txt[$template . '_body']))
		fatal_lang_error('email_no_template', 'template', array($template));

	$ret = array(
		'subject' => $txt[$template . '_subject'],
		'body' => $txt[$template . '_body'],
	);

	// Add in the default replacements.
	$replacements += array(
		'FORUMNAME' => $mbname,
		'SCRIPTURL' => $scripturl,
		'THEMEURL' => $settings['theme_url'],
		'IMAGESURL' => $settings['images_url'],
		'DEFAULT_THEMEURL' => $settings['default_theme_url'],
		'REGARDS' => $txt['regards_team'],
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
	$ret['subject'] = str_replace($find, $replace, $ret['subject']);
	$ret['body'] = str_replace($find, $replace, $ret['body']);

	// Now deal with the {USER.variable} items.
	$ret['subject'] = preg_replace_callback('~{USER.([^}]+)}~', 'user_info_callback', $ret['subject']);
	$ret['body'] = preg_replace_callback('~{USER.([^}]+)}~', 'user_info_callback', $ret['body']);

	// Finally return the email to the caller so they can send it out.
	return $ret;
}

/**
 * Prepare subject and message of an email for the preview box
 * Used in ComposeMailing and RetrievePreview (Xml.controller.php)
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
			$context[$key] = parse_bbc($context[$key]);
			$modSettings['enablePostHTML'] = $enablePostHTML;
		}

		// Replace in all the standard things.
		$context[$key] = str_replace($variables,
			array(
				!empty($context['send_html']) ? '<a href="' . $scripturl . '">' . $scripturl . '</a>' : $scripturl,
				timeformat(forum_time(), false),
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
 * @param type $matches
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