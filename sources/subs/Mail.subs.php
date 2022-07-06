<?php

/**
 * This file handles tasks related to mail.
 * The functions in this file do NOT check permissions.
 *
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * This file contains code covered by:
 * copyright: 2011 Simple Machines (http://www.simplemachines.org)
 *
 * @version 2.0 dev
 *
 */

use BBC\ParserWrapper;
use ElkArte\Languages\Loader as LangLoader;
use ElkArte\Languages\Txt;
use ElkArte\Mail\BuildMail;
use ElkArte\Mail\QueueMail;
use ElkArte\User;

/**
 * This function sends an email to the specified recipient(s).
 *
 * It uses the mail_type settings and webmaster_email variable.
 *
 * @param string[]|string $to - the email(s) to send to
 * @param string $subject - email subject, expected to have entities, and slashes, but not be parsed
 * @param string $message - email body, expected to have slashes, no htmlentities
 * @param string|null $from = null - the address to use for replies
 * @param string|null $message_id = null - if specified, it will be used as local part of the Message-ID header.
 * @param bool $send_html = false, whether the message is HTML vs. plain text
 * @param int $priority = 3 Primarily used for queue priority.  0 = send now, >3 = no PBE
 * @param bool|null $hotmail_fix = null  ** No longer used, left only for old function calls **
 * @param bool $is_private - Hides to/from names when viewing the mail queue
 * @param string|null $from_wrapper - used to provide envelope from wrapper based on if we share users display name
 * @param int|null $reference - The parent topic id for use in a References header
 * @return bool whether the email was accepted properly.
 * @package Mail
 */
function sendmail($to, $subject, $message, $from = null, $message_id = null, $send_html = false, $priority = 3, $hotmail_fix = null, $is_private = false, $from_wrapper = null, $reference = null)
{
	// Pass this on to the buildEmail and sendMail functions
	return (new BuildMail())->buildEmail($to, $subject, $message, $from, $message_id, $send_html, $priority, $is_private, $from_wrapper, $reference);
}

/**
 * Add an email to the mail queue.
 *
 * @param bool $flush = false
 * @param string[] $to_array = array()
 * @param string $subject = ''
 * @param string $message = ''
 * @param string $headers = ''
 * @param bool $send_html = false
 * @param int $priority = 3
 * @param bool $is_private
 * @param string|null $message_id
 * @return bool
 * @package Mail
 */
function AddMailQueue($flush = false, $to_array = [], $subject = '', $message = '', $headers = '', $send_html = false, $priority = 3, $is_private = false, $message_id = '')
{
	global $context;

	$db = database();

	static $cur_insert = array();
	static $cur_insert_len = 0;

	if ($cur_insert_len == 0)
	{
		$cur_insert = [];
	}

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

		$cur_insert = [];
		$context['flush_mail'] = false;
	}

	// If we're flushing we're done.
	if ($flush)
	{
		$nextSendTime = time() + 10;

		$db->query('', '
			UPDATE {db_prefix}settings
			SET 
				value = {string:nextSendTime}
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
			$cur_insert = [];
			$cur_insert_len = 0;
		}

		// Now add the current insert to the array...
		$cur_insert[] = [time(), (string) $to, (string) $message, (string) $subject, (string) $headers, ($send_html ? 1 : 0), $priority, (int) $is_private, (string) $message_id];
		$cur_insert_len += $this_insert_len;
	}

	// If they are using SSI there is a good chance obExit will never be called.  So lets be nice and flush it for them.
	if (ELK === 'SSI')
	{
		return AddMailQueue(true);
	}

	return true;
}

/**
 * Converts out of ascii range utf-8 characters in to HTML entities.  Primarily used
 * to maintain 7bit compliance for plain emails
 *
 * - Character codes <= 128 are left as is
 * - Character codes U+0080 <> U+00A0 range (control) are dropped
 * - Callback function of preg_replace_callback
 *
 * @param mixed[] $match
 *
 * @return string
 * @package Mail
 *
 */
function entityConvert($match)
{
	$c = $match[1];
	$c_strlen = strlen($c);
	$c_ord = ord($c[0]);

	// <= 127 are standard ASCII characters
	if ($c_strlen === 1 && $c_ord <= 0x7F)
	{
		return $c;
	}

	// Drop 2 byte control characters in the  U+0080 <> U+00A0 range
	if ($c_strlen === 2 && $c_ord === 0xC2 && ord($c[1]) <= 0xA0)
	{
		return '';
	}

	if ($c_strlen === 2 && $c_ord >= 0xC0 && $c_ord <= 0xDF)
	{
		return '&#' . ((($c_ord ^ 0xC0) << 6) + (ord($c[1]) ^ 0x80)) . ';';
	}

	if ($c_strlen === 3 && $c_ord >= 0xE0 && $c_ord <= 0xEF)
	{
		return '&#' . ((($c_ord ^ 0xE0) << 12) + ((ord($c[1]) ^ 0x80) << 6) + (ord($c[2]) ^ 0x80)) . ';';
	}

	if ($c_strlen === 4 && $c_ord >= 0xF0 && $c_ord <= 0xF7)
	{
		return '&#' . ((($c_ord ^ 0xF0) << 18) + ((ord($c[1]) ^ 0x80) << 12) + ((ord($c[2]) ^ 0x80) << 6) + (ord($c[3]) ^ 0x80)) . ';';
	}

	return '';
}

