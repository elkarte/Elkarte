<?php

/**
 * All the vital helper functions for use in email posting, formatting and conversion
 * and boy are there a bunch !
 *
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * @version 2.0 dev
 *
 */

use BBC\ParserWrapper;
use ElkArte\AttachmentsDirectory;
use ElkArte\Cache\Cache;
use ElkArte\EmailFormat;
use ElkArte\Html2BBC;
use ElkArte\Html2Md;
use ElkArte\MembersList;
use ElkArte\Notifications;
use ElkArte\NotificationsTask;
use ElkArte\TemporaryAttachment;
use ElkArte\Themes\ThemeLoader;
use ElkArte\Util;
use ElkArte\TemporaryAttachmentsList;

/**
 * Converts text / HTML to BBC
 *
 * What it does:
 *
 * - protects certain tags from conversion
 * - strips original message from the reply if possible
 * - If the email is html based, this will convert basic html tags to bbc tags
 * - If the email is plain text it will convert it to html based on markdown text
 * conventions and then that will be converted to bbc.
 *
 * @param string $text
 * @param bool $html
 *
 * @return mixed|null|string|string[]
 * @uses Html2BBC.class.php for the html to bbc conversion
 * @uses markdown.php for text to html conversions
 * @package Maillist
 * @throws \Exception
 */
function pbe_email_to_bbc($text, $html)
{
	// Define some things that need to be converted/modified, outside normal html or markup
	$tags = array(
		'~\*\*\s?(.*?)\*\*~is' => '**$1**',
		'~<\*>~i' => '&lt;*&gt;',
		'~-{20,}~' => '<hr>',
		'~#([0-9a-fA-F]{4,6}\b)~' => '&#35;$1',
	);

	// We are starting with HTML, our goal is to convert the best parts of it to BBC,
	if ($html)
	{
		// upfront pre-process $tags, mostly for the email template strings
		$text = preg_replace(array_keys($tags), array_values($tags), $text);

		// Run the parsers on the html
		$text = pbe_run_parsers($text);

		$bbc_converter = new Html2BBC($text);
		$bbc_converter->skip_tags(array('font', 'span'));
		$bbc_converter->skip_styles(array('font-family', 'font-size', 'color'));
		$text = $bbc_converter->get_bbc();
	}
	// Starting with plain text, possibly even markdown style ;)
	else
	{
		// Run the parser to try and remove common mail clients "reply to" stuff
		$text = pbe_run_parsers($text);

		// Set a gmail flag for special quote processing since its quotes are strange
		$gmail = (bool) preg_match('~<div class="gmail_quote">~i', $text);

		// Attempt to fix textual ('>') quotes so we also fix wrapping issues first!
		$text = pbe_fix_email_quotes($text, ($html && !$gmail));
		$text = str_replace(array('[quote]', '[/quote]'), array('&gt;blockquote>', '&gt;/blockquote>'), $text);

		// Convert this (markup) text to html
		$text = preg_replace(array_keys($tags), array_values($tags), $text);
		require_once(EXTDIR . '/markdown/markdown.php');
		$text = Markdown($text);
		$text = str_replace(array('&gt;blockquote>', '&gt;/blockquote>'), array('<blockquote>', '</blockquote>'), $text);

		// Convert any resulting HTML created by markup style text in the email to BBC
		$bbc_converter = new Html2BBC($text, false);
		$text = $bbc_converter->get_bbc();
	}

	// Some tags often end up as just empty tags - remove those.
	$emptytags = array(
		'~\[[bisu]\]\s*\[/[bisu]\]~' => '',
		'~\[quote\]\s*\[/quote\]~' => '',
		'~(\n){3,}~si' => "\n\n",
	);

	return preg_replace(array_keys($emptytags), array_values($emptytags), $text);
}

/**
 * Runs the ACP email parsers
 *     - returns cut email or original if the cut would result in a blank message
 *
 * @param string $text
 * @return string
 * @throws \Exception
 */
function pbe_run_parsers($text)
{
	// Run our parsers, as defined in the ACP,  to remove the original "replied to" message
	$text_save = $text;
	$result = pbe_parse_email_message($text);

	// If we have no message left after running the parser, then they may have replied
	// below and/or inside the original message. People like this should not be allowed
	// to use the net, or be forced to read their own messed up emails
	if (empty($result) || (trim(strip_tags(pbe_filter_email_message($text))) === ''))
	{
		$text = $text_save;
	}

	return $text;
}

/**
 * Prepares the email body so that it looks like a forum post
 *
 * What it does:
 *
 * - Removes extra content as defined in the ACP filters
 * - Fixes quotes and quote levels
 * - Re-flows (unfolds) an email using the EmailFormat.class
 * - Attempts to remove any exposed email address
 *
 * @param string $body
 * @param string $real_name
 * @param string $charset character set of the text
 *
 * @return mixed|null|string|string[]
 * @throws \Exception
 * @package Maillist
 *
 * @uses EmailFormat.class.php
 */
function pbe_fix_email_body($body, $real_name = '', $charset = 'UTF-8')
{
	global $txt;

	// Remove the \r's now so its done
	$body = trim(str_replace("\r", '', $body));

	// Remove any control characters
	$body = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $body);

	// Remove the riff-raff as defined by the ACP filters
	$body = pbe_filter_email_message($body);

	// Any old school email john smith wrote: etc style quotes that we need to update
	$body = pbe_fix_client_quotes($body);

	// Attempt to remove any exposed email addresses that are in the reply
	$body = preg_replace('~>' . $txt['to'] . '(.*)@(.*?)(?:\n|\[br\])~i', '', $body);
	$body = preg_replace('~\b\s?[a-z0-9._%+-]+@[a-zZ0-9.-]+\.[a-z]{2,4}\b.?' . $txt['email_wrote'] . ':\s?~i', '', $body);
	$body = preg_replace('~<(.*?)>(.*@.*?)(?:\n|\[br\])~', '$1' . "\n", $body);
	$body = preg_replace('~' . $txt['email_quoting'] . ' (.*) (?:<|&lt;|\[email\]).*?@.*?(?:>|&gt;|\[/email\]):~i', '', $body);

	// Remove multiple sequential blank lines, again
	$body = preg_replace('~(\n\s?){3,}~si', "\n\n", $body);

	// Check for blank quotes
	$body = preg_replace('~(\[quote\s?([a-zA-Z0-9"=]*)?\]\s*(\[br\]\s*)?\[/quote\])~s', '', $body);

	// Reflow and Cleanup this message to something that looks normal-er
	$formatter = new EmailFormat();

	return $formatter->reflow($body, $real_name, $charset);
}

/**
 * Replaces a messages >'s with BBC [quote] [/quote] blocks
 *
 * - Uses quote depth function
 * - Works with nested quotes of many forms >, > >, >>, >asd
 * - Bypassed for gmail as it only block quotes the outer layer and then plain
 * text > quotes the inner which is confusing to all
 *
 * @param string $body
 * @param bool $html
 *
 * @return mixed|string
 * @package Maillist
 *
 */
function pbe_fix_email_quotes($body, $html)
{
	// Coming from HTML then remove lines that start with > and are inside [quote] ... [/quote] blocks
	if ($html)
	{
		$quotes = array();
		if (preg_match_all('~\[quote\](.*)\[\/quote\]~sU', $body, $quotes, PREG_SET_ORDER))
		{
			foreach ($quotes as $quote)
			{
				$quotenew = preg_replace('~^.?> (.*)$~im', '$1', $quote[1] . "\n");
				$body = str_replace($quote[0], '[quote]' . $quotenew . '[/quote]', $body);
			}
		}
	}

	// Create a line by line array broken on the newlines
	$body_array = explode("\n", $body);
	$original = $body_array;

	// Init
	$current_quote = 0;
	$quote_done = '';

	// Go line by line and add the quote blocks where needed, fixing where needed
	for ($i = 0, $num = count($body_array); $i < $num; $i++)
	{
		$body_array[$i] = trim($body_array[$i]);

		// Get the quote "depth" level for this line
		$level = pbe_email_quote_depth($body_array[$i]);

		// No quote marker on this line but we we are in a quote
		if ($level === 0 && $current_quote > 0)
		{
			// Make sure we don't have an email wrap issue
			$level_prev = pbe_email_quote_depth($original[$i - 1], false);
			$level_next = pbe_email_quote_depth($original[$i + 1], false);

			// A line between two = quote or descending quote levels,
			// probably an email break so join (wrap) it back up and continue
			if (($level_prev !== 0) && ($level_prev >= $level_next && $level_next !== 0))
			{
				$body_array[$i - 1] .= ' ' . $body_array[$i];
				unset($body_array[$i]);
				continue;
			}
		}

		// No quote or in the same quote just continue
		if ($level == $current_quote)
		{
			continue;
		}

		// Deeper than we were so add a quote
		if ($level > $current_quote)
		{
			$qin_temp = '';
			while ($level > $current_quote)
			{
				$qin_temp .= '[quote]' . "\n";
				$current_quote++;
			}
			$body_array[$i] = $qin_temp . $body_array[$i];
		}

		// Less deep so back out
		if ($level < $current_quote)
		{
			$qout_temp = '';
			while ($level < $current_quote)
			{
				$qout_temp .= '[/quote]' . "\n";
				$current_quote--;
			}
			$body_array[$i] = $qout_temp . $body_array[$i];
		}

		// That's all I have to say about that
		if ($level === 0 && $current_quote !== 0)
		{
			$quote_done = '';
			while ($current_quote)
			{
				$quote_done .= '[/quote]' . "\n";
				$current_quote--;
			}
			$body_array[$i] = $quote_done . $body_array[$i];
		}
	}

	// No more lines, lets just make sure we did not leave ourselves any open quotes
	while (!empty($current_quote))
	{
		$quote_done .= '[/quote]' . "\n";
		$current_quote--;
	}
	$body_array[$i] = $quote_done;

	// Join the array back together while dropping null index's
	return implode("\n", array_values($body_array));
}

