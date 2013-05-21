<?php

/**
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 1.0 Alpha
 *
 * All the vital helper functions for use in email posting, formatting and conversion
 */

if (!defined('ELKARTE'))
	die('No access...');

/**
 * If html this will convert basic html tags to bbc tags
 * If plain text will convert it to html as though it was markdown and then to bbc
 * Converts links to bbc tags by using pbe_convert_urls function
 *
 * @param string $text
 * @param boolean $html
 */
function pbe_email_to_bbc($text, $html)
{
	// define the limited HTML we will translate to bbc and strip the rest
	$tags = array(
		// standard html to bbc
		'~&nbsp;~' => ' ',
		'~<b(\s(.)*?)*?' . '>~i' => '[b]',
		'~</b>~i' => '[/b]',
		'~<i(\s(.)*?)*?' . '>~i' => '[i]',
		'~</i>~i' => '[/i]',
		'~<u(\s(.)*?)*?' . '>~i' => '[u]',
		'~</u>~i' => '[/u]',
		'~<strong(\s(.)*?)*?' . '>~i' => '[b]',
		'~</strong>~i' => '[/b]',
		'~<em(\s(.)*?)*?' . '>~i' => '[i]',
		'~</em>~i' => '[/i]',
		'~<s(\s(.)*?)*?' . '>~i' => "[s]",
		'~</s>~i' => "[/s]",
		'~<strike(\s(.)*?)*?' . '>~i' => '[s]',
		'~</strike>~i' => '[/s]',
		'~<del(\s(.)*?)*?' . '>~i' => '[s]',
		'~</del>~i' => '[/s]',
		'~<center(\s(.)*?)*?' . '>~i' => '[center]',
		'~</center>~i' => '[/center]',
		'~<pre(\s(.)*?)*?' . '>~i' => '[pre]',
		'~</pre>~i' => '[/pre]',
		'~<sub(\s(.)*?)*?' . '>~i' => '[sub]',
		'~</sub>~i' => '[/sub]',
		'~<sup(\s(.)*?)*?' . '>~i' => '[sup]',
		'~</sup>~i' => '[/sup]',
		'~<tt(\s(.)*?)*?' . '>~i' => '[tt]',
		'~</tt>~i' => '[/tt]',
		'~<br(?:\s[^<>]*?)?' . '>~i' => "\n",
		// some clients do basic tags to take as much space as possible
		'~<span style="font-style:\s?italic;?">(.*?)</span>~isU' => '[i]$1[/i]',
		'~\[b\]<span style="font-weight:\s?bold;?">(.*?)</span>\[/b\]~iU' => '[b]$1[/b]',
		'~<span style="font-weight:\s?bold;?">(.*?)</span>~isU' => '[b]$1[/b]',
		'~<span style="text-decoration: underline[;]?">(.*)</span>~isU' => '[u]$1[/u]',
		'~<span dir=\"ltr\">\&lt;(.*)\&gt;</span>~isU' => '$1',
		'~<span dir=\"ltr\">(.*)</span>~is' => '$1',
		'~<span class="Apple-style-span" style="font-weight: normal;\s?">(.*)</span>~isU' => '$1',
		'~<style .*</style>~' => '',
		// various shapes of rules
		'~<hr[^<>]*>(\n)?~i' => "[hr]\n$1",
		// use quotes if we can find them
		'~<blockquote(\s(.)*?)*?' . '>~i' => "[quote]",
		'~</blockquote>~i' => "[/quote]",
		'~<div style="right: auto">~i' => '',
		'~<div class="gmail_quote">~i' => '',
		// lists can be nice
		'~<ul(\s(.)*?)*?' . '>~i' => "[list]\n",
		'~</ul>~i' => "[/list]\n",
		'~<ol(\s(.)*?)*?' . '>~i' => "[list type=decimal]\n",
		'~</ol>~i' => "[/list]\n",
		'~<li(\s(.)*?)*?' . '>~i' => "[li]",
		'~</li>~i' => "[/li]\n",
		// some block elements
		'~</div>~i' => "\n",
		'~<p(\s(.)*?)*?' . '>~i' => "\n\n",
		// tables can be a bit complicated
		'~<table(\s(.)*?)*?' . '>~i' => '[table]',
		'~</table>~i' => '[/table]',
		'~<tr(\s(.)*?)*?' . '>~i' => '[tr]',
		'~</tr>~i' => '[/tr]',
		'~<(td|th)\s[^<>]*?colspan="?(\d{1,2})"?.*?' . '>~ie' => 'str_repeat(\'[td][/td]\', $2 - 1) . \'[td]\'',
		'~<(td|th)(\s(.)*?)*?' . '>~i' => '[td]',
		'~</(td|th)>~i' => '[/td]',
		// the ubiquitous "other"
		'~<\*>~i' => '&lt;*&gt;',
		'~<title>(.*)</title>~iU' => '',
		'~(\[b\]){2}From:.*-{36}~s' => 'str_repeat(\'-\', 36)',
		'~\*\*(.*)\*\*~isUe' => '\'**\'.ltrim(\'$1\').\'**\'',
	);

	// We are starting with HTML, our goal is to convert only the best parts of it
	// to BBC, with email most HTML is unnecessary
	if ($html)
	{
		// Some HTML comes in as chunks, separated by line feeds etc, remove the whitespace so we have an html string
		$text = preg_replace('/(?:(?<=\>)|(?<=\/\>))(\s+)(?=\<\/?)/', '', $text);

		// Set a gmail flag for special quote processing since its quotes are strange
		$gmail = (bool) preg_match('~<div class="gmail_quote">~i', $text);

		// Convert the email-HTML to BBC
		$text = preg_replace(array_keys($tags), array_values($tags), $text);

		// Run our parsers to remove the original replied to message before we do any more work
		$text_save = $text;
		$result = pbe_parse_email_message($text);

		// If we have no message they may have replied below and/or inside the original message.
		// People like this should not be allowed to use the net, or be forced to read their own
		// messed up emails
		if (empty($result) || (trim(strip_tags(pbe_filter_email_message($text))) === ''))
			$text = $text_save;
	}
	// Starting with plain text, possibly even markdown style ;)
	else
	{
		// Run the parser to try and remove common mail clients "reply to" stuff
		$text_save = $text;
		$result = pbe_parse_email_message($text);

		// Bottom feeder?  If we have no message they could have replied below the original message
		if (empty($result) || trim(strip_tags(pbe_filter_email_message($text))) === '')
			$text = $text_save;

		// Fix textual quotes so we also fix wrapping issues first!
		$text = pbe_fix_email_quotes($text, ($html && !$gmail));

		// Convert this (markup) text to html
		require_once(EXTDIR . '/markdown/markdown.php');
		$text = Markdown($text);

		// Convert any resulting HTML created by markup style text in the email to BBC
		$text = str_replace('</p>', "\n", $text);
		$text = preg_replace(array_keys($tags), array_values($tags), $text);
	}

	// Convert (save) any links before we strip out the remaining HTML tags
	require_once(SUBSDIR . '/Editor.subs.php');
	$text = convert_urls($text);

	// Now remove any remaining html tags and convert any special tags
	$text = str_replace('<div>', "\n", $text);
	$text = strip_tags($text);
	$text = htmlspecialchars_decode($text, ENT_QUOTES);
	$text = str_replace('&nbsp;', ' ', $text);

	// Some tags often end up as just empty tags - remove those.
	$text = preg_replace('~\[[bisu]\]\s*\[/[bisu]\]~', '', $text);
	$text = preg_replace('~\[quote\]\s*\[/quote\]~', '', $text);
	$text = preg_replace('~(\n){3,}~si', "\n\n", $text);

	return $text;
}