/**
 * Adds the unique security key in to an email
 *
 * - adds the key in to (each) message body section
 * - safety net for clients that strip out the message-id and in-reply-to headers
 *
 * @param string $message
 * @param string $unq_head
 * @param string $line_break
 *
 * @return string
 * @package Mail
 *
 */
function mail_insert_key($message, $unq_head, $line_break)
{
	$regex = [];
	$regex['plain'] = '~^(.*?)(' . $line_break . '--ELK-[a-z0-9]{28})~s';
	$regex['qp'] = '~(Content-Transfer-Encoding: Quoted-Printable' . $line_break . $line_break . ')(.*?)(' . $line_break . '--ELK-[a-z0-9]{28})~s';
	$regex['base64'] = '~(Content-Transfer-Encoding: base64' . $line_break . $line_break . ')(.*?)(' . $line_break . '--ELK-[a-z0-9]{28})~s';

	// Append the key to the bottom of the plain section, it is always the first one
	$message = preg_replace($regex['plain'], "$1{$line_break}{$line_break}[{$unq_head}]{$line_break}$2", $message);

	// Quoted Printable section, add the key in background color so the html message looks good
	if (preg_match($regex['qp'], $message, $match))
	{
		$qp_message = quoted_printable_decode($match[2]);
		$qp_message = str_replace('<span class="key-holder">[]</span>', '<span style="color: #F6F6F6">[' . $unq_head . ']</span>', $qp_message);
		$qp_message = quoted_printable_encode($qp_message);
		$message = str_replace($match[2], $qp_message, $message);
	}

	// base64 the harder one as it must match RFC 2045 semantics
	// Find the sections, decode, add in the new key, and encode the new message
	if (preg_match($regex['base64'], $message, $match))
	{
		// un-chunk, add in our encoded key header, and re chunk, all so we match RFC 2045 semantics.
		$encoded_message = base64_decode(str_replace($line_break, '', $match[2]));
		$encoded_message .= $line_break . $line_break . '[' . $unq_head . ']' . $line_break;
		$encoded_message = base64_encode($encoded_message);
		$encoded_message = chunk_split($encoded_message, 76, $line_break);
		$message = str_replace($match[2], $encoded_message, $message);
	}

	return $message;
}

/**
 * Load a template from EmailTemplates language file.
 *
 * @param string $template
 * @param mixed[] $replacements
 * @param string $lang = ''
 * @param bool $html = false - If to prepare the template for HTML output (newlines to BR, <a></a> links)
 * @param bool $loadLang = true
 * @param string[] $suffixes - Additional suffixes to find and return
 * @param string[] $additional_files - Additional language files to load
 *
 * @return array
 * @throws \ElkArte\Exceptions\Exception email_no_template
 * @package Mail
 */