/**
 * Looks for text quotes in the form of > and returns the current level for the line
 *
 * - If update is true (default), will strip the >'s and return the numeric level found
 * - Called by pbe_fix_email_quotes
 *
 * @param string $string
 * @param bool $update
 *
 * @return int
 * @package Maillist
 *
 */
function pbe_email_quote_depth(&$string, $update = true)
{
	// Get the quote "depth" level for this line
	$level = 0;
	$check = true;
	$string_save = $string;

	while ($check)
	{
		// We have a quote marker, increase our depth and strip the line of that quote marker
		if ((substr($string, 0, 2) === '> ') || ($string === '>'))
		{
			$level++;
			$string = substr($string, 2);
		}
		// Maybe a poorly nested quote, with no spaces between the >'s or the > and the data with no space
		elseif ((substr($string, 0, 2) === '>>') || (preg_match('~^>[a-z0-9<-]+~Uis', $string) == 1))
		{
			$level++;
			$string = substr($string, 1);
		}
		// All done getting the depth
		else
		{
			$check = false;
		}
	}

	if (!$update)
	{
		$string = $string_save;
	}

	return $level;
}

/**
 * Splits a message at a given string, returning only the upper portion
 *
 * - Intended to split off the 'replied to' portion that often follows the reply
 * - Uses parsers as defined in the ACP to do its searching
 * - Stops after the first successful hit occurs
 * - Goes in the order defined in the table
 *
 * @param string $body
 * @return bool on find
 * @throws \Exception
 * @package Maillist
 */
function pbe_parse_email_message(&$body)
{
	$db = database();

	// Load up the parsers from the database
	$expressions = array();
	$db->fetchQuery('
		SELECT
			filter_from, filter_type
		FROM {db_prefix}postby_emails_filters
		WHERE filter_style = {string:filter_style}
		ORDER BY filter_order ASC',
		array(
			'filter_style' => 'parser'
		)
	)->fetch_callback(
		function ($row) use (&$expressions) {
			// Build an array of valid expressions
			{
				$expressions[] = array(
					'type' => $row['filter_type'] === 'regex' ? 'regex' : 'string',
					'parser' => $row['filter_from']);
			}
		}
	);

	// Look for the markers, **stop** after the first successful one, good hunting!
	$match = false;
	foreach ($expressions as $expression)
	{
		if ($expression['type'] === 'regex')
		{
			$split = preg_split($expression['parser'], $body);
		}
		else
		{
			$split = explode($expression['parser'], $body, 2);
		}

		// If an expression was matched our fine work is done
		if (!empty($split[1]))
		{
			// If we had a hit then we clip off the mail and return whats above the split
			$match = true;
			$body = $split[0];
			break;
		}
	}

	return $match;
}

/**
 * Searches for extraneous text and removes/replaces it
 *
 * - Uses filters as defined in the ACP to do the search / replace
 * - Will apply regex filters first, then string match filters
 * - Apply all filters to a message
 *
 * @param string $text
 *
 * @return mixed|null|string|string[]
 * @throws \Exception
 * @package Maillist
 *
 */
function pbe_filter_email_message($text)
{
	$db = database();

	// load up the text filters from the database, regex first and ordered by the filter order ...
	$db->fetchQuery('
		SELECT
			filter_from, filter_to, filter_type
		FROM {db_prefix}postby_emails_filters
		WHERE filter_style = {string:filter_style}
		ORDER BY filter_type ASC, filter_order ASC',
		array(
			'filter_style' => 'filter'
		)
	)->fetch_callback(
		function ($row) use (&$text) {
			if ($row['filter_type'] === 'regex')
			{
				// Newline madness
				if (!empty($row['filter_to']))
				{
					$row['filter_to'] = str_replace('\n', "\n", $row['filter_to']);
				}

				// Test the regex and if good use, else skip, don't want a bad regex to empty the message!
				$temp = preg_replace($row['filter_from'], $row['filter_to'], $text);
				if ($temp !== null)
				{
					$text = $temp;
				}
			}
			else
			{
				$text = str_replace($row['filter_from'], $row['filter_to'], $text);
			}
		}
	);

	return $text;
}

/**
 * Finds Re: Subject: FW: FWD or [$sitename] in the subject and strips it
 *
 * - Recursively calls itself till no more tags are found
 *
 * @param string $text
 * @param bool $check if true will return if there tags were found
 *
 * @return bool|string
 * @package Maillist
 *
 */
function pbe_clean_email_subject($text, $check = false)
{
	global $txt, $modSettings, $mbname;

	$sitename = !empty($modSettings['maillist_sitename']) ? $modSettings['maillist_sitename'] : $mbname;

	// Find Re: Subject: FW: FWD or [$sitename] in the subject and strip it
	$re = strpos(strtoupper($text), $txt['RE:']);
	if ($re !== false)
	{
		$text = substr($text, 0, $re) . substr($text, $re + strlen($txt['RE:']), strlen($text));
	}

	$su = strpos(strtoupper($text), $txt['SUBJECT:']);
	if ($su !== false)
	{
		$text = substr($text, 0, $su) . substr($text, $su + strlen($txt['SUBJECT:']), strlen($text));
	}

	$fw = strpos(strtoupper($text), $txt['FW:']);
	if ($fw !== false)
	{
		$text = substr($text, 0, $fw) . substr($text, $fw + strlen($txt['FW:']), strlen($text));
	}

	$gr = strpos($text, '[' . $sitename . ']');
	if ($gr !== false)
	{
		$text = substr($text, 0, $gr) . substr($text, $gr + strlen($sitename) + 2, strlen($text));
	}

	$fwd = strpos(strtoupper($text), $txt['FWD:']);
	if ($fwd !== false)
	{
		$text = substr($text, 0, $fwd) . substr($text, $fwd + strlen($txt['FWD:']), strlen($text));
	}

	// if not done then call ourselves again, we like the sound of our name
	if (strpos(strtoupper($text), $txt['RE:']) || strpos(strtoupper($text), $txt['FW:']) || strpos(strtoupper($text), $txt['FWD:']) || strpos($text, '[' . $sitename . ']'))
	{
		$text = pbe_clean_email_subject($text);
	}

	// clean or not?
	if ($check)
	{
		return ($re === false && $su === false && $gr === false && $fw === false && $fwd === false);
	}
	else
	{
		return trim($text);
	}
}

/**
 * Used if the original email could not be removed from the message (top of post)
 *
 * - Tries to quote the original message instead by using a loose original message search
 * - Looks for email client original message tags and converts them to bbc quotes
 *
 * @param string $body
 *
 * @return null|string|string[]
 * @package Maillist
 *
 */
function pbe_fix_client_quotes($body)
{
	global $txt;

	// Define some common quote markers (from the original messages)
	// @todo ACP for this? ... not sure
	$regex = array();

	// On mon, jan 12, 2004 at 10:10 AM, John Smith wrote: [quote]
	$regex[] = '~(?:' . $txt['email_on'] . ')?\w{3}, \w{3} \d{1,2},\s?\d{4} ' . $txt['email_at'] . ' \d{1,2}:\d{1,2} [AP]M,(.*)?' . $txt['email_wrote'] . ':\s?\s{1,4}\[quote\]~i';
	// [quote] on: mon jan 12, 2004 John Smith wrote:
	$regex[] = '~\[quote\]\s?' . $txt['email_on'] . ': \w{3} \w{3} \d{1,2}, \d{4} (.*)?' . $txt['email_wrote'] . ':\s~i';
	// on jan 12, 2004 at 10:10 PM, John Smith wrote:   [quote]
	$regex[] = '~' . $txt['email_on'] . ' \w{3} \d{1,2}, \d{4}, ' . $txt['email_at'] . ' \d{1,2}:\d{1,2} [AP]M,(.*)?' . $txt['email_wrote'] . ':\s{1,4}\[quote\]~i';
	// on jan 12, 2004 at 10:10, John Smith wrote   [quote]
	$regex[] = '~' . $txt['email_on'] . ' \w{3} \d{1,2}, \d{4}, ' . $txt['email_at'] . ' \d{1,2}:\d{1,2}, (.*)?' . $txt['email_wrote'] . ':\s{1,4}\[quote\]~i';
	// quoting: John Smith on stuffz at 10:10:23 AM
	$regex[] = '~' . $txt['email_quotefrom'] . ': (.*) ' . $txt['email_on'] . ' .* ' . $txt['email_at'] . ' \d{1,2}:\d{1,2}:\d{1,2} [AP]M~';
	// quoting John Smith <johnsmith@tardis.com>
	$regex[] = '~' . $txt['email_quoting'] . ' (.*) (?:<|&lt;|\[email\]).*?@.*?(?:>|&gt;|\[/email\]):~i';
	// --- in some group name "John Smith" <johnsmith@tardis.com> wrote:
	$regex[] = '~---\s.*?"(.*)"\s+' . $txt['email_wrote'] . ':\s(\[quote\])?~i';
	// --- in some@group.name John Smith wrote
	$regex[] = '~---\s.*?\b[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,6}\b,\s(.*?)\s' . $txt['email_wrote'] . ':?~iu';
	// --- In some@g..., "someone"  wrote:
	$regex[] = '~---\s.*?\b[A-Z0-9._%+-]+@[A-Z0-9][.]{3}, [A-Z0-9._%+\-"]+\b(.*?)\s' . $txt['email_wrote'] . ':?~iu';
	// --- In [email]something[/email] "someone" wrote:
	$regex[] = '~---\s.*?\[email=.*?/email\],?\s"?(.*?)"?\s' . $txt['email_wrote'] . ':?~iu';

	// For each one see if we can do a nice [quote author=john smith]
	foreach ($regex as $reg)
	{
		if (preg_match_all($reg, $body, $matches, PREG_SET_ORDER))
		{
			foreach ($matches as $quote)
			{
				$quote[1] = preg_replace('~\[email\].*\[\/email\]~', '', $quote[1]);
				$body = pbe_str_replace_once($quote[0], "\n" . '[quote author=' . trim($quote[1]) . "]\n", $body);

				$quote[1] = preg_quote($quote[1], '~');

				// Look for [quote author=][/quote][quote] issues
				$body = preg_replace('~\[quote author=' . trim($quote[1]) . '\] ?(?:\n|\[br\] ?){2,4} ?\[\/quote\] ?\[quote\]~u', '[quote author=' . trim($quote[1]) . "]\n", $body, 1);

				// And [quote author=][quote] newlines [/quote] issues
				$body = preg_replace('~\[quote author=' . trim($quote[1]) . '\] ?(?:\n|\[br\] ?){2,4}\[quote\]~u', '[quote author=' . trim($quote[1]) . "]\n", $body);
			}
		}
	}

	return $body;
}