/**
 * Prepares the email body so that it looks like a forum post
 *  - Removes extra content as defined in the ACP filters
 *  - Fixes quotes and quote levels
 *  - Re-flows (unfolds) an email using the EmailFormat.class
 *  - Attempts to remove any exposed email address
 *
 * @param string $body
 * @param boolean $html
 * @param string $member_real_name
 * @param string $charset character set of the text
 */
function pbe_fix_email_body($body, $html = false, $real_name = '', $charset = 'UTF-8')
{
	global $txt;

	// Remove the \r's now so its done
	$body = trim(str_replace("\r", '', $body));

	// Remove our wrapped image tags (like we do in outbound emails)
	$body = preg_replace('~\[Image:\s(.*?)\s\]~i', '$1', $body);

	// Remove the riff-raff as defined by the ACP filters
	$body = pbe_filter_email_message($body);

	// Any old school email wrote: etc style quotes that we need to update
	$body = pbe_fix_client_quotes($body);

	// Attempt to remove any exposed email addresses that are in the reply
	$body = preg_replace('~>' . $txt['to'] . '(.*)@(.*?)\n~i', '', $body);
	$body = preg_replace('~\b\s?[a-z0-9._%+-]+@[a-zZ0-9.-]+\.[a-z]{2,4}\b.?' . $txt['email_wrote'] . ':\s?~i', '', $body);
	$body = preg_replace('~<(.*?)>(.*@.*?)\n~', '$1' . "\n", $body);
	$body = preg_replace('~' . $txt['email_quoting'] . ' (.*) (?:<|&lt;|\[email\]).*?@.*?(?:>|&gt;|\[/email\]):~i', '', $body);

	// Remove multiple sequential blank lines
	$body = preg_replace('~(\n){3,}~si', "\n\n", $body);

	// Reflow and Cleanup this message to something that looks normal-er
	require_once(SUBSDIR . '/EmailFormat.class.php');
	$formatter = new Email_Format();
	$body = $formatter->reflow($body, $html, $real_name, $charset);

	return $body;
}

/**
 * Replaces a messages >'s with BBC [quote] [/quote] blocks
 *  - Uses quote depth function
 *  - Works with nested quotes of many forms >, > >, >>, >asd
 *  - Bypassed for gmail as it only block quotes the outer layer and then plain
 *    text > quotes the inner which is confusing to all
 *
 * @param string $body
 * @param boolean $html
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
	$body = '';
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
			if (($level_prev !==0) && ($level_prev >= $level_next && $level_next !== 0))
			{
				$body_array[$i - 1] .= ' ' . $body_array[$i];
				unset($body_array[$i]);
				continue;
			}
		}

		// No quote or in the same quote just continue
		if ($level == $current_quote)
			continue;

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

	// join the array back together while dropping null index's
	$body = implode("\n", array_values($body_array));

	return $body;
}

/**
 * Looks for text quotes in the form of > and returns the current level for the line
 *  - If update is true (default), will strip the >'s and return the numeric level found
 *  - Called by pbe_fix_email_quotes
 *
 * @param string $string
 * @param boolean $update
 */
function pbe_email_quote_depth(&$string, $update = true)
{
	// Get the quote "depth" level for this line
	$level = 0;
	$check = true;
	$string_save = $string;
	$matches = array();

	while ($check)
	{
		// we have a quote marker, increase our depth and strip the line of that quote marker
		if ((substr($string, 0, 2) === '> ') || ($string === '>'))
		{
			$level++;
			$string = substr($string, 2);
		}
		// Maybe one a poorly nested quotes ... with no spaces between the >'s or the > and the data
		elseif ((substr($string, 0, 2) === '>>') || (preg_match('~^>[a-z0-9<-]+~Uis', $string, $matches) == 1))
		{
			$level++;
			$string = substr($string, 1);
		}
		// all done getting the depth
		else
			$check = false;
	}

	if (!$update)
		$string = $string_save;

	return $level;
}

/**
 * Splits a message at a given string, returning only the upper portion
 *  - Intended to split off the 'replied to' portion that often follows the reply
 *  - Uses parsers as defined in the ACP to do its searching
 *  - Stops after the first successful hit occurs
 *  - Goes in the order defined in the table
 *
 * @param string $body
 * @return boolean on find
 */