function loadEmailTemplate($template, $replacements = [], $lang = '', $html = false, $loadLang = true, $suffixes = [], $additional_files = array())
{
	global $txt, $mbname, $scripturl, $settings, $boardurl, $modSettings;

	// First things first, load up the email templates language file, if we need to.
	if ($loadLang)
	{
		$lang_loader = new LangLoader($lang, $txt, database());
		$lang_loader->load('EmailTemplates');
		if (!empty($modSettings['maillist_enabled']))
		{
			$lang_loader->load('MaillistTemplates');
		}

		if (!empty($additional_files))
		{
			foreach ($additional_files as $file)
			{
				$lang_loader->load($file);
			}
		}
	}

	if (!isset($txt[$template . '_subject']) || !isset($txt[$template . '_body']))
	{
		throw new \ElkArte\Exceptions\Exception('email_no_template', 'template', array($template));
	}

	$ret = [
		'subject' => $txt[$template . '_subject'],
		'body' => $txt[$template . '_body'],
	];

	if (!empty($suffixes))
	{
		foreach ($suffixes as $key)
		{
			$ret[$key] = $txt[$template . '_' . $key];
		}
	}

	// Add in the default replacements.
	$replacements += [
		'FORUMNAME' => $mbname,
		'FORUMNAMESHORT' => (!empty($modSettings['maillist_sitename']) ? $modSettings['maillist_sitename'] : $mbname),
		'EMAILREGARDS' => (!empty($modSettings['maillist_sitename_regards']) ? $modSettings['maillist_sitename_regards'] : ''),
		'FORUMURL' => $boardurl,
		'SCRIPTURL' => $scripturl,
		'THEMEURL' => $settings['theme_url'],
		'IMAGESURL' => $settings['images_url'],
		'DEFAULT_THEMEURL' => $settings['default_theme_url'],
		'REGARDS' => replaceBasicActionUrl($txt['regards_team']),
	];

	// Split the replacements up into two arrays, for use with str_replace
	$find = [];
	$replace = [];

	foreach ($replacements as $f => $r)
	{
		$find[] = '{' . $f . '}';
		$replace[] = $html && strpos($r, 'http') === 0 ? '<a href="' . $r . '">' . $r . '</a>' : $r;
	}

	// Do the variable replacements.
	foreach ($ret as $key => $val)
	{
		$val = str_replace($find, $replace, $val);

		// Now deal with the {USER.variable} items.
		$ret[$key] = preg_replace_callback('~{USER.([^}]+)}~', 'user_info_callback', $val);
	}

	// If we want this template to be used as HTML,
	$ret['body'] = $html ? templateToHtml($ret['body']) : $ret['body'];

	// Finally return the email to the caller, so they can send it out.
	return $ret;
}

/**
 * Used to preserve the Pre formatted look of txt template's when sending HTML
 *
 * @param $string
 * @return string
 */