/**
 * Does a single replacement of the first found string in the haystack
 *
 * @param string $needle
 * @param string $replace
 * @param string $haystack
 * @return string
 * @package Maillist
 */
function pbe_str_replace_once($needle, $replace, $haystack)
{
	// Looks for the first occurrence of $needle in $haystack and replaces it with $replace
	$pos = strpos($haystack, $needle);
	if ($pos === false)
	{
		return $haystack;
	}

	return substr_replace($haystack, $replace, $pos, strlen($needle));
}

/**
 * Does a moderation check on a given user (global)
 *
 * - Removes permissions of PBE concern that a given moderated level denies
 *
 * @param mixed[] $pbe array of user values
 * @package Maillist
 */
function pbe_check_moderation(&$pbe)
{
	global $modSettings;

	if (empty($modSettings['postmod_active']))
	{
		return;
	}

	// Have they been muted for being naughty?
	if (!empty($modSettings['warning_mute']) && $modSettings['warning_mute'] <= $pbe['user_info']['warning'])
	{
		// Remove anything that would allow them to do anything via PBE
		$denied_permissions = array(
			'pm_send', 'postby_email',
			'admin_forum', 'moderate_forum',
			'post_new', 'post_reply_own', 'post_reply_any',
			'post_attachment', 'post_unapproved_attachments',
			'post_unapproved_topics', 'post_unapproved_replies_own', 'post_unapproved_replies_any',
		);
		$pbe['user_info']['permissions'] = array_diff($pbe['user_info']['permissions'], $denied_permissions);
	}
	elseif (!empty($modSettings['warning_moderate']) && $modSettings['warning_moderate'] <= $pbe['user_info']['warning'])
	{
		// Work out what permissions should change if they are just being moderated
		$permission_change = array(
			'post_new' => 'post_unapproved_topics',
			'post_reply_own' => 'post_unapproved_replies_own',
			'post_reply_any' => 'post_unapproved_replies_any',
			'post_attachment' => 'post_unapproved_attachments',
		);

		foreach ($permission_change as $old => $new)
		{
			if (!in_array($old, $pbe['user_info']['permissions']))
			{
				unset($permission_change[$old]);
			}
			else
			{
				$pbe['user_info']['permissions'][] = $new;
			}
		}

		$pbe['user_info']['permissions'] = array_diff($pbe['user_info']['permissions'], array_keys($permission_change));
	}

	return;
}

/**
 * Creates a failed email entry in the postby_emails_error table
 *
 * - Attempts an auto-correct for common errors so the admin / moderator
 * - can choose to approve the email with the corrections
 *
 * @param string $error
 * @param \ElkArte\EmailParse $email_message
 *
 * @return bool
 * @throws \ElkArte\Exceptions\Exception
 * @package Maillist
 *
 */
function pbe_emailError($error, $email_message)
{
	global $txt;

	$db = database();

	ThemeLoader::loadLanguageFile('EmailTemplates');

	// Some extra items we will need to remove from the message subject
	$pm_subject_leader = str_replace('{SUBJECT}', '', $txt['new_pm_subject']);

	// Clean the subject like we don't know where it has been
	$subject = trim(str_replace($pm_subject_leader, '', $email_message->subject));
	$subject = pbe_clean_email_subject($subject);
	$subject = ($subject === '' ? $txt['no_subject'] : $subject);

	// Start off with what we know about the security key, even if its nothing
	$message_key = (string) $email_message->message_key;
	$message_type = (string) $email_message->message_type;
	$message_id = (int) $email_message->message_id;
	$board_id = -1;

	// First up is the old, wrong email address, lets see who this should have come from if its not a new topic request
	if ($error === 'error_not_find_member' && $email_message->message_type !== 'x')
	{
		$key_owner = query_key_owner($email_message);
		if (!empty($key_owner))
		{
			// Valid key so show who should have sent this key in? email aggravaters :P often mess this up
			$email_message->email['from'] = $email_message->email['from'] . ' => ' . $key_owner;

			// Since we have a valid key set those details as well
			$message_key = $email_message->message_key;
			$message_type = $email_message->message_type;
			$message_id = $email_message->message_id;
		}
	}

	// A valid key but it was not sent to this user ... but we did get the email from a valid site user
	if ($error === 'error_key_sender_match')
	{
		$key_owner = query_key_owner($email_message);
		if (!empty($key_owner))
		{
			// Valid key so show who should have sent this key in
			$email_message->email['from'] = $key_owner . ' => ' . $email_message->email['from'];

			// Since we have a valid key set those details as well
			$message_key = $email_message->message_key;
			$message_type = $email_message->message_type;
			$message_id = $email_message->message_id;
		}
	}

	// No key? We should at a minimum have who its from and a subject, so use that
	if ($email_message->message_type !== 'x' && (empty($message_key) || $error === 'error_pm_not_found'))
	{
		// We don't have the message type (since we don't have a key)
		// Attempt to see if it might be a PM so we handle it correctly
		if (empty($message_type) && (strpos($email_message->subject, $pm_subject_leader) !== false))
		{
			$message_type = 'p';
		}

		// Find all keys sent to this user, sorted by date
		$user_keys = query_user_keys($email_message->email['from']);

		// While we have keys to look at see if we can match up this lost message on subjects
		foreach ($user_keys as $user_key)
		{
			$key = $user_key['message_key'];
			$type = $user_key['message_type'];
			$message = $user_key['message_id'];

			// If we know/suspect its a "m,t or p" then use that to avoid a match on a wrong type, that would be bad ;)
			if ((!empty($message_type) && $message_type === $type) || (empty($message_type) && $type !== 'p'))
			{
				// lets look up this message/topic/pm and see if the subjects match ... if they do then tada!
				if (query_load_subject($message, $type, $email_message->email['from']) === $subject)
				{
					// This email has a subject that matches the subject of a message that was sent to them
					$message_key = $key;
					$message_id = $message;
					$message_type = $type;
					break;
				}
			}
		}
	}

	// Maybe we have enough to find the board id where this was going
	if (!empty($message_id) && $message_type !== 'p')
	{
		$board_id = query_load_board($message_id);
	}

	// Log the error so the moderators can take a look, helps keep them sharp
	$id = isset($_REQUEST['item']) ? (int) $_REQUEST['item'] : 0;
	$db->insert(!empty($id) ? 'replace' : 'ignore',
		'{db_prefix}postby_emails_error',
		array(
			'id_email' => 'int', 'error' => 'string', 'message_key' => 'string',
			'subject' => 'string', 'message_id' => 'int', 'id_board' => 'int',
			'email_from' => 'string', 'message_type' => 'string', 'message' => 'string'),
		array(
			$id, $error, $message_key,
			$subject, $message_id, $board_id,
			$email_message->email['from'], $message_type, $email_message->raw_message),
		array('id_email')
	);

	// Flush the moderator error number cache, if we are here it likely just changed.
	Cache::instance()->remove('num_menu_errors');

	// If not running from the cli, then go back to the form
	if (isset($_POST['item']))
	{
		// Back to the form we go
		$_SESSION['email_error'] = $txt[$error];
		redirectexit('action=admin;area=maillist');
	}

	return false;
}

/**
 * Writes email attachments as temp names in the proper attachment directory
 *
 * What it does:
 *
 * - populates TemporaryAttachmentsList with the email attachments
 * - does all the checks to validate them
 * - skips ones flagged with errors
 * - adds valid ones to attachmentOptions
 * - calls createAttachment to store them
 *
 * @param mixed[] $pbe
 * @param \ElkArte\EmailParse $email_message
 *
 * @return array
 * @throws \ElkArte\Exceptions\Exception
 * @package Maillist
 *
 */