function pbe_parse_email_message(&$body)
{
	$db = database();

	// Load up the parsers from the database
	$request = $db->query('', '
		SELECT filter_from, filter_type
		FROM {db_prefix}postby_emails_filters
		WHERE filter_style = {string:filter_style}',
		array(
			'filter_style' => 'parser'
		)
	);

	// Build an array of valid expressions
	$expressions = array();
	while ($row = $db->fetch_assoc($request))
	{
		if ($row['filter_type'] === 'regex')
		{
			// Test the regex and if good add it to the array, else skip it
			// @todo these are tested at insertion, so this may be unnecessary
			$temp = preg_replace($row['filter_from'], '', '$5#6#8%9456@^)098');
			if ($temp != null)
				$expressions[] = array('type' => 'regex', 'parser' => $row['filter_from']);
		}
		else
			$expressions[] = array('type' => 'string', 'parser' => $row['filter_from']);
	}
	$db->free_result($request);

	// Look for the markers, **stop** after the first successful one, good hunting!
	$match = false;
	$split = array();
	foreach ($expressions as $expression)
	{
		if ($expression['type'] === 'regex')
			$split = preg_split($expression['parser'], $body);
		else
			$split = explode($expression['parser'], $body, 2);

		// If an expression was matched our fine work is done
		if (!empty($split[1]))
		{
			// If we had a find then we clip off the mail clients "reply to" section
			$match = true;
			$body = $split[0];
			break;
		}
	}

	return $match;
}

/**
 * Searches for extraneous text and removes/replaces it
 *  - Uses filters as defined in the ACP to do the search / replace
 *  - Will apply regex filters first, then string match filters
 *  - Apply all filters to a message
 *
 * @param string $text
 */
function pbe_filter_email_message($text)
{
	$db = database();

	// load up the text filters from the database, regex first and ordered by id number
	$request = $db->query('', '
		SELECT filter_from, filter_to, filter_type
		FROM {db_prefix}postby_emails_filters
		WHERE filter_style = {string:filter_style}
		ORDER BY id_filter ASC, filter_type ASC',
		array(
			'filter_style' => 'filter'
		)
	);

	// Remove all the excess things as defined, i.e. sent from my iPhone, I hate those >:D
	while ($row = $db->fetch_assoc($request))
	{
		if ($row['filter_type'] === 'regex')
		{
			// Newline madness
			if (!empty($row['filter_to']))
				$row['filter_to'] = str_replace('\n', "\n", $row['filter_to']);

			// Test the regex and if good use, else skip, don't want a bad regex to null the message ;)
			$temp = preg_replace($row['filter_from'], $row['filter_to'], $text);
			if ($temp != null)
				$text = $temp;
		}
		else
			$text = str_replace($row['filter_from'], $row['filter_to'], $text);
	}
	$db->free_result($request);

	return $text;
}

/**
 * Finds Re: Subject: FW: FWD or [$sitename] in the subject and strips it
 *  - Recursively calls itself till no more tags are found
 *
 * @param string $text
 * @param boolean $check if true will return if there tags were found
 */
function pbe_clean_email_subject($text, $check = false)
{
	global $txt, $modSettings, $context;

	$sitename = !empty($modSettings['maillist_sitename']) ? $modSettings['maillist_sitename'] : $context['forum_name'];

	// Find Re: Subject: FW: FWD or [$sitename] in the subject and strip it
	$re = strpos(strtoupper($text), $txt['RE:']);
	if ($re !== false)
		$text = substr($text, 0, $re) . substr($text, $re + strlen($txt['RE:']), strlen($text));

	$su = strpos(strtoupper($text), $txt['SUBJECT:']);
	if ($su !== false)
		$text = substr($text, 0, $su) . substr($text, $su + strlen($txt['SUBJECT:']), strlen($text));

	$fw = strpos(strtoupper($text), $txt['FW:']);
	if ($fw !== false)
		$text = substr($text, 0, $fw) . substr($text, $fw + strlen($txt['FW:']), strlen($text));

	$gr = strpos($text, "[{$sitename}]");
	if ($gr !== false)
		$text = substr($text, 0, $gr) . substr($text, $gr + strlen($sitename) + 2, strlen($text));

	$fwd = strpos(strtoupper($text), $txt['FWD:']);
	if ($fwd !== false)
		$text = substr($text, 0, $fwd) . substr($text, $fwd + strlen($txt['FWD:']), strlen($text));

	// if not done then call ourselves again, we like the sound of our name
	if (strpos(strtoupper($text), $txt['RE:']) || strpos(strtoupper($text), $txt['FW:']) || strpos(strtoupper($text), $txt['FWD:']) || strpos($text, "[{$sitename}]"))
		$text = pbe_clean_email_subject($text);

	// clean or not?
	if ($check)
		return ($re === false && $su === false && $gr === false && $fw === false && $fwd === false);
	else
		return trim($text);
}

/**
 * Used if the original email could not be removed from the message
 *  - Tries to quote the original message instead by using a loose original message search
 *
 * @param string $body
 */