function templateToHtml($string)
{
	$newString = preg_replace('~^-{3,40}$~m', '<hr />', $string);

	$newString = str_replace("\n", '<br />', $newString);

	return $newString ?? $string;
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
	global $context, $modSettings, $scripturl, $txt;

	Txt::load('Errors');
	require_once(SUBSDIR . '/Post.subs.php');

	$processing = array(
		'preview_subject' => 'subject',
		'preview_message' => 'message'
	);

	// Use the default time format.
	User::$info->time_format = $modSettings['time_format'];

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

	$bbc_parser = ParserWrapper::instance();

	foreach ($processing as $key => $post)
	{
		$context[$key] = !empty($_REQUEST[$post]) ? $_REQUEST[$post] : '';

		if (empty($context[$key]) && empty($_REQUEST['xml']))
		{
			$context['post_error']['messages'][] = $txt['error_no_' . $post];
		}
		elseif (!empty($_REQUEST['xml']))
		{
			continue;
		}

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
 * @param mixed[] $matches
 * @return string
 * @package Mail
 */
function user_info_callback($matches)
{
	if (empty($matches[1]))
	{
		return '';
	}

	$use_ref = true;
	$ref = User::$info->toArray();

	foreach (explode('.', $matches[1]) as $index)
	{
		if ($use_ref && isset($ref[$index]))
		{
			$ref = &$ref[$index];
		}
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
 * @param int $start The item to start with (for pagination purposes)
 * @param int $items_per_page The number of items to show per page
 * @param string $sort A string indicating how to sort the results
 * @return array
 * @package Mail
 */
function list_getMailQueue($start, $items_per_page, $sort)
{
	global $txt;

	$db = database();

	return $db->fetchQuery('
		SELECT
			id_mail, time_sent, recipient, priority, private, subject
		FROM {db_prefix}mail_queue
		ORDER BY {raw:sort}
		LIMIT {int:start}, {int:items_per_page}',
		array(
			'start' => $start,
			'sort' => $sort,
			'items_per_page' => $items_per_page,
		)
	)->fetch_callback(
		function ($row) use ($txt) {
			// Private PM/email subjects and similar shouldn't be shown in the mailbox area.
			if (!empty($row['private']))
			{
				$row['subject'] = $txt['personal_message'];
			}

			return $row;
		}
	);
}

/**
 * Returns the total count of items in the mail queue.
 *
 * @return int
 * @package Mail
 */
function list_getMailQueueSize()
{
	$db = database();

	// How many items do we have?
	$request = $db->query('', '
		SELECT 
			COUNT(*) AS queue_size
		FROM {db_prefix}mail_queue',
		array()
	);
	list ($mailQueueSize) = $request->fetch_row();
	$request->free_result();

	return $mailQueueSize;
}

/**
 * Deletes items from the mail queue
 *
 * @param int[] $items
 * @package Mail
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
		SELECT 
		    COUNT(*) AS queue_size, MIN(time_sent) AS oldest
		FROM {db_prefix}mail_queue',
		array()
	);
	list ($items['mailQueueSize'], $items['mailOldest']) = $request->fetch_row();
	$request->free_result();

	return $items;
}

/**
 * This function handles updates to account for failed emails.
 *
 * - It is used to keep track of failed emails attempts and next try.
 *
 * @param mixed[] $failed_emails
 * @package Mail
 */
function updateFailedQueue($failed_emails)
{
	global $modSettings;

	$db = database();

	// Update the failed attempts check.
	$db->replace(
		'{db_prefix}settings',
		array('variable' => 'string', 'value' => 'string'),
		array('mail_failed_attempts', empty($modSettings['mail_failed_attempts']) ? 1 : ++$modSettings['mail_failed_attempts']),
		array('variable')
	);

	// If we have failed to many times, tell mail to wait a bit and try again.
	if ($modSettings['mail_failed_attempts'] > 5)
	{
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
	}

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
 * - By default, move it 10 seconds for lower per mail_period_limits
 * and 5 seconds for larger mail_period_limits
 * - Requires an affected row
 *
 * @return int|bool
 * @package Mail
 */
function updateNextSendTime()
{
	global $modSettings;

	$db = database();

	// Set a delay based on the per minute limit (mail_period_limit)
	$delay = !empty($modSettings['mail_queue_delay'])
		? $modSettings['mail_queue_delay']
		: (!empty($modSettings['mail_period_limit']) && $modSettings['mail_period_limit'] <= 5 ? 10 : 5);

	$request = $db->query('', '
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
	if ($request->affected_rows() === 0)
	{
		return false;
	}

	return (int) $delay;
}

/**
 * Retrieve all details from the database on the next emails in the queue
 *
 * - Will fetch the next batch number of queued emails, sorted by priority
 *
 * @param int $number
 * @return array
 * @package Mail
 */
function emailsInfo($number)
{
	$db = database();
	$ids = [];
	$emails = [];

	// Get the next $number emails, with all that's to know about them and one more.
	$db->fetchQuery('
		SELECT /*!40001 SQL_NO_CACHE */ 
			id_mail, recipient, body, subject, headers, send_html, time_sent, priority, private, message_id
		FROM {db_prefix}mail_queue
		ORDER BY priority ASC, id_mail ASC
		LIMIT ' . $number,
		array()
	)->fetch_callback(
		function ($row) use (&$ids, &$emails) {
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

	return [$ids, $emails];
}

/**
 * Sends a group of emails from the mail queue.
 *
 * - Allows a batch of emails to be released every 5 to 10 seconds (based on per period limits)
 * - If batch size is not set, will determine a size such that it sends in 1/2 the period (buffer)
 *
 * @param int|bool $batch_size = false the number to send each loop
 * @param bool $override_limit = false bypassing our limit flaf
 * @param bool $force_send = false
 * @return bool
 * @package Mail
 */
function reduceMailQueue($batch_size = false, $override_limit = false, $force_send = false)
{
	return (new QueueMail())->reduceMailQueue($batch_size, $override_limit, $force_send);
}

/**
 * This function finds email address and few other details of the
 * poster of a certain message.
 *
 * @param int $id_msg the id of a message
 * @param int $topic_id the topic the message belongs to
 * @return mixed[] the poster's details
 * @todo very similar to mailFromMessage
 * @package Mail
 */
function posterDetails($id_msg, $topic_id)
{
	$db = database();

	$request = $db->query('', '
		SELECT 
			m.id_msg, m.id_topic, m.id_board, m.subject, m.body, m.id_member AS id_poster, m.poster_name, mem.real_name
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
	$message = $request->fetch_assoc();
	$request->free_result();

	return $message;
}

/**
 * Little utility function to calculate how long ago a time was.
 *
 * @param int|double $time_diff
 * @return string
 * @package Mail
 */
function time_since($time_diff)
{
	global $txt;

	if ($time_diff < 0)
	{
		$time_diff = 0;
	}

	// Just do a bit of an if fest...
	if ($time_diff > 86400)
	{
		$days = round($time_diff / 86400, 1);

		return sprintf($days == 1 ? $txt['mq_day'] : $txt['mq_days'], $time_diff / 86400);
	}

	// Hours?
	if ($time_diff > 3600)
	{
		$hours = round($time_diff / 3600, 1);

		return sprintf($hours == 1 ? $txt['mq_hour'] : $txt['mq_hours'], $hours);
	}

	// Minutes?
	if ($time_diff > 60)
	{
		$minutes = (int) ($time_diff / 60);

		return sprintf($minutes === 1 ? $txt['mq_minute'] : $txt['mq_minutes'], $minutes);
	}

	// Otherwise must be second
	return sprintf($time_diff == 1 ? $txt['mq_second'] : $txt['mq_seconds'], $time_diff);
}