function pbe_email_attachments($pbe, $email_message)
{
	// Trying to attach a file with this post ....
	global $modSettings, $context, $txt;

	// Init
	$attachment_count = 0;
	$attachIDs = array();
	$tmp_attachments = new TemporaryAttachmentsList();

	// Make sure we're know where to upload
	$attachmentDirectory = new AttachmentsDirectory($modSettings, database());
	try
	{
		$attachmentDirectory->automanageCheckDirectory(isset($_REQUEST['action']) && $_REQUEST['action'] == 'admin');

		$attach_current_dir = $attachmentDirectory->getCurrent();

		if (!is_dir($attach_current_dir))
		{
			$tmp_attachments->setSystemError('attach_folder_warning');
			\ElkArte\Errors\Errors::instance()->log_error(sprintf($txt['attach_folder_admin_warning'], $attach_current_dir), 'critical');
		}
	}
	catch (\Exception $e)
	{
		// If the attachments folder is not there: error.
		$tmp_attachments->setSystemError($e->getMessage());
	}

	// For attachmentChecks function
	require_once(SUBSDIR . '/Attachments.subs.php');
	$context['attachments'] = array('quantity' => 0, 'total_size' => 0);

	// Create the file(s) with a temp name so we can validate its contents/type
	foreach ($email_message->attachments as $name => $attachment)
	{
		if ($tmp_attachments->hasSystemError())
		{
			continue;
		}

		$attachID = $tmp_attachments->getTplName($pbe['profile']['id_member'], bin2hex(random_bytes(16)));

		// Write the contents to an actual file
		$destName = $attach_current_dir . '/' . $attachID;
		if (file_put_contents($destName, $attachment) !== false)
		{
			@chmod($destName, 0644);

			$temp_file = new TemporaryAttachment([
				'name' => basename($name),
				'tmp_name' => $destName,
				'attachid' => $attachID,
				'user_id' => $pbe['profile']['id_member'],
				'size' => strlen($attachment),
				'type' => null,
				'id_folder' => $attachmentDirectory->currentDirectoryId(),
			]);

			// Make sure its valid
			$temp_file->doChecks($attachmentDirectory);
			$tmp_attachments->addAttachment($temp_file);
			$attachment_count++;
		}
	}

	$prefix = $tmp_attachments->getTplName($pbe['profile']['id_member'], '');
	// Space for improvement: move the removeAll to the end before ->unset
	if ($tmp_attachments->hasSystemError())
	{
		$tmp_attachments->removeAll();
	}
	else
	{
		// Get the results from attachmentChecks and see if its suitable for posting
		foreach ($tmp_attachments as $attachID => $attachment)
		{
			// If there were any errors we just skip that file
			if (strpos($attachID, $prefix) === false || $attachment->hasErrors())
			{
				$attachment->remove(false);
				continue;
			}

			// Load the attachmentOptions array with the data needed to create an attachment
			$attachmentOptions = array(
				'post' => 0,
				'poster' => $pbe['profile']['id_member'],
				'name' => $attachment['name'],
				'tmp_name' => $attachment['tmp_name'],
				'size' => (int) $attachment['size'],
				'mime_type' => (string) $attachment['type'],
				'id_folder' => (int) $attachment['id_folder'],
				'approved' => !$modSettings['postmod_active'] || in_array('post_unapproved_attachments', $pbe['user_info']['permissions']),
				'errors' => array(),
			);

			// Make it available to the forum/post
			if (createAttachment($attachmentOptions))
			{
				$attachIDs[] = $attachmentOptions['id'];
				if (!empty($attachmentOptions['thumb']))
				{
					$attachIDs[] = $attachmentOptions['thumb'];
				}
			}
			// We had a problem so simply remove it
			else
			{
				$tmp_attachments->removeById($attachID, false);
			}
		}
	}
	$tmp_attachments->unset();

	return $attachIDs;
}

/**
 * Used when a email attempts to start a new topic
 *
 * - Load the board id that a given email address is assigned to in the ACP
 * - Returns the board number in which the new topic must go
 *
 * @param \ElkArte\EmailParse $email_address
 *
 * @return int
 * @package Maillist
 *
 */
function pbe_find_board_number($email_address)
{
	global $modSettings;

	$valid_address = array();
	$board_number = 0;

	// Load our valid email ids and the corresponding board ids
	$data = (!empty($modSettings['maillist_receiving_address'])) ? Util::unserialize($modSettings['maillist_receiving_address']) : array();
	foreach ($data as $key => $addr)
	{
		$valid_address[$addr[0]] = $addr[1];
	}

	// Who was this message sent to, may have been sent to multiple addresses
	// so we must check each one to see if we have a valid entry
	foreach ($email_address->email['to'] as $to_email)
	{
		if (isset($valid_address[$to_email]))
		{
			$board_number = (int) $valid_address[$to_email];
			continue;
		}
	}

	return $board_number;
}

/**
 * Converts a post/pm to text (markdown) for sending in an email
 *
 * - censors everything it will send
 * - pre-converts select bbc tags to html so they can be markdowned properly
 * - uses parse-bbc to convert remaining bbc to html
 * - uses html2markdown to convert html to markdown text suitable for email
 * - if someone wants to write a direct bbc->markdown conversion tool, I'm listening!
 *
 * @param string $message
 * @param string $subject
 * @param string $signature
 * @package Maillist
 */
function pbe_prepare_text(&$message, &$subject = '', &$signature = '')
{
	global $context;

	ThemeLoader::loadLanguageFile('Maillist');

	// Server?
	detectServer();

	// Clean it up.
	$message = censor($message);
	$signature = censor($signature);
	$subject = censor(un_htmlspecialchars($subject));

	// Convert bbc [quotes] before we go to parsebbc so they are easier to plain-textify later
	$message = preg_replace_callback('~(\[quote)\s?author=(.*)\s?link=(.*)\s?date=(\d{10})(\])~sU', 'quote_callback', $message);
	$message = preg_replace_callback('~(\[quote)\s?author=(.*)\s?date=(\d{10})\s?link=(.*)(\])~sU', 'quote_callback_2', $message);
	$message = preg_replace('~(\[quote\s?\])~sU', "\n" . '<blockquote>', $message);
	$message = str_replace('[/quote]', "</blockquote>\n\n", $message);

	// Prevent img tags from getting linked
	$message = preg_replace('~\[img\](.*?)\[/img\]~is', '`&lt;img src="\\1">', $message);

	// Leave code tags as code tags for the conversion
	$message = preg_replace('~\[code(.*?)\](.*?)\[/code\]~is', '`&lt;code\\1>\\2`&lt;/code>', $message);

	// Allow addons to account for their own unique bbc additions e.g. gallery's etc.
	call_integration_hook('integrate_mailist_pre_parsebbc', array(&$message));

	// Convert the remaining bbc to html
	$bbc_wrapper = ParserWrapper::instance();
	$message = $bbc_wrapper->parseMessage($message, false);

	// Change list style to something standard to make text conversion easier
	$message = preg_replace('~<ul class=\"bbc_list\" style=\"list-style-type: decimal;\">(.*?)</ul>~si', '<ol>\\1</ol>', $message);

	// Do we have any tables? if so we add in th's based on the number of cols.
	$table_content = array();
	if (preg_match_all('~<table class="bbc_table">(.*?)</tr>.*?</table>~si', $message, $table_content, PREG_SET_ORDER))
	{
		// The answer is yes ... work on each one
		foreach ($table_content as $table_temp)
		{
			$cols = substr_count($table_temp[1], '<td>');
			$table_header = '';

			// Build the th line for this table
			for ($i = 1; $i <= $cols; $i++)
			{
				$table_header .= '<th>- ' . $i . ' -</th>';
			}

			// Insert it in to the table tag
			$table_header = '<tr>' . $table_header . '</tr>';
			$new_table = str_replace('<table class="bbc_table">', '<br /><table>' . $table_header, $table_temp[0]);

			// Replace the old table with the new th enabled one
			$message = str_replace($table_temp[0], $new_table, $message);
		}
	}

	// Allow addons to account for their own unique bbc additions e.g. gallery's etc.
	call_integration_hook('integrate_mailist_pre_markdown', array(&$message));

	// Convert the protected (hidden) entities back for the final conversion
	$message = strtr($message, array(
			'&#91;' => '[',
			'&#93;' => ']',
			'`&lt;' => '<',
		)
	);

	// Convert this to text (markdown)
	$mark_down = new Html2Md($message);
	$message = $mark_down->get_markdown();

	// Finally the sig, its goes as just plain text
	if ($signature !== '')
	{
		call_integration_hook('integrate_mailist_pre_sig_parsebbc', array(&$signature));

		$signature = $bbc_wrapper->parseSignature($signature, false);
		$signature = trim(un_htmlspecialchars(strip_tags(strtr($signature, array('</tr>' => "   \n", '<br />' => "   \n", '</div>' => "\n", '</li>' => "   \n", '&#91;' => '[', '&#93;' => ']')))));
	}

	return;
}

/**
 * When a DSN (bounce) is received and the feature is enabled, update the settings
 * For the user in question to disable Board and Post notifications. Do not clear
 * Notification subscriptions.
 *
 * When finished, fire off a site notification informing the user of the action and reason
 *
 * @param \ElkArte\EmailParse $email_message
 * @throws \ElkArte\Exceptions\Exception
 * @package Maillist
 */
function pbe_disable_user_notify($email_message)
{
	global $modSettings;
	$db = database();

	$email = $email_message->get_failed_dest();

	$request = $db->query('', '
		SELECT
			id_member
		FROM {db_prefix}members
		WHERE email_address = {string:email}
		LIMIT 1',
		array(
			'email' => $email
		)
	);

	if ($request->num_rows() !== 0)
	{
		list ($id_member) = $request->fetch_row();
		$request->free_result();

		// Once we have the member's ID, we can turn off board/topic notifications
		// by setting notify_regularity->99 ("Never")
		$db->query('', '
			UPDATE {db_prefix}members
			SET
				notify_regularity = 99
			WHERE id_member = {int:id_member}',
			array(
				'id_member' => $id_member
			)
		);

		// Now that other notifications have been added, we need to turn off email for those, too.
		$db->query('', '
			DELETE FROM {db_prefix}notifications_pref
			WHERE id_member = {int:id_member}
				AND notification_type = {string:email}',
			array(
				'id_member' => $id_member,
				'email' => 'email'
			)
		);

		//Add a "mention" of email notification being disabled
		if (!empty($modSettings['mentions_enabled']))
		{
			$notifier = Notifications::instance();
			$notifier->add(new NotificationsTask(
				'mailfail',
				0,
				$id_member,
				array('id_members' => array($id_member))
			));
			$notifier->send();
		}
	}
}