function pbe_fix_client_quotes($body)
{
	global $txt;

	// Define some common quote markers (from the original messages)
	// @todo ACP for this? ... not sure really since this is really damage repair
	$regex = array();

	// On mon, jan 12, 2004 at 10:10 AM, Joe Blow wrote: [quote]
	$regex[] = "~(?:" . $txt['email_on'] . ")?\w{3}, \w{3} \d{1,2},\s?\d{4} " . $txt['email_at'] . " \d{1,2}:\d{1,2} [AP]M,(.*)?" . $txt['email_wrote'] . ":\s?\s{1,4}\[quote\]~i";

	// [quote] on: mon jan 12, 2004 Joe Blow wrote:
	$regex[] = "~\[quote\]\s?" . $txt['email_on'] . ": \w{3} \w{3} \d{1,2}, \d{4} (.*)?" . $txt['email_wrote'] . ":\s~i";

	// on jan 12, 2004 at 10:10 PM, Joe Blow wrote:   [quote]
	$regex[] = "~" .  $txt['email_on'] . " \w{3} \d{1,2}, \d{4}, " . $txt['email_at'] . " \d{1,2}:\d{1,2} [AP]M,(.*)?" . $txt['email_wrote'] . ":\s{1,4}\[quote\]~i";

	// on jan 12, 2004 at 10:10, Joe Blow wrote   [quote]
	$regex[] = "~" .  $txt['email_on'] . " \w{3} \d{1,2}, \d{4}, " . $txt['email_at'] . " \d{1,2}:\d{1,2}, (.*)?" . $txt['email_wrote'] . ":\s{1,4}\[quote\]~i";

	// quoting: Joe Blow on stuffz at 10:10:23 AM
	$regex[] = "~" . $txt['email_quotefrom'] . ": (.*) " . $txt['email_on'] . " .* " . $txt['email_at'] . " \d{1,2}:\d{1,2}:\d{1,2} [AP]M~";

	// quoting Joe Blow <joeblow@blow.com>
	$regex[] = "~" . $txt['email_quoting'] . " (.*) (?:<|&lt;|\[email\]).*?@.*?(?:>|&gt;|\[/email\]):~i";

	// For each one see if we can do a nice [quote author=joe blow]
	foreach ($regex as $reg)
	{
		if (preg_match_all($reg, $body, $matches, PREG_SET_ORDER))
		{
			foreach ($matches as $quote)
			{
				$quote[1] = preg_replace('~\[email\].*\[\/email\]~', '', $quote[1]);
				$body = pbe_str_replace_once($quote[0], "\n" . '[quote author=' . trim($quote[1]) . "]\n", $body);

				// look for [quote author=][/quote][quote] issues
				$body = pbe_str_replace_once('[quote author=' . trim($quote[1]) . "]\n\n" . '[/quote][quote]', '[quote author=' . trim($quote[1]) . "]\n", $body);

				// and [quote author=][quote] .... [/quote] issues
				$body = preg_replace('~\[quote author=' . trim($quote[1]) . '\][\n]{2,3}\[quote\]~', '[quote author=' . trim($quote[1]) . "]\n", $body);
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
 */
function pbe_str_replace_once($needle, $replace, $haystack)
{
	// Looks for the first occurrence of $needle in $haystack and replaces it with $replace
	// This is a single replace
	$pos = strpos($haystack, $needle);
	if ($pos === false)
		return $haystack;

	return substr_replace($haystack, $replace, $pos, strlen($needle));
}

/**
 * Does a moderation check on a given user (global)
 *  - Removes permissions of PBE concern that a given moderation level denies
 *
 * @param array $pbe array of user values
 */
function pbe_check_moderation(&$pbe)
{
	global $modSettings;

	if (empty($modSettings['postmod_active']))
		return;

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
				unset($permission_change[$old]);
			else
				$pbe['user_info']['permissions'][] = $new;
		}
		$pbe['user_info']['permissions'] = array_diff($pbe['user_info']['permissions'], array_keys($permission_change));
	}

	return;
}

/**
 * Creates a failed email entry in the postby_emails_error table
 * - Attempts to correct for common errors so the admin / moderator
 *   can choose to approve the email
 *
 * @param string $error
 * @param object $email_message
 */
function pbe_emailError($error, $email_message)
{
	global $txt;

	$db = database();

	loadLanguage('EmailTemplates');

	// Some extra items we will need to remove from the message subject
	$pm_subject_leader = str_replace('{SUBJECT}', '', $txt['new_pm_subject']);

	// Clean the subject like we don't know where it has been
	$subject = trim(str_replace($pm_subject_leader, '', $email_message->subject));
	$subject = pbe_clean_email_subject($subject);
	$subject = ($subject === '') ? $txt['no_subject'] : $subject;

	// Start off with what we know about the security key, even if its nothing
	$message_key = (string) $email_message->message_key_id;
	$message_type = (string) $email_message->message_type;
	$message_id = (int) $email_message->message_id;
	$board_id = -1;

	// First up is the old, wrong email address, lets see who this should have come from if its not a new topic request
	if ($error === 'error_not_find_member' && $email_message->message_type !== 'x')
	{
		$key_owner = query_key_owner($email_message->message_key_id);
		if (!empty($key_owner))
		{
			// Valid key so show who should have sent this key in, email aggravaters :P often mess this up
			$email_message->email['from'] = $email_message->email['from'] . ' => ' . $key_owner;

			// Since we have a valid key set those details as well
			$message_key = $email_message->message_key_id;
			$message_type = $email_message->message_type;
			$message_id = $email_message->message_id;
		}
	}

	// A valid key but it was not sent to this user ... but we got it from a valid user
	if ($error === 'error_key_sender_match')
	{
		$key_owner = query_key_owner($email_message->message_key_id);
		if (!empty($key_owner))
		{
			// Valid key so show who should have sent this key in
			$email_message->email['from'] = $key_owner . ' => ' . $email_message->email['from'];

			// Since we have a valid key set those details as well
			$message_key = $email_message->message_key_id;
			$message_type = $email_message->message_type;
			$message_id = $email_message->message_id;
		}
	}

	// No key? We should at a minimum have who its from and a subject, so use that
	if (empty($message_key) && $email_message->message_type !== 'x')
	{
		// We don't have the message type (since we don't have a key)
		// Attempt to see if it might be a PM so we handle it correctly
		if (empty($message_type) && (strpos($email_message->subject, $pm_subject_leader) !== false))
			$message_type = 'p';

		// Find all keys sent to this user, sorted by date
		$user_keys = array();
		$user_keys = query_user_keys($email_message->email['from']);

		// While we have keys to look at see if we can match up this lost message on subjects
		foreach ($user_keys as $user_key)
		{
			if (preg_match('~([a-z0-9]{32})\-(p|t|m)(\d+)~', $user_key['id_email'], $match))
			{
				$key = $match[0];
				$type = $match[2];
				$message = $match[3];

				// If we know/suspect its a "m,t or p" then use that to avoid a match on a wrong type, that would be bad ;)
				if ((!empty($message_type) && $message_type === $type) || empty($message_type))
				{
					// lets look up this message/topic/pm and see if the subjects match ... if they do then tada!
					if (query_load_subject($message, $type, $email_message->email['from']) === $email_message->subject)
					{
						// This email has a subject that matches the subject of a message that was sent to them
						$message_key = $key;
						$message_id = $message;
						$message_type = $type;
						continue;
					}
				}
			}
		}
	}

	// Maybe we have enough to find the board id where this was going
	if (!empty($message_id) && $message_type !== 'p')
		$board_id = query_load_board($message_id);

	// Log the error so the moderators can take a look, helps keep them sharp
	$id = isset($_POST['item']) ? (int) $_POST['item'] : 0;
	$db->insert(isset($_POST['item']) ? 'replace' : 'ignore',
		'{db_prefix}postby_emails_error',
		array('id_email' => 'int', 'error' => 'string', 'data_id' => 'string', 'subject' => 'string', 'id_message' => 'int', 'id_board' => 'int', 'email_from' => 'string', 'message_type' => 'string', 'message' => 'string'),
		array($id, $error, $message_key, $email_message->subject, $message_id, $board_id, $email_message->email['from'], $message_type, $email_message->raw_message),
		array('id_email')
	);

	// Flush the moderator error number cache, if we are here it likely just changed.
	cache_put_data('num_menu_errors', null, 900);

	// if not running from the cli, then go back to the form
	if (isset($_POST['item']))
	{
		// back to the form we go
		$_SESSION['email_error'] = $txt[$error];
		redirectexit('action=admin;area=maillist');
	}

	return false;
}

/**
 * Writes email attachments as temp names in the proper attachment directory
 *  - populates $_SESSION['temp_attachments'] with the email attachments
 *  - calls attachmentChecks to validate them
 *  - skips ones flagged with errors
 *  - adds valid ones to attachmentOptions
 *  - calls createAttachment to store them
 *
 * @param array pbe
 * @param object $email_message
 */
function pbe_email_attachments($pbe, $email_message)
{
	// Trying to attach a file with this post ....
	global $modSettings, $context;

	// Init
	$attachment_count = 0;
	$attachments = array();
	$attachIDs = array();

	// Make sure we're uploading the files to the right place.
	if (!empty($modSettings['currentAttachmentUploadDir']))
	{
		if (!is_array($modSettings['attachmentUploadDir']))
			$modSettings['attachmentUploadDir'] = unserialize($modSettings['attachmentUploadDir']);

		// The current directory, of course!
		$current_attach_dir = $modSettings['attachmentUploadDir'][$modSettings['currentAttachmentUploadDir']];
	}
	else
		$current_attach_dir = $modSettings['attachmentUploadDir'];

	// For attachmentChecks function
	require_once(SUBSDIR . '/Attachments.subs.php');
	$context['attachments'] = array('quantity' => 0, 'total_size' => 0);
	$context['attach_dir'] = $current_attach_dir;

	// Create the file(s) with a temp name so we can validate its contents/type
	foreach ($email_message->attachments as $name => $attachment)
	{
		// Write the contents to an actual file
		$attachID = 'post_tmp_' . $pbe['profile']['id_member'] . '_' . md5(mt_rand()) . $attachment_count;
		$destName = $current_attach_dir . '/' . $attachID;

		if (file_put_contents($destName, $attachment) !== false)
		{
			@chmod($destName, 0644);

			// Place them in session since that's where attachmentChecks looks
			$_SESSION['temp_attachments'][$attachID] = array(
				'name' => htmlspecialchars(basename($name)),
				'tmp_name' => $destName,
				'size' => strlen($attachment),
				'id_folder' => $modSettings['currentAttachmentUploadDir'],
				'errors' => array(),
				'approved' => !$modSettings['postmod_active'] || in_array('post_unapproved_attachments', $pbe['user_info']['permissions'])
			);

			// Make sure its valid
			attachmentChecks($attachID);
			$attachment_count++;
		}
	}

	// Get the results from attachmentChecks and see if its suitable for posting
	$attachments = $_SESSION['temp_attachments'];
	unset($_SESSION['temp_attachments']);
	foreach ($attachments as $attachID => $attachment)
	{
		// If there were any errors we just skip that file
		if (($attachID != 'initial_error' && strpos($attachID, 'post_tmp_' . $pbe['profile']['id_member']) === false) || ($attachID == 'initial_error' || !empty($attachment['errors'])))
		{
			@unlink($attachment['tmp_name']);
			continue;
		}

		// Load the attachmentOptions array with the data needed to create an attachment
		$attachmentOptions = array(
			'post' => !empty($email_message->message_id) ? $email_message->message_id : 0,
			'poster' => $pbe['profile']['id_member'],
			'name' => $attachment['name'],
			'tmp_name' => $attachment['tmp_name'],
			'size' => isset($attachment['size']) ? $attachment['size'] : 0,
			'mime_type' => isset($attachment['type']) ? $attachment['type'] : '',
			'id_folder' => isset($attachment['id_folder']) ? $attachment['id_folder'] : 0,
			'approved' => !$modSettings['postmod_active'] || allowedTo('post_attachment'),
			'errors' => array(),
		);

		// Make it available to the forum/post
		if (createAttachment($attachmentOptions))
		{
			$attachIDs[] = $attachmentOptions['id'];
			if (!empty($attachmentOptions['thumb']))
				$attachIDs[] = $attachmentOptions['thumb'];
		}
		// We had a problem so simply remove it
		elseif (file_exists($attachment['tmp_name']))
			@unlink($attachment['tmp_name']);
	}
	return $attachIDs;
}

/**
 * Used when a email attempts to start a new topic
 *  - Load the board id that a given email address is assigned
 *  - Returns the board number in which the new topic should go
 *
 * @param object $email_address
 * @param boolean $check
 */
function pbe_find_board_number($email_address, $check = false)
{
	global $modSettings;

	$valid_address = array();
	$board_number = 0;

	// load our valid email ids and the corresponding board ids
	$data = (!empty($modSettings['maillist_receiving_address'])) ? unserialize($modSettings['maillist_receiving_address']) : array();
	foreach ($data as $key => $addr)
		$valid_address[$addr[0]] = $addr[1];

	// Who was this message sent to, may have been sent to multiple addresses
	// so we check each one to see if we have a valid entry
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
 * Converts a post/pm to text for sending in an email
 * - censors everything it will send
 * - pre-converts select bbc tags to html so they can be markdowned properly
 * - uses parse-bbc to convert remaining bbc to html
 * - uses html2markdown to convert html to markdown text suitable for email
 * - if someone wants to write a direct bbc->markdown conversion tool, I'm listening!
 *
 * @param string $message
 * @param string $subject
 * @param string $signature
 */
function pbe_prepare_text(&$message, &$subject = '', &$signature = '')
{
	global $txt;

	loadLanguage('Maillist');

	// Check on some things needed by parse_bbc as autotask does not load em
	if (!isset($context['browser']))
		detectBrowser();

	// Server?
	if (!isset($context['server']))
		detectServer();

	// Clean it up.
	censorText($message);
	censorText($signature);
	$subject = un_htmlspecialchars($subject);

	// Convert bbc [quotes] before we go to parsebbc so they are easier to plain-textify later
	$message = preg_replace('~(\[quote)\s?author=(.*)\s?link=(.*)\s?date=([0-9]{10})(\])~seU', "'<blockquote>{$txt['email_on']}: ' . date('D M j, Y','\\4') . ' \\2 {$txt['email_wrote']}:'", $message);
	$message = preg_replace('~(\[quote\s?\])~sU', "'<blockquote>'", $message);
	$message = str_replace('[/quote]', "</blockquote>\n\n", $message);

	// Prevent img tags from getting linked
	$message = preg_replace('~\[img\](.*?)\[/img\]~is', '`&lt;img src="\\1">', $message);

	// Leave code tags as code tags for the conversion
	$message = preg_replace('~\[code(.*?)\](.*?)\[/code\]~is', '`&lt;code\\1>\\2`&lt;/code>', $message);

	// Convert the remaining bbc to html
	$message = parse_bbc($message, false);

	// Change list style to make text conversion easier
	$message = preg_replace('~<ul class=\"bbc_list\" style=\"list-style-type: decimal;\">(.*?)</ul>~si', "<ol>\\1</ol>", $message);

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
				$table_header .= '<th>- ' . $i . ' -</th>';

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
	require_once(EXTDIR . '/html2Md/html2markdown.php');
	$mark_down = new Convert_Md($message);
	$message = $mark_down->get_markdown();

	// Finally the sig, its just plain text
	if ($signature !== '')
	{
		$signature = parse_bbc($signature, false);
		$signature = trim(un_htmlspecialchars(strip_tags(strtr($signature, array('</tr>' => "   \n", '<br />' => "   \n", '</div>' => "\n", '</li>' => "   \n", '&#91;' => '[', '&#93;' => ']')))));
	}

	return;
}

/**
 * Loads up the vital user information given an email address
 *  - Similar to loadMemberData, loadPermissions, loadUserSettings, but only loads
 *    a subset of that data, enough to validate a user can make a post to a given
 *    board.  Done this way to avoid over-writting user_info etc for those who
 *    maybe running this function (on behalf of the email owner)
 * Sets
 *	- pbe['profile']
 *  - pbe['profile']['options']
 *  - pbe['user_info']
 *  - pbe['user_info']['permissions']
 * -  pbe['user_info']['groups']
 *
 * @param string $email
 */
function query_load_user_info($email)
{
	global $user_profile, $modSettings, $language;

	$db = database();

	if (empty($email))
		return false;

	// Find the user who owns this email address
	$request = $db->query('', '
		SELECT id_member
		FROM {db_prefix}members
		WHERE email_address = {string:email}
		AND is_activated = {int:act}
		LIMIT 1',
		array(
			'email' => $email,
			'act' => 1,
		)
	);
	list($id_member) = $db->fetch_row($request);
	$db->free_result($request);

	// No user found ... back we go
	if (empty($id_member))
		return false;

	// Load the users profile information
	$pbe = array();
	if (loadMemberData($id_member, false, 'profile'))
	{
		$pbe['profile'] = $user_profile[$id_member];

		// Load in *some* user_info data just like loadUserSettings would do
		if (empty($pbe['profile']['additional_groups']))
			$pbe['user_info']['groups'] = array(
				$pbe['profile']['id_group'], $pbe['profile']['id_post_group']);
		else
			$pbe['user_info']['groups'] = array_merge(
				array($pbe['profile']['id_group'], $pbe['profile']['id_post_group']),
				explode(',', $pbe['profile']['additional_groups'])
		);

		// Clean up the groups
		foreach ($pbe['user_info']['groups'] as $k => $v)
			$pbe['user_info']['groups'][$k] = (int) $v;
		$pbe['user_info']['groups'] = array_unique($pbe['user_info']['groups']);

		// Load the user's general permissions....
		query_load_permissions('general', $pbe);

		// Set the moderation warning level
		$pbe['user_info']['warning'] = isset($pbe['profile']['warning']) ? $pbe['profile']['warning'] : 0;

		// Work out our query_see_board string for security
		if (in_array(1, $pbe['user_info']['groups']))
			$pbe['user_info']['query_see_board'] = '1=1';
		else
			$pbe['user_info']['query_see_board'] = '((FIND_IN_SET(' . implode(', b.member_groups) != 0 OR FIND_IN_SET(', $pbe['user_info']['groups']) . ', b.member_groups) != 0)' . (!empty($modSettings['deny_boards_access']) ? ' AND (FIND_IN_SET(' . implode(', b.deny_member_groups) = 0 AND FIND_IN_SET(', $pbe['user_info']['groups']) . ', b.deny_member_groups) = 0)' : '') . ')';

		// Set some convenience items
		$pbe['user_info']['is_admin'] = in_array(1, $pbe['user_info']['groups']) ? 1 : 0;
		$pbe['user_info']['id'] = $id_member;
		$pbe['user_info']['username'] = isset($pbe['profile']['member_name']) ? $pbe['profile']['member_name'] : '';
		$pbe['user_info']['name'] = isset($pbe['profile']['real_name']) ? $pbe['profile']['real_name'] : '';
		$pbe['user_info']['email'] = isset($pbe['profile']['email_address']) ? $pbe['profile']['email_address'] : '';
		$pbe['user_info']['language'] = empty($pbe['profile']['lngfile']) || empty($modSettings['userLanguage']) ? $language :$pbe['profile']['lngfile'];
	}

	return !empty($pbe) ? $pbe : false;
}

/**
 * Load the users permissions either general or board specific
 *  - Similar to the functions in loadPermissions()
 *
 * @param string $type board to load board permissions, otherwise general permissions
 * @param array $pbe
 */
function query_load_permissions($type, &$pbe, $topic_info = array())
{
	global $modSettings;

	$db = database();

	$where_query = ($type === 'board' ? '({array_int:member_groups}) AND id_profile = {int:id_profile}' : '({array_int:member_groups})');

	// Load up the users board or general site permissions.
	$request = $db->query('', '
		SELECT permission, add_deny
		FROM {db_prefix}' . ($type === 'board' ? 'board_permissions' : 'permissions') . '
		WHERE id_group IN ' . $where_query,
		array(
			'member_groups' => $pbe['user_info']['groups'],
			'id_profile' => ($type === 'board') ? $topic_info['id_profile'] : '',
		)
	);
	$removals = array();
	$pbe['user_info']['permissions'] = array();
	// While we have results, put them in our yeah or nay arrays
	while ($row = $db->fetch_assoc($request))
	{
		if (empty($row['add_deny']))
			$removals[] = $row['permission'];
		else
			$pbe['user_info']['permissions'][] = $row['permission'];
	}
	$db->free_result($request);

	// Remove all the permissions they shouldn't have ;)
	if (!empty($modSettings['permission_enable_deny']))
		$pbe['user_info']['permissions'] = array_diff($pbe['user_info']['permissions'], $removals);
}

/**
 * Fetches the senders email wrapper details
 * - Gets the senders signature for inclusion in the email
 * - Gets the senders email address and visibility flag
 *
 * @param string $from
 */
function query_sender_wrapper($from)
{
	$db = database();

	$result = array();

	// The signature and email visibility details
	$request = $db->query('', '
	SELECT hide_email, email_address, signature
		FROM {db_prefix}members
		WHERE id_member  = {int:uid}
			AND is_activated = {int:act}
		LIMIT 1',
		array(
			'uid' => $from,
			'act' => 1,
		)
	);
	$result = $db->fetch_assoc($request);

	// Clean up the signature line
	if (!empty($result['signature']))
		$result['signature'] = trim(un_htmlspecialchars(strip_tags(strtr(parse_bbc($result['signature'], false), array('</tr>' => "   \n", '<br />' => "   \n", '</div>' => "\n", '</li>' => "   \n", '&#91;' => '[', '&#93;' => ']')))));

	$db->free_result($request);

	return $result;
}

/**
 * Reads all the keys that have been sent to a given email id
 *  - Returns all keys sent to a user in date order
 *
 * @param string $email email address to lookup
 */
function query_user_keys($email)
{
	$db = database();

	$keys = array();

	// Find all keys sent to this email, sorted by date
	$request = $db->query('', '
		SELECT id_email
		FROM {db_prefix}postby_emails
		WHERE email_to = {string:email}
		ORDER BY time_sent DESC',
		array(
			'email' => $email,
		)
	);
	while ($row = $db->fetch_assoc($request))
		$keys[] = $row;
	$db->free_result($request);

	return $keys;
}

/**
 * Return the email that a given key was sent to
 *
 * @param string $key security key
 * @return string email address the key was sent to
 */
function query_key_owner($key)
{
	$db = database();

	$email_to = false;

	// Check that this is a reply to an "actual" message by finding the key in the sent email table
	$request = $db->query('', '
		SELECT email_to
		FROM {db_prefix}postby_emails
		WHERE id_email = {string:database_id}
		LIMIT 1',
		array(
			'database_id' => $key
		)
	);
	list($email_to) = $db->fetch_row($request);
	$db->free_result($request);

	return $email_to;
}

/**
 * For a given type, t m or p, query the appropriate table for a given message id
 * If found returns the message subject
 *
 * @param int $message_id
 * @param string $message_type
 * @param string $email
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
			SELECT id_member
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
		if ($db->num_rows($request) !== 0)
		{
			list($id_member) = $db->fetch_row($request);
			$db->free_result($request);

			// Now find this PM ID and make sure it was sent to this member
			$request = $db->query('', '
				SELECT p.subject
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

	// if we found the message, topic or PM, return the subject
	if ($db->num_rows($request) != 0)
	{
		list($subject) = $db->fetch_row($request);
		$subject = pbe_clean_email_subject($subject);
	}
	$db->free_result($request);

	return $subject;
}

/**
 * Loads the important information for a given topic or pm ID
 *  - Returns array with the topic or PM details
 *
 * @param string $message_type
 * @param int $message_id
 * @param array $pbe
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
			SELECT p.id_pm, p.subject, p.id_member_from, p.id_pm_head
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
	// Found the information, load the topic_info array with the data for this topic and board
	if ($db->num_rows($request) !== 0)
		$topic_info = $db->fetch_assoc($request);
	$db->free_result($request);

	// Return the results or false
	return !empty($topic_info) ? $topic_info : false;
}

/**
 * Loads the board_id for where a given message resides
 *
 * @param int $message_id
 */
function query_load_board($message_id)
{
	$db = database();

	$request = $db->query('', '
		SELECT id_board
		FROM {db_prefix}messages
		WHERE id_msg = {int:message_id}',
		array(
			'message_id' => $message_id,
		)
	);

	list($board_id) = $db->fetch_row($request);
	$db->free_result($request);

	return $board_id === '' ? 0 : $board_id;
}

/**
 * Loads the basic board information for a given board id
 *
 * @param int $board_id
 */
function query_load_board_details($board_id, $pbe)
{
	$db = database();

	$board_info = array();

	// To post a NEW Topic, we need certain board details
	$request = $db->query('', '
		SELECT b.count_posts, b.id_profile, b.member_groups, b.id_theme, b.id_board
		FROM {db_prefix}boards as b
		WHERE {raw:query_see_board} AND id_board = {int:id_board}',
		array(
			'id_board' => $board_id,
			'query_see_board' => $pbe['user_info']['query_see_board'],
		)
	);
	$board_info = $db->fetch_assoc($request);
	$db->free_result($request);

	return $board_info;
}

/**
 * Loads the theme settings for the theme this user is using
 *  - Mainly used to determine a userws notify settings
 *
 * @param int $id_member
 * @param int $id_theme
 * @param array $board_info
 */
function query_get_theme($id_member, $id_theme, $board_info)
{
	global $modSettings;

	$db = database();

	// Verify the id_theme...
	// Allow the board specific theme, if they are overriding.
	if (!empty($board_info['id_theme']) && $board_info['override_theme'])
		$id_theme = (int) $board_info['id_theme'];
	elseif (!empty($modSettings['knownThemes']))
	{
		$themes = explode(',', $modSettings['knownThemes']);
		if (!in_array($id_theme, $themes))
			$id_theme = $modSettings['theme_guests'];
		else
			$id_theme = (int) $id_theme;
	}
	else
		$id_theme = (int) $id_theme;

	// With the theme and member, load the auto_notify variables
	$result = $db->query('', '
		SELECT variable, value
		FROM {db_prefix}themes
		WHERE id_member = {int:id_member}
			AND id_theme = {int:id_theme}',
		array(
			'id_theme' => $id_theme,
			'id_member' => $id_member,
		)
	);

	// Put everything about this member/theme into a theme setting array
	$theme_settings = array();
	while ($row = $db->fetch_assoc($result))
		$theme_settings[$row['variable']] = $row['value'];

	$db->free_result($result);

	return $theme_settings;
}

/**
 * Turn notifications on or off if the user has set auto notify 'when I reply'
 *
 * @param int $id_member
 * @param int $id_board
 * @param int $id_topic
 * @param boolean $auto_notify
 */
function query_notifications($id_member, $id_board, $id_topic, $auto_notify)
{
	$db = database();

	// First see if they have a board notification on for this board
	// so we don't set both board and individual topic notifications
	$board_notify = false;
	$request = $db->query('', '
		SELECT id_member
		FROM {db_prefix}log_notify
		WHERE id_board = {int:board_list}
			AND id_member = {int:current_member}',
		array(
			'current_member' => $id_member,
			'board_list' => $id_board,
		)
	);
	if ($db->fetch_row($request))
		$board_notify = true;
	$db->free_result($request);

	// If they have topic notification on and not board notification then
	// add this post to the notification log
	if (!empty($auto_notify) && (in_array('mark_any_notify', $pbe['user_info']['permissions'])) && !$board_notify)
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
 *  - Marks the PM replied to as read
 *  - Marks the PM replied to as replied to
 *  - Updates the number of unread to reflect this
 *
 * @param array $pbe
 */
function query_mark_pms($email_message, $pbe)
{
	$db = database();

	$db->query('', '
		UPDATE {db_prefix}pm_recipients
		SET is_read = is_read | 1
		WHERE id_member = {int:id_member}
			AND NOT (is_read & 1 >= 1)
			AND id_pm = {int:personal_messages}',
		array(
			'personal_messages' => $email_message->message_id,
			'id_member' => $pbe['profile']['id_member'],
		)
	);

	// If something was marked as read, get the number of unread messages remaining.
	if ($db->affected_rows() > 0)
	{
		$result = $db->query('', '
			SELECT labels, COUNT(*) AS num
			FROM {db_prefix}pm_recipients
			WHERE id_member = {int:id_member}
				AND NOT (is_read & 1 >= 1)
				AND deleted = {int:is_not_deleted}
			GROUP BY labels',
			array(
				'id_member' => $pbe['profile']['id_member'],
				'is_not_deleted' => 0,
			)
		);
		$total_unread = 0;
		while ($row = $db->fetch_assoc($result))
			$total_unread += $row['num'];
		$db->free_result($result);

		// Update things for when they come to the site
		updateMemberData($pbe['profile']['id_member'], array('unread_messages' => $total_unread));
	}

	// Now mark the message as "replied to" since they just did
	$db->query('', '
		UPDATE {db_prefix}pm_recipients
		SET is_read = is_read | 2
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
 *  - Removes old keys are removed to minimize security issues
 *
 * @param object $email_message
 */
function query_key_maintenance($email_message)
{
	global $modSettings;

	$db = database();

	$days = (!empty($modSettings['maillist_key_active'])) ? $modSettings['maillist_key_active'] : 21;
	$delete_old = time() - ($days * 24 * 60 * 60);

	// Consume the database key that was just used .. one reply per key
	// but we let PM's slide, they often seem to be re re re replied to
	if ($email_message->message_type !== 'p')
	{
		$request = $db->query('', '
			DELETE FROM {db_prefix}postby_emails
			WHERE id_email = {string:message_key_id}',
			array(
				'message_key_id' => $email_message->message_key_id,
			)
		);
	}

	// Since we are here lets delete any items older than delete_old days,
	// if they have not responded in that time tuff
	$request = $db->query('', '
		DELETE FROM {db_prefix}postby_emails
		WHERE time_sent < {int:delete_old}',
		array(
			'delete_old' => $delete_old
		)
	);
}

/**
 * After a email post has been made, this updates the users information like
 * they had been on the site to perform the given action.
 *  - Updates time on line
 *  - Updates last active
 *  - Updates the who's online list with the member and action
 *
 * @param array $pbe
 * @param object $email_message
 * @param array $topic_info
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
		$total_time_logged_in = $total_time_logged_in + (60 * 10);
	}

	// Update the members total time logged in data
	$db->query('', '
		UPDATE {db_prefix}members
		SET total_time_logged_in = {int:total_time_logged_in},
			last_login = {int:last_login}
		WHERE id_member = {int:member}
		LIMIT 1',
		array(
			'member' => $pbe['profile']['id_member'],
			'last_login' => $last_login,
			'total_time_logged_in' => $total_time_logged_in
		)
	);

	// Show they are active in the who's online list and what they have done
	if ($email_message->message_type === 'm' || $email_message->message_type === 't')
		$get_temp = array(
			'action' => 'postbyemail',
			'topic' => $topic_info['id_topic'],
			'last_msg' => $topic_info['id_last_msg'],
			'board' => $topic_info['id_board']
		);
	elseif ($email_message->message_type === 'x')
		$get_temp = array(
			'action' => 'topicbyemail',
			'topic' => $topic_info['id'],
			'board' => $topic_info['board'],
		);
	else
		$get_temp = array(
			'action' => 'pm',
			'sa' => 'byemail'
		);

	// Place the entry in to the online log so the who's online can use it
	$serialized = serialize($get_temp);
	$session_id = 'ip' . $pbe['profile']['member_ip'];
	$db->insert($do_delete ? 'ignore' : 'replace',
		'{db_prefix}log_online',
		array('session' => 'string', 'id_member' => 'int', 'id_spider' => 'int', 'log_time' => 'int', 'ip' => 'raw', 'url' => 'string'),
		array($session_id, $pbe['profile']['id_member'], 0, $last_login, 'IFNULL(INET_ATON(\'' . $pbe['profile']['member_ip'] . '\'), 0)', $serialized),
		array('session')
	);
}