/**
 * Replace full bbc quote tags with an html blockquote version
 *
 * - Callback for pbe_prepare_text
 * - Only changes the leading [quote], the closing /quote is not changed but
 * handled back in the main function
 *
 * @param string[] $matches array of matches from the regex in the preg_replace
 *
 * @return string
 */
function quote_callback($matches)
{
	global $txt;

	return "\n" . '<blockquote>' . $txt['email_on'] . ': ' . date('D M j, Y', $matches[4]) . ' ' . $matches[2] . ' ' . $txt['email_wrote'] . ': ';
}

/**
 * Replace full bbc quote tags with an html blockquote version
 *
 * - Callback for pbe_prepare_text
 * - Only changes the leading [quote], the closing /quote is not changed but
 * handled back in the main function
 *
 * @param string[] $matches array of matches from the regex in the preg_replace
 *
 * @return string
 */
function quote_callback_2($matches)
{
	global $txt;

	return "\n" . '<blockquote>' . $txt['email_on'] . ': ' . date('D M j, Y', $matches[3]) . ' ' . $matches[2] . ' ' . $txt['email_wrote'] . ': ';
}

/**
 * Loads up the vital user information given an email address
 *
 * - Similar to \ElkArte\MembersList::load, loadPermissions, loadUserSettings, but only loads a
 * subset of that data, enough to validate that a user can make a post to a given board.
 * - Done this way to avoid over-writing user_info etc for those who are running
 * this function (on behalf of the email owner, similar to profile views etc)
 *
 * Sets:
 * - pbe['profile']
 * - pbe['profile']['options']
 * - pbe['user_info']
 * - pbe['user_info']['permissions']
 * - pbe['user_info']['groups']
 *
 * @param string $email
 *
 * @return array|bool
 * @throws \ElkArte\Exceptions\Exception
 * @package Maillist
 *
 */
function query_load_user_info($email)
{
	global $modSettings, $language;

	$db = database();

	if (empty($email))
	{
		return false;
	}

	// Find the user who owns this email address
	$request = $db->query('', '
		SELECT
			id_member
		FROM {db_prefix}members
		WHERE email_address = {string:email}
		AND is_activated = {int:act}
		LIMIT 1',
		array(
			'email' => $email,
			'act' => 1,
		)
	);
	list ($id_member) = $request->fetch_row();
	$request->free_result();

	// No user found ... back we go
	if (empty($id_member))
	{
		return false;
	}

	// Load the users profile information
	$pbe = array();
	if (MembersList::load($id_member, false, 'profile'))
	{
		$pbe['profile'] = MembersList::get($id_member);

		// Load in *some* user_info data just like loadUserSettings would do
		if (empty($pbe['profile']['additional_groups']))
		{
			$pbe['user_info']['groups'] = array(
				$pbe['profile']['id_group'], $pbe['profile']['id_post_group']);
		}
		else
		{
			$pbe['user_info']['groups'] = array_merge(
				array($pbe['profile']['id_group'], $pbe['profile']['id_post_group']),
				explode(',', $pbe['profile']['additional_groups'])
			);
		}

		// Clean up the groups
		foreach ($pbe['user_info']['groups'] as $k => $v)
		{
			$pbe['user_info']['groups'][$k] = (int) $v;
		}
		$pbe['user_info']['groups'] = array_unique($pbe['user_info']['groups']);

		// Load the user's general permissions....
		query_load_permissions('general', $pbe);

		// Set the moderation warning level
		$pbe['user_info']['warning'] = isset($pbe['profile']['warning']) ? $pbe['profile']['warning'] : 0;

		// Work out our query_see_board string for security
		if (in_array(1, $pbe['user_info']['groups']))
		{
			$pbe['user_info']['query_see_board'] = '1=1';
		}
		else
		{
			$pbe['user_info']['query_see_board'] = '((FIND_IN_SET(' . implode(', b.member_groups) != 0 OR FIND_IN_SET(', $pbe['user_info']['groups']) . ', b.member_groups) != 0)' . (!empty($modSettings['deny_boards_access']) ? ' AND (FIND_IN_SET(' . implode(', b.deny_member_groups) = 0 AND FIND_IN_SET(', $pbe['user_info']['groups']) . ', b.deny_member_groups) = 0)' : '') . ')';
		}

		// Set some convenience items
		$pbe['user_info']['is_admin'] = in_array(1, $pbe['user_info']['groups']) ? 1 : 0;
		$pbe['user_info']['id'] = $id_member;
		$pbe['user_info']['username'] = isset($pbe['profile']['member_name']) ? $pbe['profile']['member_name'] : '';
		$pbe['user_info']['name'] = isset($pbe['profile']['real_name']) ? $pbe['profile']['real_name'] : '';
		$pbe['user_info']['email'] = isset($pbe['profile']['email_address']) ? $pbe['profile']['email_address'] : '';
		$pbe['user_info']['language'] = empty($pbe['profile']['lngfile']) || empty($modSettings['userLanguage']) ? $language : $pbe['profile']['lngfile'];
	}

	return !empty($pbe) ? $pbe : false;
}

/**
 * Load the users permissions either general or board specific
 *
 * - Similar to the functions in loadPermissions()
 *
 * @param string $type board to load board permissions, otherwise general permissions
 * @param mixed[] $pbe
 * @param mixed[] $topic_info
 * @throws \Exception
 * @package Maillist
 */
function query_load_permissions($type, &$pbe, $topic_info = array())
{
	global $modSettings;

	$db = database();

	$where_query = ($type === 'board' ? '({array_int:member_groups}) AND id_profile = {int:id_profile}' : '({array_int:member_groups})');

	// Load up the users board or general site permissions.
	$removals = array();
	$pbe['user_info']['permissions'] = array();
	$db->fetchQuery('
		SELECT
			permission, add_deny
		FROM {db_prefix}' . ($type === 'board' ? 'board_permissions' : 'permissions') . '
		WHERE id_group IN ' . $where_query,
		array(
			'member_groups' => $pbe['user_info']['groups'],
			'id_profile' => ($type === 'board') ? $topic_info['id_profile'] : '',
		)
	)->fetch_callback(
		function ($row) use (&$removals, &$pbe) {
			if (empty($row['add_deny']))
			{
				$removals[] = $row['permission'];
			}
			else
			{
				$pbe['user_info']['permissions'][] = $row['permission'];
			}
		}
	);

	// Remove all the permissions they shouldn't have ;)
	if (!empty($modSettings['permission_enable_deny']))
	{
		$pbe['user_info']['permissions'] = array_diff($pbe['user_info']['permissions'], $removals);
	}
}

/**
 * Fetches the senders email wrapper details
 *
 * - Gets the senders signature for inclusion in the email
 * - Gets the senders email address and visibility flag
 *
 * @param string $from
 * @return mixed[]
 * @throws \ElkArte\Exceptions\Exception
 * @package Maillist
 */
function query_sender_wrapper($from)
{
	$db = database();

	// The signature and email visibility details
	$request = $db->query('', '
		SELECT
			hide_email, email_address, signature
		FROM {db_prefix}members
		WHERE id_member  = {int:uid}
			AND is_activated = {int:act}
		LIMIT 1',
		array(
			'uid' => $from,
			'act' => 1,
		)
	);
	$result = $request->fetch_assoc();

	// Clean up the signature line
	if (!empty($result['signature']))
	{
		$bbc_wrapper = ParserWrapper::instance();
		$result['signature'] = trim(un_htmlspecialchars(strip_tags(strtr($bbc_wrapper->parseSignature($result['signature'], false), array('</tr>' => "   \n", '<br />' => "   \n", '</div>' => "\n", '</li>' => "   \n", '&#91;' => '[', '&#93;' => ']')))));
	}

	$request->free_result();

	return $result;
}

/**
 * Reads all the keys that have been sent to a given email id
 *
 * - Returns all keys sent to a user in date order
 *
 * @param string $email email address to lookup
 *
 * @return array
 * @throws \Exception
 * @package Maillist
 *
 */
function query_user_keys($email)
{
	$db = database();

	// Find all keys sent to this email, sorted by date
	return $db->fetchQuery('
		SELECT
			message_key, message_type, message_id
		FROM {db_prefix}postby_emails
		WHERE email_to = {string:email}
		ORDER BY time_sent DESC',
		array(
			'email' => $email,
		)
	)->fetch_all();
}

/**
 * Return the email that a given key was sent to
 *
 * @param \ElkArte\EmailParse $email_message
 * @return string email address the key was sent to
 * @throws \ElkArte\Exceptions\Exception
 * @package Maillist
 */
function query_key_owner($email_message)
{
	$db = database();

	if (!isset($email_message->message_key, $email_message->message_type, $email_message->message_id))
	{
		return false;
	}

	// Check that this is a reply to an "actual" message by finding the key in the sent email table
	$request = $db->query('', '
		SELECT
			email_to
		FROM {db_prefix}postby_emails
		WHERE message_key = {string:key}
			AND message_type = {string:type}
			AND message_id = {string:message}
		LIMIT 1',
		array(
			'key' => $email_message->message_key,
			'type' => $email_message->message_type,
			'message' => $email_message->message_id,
		)
	);
	list ($email_to) = $request->fetch_row();
	$request->free_result();

	return $email_to;
}

/**
 * For a given type, t m or p, query the appropriate table for a given message id
 *
 * - If found returns the message subject
 *
 * @param int $message_id
 * @param string $message_type
 * @param string $email
 *
 * @return bool|string
 * @throws \ElkArte\Exceptions\Exception
 * @package Maillist
 *
 */
function query_load_subject($message_id, $message_type, $email)
{
	$db = database();

	$subject = '';

	// Load up the core topic details,
	if ($message_type === 't')
	{
		$request = $db->query('', '
			SELECT
				t.id_topic, m.subject
			FROM {db_prefix}topics AS t
				INNER JOIN {db_prefix}messages AS m ON (m.id_msg = t.id_first_msg)
			WHERE t.id_topic = {int:id_topic}',
			array(
				'id_topic' => $message_id
			)
		);
	}
	elseif ($message_type === 'm')
	{
		$request = $db->query('', '
			SELECT
				m.id_topic, m.subject
			FROM {db_prefix}messages AS m
				INNER JOIN {db_prefix}topics AS t ON (t.id_topic = m.id_topic)
			WHERE m.id_msg = {int:message_id}',
			array(
				'message_id' => $message_id
			)
		);
	}
	elseif ($message_type === 'p')
	{
		// With PM's ... first get the member id based on the email
		$request = $db->query('', '
			SELECT
				id_member
			FROM {db_prefix}members
			WHERE email_address = {string:email}
				AND is_activated = {int:act}
			LIMIT 1',
			array(
				'email' => $email,
				'act' => 1,
			)
		);

		// Found them, now we find the PM to them with this ID
		if ($request->num_rows() !== 0)
		{
			list ($id_member) = $request->fetch_row();
			$request->free_result();

			// Now find this PM ID and make sure it was sent to this member
			$request = $db->query('', '
				SELECT
					p.subject
				FROM {db_prefix}pm_recipients AS pmr, {db_prefix}personal_messages AS p
				WHERE pmr.id_pm = {int:id_pm}
					AND pmr.id_member = {int:id_member}
					AND p.id_pm = pmr.id_pm',
				array(
					'id_member' => $id_member,
					'id_pm' => $message_id,
				)
			);
		}
	}
	else
	{
		return $subject;
	}

	// If we found the message, topic or PM, return the subject
	if ($request->num_rows() !== 0)
	{
		list ($subject) = $request->fetch_row();
		$subject = pbe_clean_email_subject($subject);
	}
	$request->free_result();

	return $subject;
}

/**
 * Loads the important information for a given topic or pm ID
 *
 * - Returns array with the topic or PM details
 *
 * @param string $message_type
 * @param int $message_id
 * @param mixed[] $pbe
 *
 * @return array|bool
 * @throws \ElkArte\Exceptions\Exception
 * @package Maillist
 *
 */
function query_load_message($message_type, $message_id, $pbe)
{
	$db = database();

	// Load up the topic details
	if ($message_type === 't')
	{
		$request = $db->query('', '
			SELECT
				t.id_topic, t.id_board, t.locked, t.id_member_started, t.id_last_msg,
				m.subject,
				b.count_posts, b.id_profile, b.member_groups, b.id_theme, b.override_theme
			FROM {db_prefix}topics AS t
				INNER JOIN {db_prefix}boards AS b ON (b.id_board = t.id_board)
				INNER JOIN {db_prefix}messages AS m ON (m.id_msg = t.id_first_msg)
			WHERE {raw:query_see_board}
				AND t.id_topic = {int:message_id}',
			array(
				'message_id' => $message_id,
				'query_see_board' => $pbe['user_info']['query_see_board'],
			)
		);
	}
	elseif ($message_type === 'm')
	{
		$request = $db->query('', '
			SELECT
				m.id_topic, m.id_board, m.subject,
				t.locked, t.id_member_started, t.approved, t.id_last_msg,
				b.count_posts, b.id_profile, b.member_groups, b.id_theme, b.override_theme
			FROM {db_prefix}messages AS m
				INNER JOIN {db_prefix}boards AS b ON (b.id_board = m.id_board)
				INNER JOIN {db_prefix}topics AS t ON (t.id_topic = m.id_topic)
			WHERE  {raw:query_see_board}
				AND m.id_msg = {int:message_id}',
			array(
				'message_id' => $message_id,
				'query_see_board' => $pbe['user_info']['query_see_board'],
			)
		);
	}
	elseif ($message_type === 'p')
	{
		// Load up the personal message...
		$request = $db->query('', '
			SELECT
				p.id_pm, p.subject, p.id_member_from, p.id_pm_head
			FROM {db_prefix}pm_recipients AS pm, {db_prefix}personal_messages AS p, {db_prefix}members AS mem
			WHERE pm.id_pm = {int:mess_id}
				AND pm.id_member = {int:id_mem}
				AND p.id_pm = pm.id_pm
				AND mem.id_member = p.id_member_from',
			array(
				'id_mem' => $pbe['profile']['id_member'],
				'mess_id' => $message_id
			)
		);
	}
	$topic_info = array();
	if (isset($request))
	{
		// Found the information, load the topic_info array with the data for this topic and board
		if ($request->num_rows() !== 0)
		{
			$topic_info = $request->fetch_assoc();
		}
		$request->free_result();
	}

	// Return the results or false
	return !empty($topic_info) ? $topic_info : false;
}

/**
 * Loads the board_id for where a given message resides
 *
 * @param int $message_id
 *
 * @return int
 * @throws \ElkArte\Exceptions\Exception
 * @package Maillist
 *
 */
function query_load_board($message_id)
{
	$db = database();

	$request = $db->query('', '
		SELECT
			id_board
		FROM {db_prefix}messages
		WHERE id_msg = {int:message_id}',
		array(
			'message_id' => $message_id,
		)
	);
	list ($board_id) = $request->fetch_row();
	$request->free_result();

	return empty($board_id) ? 0 : $board_id;
}

/**
 * Loads the basic board information for a given board id
 *
 * @param int $board_id
 * @param mixed[] $pbe
 * @return mixed[]
 * @throws \ElkArte\Exceptions\Exception
 * @package Maillist
 */
function query_load_board_details($board_id, $pbe)
{
	$db = database();

	// To post a NEW Topic, we need certain board details
	$request = $db->query('', '
		SELECT
			b.count_posts, b.id_profile, b.member_groups, b.id_theme, b.id_board
		FROM {db_prefix}boards AS b
		WHERE {raw:query_see_board} AND id_board = {int:id_board}',
		array(
			'id_board' => $board_id,
			'query_see_board' => $pbe['user_info']['query_see_board'],
		)
	);
	$board_info = $request->fetch_assoc();
	$request->free_result();

	return $board_info;
}

/**
 * Loads the theme settings for the theme this user is using
 *
 * - Mainly used to determine a users notify settings
 *
 * @param int $id_member
 * @param int $id_theme
 * @param mixed[] $board_info
 *
 * @return array
 * @throws \Exception
 * @package Maillist
 *
 */
function query_get_theme($id_member, $id_theme, $board_info)
{
	global $modSettings;

	$db = database();

	// Verify the id_theme...
	// Allow the board specific theme, if they are overriding.
	if (!empty($board_info['id_theme']) && $board_info['override_theme'])
	{
		$id_theme = (int) $board_info['id_theme'];
	}
	elseif (!empty($modSettings['knownThemes']))
	{
		$themes = explode(',', $modSettings['knownThemes']);

		$id_theme = !in_array($id_theme, $themes) ? $modSettings['theme_guests'] : (int) $id_theme;
	}
	else
	{
		$id_theme = (int) $id_theme;
	}

	// With the theme and member, load the auto_notify variables
	$theme_settings = array();
	$db->fetchQuery('
		SELECT
			variable, value
		FROM {db_prefix}themes
		WHERE id_member = {int:id_member}
			AND id_theme = {int:id_theme}',
		array(
			'id_theme' => $id_theme,
			'id_member' => $id_member,
		)
	)->fetch_callback(
		function ($row) use (&$theme_settings) {
			// Put everything about this member/theme into a theme setting array
			$theme_settings[$row['variable']] = $row['value'];
		}
	);

	return $theme_settings;
}

/**
 * Turn notifications on or off if the user has set auto notify 'when I reply'
 *
 * @param int $id_member
 * @param int $id_board
 * @param int $id_topic
 * @param bool $auto_notify
 * @param mixed[] $permissions
 * @throws \ElkArte\Exceptions\Exception
 * @package Maillist
 */
function query_notifications($id_member, $id_board, $id_topic, $auto_notify, $permissions)
{
	$db = database();

	// First see if they have a board notification on for this board
	// so we don't set both board and individual topic notifications
	$board_notify = false;
	$request = $db->query('', '
		SELECT
			id_member
		FROM {db_prefix}log_notify
		WHERE id_board = {int:board_list}
			AND id_member = {int:current_member}',
		array(
			'current_member' => $id_member,
			'board_list' => $id_board,
		)
	);
	if ($request->fetch_row())
	{
		$board_notify = true;
	}
	$request->free_result();

	// If they have topic notification on and not board notification then
	// add this post to the notification log
	if (!empty($auto_notify) && (in_array('mark_any_notify', $permissions)) && !$board_notify)
	{
		$db->insert('ignore',
			'{db_prefix}log_notify',
			array('id_member' => 'int', 'id_topic' => 'int', 'id_board' => 'int'),
			array($id_member, $id_topic, 0),
			array('id_member', 'id_topic', 'id_board')
		);
	}
	else
	{
		// Make sure they don't get notified
		$db->query('', '
			DELETE FROM {db_prefix}log_notify
			WHERE id_member = {int:current_member}
				AND id_topic = {int:current_topic}',
			array(
				'current_member' => $id_member,
				'current_topic' => $id_topic,
			)
		);
	}
}

/**
 * Called when a pm reply has been made
 *
 * - Marks the PM replied to as read
 * - Marks the PM replied to as replied to
 * - Updates the number of unread to reflect this
 *
 * @param \ElkArte\EmailParse $email_message
 * @param mixed[] $pbe
 * @throws \ElkArte\Exceptions\Exception
 * @package Maillist
 */
function query_mark_pms($email_message, $pbe)
{
	$db = database();

	$request = $db->query('', '
		UPDATE {db_prefix}pm_recipients
		SET 
			is_read = is_read | 1
		WHERE id_member = {int:id_member}
			AND NOT ((is_read & 1) >= 1)
			AND id_pm = {int:personal_messages}',
		array(
			'personal_messages' => $email_message->message_id,
			'id_member' => $pbe['profile']['id_member'],
		)
	);

	// If something was marked as read, get the number of unread messages remaining.
	if ($request->affected_rows() > 0)
	{
		$total_unread = 0;
		$db->fetchQuery('
			SELECT
				labels, COUNT(*) AS num
			FROM {db_prefix}pm_recipients
			WHERE id_member = {int:id_member}
				AND NOT ((is_read & 1) >= 1)
				AND deleted = {int:is_not_deleted}
			GROUP BY labels',
			array(
				'id_member' => $pbe['profile']['id_member'],
				'is_not_deleted' => 0,
			)
		)->fetch_callback(
			function ($row) use (&$total_unread) {
				$total_unread += $row['num'];
			}
		);

		// Update things for when they do come to the site
		require_once(SUBSDIR . '/Members.subs.php');
		updateMemberData($pbe['profile']['id_member'], array('unread_messages' => $total_unread));
	}

	// Now mark the message as "replied to" since they just did
	$db->query('', '
		UPDATE {db_prefix}pm_recipients
		SET 
			is_read = is_read | 2
		WHERE id_pm = {int:replied_to}
			AND id_member = {int:current_member}',
		array(
			'current_member' => $pbe['profile']['id_member'],
			'replied_to' => $email_message->message_id,
		)
	);
}

/**
 * Once a key has been used it is removed and can not be used again
 *
 * - Also removes any old keys to minimize security issues
 *
 * @param \ElkArte\EmailParse $email_message
 * @throws \ElkArte\Exceptions\Exception
 * @package Maillist
 */
function query_key_maintenance($email_message)
{
	global $modSettings;

	$db = database();

	// Old keys simply expire
	$days = (!empty($modSettings['maillist_key_active'])) ? $modSettings['maillist_key_active'] : 21;
	$delete_old = time() - ($days * 24 * 60 * 60);

	// Consume the database key that was just used .. one reply per key
	// but we let PM's slide, they often seem to be re re re replied to
	if ($email_message->message_type !== 'p')
	{
		$db->query('', '
			DELETE FROM {db_prefix}postby_emails
			WHERE message_key = {string:key}
				AND message_type = {string:type}
				AND message_id = {string:message_id}',
			array(
				'key' => $email_message->message_key,
				'type' => $email_message->message_type,
				'message_id' => $email_message->message_id,
			)
		);
	}

	// Since we are here lets delete any items older than delete_old days,
	// if they have not responded in that time tuff
	$db->query('', '
		DELETE FROM {db_prefix}postby_emails
		WHERE time_sent < {int:delete_old}',
		array(
			'delete_old' => $delete_old
		)
	);
}

/**
 * After a email post has been made, this updates the users information just like
 * they are on the site to perform the given action.
 *
 * - Updates time on line
 * - Updates last active
 * - Updates the who's online list with the member and action
 *
 * @param mixed[] $pbe
 * @param \ElkArte\EmailParse $email_message
 * @param mixed[] $topic_info
 * @throws \Exception
 * @package Maillist
 */
function query_update_member_stats($pbe, $email_message, $topic_info = array())
{
	$db = database();

	$last_login = time();
	$do_delete = false;
	$total_time_logged_in = empty($pbe['profile']['total_time_logged_in']) ? 0 : $pbe['profile']['total_time_logged_in'];

	// If they were active in the last 15 min, we don't want to run up their time
	if (!empty($pbe['profile']['last_login']) && $pbe['profile']['last_login'] < (time() - (60 * 15)))
	{
		// not recently active so add some time to their login ....
		$do_delete = true;
		$total_time_logged_in += 60 * 10;
	}

	// Update the members total time logged in data
	require_once(SUBSDIR . '/Members.subs.php');
	updateMemberData($pbe['profile']['id_member'], array('total_time_logged_in' => $total_time_logged_in, 'last_login' => $last_login));

	// Show they are active in the who's online list and what they have done
	if ($email_message->message_type === 'm' || $email_message->message_type === 't')
	{
		$get_temp = array(
			'action' => 'postbyemail',
			'topic' => $topic_info['id_topic'],
			'last_msg' => $topic_info['id_last_msg'],
			'board' => $topic_info['id_board']
		);
	}
	elseif ($email_message->message_type === 'x')
	{
		$get_temp = array(
			'action' => 'topicbyemail',
			'topic' => $topic_info['id'],
			'board' => $topic_info['board'],
		);
	}
	else
	{
		$get_temp = array(
			'action' => 'pm',
			'sa' => 'byemail'
		);
	}

	// Place the entry in to the online log so the who's online can use it
	$serialized = serialize($get_temp);
	$session_id = 'ip' . $pbe['profile']['member_ip'];
	$member_ip = empty($pbe['profile']['member_ip']) ? 0 : $pbe['profile']['member_ip'];
	$db->insert($do_delete ? 'ignore' : 'replace',
		'{db_prefix}log_online',
		array('session' => 'string', 'id_member' => 'int', 'id_spider' => 'int', 'log_time' => 'int', 'ip' => 'string', 'url' => 'string'),
		array($session_id, $pbe['profile']['id_member'], 0, $last_login, $member_ip, $serialized),
		array('session')
	);
}

/**
 * Calls the necessary functions to extract and format the message so its ready for posting
 *
 * What it does:
 *
 * - Converts an email response (text or html) to a BBC equivalent via pbe_Email_to_bbc
 * - Formats the email response so it looks structured and not chopped up (via pbe_fix_email_body)
 *
 * @param bool $html
 * @param \ElkArte\EmailParse $email_message
 * @param mixed[] $pbe
 *
 * @return mixed|null|string|string[]
 * @throws \Exception
 * @package Maillist
 *
 */
function pbe_load_text(&$html, $email_message, $pbe)
{
	if (!$html || ($html && preg_match_all('~<table.*?>~i', $email_message->body, $match) >= 2))
	{
		// Some mobile responses wrap everything in a table structure so use plain text
		$text = $email_message->plain_body;
		$html = false;
	}
	else
	{
		$text = un_htmlspecialchars($email_message->body);
	}

	// Run filters now, before the data is manipulated
	$text = pbe_filter_email_message($text);

	// Convert to BBC and format it so it looks like a post
	$text = pbe_email_to_bbc($text, $html);

	$pbe['profile']['real_name'] = $pbe['profile']['real_name'] ?? '';
	$text = pbe_fix_email_body($text, $pbe['profile']['real_name'], (empty($email_message->_converted_utf8) ? $email_message->headers['x-parameters']['content-type']['charset'] : 'UTF-8'));

	// Do we even have a message left to post?
	$text = Util::htmltrim($text);
	if (empty($text))
	{
		return '';
	}

	if ($email_message->message_type !== 'p')
	{
		// Prepare it for the database
		require_once(SUBSDIR . '/Post.subs.php');
		preparsecode($text);
	}

	return $text;
}

/**
 * Attempts to create a reply post on the forum
 *
 * What it does:
 *
 * - Checks if the user has permissions to post/reply/postby email
 * - Calls pbe_load_text to prepare text for the post
 * - returns true if successful or false for any number of failures
 *
 * @param mixed[] $pbe array of all pbe user_info values
 * @param \ElkArte\EmailParse $email_message
 * @param mixed[] $topic_info
 *
 * @return bool
 * @throws \ElkArte\Exceptions\Exception
 * @package Maillist
 *
 */
function pbe_create_post($pbe, $email_message, $topic_info)
{
	global $modSettings, $txt;

	// Validate they have permission to reply
	$becomesApproved = true;
	if (!in_array('postby_email', $pbe['user_info']['permissions']) && !$pbe['user_info']['is_admin'])
	{
		return pbe_emailError('error_permission', $email_message);
	}

	if ($topic_info['locked'] && !$pbe['user_info']['is_admin'] && !in_array('moderate_forum', $pbe['user_info']['permissions']))
	{
		return pbe_emailError('error_locked', $email_message);
	}

	if ($topic_info['id_member_started'] === $pbe['profile']['id_member'] && !$pbe['user_info']['is_admin'])
	{
		if ($modSettings['postmod_active'] && in_array('post_unapproved_replies_any', $pbe['user_info']['permissions']) && (!in_array('post_reply_any', $pbe['user_info']['permissions'])))
		{
			$becomesApproved = false;
		}
		elseif (!in_array('post_reply_own', $pbe['user_info']['permissions']))
		{
			return pbe_emailError('error_cant_reply', $email_message);
		}
	}
	elseif (!$pbe['user_info']['is_admin'])
	{
		if ($modSettings['postmod_active'] && in_array('post_unapproved_replies_any', $pbe['user_info']['permissions']) && (!in_array('post_reply_any', $pbe['user_info']['permissions'])))
		{
			$becomesApproved = false;
		}
		elseif (!in_array('post_reply_any', $pbe['user_info']['permissions']))
		{
			return pbe_emailError('error_cant_reply', $email_message);
		}
	}

	// Convert to BBC and Format the message
	$html = $email_message->html_found;
	$text = pbe_load_text($html, $email_message, $pbe);
	if (empty($text))
	{
		return pbe_emailError('error_no_message', $email_message);
	}

	// Seriously? Attachments?
	if (!empty($email_message->attachments) && !empty($modSettings['maillist_allow_attachments']) && !empty($modSettings['attachmentEnable']) && $modSettings['attachmentEnable'] == 1)
	{
		if (($modSettings['postmod_active'] && in_array('post_unapproved_attachments', $pbe['user_info']['permissions'])) || in_array('post_attachment', $pbe['user_info']['permissions']))
		{
			$attachIDs = pbe_email_attachments($pbe, $email_message);
		}
		else
		{
			$text .= "\n\n" . $txt['error_no_attach'] . "\n";
		}
	}

	// Setup the post variables.
	$msgOptions = array(
		'id' => 0,
		'subject' => strpos($topic_info['subject'], trim($pbe['response_prefix'])) === 0 ? $topic_info['subject'] : $pbe['response_prefix'] . $topic_info['subject'],
		'smileys_enabled' => true,
		'body' => $text,
		'attachments' => empty($attachIDs) ? array() : $attachIDs,
		'approved' => $becomesApproved
	);

	$topicOptions = array(
		'id' => $topic_info['id_topic'],
		'board' => $topic_info['id_board'],
		'mark_as_read' => true,
		'is_approved' => !$modSettings['postmod_active'] || empty($topic_info['id_topic']) || !empty($topic_info['approved'])
	);

	$posterOptions = array(
		'id' => $pbe['profile']['id_member'],
		'name' => $pbe['profile']['real_name'],
		'email' => $pbe['profile']['email_address'],
		'update_post_count' => empty($topic_info['count_posts']),
		'ip' => $email_message->load_ip() ? $email_message->ip : $pbe['profile']['member_ip']
	);

	// Make the post.
	createPost($msgOptions, $topicOptions, $posterOptions);

	// Bind any attachments that may be included to this new message
	if (!empty($attachIDs) && !empty($msgOptions['id']))
	{
		bindMessageAttachments($msgOptions['id'], $attachIDs);
	}

	// We need the auto_notify setting, it may be theme based so pass the theme in use
	$theme_settings = query_get_theme($pbe['profile']['id_member'], $pbe['profile']['id_theme'], $topic_info);
	$auto_notify = $theme_settings['auto_notify'] ?? 0;

	// Turn notifications on or off
	query_notifications($pbe['profile']['id_member'], $topic_info['id_board'], $topic_info['id_topic'], $auto_notify, $pbe['user_info']['permissions']);

	// Notify members who have notification turned on for this,
	// but only if it's going to be approved
	if ($becomesApproved)
	{
		require_once(SUBSDIR . '/Notification.subs.php');
		sendNotifications($topic_info['id_topic'], 'reply', array(), array(), $pbe);
	}

	return true;
}

/**
 * Attempts to create a PM (reply) on the forum
 *
 * What it does
 * - Checks if the user has permissions
 * - Calls pbe_load_text to prepare text for the pm
 * - Calls query_mark_pms to mark things as read
 * - Returns true if successful or false for any number of failures
 *
 * @param mixed[] $pbe array of pbe 'user_info' values
 * @param \ElkArte\EmailParse $email_message
 * @param mixed[] $pm_info
 *
 * @return bool
 * @throws \ElkArte\Exceptions\Exception
 * @package Maillist
 *
 * @uses sendpm to do the actual "sending"
 */
function pbe_create_pm($pbe, $email_message, $pm_info)
{
	global $modSettings, $txt;

	// Can they send?
	if (!$pbe['user_info']['is_admin'] && !in_array('pm_send', $pbe['user_info']['permissions']))
	{
		return pbe_emailError('error_pm_not_allowed', $email_message);
	}

	// Convert the PM to BBC and Format the message
	$html = $email_message->html_found;
	$text = pbe_load_text($html, $email_message, $pbe);
	if (empty($text))
	{
		return pbe_emailError('error_no_message', $email_message);
	}

	// If they tried to attach a file, just say sorry
	if (!empty($email_message->attachments) && !empty($modSettings['maillist_allow_attachments']) && !empty($modSettings['attachmentEnable']) && $modSettings['attachmentEnable'] == 1)
	{
		$text .= "\n\n" . $txt['error_no_pm_attach'] . "\n";
	}

	// For sending the message...
	$from = array(
		'id' => $pbe['profile']['id_member'],
		'name' => $pbe['profile']['real_name'],
		'username' => $pbe['profile']['member_name']
	);

	$pm_info['subject'] = strpos($pm_info['subject'], trim($pbe['response_prefix'])) === 0 ? $pm_info['subject'] : $pbe['response_prefix'] . $pm_info['subject'];

	// send/save the actual PM.
	require_once(SUBSDIR . '/PersonalMessage.subs.php');
	$pm_result = sendpm(array('to' => array($pm_info['id_member_from']), 'bcc' => array()), $pm_info['subject'], $text, true, $from, $pm_info['id_pm_head']);

	// Assuming all went well, mark this as read, replied to and update the unread counter
	if (!empty($pm_result))
	{
		query_mark_pms($email_message, $pbe);
	}

	return !empty($pm_result);
}

/**
 * Create a new topic by email
 *
 * What it does:
 *
 * - Called by pbe_topic to create a new topic or by pbe_main to create a new topic via a subject change
 * - checks posting permissions, but requires all email validation checks are complete
 * - Calls pbe_load_text to prepare text for the post
 * - Calls sendNotifications to announce the new post
 * - Calls query_update_member_stats to show they did something
 * - Requires the pbe, email_message and board_info arrays to be populated.
 *
 * @param mixed[] $pbe array of pbe 'user_info' values
 * @param \ElkArte\EmailParse $email_message
 * @param mixed[] $board_info
 *
 * @return bool
 * @throws \ElkArte\Exceptions\Exception
 * @package Maillist
 *
 * @uses createPost to do the actual "posting"
 */
function pbe_create_topic($pbe, $email_message, $board_info)
{
	global $txt, $modSettings;

	// It does not work like that
	if (empty($pbe) || empty($email_message))
	{
		return false;
	}

	// We have the board info, and their permissions - do they have a right to start a new topic?
	$becomesApproved = true;
	if (!$pbe['user_info']['is_admin'])
	{
		if (!in_array('postby_email', $pbe['user_info']['permissions']))
		{
			return pbe_emailError('error_permission', $email_message);
		}

		if ($modSettings['postmod_active'] && in_array('post_unapproved_topics', $pbe['user_info']['permissions']) && (!in_array('post_new', $pbe['user_info']['permissions'])))
		{
			$becomesApproved = false;
		}
		elseif (!in_array('post_new', $pbe['user_info']['permissions']))
		{
			return pbe_emailError('error_cant_start', $email_message);
		}
	}

	// Approving all new topics by email anyway, smart admin this one is ;)
	if (!empty($modSettings['maillist_newtopic_needsapproval']))
	{
		$becomesApproved = false;
	}

	// First on the agenda the subject
	$subject = pbe_clean_email_subject($email_message->subject);
	$subject = strtr(Util::htmlspecialchars($subject), array("\r" => '', "\n" => '', "\t" => ''));

	// Not to long not to short
	if (Util::strlen($subject) > 100)
	{
		$subject = Util::substr($subject, 0, 100);
	}

	if ($subject === '')
	{
		return pbe_emailError('error_no_subject', $email_message);
	}

	// The message itself will need a bit of work
	$html = $email_message->html_found;
	$text = pbe_load_text($html, $email_message, $pbe);
	if (empty($text))
	{
		return pbe_emailError('error_no_message', $email_message);
	}

	// Build the attachment array if needed
	if (!empty($email_message->attachments) && !empty($modSettings['maillist_allow_attachments']) && !empty($modSettings['attachmentEnable']) && $modSettings['attachmentEnable'] == 1)
	{
		if (($modSettings['postmod_active'] && in_array('post_unapproved_attachments', $pbe['user_info']['permissions'])) || in_array('post_attachment', $pbe['user_info']['permissions']))
		{
			$attachIDs = pbe_email_attachments($pbe, $email_message);
		}
		else
		{
			$text .= "\n\n" . $txt['error_no_attach'] . "\n";
		}
	}

	// If we get to this point ... then its time to play, lets start a topic !
	require_once(SUBSDIR . '/Post.subs.php');

	// Setup the topic variables.
	$msgOptions = array(
		'id' => 0,
		'subject' => $subject,
		'smileys_enabled' => true,
		'body' => $text,
		'attachments' => empty($attachIDs) ? array() : $attachIDs,
		'approved' => $becomesApproved
	);

	$topicOptions = array(
		'id' => 0,
		'board' => $board_info['id_board'],
		'mark_as_read' => false
	);

	$posterOptions = array(
		'id' => $pbe['profile']['id_member'],
		'name' => $pbe['profile']['real_name'],
		'email' => $pbe['profile']['email_address'],
		'update_post_count' => empty($board_info['count_posts']),
		'ip' => (isset($email_message->ip)) ? $email_message->ip : $pbe['profile']['member_ip']
	);

	// Attempt to make the new topic.
	createPost($msgOptions, $topicOptions, $posterOptions);

	// Bind any attachments that may be included to this new topic
	if (!empty($attachIDs) && !empty($msgOptions['id']))
	{
		bindMessageAttachments($msgOptions['id'], $attachIDs);
	}

	// The auto_notify setting
	$theme_settings = query_get_theme($pbe['profile']['id_member'], $pbe['profile']['id_theme'], $board_info);
	$auto_notify = $theme_settings['auto_notify'] ?? 0;

	// Notifications on or off
	query_notifications($pbe['profile']['id_member'], $board_info['id_board'], $topicOptions['id'], $auto_notify, $pbe['user_info']['permissions']);

	// Notify members who have notification turned on for this, (if it's approved)
	if ($becomesApproved)
	{
		require_once(SUBSDIR . '/Notification.subs.php');
		sendNotifications($topicOptions['id'], 'reply', array(), array(), $pbe);
	}

	// Update this users info so the log shows them as active
	query_update_member_stats($pbe, $email_message, $topicOptions);

	return true;
}
