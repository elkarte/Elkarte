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
 * This file contains those functions pertaining to posting, and other such
 * operations, including sending emails, ims, blocking spam, preparsing posts,
 * spell checking, and the post box.
 *
 */

if (!defined('ELKARTE'))
	die('No access...');

/**
 * Takes a message and parses it, returning nothing.
 * Cleans up links (javascript, etc.) and code/quote sections.
 * Won't convert \n's and a few other things if previewing is true.
 *
 * @param $message
 * @param $previewing
 */
function preparsecode(&$message, $previewing = false)
{
	global $user_info;

	// This line makes all languages *theoretically* work even with the wrong charset ;).
	$message = preg_replace('~&amp;#(\d{4,5}|[2-9]\d{2,4}|1[2-9]\d);~', '&#$1;', $message);

	// Clean up after nobbc ;).
	$message = preg_replace('~\[nobbc\](.+?)\[/nobbc\]~ie', '\'[nobbc]\' . strtr(\'$1\', array(\'[\' => \'&#91;\', \']\' => \'&#93;\', \':\' => \'&#58;\', \'@\' => \'&#64;\')) . \'[/nobbc]\'', $message);

	// Remove \r's... they're evil!
	$message = strtr($message, array("\r" => ''));

	// You won't believe this - but too many periods upsets apache it seems!
	$message = preg_replace('~\.{100,}~', '...', $message);

	// Trim off trailing quotes - these often happen by accident.
	while (substr($message, -7) == '[quote]')
		$message = substr($message, 0, -7);
	while (substr($message, 0, 8) == '[/quote]')
		$message = substr($message, 8);

	// Find all code blocks, work out whether we'd be parsing them, then ensure they are all closed.
	$in_tag = false;
	$had_tag = false;
	$codeopen = 0;
	if (preg_match_all('~(\[(/)*code(?:=[^\]]+)?\])~is', $message, $matches))
		foreach ($matches[0] as $index => $dummy)
		{
			// Closing?
			if (!empty($matches[2][$index]))
			{
				// If it's closing and we're not in a tag we need to open it...
				if (!$in_tag)
					$codeopen = true;
				// Either way we ain't in one any more.
				$in_tag = false;
			}
			// Opening tag...
			else
			{
				$had_tag = true;
				// If we're in a tag don't do nought!
				if (!$in_tag)
					$in_tag = true;
			}
		}

	// If we have an open tag, close it.
	if ($in_tag)
		$message .= '[/code]';

	// Open any ones that need to be open, only if we've never had a tag.
	if ($codeopen && !$had_tag)
		$message = '[code]' . $message;

	// Now that we've fixed all the code tags, let's fix the img and url tags...
	$parts = preg_split('~(\[/code\]|\[code(?:=[^\]]+)?\])~i', $message, -1, PREG_SPLIT_DELIM_CAPTURE);

	// The regular expression non breaking space.
	$non_breaking_space = '\x{A0}';

	// Only mess with stuff outside [code] tags.
	for ($i = 0, $n = count($parts); $i < $n; $i++)
	{
		// It goes 0 = outside, 1 = begin tag, 2 = inside, 3 = close tag, repeat.
		if ($i % 4 == 0)
		{
			fixTags($parts[$i]);

			// Replace /me.+?\n with [me=name]dsf[/me]\n.
			if (strpos($user_info['name'], '[') !== false || strpos($user_info['name'], ']') !== false || strpos($user_info['name'], '\'') !== false || strpos($user_info['name'], '"') !== false)
				$parts[$i] = preg_replace('~(\A|\n)/me(?: |&nbsp;)([^\n]*)(?:\z)?~i', '$1[me=&quot;' . $user_info['name'] . '&quot;]$2[/me]', $parts[$i]);
			else
				$parts[$i] = preg_replace('~(\A|\n)/me(?: |&nbsp;)([^\n]*)(?:\z)?~i', '$1[me=' . $user_info['name'] .  ']$2[/me]', $parts[$i]);

			if (!$previewing && strpos($parts[$i], '[html]') !== false)
			{
				if (allowedTo('admin_forum'))
					$parts[$i] = preg_replace('~\[html\](.+?)\[/html\]~ise', '\'[html]\' . strtr(un_htmlspecialchars(\'$1\'), array("\n" => \'&#13;\', \'  \' => \' &#32;\', \'[\' => \'&#91;\', \']\' => \'&#93;\')) . \'[/html]\'', $parts[$i]);

				// We should edit them out, or else if an admin edits the message they will get shown...
				else
				{
					while (strpos($parts[$i], '[html]') !== false)
						$parts[$i] = preg_replace('~\[[/]?html\]~i', '', $parts[$i]);
				}
			}

			// Let's look at the time tags...
			$parts[$i] = preg_replace('~\[time(?:=(absolute))*\](.+?)\[/time\]~ie', '\'[time]\' . (is_numeric(\'$2\') || @strtotime(\'$2\') == 0 ? \'$2\' : strtotime(\'$2\') - (\'$1\' == \'absolute\' ? 0 : (($modSettings[\'time_offset\'] + $user_info[\'time_offset\']) * 3600))) . \'[/time]\'', $parts[$i]);

			// Change the color specific tags to [color=the color].
			$parts[$i] = preg_replace('~\[(black|blue|green|red|white)\]~', '[color=$1]', $parts[$i]);  // First do the opening tags.
			$parts[$i] = preg_replace('~\[/(black|blue|green|red|white)\]~', '[/color]', $parts[$i]);   // And now do the closing tags

			// Make sure all tags are lowercase.
			$parts[$i] = preg_replace('~\[([/]?)(list|li|table|tr|td)((\s[^\]]+)*)\]~ie', '\'[$1\' . strtolower(\'$2\') . \'$3]\'', $parts[$i]);

			$list_open = substr_count($parts[$i], '[list]') + substr_count($parts[$i], '[list ');
			$list_close = substr_count($parts[$i], '[/list]');
			if ($list_close - $list_open > 0)
				$parts[$i] = str_repeat('[list]', $list_close - $list_open) . $parts[$i];
			if ($list_open - $list_close > 0)
				$parts[$i] = $parts[$i] . str_repeat('[/list]', $list_open - $list_close);

			$mistake_fixes = array(
				// Find [table]s not followed by [tr].
				'~\[table\](?![\s' . $non_breaking_space . ']*\[tr\])~su' => '[table][tr]',
				// Find [tr]s not followed by [td].
				'~\[tr\](?![\s' . $non_breaking_space . ']*\[td\])~su' => '[tr][td]',
				// Find [/td]s not followed by something valid.
				'~\[/td\](?![\s' . $non_breaking_space . ']*(?:\[td\]|\[/tr\]|\[/table\]))~su' => '[/td][/tr]',
				// Find [/tr]s not followed by something valid.
				'~\[/tr\](?![\s' . $non_breaking_space . ']*(?:\[tr\]|\[/table\]))~su' => '[/tr][/table]',
				// Find [/td]s incorrectly followed by [/table].
				'~\[/td\][\s' . $non_breaking_space . ']*\[/table\]~su' => '[/td][/tr][/table]',
				// Find [table]s, [tr]s, and [/td]s (possibly correctly) followed by [td].
				'~\[(table|tr|/td)\]([\s' . $non_breaking_space . ']*)\[td\]~su' => '[$1]$2[_td_]',
				// Now, any [td]s left should have a [tr] before them.
				'~\[td\]~s' => '[tr][td]',
				// Look for [tr]s which are correctly placed.
				'~\[(table|/tr)\]([\s' . $non_breaking_space . ']*)\[tr\]~su' => '[$1]$2[_tr_]',
				// Any remaining [tr]s should have a [table] before them.
				'~\[tr\]~s' => '[table][tr]',
				// Look for [/td]s followed by [/tr].
				'~\[/td\]([\s' . $non_breaking_space . ']*)\[/tr\]~su' => '[/td]$1[_/tr_]',
				// Any remaining [/tr]s should have a [/td].
				'~\[/tr\]~s' => '[/td][/tr]',
				// Look for properly opened [li]s which aren't closed.
				'~\[li\]([^\[\]]+?)\[li\]~s' => '[li]$1[_/li_][_li_]',
				'~\[li\]([^\[\]]+?)\[/list\]~s' => '[_li_]$1[_/li_][/list]',
				'~\[li\]([^\[\]]+?)$~s' => '[li]$1[/li]',
				// Lists - find correctly closed items/lists.
				'~\[/li\]([\s' . $non_breaking_space . ']*)\[/list\]~su' => '[_/li_]$1[/list]',
				// Find list items closed and then opened.
				'~\[/li\]([\s' . $non_breaking_space . ']*)\[li\]~su' => '[_/li_]$1[_li_]',
				// Now, find any [list]s or [/li]s followed by [li].
				'~\[(list(?: [^\]]*?)?|/li)\]([\s' . $non_breaking_space . ']*)\[li\]~su' => '[$1]$2[_li_]',
				// Allow for sub lists.
				'~\[/li\]([\s' . $non_breaking_space . ']*)\[list\]~u' => '[_/li_]$1[list]',
				'~\[/list\]([\s' . $non_breaking_space . ']*)\[li\]~u' => '[/list]$1[_li_]',
				// Any remaining [li]s weren't inside a [list].
				'~\[li\]~' => '[list][li]',
				// Any remaining [/li]s weren't before a [/list].
				'~\[/li\]~' => '[/li][/list]',
				// Put the correct ones back how we found them.
				'~\[_(li|/li|td|tr|/tr)_\]~' => '[$1]',
				// Images with no real url.
				'~\[img\]https?://.{0,7}\[/img\]~' => '',
			);

			// Fix up some use of tables without [tr]s, etc. (it has to be done more than once to catch it all.)
			for ($j = 0; $j < 3; $j++)
				$parts[$i] = preg_replace(array_keys($mistake_fixes), $mistake_fixes, $parts[$i]);

			// Now we're going to do full scale table checking...
			$table_check = $parts[$i];
			$table_offset = 0;
			$table_array = array();
			$table_order = array(
				'table' => 'td',
				'tr' => 'table',
				'td' => 'tr',
			);
			while (preg_match('~\[(/)*(table|tr|td)\]~', $table_check, $matches) != false)
			{
				// Keep track of where this is.
				$offset = strpos($table_check, $matches[0]);
				$remove_tag = false;

				// Is it opening?
				if ($matches[1] != '/')
				{
					// If the previous table tag isn't correct simply remove it.
					if ((!empty($table_array) && $table_array[0] != $table_order[$matches[2]]) || (empty($table_array) && $matches[2] != 'table'))
						$remove_tag = true;
					// Record this was the last tag.
					else
						array_unshift($table_array, $matches[2]);
				}
				// Otherwise is closed!
				else
				{
					// Only keep the tag if it's closing the right thing.
					if (empty($table_array) || ($table_array[0] != $matches[2]))
						$remove_tag = true;
					else
						array_shift($table_array);
				}

				// Removing?
				if ($remove_tag)
				{
					$parts[$i] = substr($parts[$i], 0, $table_offset + $offset) . substr($parts[$i], $table_offset + strlen($matches[0]) + $offset);
					// We've lost some data.
					$table_offset -= strlen($matches[0]);
				}

				// Remove everything up to here.
				$table_offset += $offset + strlen($matches[0]);
				$table_check = substr($table_check, $offset + strlen($matches[0]));
			}

			// Close any remaining table tags.
			foreach ($table_array as $tag)
				$parts[$i] .= '[/' . $tag . ']';

			// Remove empty bbc from the sections outside the code tags
			$parts[$i] = preg_replace('~\[[bisu]\]\s*\[/[bisu]\]~', '', $parts[$i]);
			$parts[$i] = preg_replace('~\[quote\]\s*\[/quote\]~', '', $parts[$i]);
			$parts[$i] = preg_replace('~\[color=(?:#[\da-fA-F]{3}|#[\da-fA-F]{6}|[A-Za-z]{1,20}|rgb\(\d{1,3}, ?\d{1,3}, ?\d{1,3}\))\]\s*\[/color\]~', '', $parts[$i]);
		}

		call_integration_hook('integrate_preparse_code', array(&$parts[$i]));
	}

	// Put it back together!
	if (!$previewing)
		$message = strtr(implode('', $parts), array('  ' => '&nbsp; ', "\n" => '<br />', "\xC2\xA0" => '&nbsp;'));
	else
		$message = strtr(implode('', $parts), array('  ' => '&nbsp; ', "\xC2\xA0" => '&nbsp;'));

	// Now let's quickly clean up things that will slow our parser (which are common in posted code.)
	$message = strtr($message, array('[]' => '&#91;]', '[&#039;' => '&#91;&#039;'));
}

/**
 * This is very simple, and just removes things done by preparsecode.
 *
 * @param $message
 */
function un_preparsecode($message)
{
	$parts = preg_split('~(\[/code\]|\[code(?:=[^\]]+)?\])~i', $message, -1, PREG_SPLIT_DELIM_CAPTURE);

	// We're going to unparse only the stuff outside [code]...
	for ($i = 0, $n = count($parts); $i < $n; $i++)
	{
		// If $i is a multiple of four (0, 4, 8, ...) then it's not a code section...
		if ($i % 4 == 0)
		{
			$parts[$i] = preg_replace('~\[html\](.+?)\[/html\]~ie', '\'[html]\' . strtr(htmlspecialchars(\'$1\', ENT_QUOTES), array(\'\\&quot;\' => \'&quot;\', \'&amp;#13;\' => \'<br />\', \'&amp;#32;\' => \' \', \'&amp;#91;\' => \'[\', \'&amp;#93;\' => \']\')) . \'[/html]\'', $parts[$i]);
			// $parts[$i] = preg_replace('~\[html\](.+?)\[/html\]~ie', '\'[html]\' . strtr(htmlspecialchars(\'$1\', ENT_QUOTES), array(\'\\&quot;\' => \'&quot;\', \'&amp;#13;\' => \'<br />\', \'&amp;#32;\' => \' \', \'&amp;#38;\' => \'&#38;\', \'&amp;#91;\' => \'[\', \'&amp;#93;\' => \']\')) . \'[/html]\'', $parts[$i]);

			// Attempt to un-parse the time to something less awful.
			$parts[$i] = preg_replace('~\[time\](\d{0,10})\[/time\]~ie', '\'[time]\' . standardTime(\'$1\', false) . \'[/time]\'', $parts[$i]);
		}

		call_integration_hook('integrate_unpreparse_code', array(&$message, &$parts, &$i));
	}

	// Change breaks back to \n's and &nsbp; back to spaces.
	return preg_replace('~<br( /)?' . '>~', "\n", str_replace('&nbsp;', ' ', implode('', $parts)));
}

/**
 * Fix any URLs posted - ie. remove 'javascript:'.
 * Used by preparsecode, fixes links in message and returns nothing.
 *
 * @param string $message
 */
function fixTags(&$message)
{
	global $modSettings;

	// WARNING: Editing the below can cause large security holes in your forum.
	// Edit only if you are sure you know what you are doing.

	$fixArray = array(
		// [img]http://...[/img] or [img width=1]http://...[/img]
		array(
			'tag' => 'img',
			'protocols' => array('http', 'https'),
			'embeddedUrl' => false,
			'hasEqualSign' => false,
			'hasExtra' => true,
		),
		// [url]http://...[/url]
		array(
			'tag' => 'url',
			'protocols' => array('http', 'https'),
			'embeddedUrl' => true,
			'hasEqualSign' => false,
		),
		// [url=http://...]name[/url]
		array(
			'tag' => 'url',
			'protocols' => array('http', 'https'),
			'embeddedUrl' => true,
			'hasEqualSign' => true,
		),
		// [iurl]http://...[/iurl]
		array(
			'tag' => 'iurl',
			'protocols' => array('http', 'https'),
			'embeddedUrl' => true,
			'hasEqualSign' => false,
		),
		// [iurl=http://...]name[/iurl]
		array(
			'tag' => 'iurl',
			'protocols' => array('http', 'https'),
			'embeddedUrl' => true,
			'hasEqualSign' => true,
		),
		// [ftp]ftp://...[/ftp]
		array(
			'tag' => 'ftp',
			'protocols' => array('ftp', 'ftps'),
			'embeddedUrl' => true,
			'hasEqualSign' => false,
		),
		// [ftp=ftp://...]name[/ftp]
		array(
			'tag' => 'ftp',
			'protocols' => array('ftp', 'ftps'),
			'embeddedUrl' => true,
			'hasEqualSign' => true,
		),
		// [flash]http://...[/flash]
		array(
			'tag' => 'flash',
			'protocols' => array('http', 'https'),
			'embeddedUrl' => false,
			'hasEqualSign' => false,
			'hasExtra' => true,
		),
	);

	call_integration_hook('integrate_fixtags', array(&$fixArray, &$message));
	// Fix each type of tag.
	foreach ($fixArray as $param)
		fixTag($message, $param['tag'], $param['protocols'], $param['embeddedUrl'], $param['hasEqualSign'], !empty($param['hasExtra']));

	// Now fix possible security problems with images loading links automatically...
	$message = preg_replace('~(\[img.*?\])(.+?)\[/img\]~eis', '\'$1\' . preg_replace(\'~action(=|%3d)(?!dlattach)~i\', \'action-\', \'$2\') . \'[/img]\'', $message);

	// Limit the size of images posted?
	if (!empty($modSettings['max_image_width']) || !empty($modSettings['max_image_height']))
		resizeBBCImages($message);
}

/**
 * Fix a specific class of tag - ie. url with =.
 * Used by fixTags, fixes a specific tag's links.
 *
 * @param string $message
 * @param string $myTag - the tag
 * @param string $protocols - http or ftp
 * @param bool $embeddedUrl = false - whether it *can* be set to something
 * @param bool $hasEqualSign = false, whether it *is* set to something
 * @param bool $hasExtra = false - whether it can have extra cruft after the begin tag.
 */
function fixTag(&$message, $myTag, $protocols, $embeddedUrl = false, $hasEqualSign = false, $hasExtra = false)
{
	global $boardurl, $scripturl;

	if (preg_match('~^([^:]+://[^/]+)~', $boardurl, $match) != 0)
		$domain_url = $match[1];
	else
		$domain_url = $boardurl . '/';

	$replaces = array();

	if ($hasEqualSign)
		preg_match_all('~\[(' . $myTag . ')=([^\]]*?)\](?:(.+?)\[/(' . $myTag . ')\])?~is', $message, $matches);
	else
		preg_match_all('~\[(' . $myTag . ($hasExtra ? '(?:[^\]]*?)' : '') . ')\](.+?)\[/(' . $myTag . ')\]~is', $message, $matches);

	foreach ($matches[0] as $k => $dummy)
	{
		// Remove all leading and trailing whitespace.
		$replace = trim($matches[2][$k]);
		$this_tag = $matches[1][$k];
		$this_close = $hasEqualSign ? (empty($matches[4][$k]) ? '' : $matches[4][$k]) : $matches[3][$k];

		$found = false;
		foreach ($protocols as $protocol)
		{
			$found = strncasecmp($replace, $protocol . '://', strlen($protocol) + 3) === 0;
			if ($found)
				break;
		}

		if (!$found && $protocols[0] == 'http')
		{
			if (substr($replace, 0, 1) == '/')
				$replace = $domain_url . $replace;
			elseif (substr($replace, 0, 1) == '?')
				$replace = $scripturl . $replace;
			elseif (substr($replace, 0, 1) == '#' && $embeddedUrl)
			{
				$replace = '#' . preg_replace('~[^A-Za-z0-9_\-#]~', '', substr($replace, 1));
				$this_tag = 'iurl';
				$this_close = 'iurl';
			}
			else
				$replace = $protocols[0] . '://' . $replace;
		}
		elseif (!$found && $protocols[0] == 'ftp')
			$replace = $protocols[0] . '://' . preg_replace('~^(?!ftps?)[^:]+://~', '', $replace);
		elseif (!$found)
			$replace = $protocols[0] . '://' . $replace;

		if ($hasEqualSign && $embeddedUrl)
			$replaces[$matches[0][$k]] = '[' . $this_tag . '=' . $replace . ']' . (empty($matches[4][$k]) ? '' : $matches[3][$k] . '[/' . $this_close . ']');
		elseif ($hasEqualSign)
			$replaces['[' . $matches[1][$k] . '=' . $matches[2][$k] . ']'] = '[' . $this_tag . '=' . $replace . ']';
		elseif ($embeddedUrl)
			$replaces['[' . $matches[1][$k] . ']' . $matches[2][$k] . '[/' . $matches[3][$k] . ']'] = '[' . $this_tag . '=' . $replace . ']' . $matches[2][$k] . '[/' . $this_close . ']';
		else
			$replaces['[' . $matches[1][$k] . ']' . $matches[2][$k] . '[/' . $matches[3][$k] . ']'] = '[' . $this_tag . ']' . $replace . '[/' . $this_close . ']';
	}

	foreach ($replaces as $k => $v)
	{
		if ($k == $v)
			unset($replaces[$k]);
	}

	if (!empty($replaces))
		$message = strtr($message, $replaces);
}

function resizeBBCImages(&$message)
{
	global $modSettings;

	// We'll need this for image processing
	require_once(SUBSDIR . '/Attachments.subs.php');

	// Find all the img tags - with or without width and height.
	preg_match_all('~\[img(\s+width=\d+)?(\s+height=\d+)?(\s+width=\d+)?\](.+?)\[/img\]~is', $message, $matches, PREG_PATTERN_ORDER);

	$replaces = array();
	foreach ($matches[0] as $match => $dummy)
	{
		// If the width was after the height, handle it.
		$matches[1][$match] = !empty($matches[3][$match]) ? $matches[3][$match] : $matches[1][$match];

		// Now figure out if they had a desired height or width...
		$desired_width = !empty($matches[1][$match]) ? (int) substr(trim($matches[1][$match]), 6) : 0;
		$desired_height = !empty($matches[2][$match]) ? (int) substr(trim($matches[2][$match]), 7) : 0;

		// One was omitted, or both.  We'll have to find its real size...
		if (empty($desired_width) || empty($desired_height))
		{
			list ($width, $height) = url_image_size(un_htmlspecialchars($matches[4][$match]));

			// They don't have any desired width or height!
			if (empty($desired_width) && empty($desired_height))
			{
				$desired_width = $width;
				$desired_height = $height;
			}
			// Scale it to the width...
			elseif (empty($desired_width) && !empty($height))
				$desired_width = (int) (($desired_height * $width) / $height);
			// Scale if to the height.
			elseif (!empty($width))
				$desired_height = (int) (($desired_width * $height) / $width);
		}

		// If the width and height are fine, just continue along...
		if ($desired_width <= $modSettings['max_image_width'] && $desired_height <= $modSettings['max_image_height'])
			continue;

		// Too bad, it's too wide.  Make it as wide as the maximum.
		if ($desired_width > $modSettings['max_image_width'] && !empty($modSettings['max_image_width']))
		{
			$desired_height = (int) (($modSettings['max_image_width'] * $desired_height) / $desired_width);
			$desired_width = $modSettings['max_image_width'];
		}

		// Now check the height, as well.  Might have to scale twice, even...
		if ($desired_height > $modSettings['max_image_height'] && !empty($modSettings['max_image_height']))
		{
			$desired_width = (int) (($modSettings['max_image_height'] * $desired_width) / $desired_height);
			$desired_height = $modSettings['max_image_height'];
		}

		$replaces[$matches[0][$match]] = '[img' . (!empty($desired_width) ? ' width=' . $desired_width : '') . (!empty($desired_height) ? ' height=' . $desired_height : '') . ']' . $matches[4][$match] . '[/img]';
	}

	// If any img tags were actually changed...
	if (!empty($replaces))
		$message = strtr($message, $replaces);
}

/**
 * Sends a notification to members who have elected to receive emails
 * when things happen to a topic, such as replies are posted.
 * The function automatically finds the subject and its board, and
 * checks permissions for each member who is "signed up" for notifications.
 * It will not send 'reply' notifications more than once in a row.
 *
 * @param array $topics - represents the topics the action is happening to.
 * @param string $type - can be any of reply, sticky, lock, unlock, remove,
 *  move, merge, and split.  An appropriate message will be sent for each.
 * @param array $exclude = array() - members in the exclude array will not be
 *  processed for the topic with the same key.
 * @param array $members_only = array() - are the only ones that will be sent the notification if they have it on.
 * @uses Post language file
 */
function sendNotifications($topics, $type, $exclude = array(), $members_only = array(), $pbe = array())
{
	global $txt, $scripturl, $language, $user_info, $webmaster_email, $mbname;
	global $modSettings;

	$db = database();

	// Coming in from emailpost or emailtopic, if so pbe values will be set to the credentials of the emailer
	$user_id = (!empty($pbe['user_info']['id']) && !empty($modSettings['maillist_enabled'])) ? $pbe['user_info']['id'] : $user_info['id'];
	$user_language = (!empty($pbe['user_info']['language']) && !empty($modSettings['maillist_enabled'])) ? $pbe['user_info']['language'] : $user_info['language'];

	// Load in our dependencies
	$maillist = !empty($modSettings['maillist_enabled']) && !empty($modSettings['pbe_post_enabled']);
	if ($maillist)
		require_once(SUBSDIR . '/Emailpost.subs.php');
	require_once(SUBSDIR . '/Mail.subs.php');

	// Can't do it if there's no topics.
	if (empty($topics))
		return;

	// It must be an array - it must!
	if (!is_array($topics))
		$topics = array($topics);

	// I hope we are not sending one of silly moderation notices
	if ($type !== 'reply' && !empty($maillist) && !empty($modSettings['pbe_no_mod_notices']))
		return;

	// Get the subject, body and basic poster details
	$result = $db->query('', '
		SELECT mf.subject, ml.body, ml.id_member, t.id_last_msg, t.id_topic, t.id_board, mem.signature,
			IFNULL(mem.real_name, ml.poster_name) AS poster_name
		FROM {db_prefix}topics AS t
			INNER JOIN {db_prefix}messages AS mf ON (mf.id_msg = t.id_first_msg)
			INNER JOIN {db_prefix}messages AS ml ON (ml.id_msg = t.id_last_msg)
			LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = ml.id_member)
		WHERE t.id_topic IN ({array_int:topic_list})
		LIMIT 1',
		array(
			'topic_list' => $topics,
		)
	);
	$topicData = array();
	$boards_index = array();
	while ($row = $db->fetch_assoc($result))
	{
		// Any attachments, we just want the number of them for display
		$num_attachments = 0;
		$request = $db->query('', '
			SELECT COUNT(id_attach)
			FROM {db_prefix}attachments
			WHERE attachment_type = {int:attachment_type}
				AND id_msg = {int:id_msg}
			LIMIT 1',
			array(
				'attachment_type' => 0,
				'id_msg' => $row['id_last_msg'],
			)
		);
		list($num_attachments) = $db->fetch_row($request);
		$db->free_result($request);

		// Using the maillist function or the standard style
		if ($maillist)
		{
			// Convert to markdown markup e.g. text ;)
			pbe_prepare_text($row['body'], $row['subject'], $row['signature']);
		}
		else
		{
			// Clean it up.
			censorText($row['subject']);
			censorText($row['body']);
			censorText($row['signature']);
			$row['subject'] = un_htmlspecialchars($row['subject']);
			$row['body'] = trim(un_htmlspecialchars(strip_tags(strtr(parse_bbc($row['body'], false, $row['id_last_msg']), array('<br />' => "\n", '</div>' => "\n", '</li>' => "\n", '&#91;' => '[', '&#93;' => ']')))));
		}

		// all the boards for these topics, used to find all the members to be notified
		$boards_index[] = $row['id_board'];

		$topicData[$row['id_topic']] = array(
			'subject' => $row['subject'],
			'body' => $row['body'],
			'last_id' => $row['id_last_msg'],
			'topic' => $row['id_topic'],
			'board' => $row['id_board'],
			'name' => $row['poster_name'],
			'exclude' => '',
			'signature' => $row['signature'],
			'attachments' => $num_attachments,
		);
	}
	$db->free_result($result);

	// Work out any exclusions...
	foreach ($topics as $key => $id)
		if (isset($topicData[$id]) && !empty($exclude[$key]))
			$topicData[$id]['exclude'] = (int) $exclude[$key];

	// Nada?
	if (empty($topicData))
		trigger_error('sendNotifications(): topics not found', E_USER_NOTICE);

	$topics = array_keys($topicData);

	// Just in case they've gone walkies.
	if (empty($topics))
		return;

	// Insert all of these items into the digest log for those who want notifications later.
	$digest_insert = array();
	foreach ($topicData as $id => $data)
		$digest_insert[] = array($data['topic'], $data['last_id'], $type, (int) $data['exclude']);

	$db->insert('',
		'{db_prefix}log_digest',
		array(
			'id_topic' => 'int', 'id_msg' => 'int', 'note_type' => 'string', 'exclude' => 'int',
		),
		$digest_insert,
		array()
	);

	// Are we doing anything here?
	$sent = 0;

	// Using the posting email function in either group or list mode
	if ($maillist)
	{
		// Find the members with *board* notifications on.
		$members = $db->query('', '
			SELECT
				mem.id_member, mem.email_address, mem.notify_regularity, mem.notify_types, mem.notify_send_body, mem.lngfile, mem.warning,
				ln.sent, mem.id_group, mem.additional_groups, b.member_groups, mem.id_post_group, b.name, b.id_profile,
				ln.id_board
			FROM {db_prefix}log_notify AS ln
				INNER JOIN {db_prefix}boards AS b ON (b.id_board = ln.id_board)
				INNER JOIN {db_prefix}members AS mem ON (mem.id_member = ln.id_member)
			WHERE ln.id_board IN ({array_int:board_list})
				AND mem.notify_types != {int:notify_types}
				AND mem.notify_regularity < {int:notify_regularity}
				AND mem.is_activated = {int:is_activated}
				AND ln.id_member != {int:current_member}' .
				(empty($members_only) ? '' : ' AND ln.id_member IN ({array_int:members_only})') . '
			ORDER BY mem.lngfile',
			array(
				'current_member' => $user_id,
				'board_list' => $boards_index,
				'notify_types' => $type === 'reply' ? 4 : 3,
				'notify_regularity' => 2,
				'is_activated' => 1,
				'members_only' => is_array($members_only) ? $members_only : array($members_only),
			)
		);
		$boards = array();
		while ($row = $db->fetch_assoc($members))
		{
			// for this member/board, loop through the topics and see if we should send it
			foreach ($topicData as $id => $data)
			{
				// Don't send it if its not from the right board
				if ($data['board'] !== $row['id_board'])
					continue;
				else
					$data['board_name'] = $row['name'];

				// Don't do the excluded...
				if ($data['exclude'] === $row['id_member'])
					continue;

				// If they are not the poster do they want to know?
				// @todo maybe if they posted via email?
				if ($type !== 'reply' && $row['notify_types'] == 2)
					continue;

				$email_perm = true;
				if (validateAccess($row, $maillist, $email_perm) === false)
					continue;

				$needed_language = empty($row['lngfile']) || empty($modSettings['userLanguage']) ? $language : $row['lngfile'];
				if (empty($current_language) || $current_language != $needed_language)
					$current_language = loadLanguage('Post', $needed_language, false);

				$message_type = 'notification_' . $type;
				$replacements = array(
					'TOPICSUBJECT' => $data['subject'],
					'POSTERNAME' => un_htmlspecialchars($data['name']),
					'TOPICLINKNEW' => $scripturl . '?topic=' . $id . '.new;topicseen#new',
					'TOPICLINK' => $scripturl . '?topic=' . $id . '.msg' . $data['last_id'] . '#msg' . $data['last_id'],
					'UNSUBSCRIBELINK' => $scripturl . '?action=notifyboard;board=' . $data['board'] . '.0',
					'SIGNATURE' => $data['signature'],
					'BOARDNAME' => $data['board_name'],
				);

				if ($type === 'remove')
					unset($replacements['TOPICLINK'], $replacements['UNSUBSCRIBELINK']);

				// Do they want the body of the message sent too?
				if (!empty($row['notify_send_body']) && $type === 'reply')
				{
					$message_type .= '_body';
					$replacements['MESSAGE'] = $data['body'];

					// Any attachments? if so lets make a big deal about them!
					if ($data['attachments'] != 0)
						$replacements['MESSAGE'] .=  "\n\n" . sprintf($txt['message_attachments'], $data['attachments'], $replacements['TOPICLINK']);
				}

				if (!empty($row['notify_regularity']) && $type === 'reply')
					$message_type .= '_once';

				// Send only if once is off or it's on and it hasn't been sent.
				if ($type !== 'reply' || empty($row['notify_regularity']) || empty($row['sent']))
				{
					$emaildata = loadEmailTemplate((($maillist && $email_perm && $type === 'reply') ? 'pbe_' : '') . $message_type, $replacements, $needed_language);

					if ($maillist && $email_perm && $type === 'reply')
					{
						// In group mode like google group or yahoo group, the mail is from the poster
						// Otherwise in maillist mode, it is from the site
						$emailfrom = !empty($modSettings['maillist_group_mode']) ? un_htmlspecialchars($data['name']) : (!empty($modSettings['maillist_sitename']) ? un_htmlspecialchars($modSettings['maillist_sitename']) : $mbname);

						// The email address of the sender, irrespective of the envelope name above
						$from_wrapper = !empty($modSettings['maillist_sitename_address']) ? $modSettings['maillist_sitename_address'] : (empty($modSettings['maillist_mail_from']) ? $webmaster_email : $modSettings['maillist_mail_from']);
						sendmail($row['email_address'], $emaildata['subject'], $emaildata['body'], $emailfrom, 'm' . $data['last_id'], false, 3, null, false, $from_wrapper, $id);
					}
					else
						sendmail($row['email_address'], $emaildata['subject'], $emaildata['body'], null, 'm' . $data['last_id']);

					$sent++;

					// make a note that this member was sent this topic
					$boards[$row['id_member']][$id] = 1;
				}
			}
		}
		$db->free_result($members);
	}

	// Find the members with notification on for this topic.
	$members = $db->query('', '
		SELECT
			mem.id_member, mem.email_address, mem.notify_regularity, mem.notify_types, mem.notify_send_body, mem.lngfile,
			ln.sent, mem.id_group, mem.additional_groups, b.member_groups, mem.id_post_group, t.id_member_started, b.name,
			ln.id_topic
		FROM {db_prefix}log_notify AS ln
			INNER JOIN {db_prefix}members AS mem ON (mem.id_member = ln.id_member)
			INNER JOIN {db_prefix}topics AS t ON (t.id_topic = ln.id_topic)
			INNER JOIN {db_prefix}boards AS b ON (b.id_board = t.id_board)
		WHERE ln.id_topic IN ({array_int:topic_list})
			AND mem.notify_types < {int:notify_types}
			AND mem.notify_regularity < {int:notify_regularity}
			AND mem.is_activated = {int:is_activated}
			AND ln.id_member != {int:current_member}' .
			(empty($members_only) ? '' : ' AND ln.id_member IN ({array_int:members_only})') . '
		ORDER BY mem.lngfile',
		array(
			'current_member' => $user_id,
			'topic_list' => $topics,
			'notify_types' => $type == 'reply' ? 4 : 3,
			'notify_regularity' => 2,
			'is_activated' => 1,
			'members_only' => is_array($members_only) ? $members_only : array($members_only),
		)
	);

	while ($row = $db->fetch_assoc($members))
	{
		// Don't do the excluded...
		if ($topicData[$row['id_topic']]['exclude'] == $row['id_member'])
			continue;

		// Don't do the ones that were sent via board notification, you only get one notice
		if (isset($boards[$row['id_member']][$row['id_topic']]))
			continue;

		// Easier to check this here... if they aren't the topic poster do they really want to know?
		// @todo prehaps just if they posted by email?
		if ($type != 'reply' && $row['notify_types'] == 2 && $row['id_member'] != $row['id_member_started'])
			continue;

		$email_perm = true;
		if (validateAccess($row, $maillist, $email_perm) === false)
			continue;

		$needed_language = empty($row['lngfile']) || empty($modSettings['userLanguage']) ? $language : $row['lngfile'];
		if (empty($current_language) || $current_language != $needed_language)
			$current_language = loadLanguage('Post', $needed_language, false);

		$message_type = 'notification_' . $type;
		$replacements = array(
			'TOPICSUBJECT' => $topicData[$row['id_topic']]['subject'],
			'POSTERNAME' => un_htmlspecialchars($topicData[$row['id_topic']]['name']),
			'TOPICLINKNEW' => $scripturl . '?topic=' . $row['id_topic'] . '.new;topicseen#new',
			'TOPICLINK' => $scripturl . '?topic=' . $row['id_topic'] . '.msg' . $data['last_id'] . '#msg' . $data['last_id'],
			'UNSUBSCRIBELINK' => $scripturl . '?action=notify;topic=' . $row['id_topic'] . '.0',
			'SIGNATURE' => $topicData[$row['id_topic']]['signature'],
			'BOARDNAME' => $row['name'],
		);

		if ($type == 'remove')
			unset($replacements['TOPICLINK'], $replacements['UNSUBSCRIBELINK']);

		// Do they want the body of the message sent too?
		if (!empty($row['notify_send_body']) && $type == 'reply')
		{
			$message_type .= '_body';
			$replacements['MESSAGE'] = $topicData[$row['id_topic']]['body'];
		}
		if (!empty($row['notify_regularity']) && $type == 'reply')
			$message_type .= '_once';

		// Send only if once is off or it's on and it hasn't been sent.
		if ($type != 'reply' || empty($row['notify_regularity']) || empty($row['sent']))
		{
			$emaildata = loadEmailTemplate((($maillist && $email_perm && $type === 'reply') ? 'pbe_' : '') . $message_type, $replacements, $needed_language);

			// Using the maillist functions?
			if ($maillist && $email_perm && $type === 'reply')
			{
				// Set the from name base on group or maillist mode
				$emailfrom = !empty($modSettings['maillist_group_mode']) ? un_htmlspecialchars($topicData[$row['id_topic']]['name']) : un_htmlspecialchars($modSettings['maillist_sitename']);
				$from_wrapper = !empty($modSettings['maillist_sitename_address']) ? $modSettings['maillist_sitename_address'] : (empty($modSettings['maillist_mail_from']) ? $webmaster_email : $modSettings['maillist_mail_from']);
				sendmail($row['email_address'], $emaildata['subject'], $emaildata['body'], $emailfrom, 'm' . $data['last_id'], false, 3, null, false, $from_wrapper, $row['id_topic']);
			}
			else
				sendmail($row['email_address'], $emaildata['subject'], $emaildata['body'], null, 'm' . $topicData[$row['id_topic']]['last_id']);

			$sent++;
		}
	}
	$db->free_result($members);

	if (isset($current_language) && $current_language != $user_language)
		loadLanguage('Post');

	// Sent!
	if ($type == 'reply' && !empty($sent))
		$db->query('', '
			UPDATE {db_prefix}log_notify
			SET sent = {int:is_sent}
			WHERE id_topic IN ({array_int:topic_list})
				AND id_member != {int:current_member}',
			array(
				'current_member' => $user_id,
				'topic_list' => $topics,
				'is_sent' => 1,
			)
		);

	// For approvals we need to unsend the exclusions (This *is* the quickest way!)
	if (!empty($sent) && !empty($exclude))
	{
		foreach ($topicData as $id => $data)
		{
			if ($data['exclude'])
			{
				$db->query('', '
					UPDATE {db_prefix}log_notify
					SET sent = {int:not_sent}
					WHERE id_topic = {int:id_topic}
						AND id_member = {int:id_member}',
					array(
						'not_sent' => 0,
						'id_topic' => $id,
						'id_member' => $data['exclude'],
					)
				);
			}
		}
	}
}

/**
 * Checks if a user has the correct access to get notifications
 * - validates they have proper group access to a board
 * - if using the maillist, checks if they should get a reply-able message
 * 		- not muted
 * 		- has postby_email permission on the board
 *
 * Returns false if they do not have the proper group access to a board
 * Sets email_perm to false if they should not get a reply-able message
 *
 * @param array $row
 * @param boolean $maillist
 * @param boolean $email_perm
 */
function validateAccess($row, $maillist, &$email_perm = true)
{
	global $modSettings;

	$db = database();
	static $board_profile = array();

	// No need to check for you ;)
	if ($row['id_group'] == 1)
		return;

	$allowed = explode(',', $row['member_groups']);
	$row['additional_groups'] = !empty( $row['additional_groups']) ? explode(',', $row['additional_groups']) : array();
	$row['additional_groups'][] = $row['id_group'];
	$row['additional_groups'][] = $row['id_post_group'];

	// They do have access to this board?
	if (count(array_intersect($allowed, $row['additional_groups'])) === 0)
		return false;

	// If using maillist, see if they should get a reply-able message
	if ($maillist)
	{
		// Perhaps they don't require a security key in the message
		if (!empty($modSettings['postmod_active']) && !empty($modSettings['warning_mute']) && $modSettings['warning_mute'] <= $row['warning'])
			$email_perm = false;
		else
		{
			// In a group that has email posting permissions on this board
			if (!isset($board_profile[$row['id_profile']]))
			{
				$request = $db->query('', '
					SELECT permission, add_deny, id_group
					FROM {db_prefix}board_permissions
					WHERE id_profile = {int:id_profile}
						AND permission = {string:permission}',
					array(
						'id_profile' => $row['id_profile'],
						'permission' => 'postby_email',
					)
				);
				while ($row_perm = $db->fetch_assoc($request))
					$board_profile[$row['id_profile']][] = $row_perm['id_group'];
				$db->free_result($request);
			}

			// Get the email permission for this board / posting group
			if (count(array_intersect($board_profile[$row['id_profile']], $row['additional_groups'])) === 0)
				$email_perm = false;
		}
	}
}

/**
 * Create a post, either as new topic (id_topic = 0) or in an existing one.
 * The input parameters of this function assume:
 * - Strings have been escaped.
 * - Integers have been cast to integer.
 * - Mandatory parameters are set.
 *
 * @param array $msgOptions
 * @param array $topicOptions
 * @param array $posterOptions
 */
function createPost(&$msgOptions, &$topicOptions, &$posterOptions)
{
	global $user_info, $txt, $modSettings;

	$db = database();

	// Set optional parameters to the default value.
	$msgOptions['icon'] = empty($msgOptions['icon']) ? 'xx' : $msgOptions['icon'];
	$msgOptions['smileys_enabled'] = !empty($msgOptions['smileys_enabled']);
	$msgOptions['attachments'] = empty($msgOptions['attachments']) ? array() : $msgOptions['attachments'];
	$msgOptions['approved'] = isset($msgOptions['approved']) ? (int) $msgOptions['approved'] : 1;
	$topicOptions['id'] = empty($topicOptions['id']) ? 0 : (int) $topicOptions['id'];
	$topicOptions['poll'] = isset($topicOptions['poll']) ? (int) $topicOptions['poll'] : null;
	$topicOptions['lock_mode'] = isset($topicOptions['lock_mode']) ? $topicOptions['lock_mode'] : null;
	$topicOptions['sticky_mode'] = isset($topicOptions['sticky_mode']) ? $topicOptions['sticky_mode'] : null;
	$topicOptions['redirect_expires'] = isset($topicOptions['redirect_expires']) ? $topicOptions['redirect_expires'] : null;
	$topicOptions['redirect_topic'] = isset($topicOptions['redirect_topic']) ? $topicOptions['redirect_topic'] : null;
	$posterOptions['id'] = empty($posterOptions['id']) ? 0 : (int) $posterOptions['id'];
	$posterOptions['ip'] = empty($posterOptions['ip']) ? $user_info['ip'] : $posterOptions['ip'];

	// We need to know if the topic is approved. If we're told that's great - if not find out.
	if (!$modSettings['postmod_active'])
		$topicOptions['is_approved'] = true;
	elseif (!empty($topicOptions['id']) && !isset($topicOptions['is_approved']))
	{
		$request = $db->query('', '
			SELECT approved
			FROM {db_prefix}topics
			WHERE id_topic = {int:id_topic}
			LIMIT 1',
			array(
				'id_topic' => $topicOptions['id'],
			)
		);
		list ($topicOptions['is_approved']) = $db->fetch_row($request);
		$db->free_result($request);
	}

	// If nothing was filled in as name/e-mail address, try the member table.
	if (!isset($posterOptions['name']) || $posterOptions['name'] == '' || (empty($posterOptions['email']) && !empty($posterOptions['id'])))
	{
		if (empty($posterOptions['id']))
		{
			$posterOptions['id'] = 0;
			$posterOptions['name'] = $txt['guest_title'];
			$posterOptions['email'] = '';
		}
		elseif ($posterOptions['id'] != $user_info['id'])
		{
			require_once(SUBSDIR . '/Members.subs.php');
			$result = getBasicMemberData($posterOptions['id']);
			// Couldn't find the current poster?
			if (empty($result))
			{
				trigger_error('createPost(): Invalid member id ' . $posterOptions['id'], E_USER_NOTICE);
				$posterOptions['id'] = 0;
				$posterOptions['name'] = $txt['guest_title'];
				$posterOptions['email'] = '';
			}
			else
			{
				$posterOptions['name'] = $result['member_name'];
				$posterOptions['email'] = $result['email_address'];
			}
		}
		else
		{
			$posterOptions['name'] = $user_info['name'];
			$posterOptions['email'] = $user_info['email'];
		}
	}

	// It's do or die time: forget any user aborts!
	$previous_ignore_user_abort = ignore_user_abort(true);

	$new_topic = empty($topicOptions['id']);

	$message_columns = array(
		'id_board' => 'int', 'id_topic' => 'int', 'id_member' => 'int', 'subject' => 'string-255', 'body' => (!empty($modSettings['max_messageLength']) && $modSettings['max_messageLength'] > 65534 ? 'string-' . $modSettings['max_messageLength'] : (empty($modSettings['max_messageLength']) ? 'string' : 'string-65534')),
		'poster_name' => 'string-255', 'poster_email' => 'string-255', 'poster_time' => 'int', 'poster_ip' => 'string-255',
		'smileys_enabled' => 'int', 'modified_name' => 'string', 'icon' => 'string-16', 'approved' => 'int',
	);

	$message_parameters = array(
		$topicOptions['board'], $topicOptions['id'], $posterOptions['id'], $msgOptions['subject'], $msgOptions['body'],
		$posterOptions['name'], $posterOptions['email'], time(), $posterOptions['ip'],
		$msgOptions['smileys_enabled'] ? 1 : 0, '', $msgOptions['icon'], $msgOptions['approved'],
	);

	// What if we want to do anything with posts?
	call_integration_hook('integrate_create_post', array(&$msgOptions, &$topicOptions, &$posterOptions, &$message_columns, &$message_parameters));

	// Insert the post.
	$db->insert('',
		'{db_prefix}messages',
		$message_columns,
		$message_parameters,
		array('id_msg')
	);
	$msgOptions['id'] = $db->insert_id('{db_prefix}messages', 'id_msg');

	// Something went wrong creating the message...
	if (empty($msgOptions['id']))
		return false;

	// Fix the attachments.
	if (!empty($msgOptions['attachments']))
		$db->query('', '
			UPDATE {db_prefix}attachments
			SET id_msg = {int:id_msg}
			WHERE id_attach IN ({array_int:attachment_list})',
			array(
				'attachment_list' => $msgOptions['attachments'],
				'id_msg' => $msgOptions['id'],
			)
		);

	// Insert a new topic (if the topicID was left empty.)
	if ($new_topic)
	{
		$topic_columns = array(
			'id_board' => 'int', 'id_member_started' => 'int', 'id_member_updated' => 'int', 'id_first_msg' => 'int',
			'id_last_msg' => 'int', 'locked' => 'int', 'is_sticky' => 'int', 'num_views' => 'int',
			'id_poll' => 'int', 'unapproved_posts' => 'int', 'approved' => 'int',
			'redirect_expires' => 'int', 'id_redirect_topic' => 'int',
		);
		$topic_parameters = array(
			$topicOptions['board'], $posterOptions['id'], $posterOptions['id'], $msgOptions['id'],
			$msgOptions['id'], $topicOptions['lock_mode'] === null ? 0 : $topicOptions['lock_mode'], $topicOptions['sticky_mode'] === null ? 0 : $topicOptions['sticky_mode'], 0,
			$topicOptions['poll'] === null ? 0 : $topicOptions['poll'], $msgOptions['approved'] ? 0 : 1, $msgOptions['approved'],
			$topicOptions['redirect_expires'] === null ? 0 : $topicOptions['redirect_expires'], $topicOptions['redirect_topic'] === null ? 0 : $topicOptions['redirect_topic'],
		);

		call_integration_hook('integrate_before_create_topic', array(&$msgOptions, &$topicOptions, &$posterOptions, &$topic_columns, &$topic_parameters));

		$db->insert('',
			'{db_prefix}topics',
			$topic_columns,
			$topic_parameters,
			array('id_topic')
		);
		$topicOptions['id'] = $db->insert_id('{db_prefix}topics', 'id_topic');

		// The topic couldn't be created for some reason.
		if (empty($topicOptions['id']))
		{
			// We should delete the post that did work, though...
			$db->query('', '
				DELETE FROM {db_prefix}messages
				WHERE id_msg = {int:id_msg}',
				array(
					'id_msg' => $msgOptions['id'],
				)
			);

			return false;
		}

		// Fix the message with the topic.
		$db->query('', '
			UPDATE {db_prefix}messages
			SET id_topic = {int:id_topic}
			WHERE id_msg = {int:id_msg}',
			array(
				'id_topic' => $topicOptions['id'],
				'id_msg' => $msgOptions['id'],
			)
		);

		// There's been a new topic AND a new post today.
		trackStats(array('topics' => '+', 'posts' => '+'));

		updateStats('topic', true);
		updateStats('subject', $topicOptions['id'], $msgOptions['subject']);

		// What if we want to export new topics out to a CMS?
		call_integration_hook('integrate_create_topic', array($msgOptions, $topicOptions, $posterOptions));
	}
	// The topic already exists, it only needs a little updating.
	else
	{
		$update_parameters = array(
			'poster_id' => $posterOptions['id'],
			'id_msg' => $msgOptions['id'],
			'locked' => $topicOptions['lock_mode'],
			'is_sticky' => $topicOptions['sticky_mode'],
			'id_topic' => $topicOptions['id'],
			'counter_increment' => 1,
		);
		$topics_columns = array();
		if ($msgOptions['approved'])
			$topics_columns = array(
				'id_member_updated = {int:poster_id}',
				'id_last_msg = {int:id_msg}',
				'num_replies = num_replies + {int:counter_increment}',
			);
		else
			$topics_columns = array(
				'unapproved_posts = unapproved_posts + {int:counter_increment}',
			);
		if ($topicOptions['lock_mode'] !== null)
			$topics_columns = array(
				'locked = {int:locked}',
			);
		if ($topicOptions['sticky_mode'] !== null)
			$topics_columns = array(
				'is_sticky = {int:is_sticky}',
			);

		call_integration_hook('integrate_modify_topic', array(&$topics_columns, &$update_parameters, &$msgOptions, &$topicOptions, &$posterOptions));

		// Update the number of replies and the lock/sticky status.
		$db->query('', '
			UPDATE {db_prefix}topics
			SET
				' . implode(', ', $topics_columns) . '
			WHERE id_topic = {int:id_topic}',
			$update_parameters
		);

		// One new post has been added today.
		trackStats(array('posts' => '+'));
	}

	// Creating is modifying...in a way.
	// @todo Why not set id_msg_modified on the insert?
	$db->query('', '
		UPDATE {db_prefix}messages
		SET id_msg_modified = {int:id_msg}
		WHERE id_msg = {int:id_msg}',
		array(
			'id_msg' => $msgOptions['id'],
		)
	);

	// Increase the number of posts and topics on the board.
	if ($msgOptions['approved'])
		$db->query('', '
			UPDATE {db_prefix}boards
			SET num_posts = num_posts + 1' . ($new_topic ? ', num_topics = num_topics + 1' : '') . '
			WHERE id_board = {int:id_board}',
			array(
				'id_board' => $topicOptions['board'],
			)
		);
	else
	{
		$db->query('', '
			UPDATE {db_prefix}boards
			SET unapproved_posts = unapproved_posts + 1' . ($new_topic ? ', unapproved_topics = unapproved_topics + 1' : '') . '
			WHERE id_board = {int:id_board}',
			array(
				'id_board' => $topicOptions['board'],
			)
		);

		// Add to the approval queue too.
		$db->insert('',
			'{db_prefix}approval_queue',
			array(
				'id_msg' => 'int',
			),
			array(
				$msgOptions['id'],
			),
			array()
		);
	}

	// Mark inserted topic as read (only for the user calling this function).
	if (!empty($topicOptions['mark_as_read']) && !$user_info['is_guest'])
	{
		// Since it's likely they *read* it before replying, let's try an UPDATE first.
		if (!$new_topic)
		{
			$db->query('', '
				UPDATE {db_prefix}log_topics
				SET id_msg = {int:id_msg}
				WHERE id_member = {int:current_member}
					AND id_topic = {int:id_topic}',
				array(
					'current_member' => $posterOptions['id'],
					'id_msg' => $msgOptions['id'],
					'id_topic' => $topicOptions['id'],
				)
			);

			$flag = $db->affected_rows() != 0;
		}

		if (empty($flag))
		{
			require_once(SUBSDIR . '/Topic.subs.php');
			markTopicsRead(array($posterOptions['id'], $topicOptions['id'], $msgOptions['id'], 0), false);
		}
	}

	// If there's a custom search index, it may need updating...
	require_once(SUBSDIR . '/Search.subs.php');
	$searchAPI = findSearchAPI();
	if (is_callable(array($searchAPI, 'postCreated')))
		$searchAPI->postCreated($msgOptions, $topicOptions, $posterOptions);

	// Increase the post counter for the user that created the post.
	if (!empty($posterOptions['update_post_count']) && !empty($posterOptions['id']) && $msgOptions['approved'])
	{
		// Are you the one that happened to create this post?
		if ($user_info['id'] == $posterOptions['id'])
			$user_info['posts']++;
		updateMemberData($posterOptions['id'], array('posts' => '+'));
	}

	// They've posted, so they can make the view count go up one if they really want. (this is to keep views >= replies...)
	$_SESSION['last_read_topic'] = 0;

	// Better safe than sorry.
	if (isset($_SESSION['topicseen_cache'][$topicOptions['board']]))
		$_SESSION['topicseen_cache'][$topicOptions['board']]--;

	// Update all the stats so everyone knows about this new topic and message.
	updateStats('message', true, $msgOptions['id']);

	// Update the last message on the board assuming it's approved AND the topic is.
	if ($msgOptions['approved'])
		updateLastMessages($topicOptions['board'], $new_topic || !empty($topicOptions['is_approved']) ? $msgOptions['id'] : 0);

	// Alright, done now... we can abort now, I guess... at least this much is done.
	ignore_user_abort($previous_ignore_user_abort);

	// Success.
	return true;
}

/**
 * Modifying a post...
 *
 * @param array &$msgOptions
 * @param array &$topicOptions
 * @param array &$posterOptions
 */
function modifyPost(&$msgOptions, &$topicOptions, &$posterOptions)
{
	global $user_info, $modSettings;

	$db = database();

	$topicOptions['poll'] = isset($topicOptions['poll']) ? (int) $topicOptions['poll'] : null;
	$topicOptions['lock_mode'] = isset($topicOptions['lock_mode']) ? $topicOptions['lock_mode'] : null;
	$topicOptions['sticky_mode'] = isset($topicOptions['sticky_mode']) ? $topicOptions['sticky_mode'] : null;

	// This is longer than it has to be, but makes it so we only set/change what we have to.
	$messages_columns = array();
	if (isset($posterOptions['name']))
		$messages_columns['poster_name'] = $posterOptions['name'];
	if (isset($posterOptions['email']))
		$messages_columns['poster_email'] = $posterOptions['email'];
	if (isset($msgOptions['icon']))
		$messages_columns['icon'] = $msgOptions['icon'];
	if (isset($msgOptions['subject']))
		$messages_columns['subject'] = $msgOptions['subject'];
	if (isset($msgOptions['body']))
	{
		$messages_columns['body'] = $msgOptions['body'];

		// using a custom search index, then lets get the old message so we can update our index as needed
		if (!empty($modSettings['search_custom_index_config']))
		{
			require_once(SUBSDIR . '/Messages.subs.php');
			$message = basicMessageInfo($msgOptions['id'], true);
			$msgOptions['old_body'] = $message['body'];
		}
	}
	if (!empty($msgOptions['modify_time']))
	{
		$messages_columns['modified_time'] = $msgOptions['modify_time'];
		$messages_columns['modified_name'] = $msgOptions['modify_name'];
		$messages_columns['id_msg_modified'] = $modSettings['maxMsgID'];
	}
	if (isset($msgOptions['smileys_enabled']))
		$messages_columns['smileys_enabled'] = empty($msgOptions['smileys_enabled']) ? 0 : 1;

	// Which columns need to be ints?
	$messageInts = array('modified_time', 'id_msg_modified', 'smileys_enabled');
	$update_parameters = array(
		'id_msg' => $msgOptions['id'],
	);

	call_integration_hook('integrate_modify_post', array(&$messages_columns, &$update_parameters, &$msgOptions, &$topicOptions, &$posterOptions, &$messageInts));

	foreach ($messages_columns as $var => $val)
	{
		$messages_columns[$var] = $var . ' = {' . (in_array($var, $messageInts) ? 'int' : 'string') . ':var_' . $var . '}';
		$update_parameters['var_' . $var] = $val;
	}

	// Nothing to do?
	if (empty($messages_columns))
		return true;

	// Change the post.
	$db->query('', '
		UPDATE {db_prefix}messages
		SET ' . implode(', ', $messages_columns) . '
		WHERE id_msg = {int:id_msg}',
		$update_parameters
	);

	// Lock and or sticky the post.
	if ($topicOptions['sticky_mode'] !== null || $topicOptions['lock_mode'] !== null || $topicOptions['poll'] !== null)
	{
		$db->query('', '
			UPDATE {db_prefix}topics
			SET
				is_sticky = {raw:is_sticky},
				locked = {raw:locked},
				id_poll = {raw:id_poll}
			WHERE id_topic = {int:id_topic}',
			array(
				'is_sticky' => $topicOptions['sticky_mode'] === null ? 'is_sticky' : (int) $topicOptions['sticky_mode'],
				'locked' => $topicOptions['lock_mode'] === null ? 'locked' : (int) $topicOptions['lock_mode'],
				'id_poll' => $topicOptions['poll'] === null ? 'id_poll' : (int) $topicOptions['poll'],
				'id_topic' => $topicOptions['id'],
			)
		);
	}

	// Mark the edited post as read.
	if (!empty($topicOptions['mark_as_read']) && !$user_info['is_guest'])
	{
		// Since it's likely they *read* it before editing, let's try an UPDATE first.
		$db->query('', '
			UPDATE {db_prefix}log_topics
			SET id_msg = {int:id_msg}
			WHERE id_member = {int:current_member}
				AND id_topic = {int:id_topic}',
			array(
				'current_member' => $user_info['id'],
				'id_msg' => $modSettings['maxMsgID'],
				'id_topic' => $topicOptions['id'],
			)
		);

		$flag = $db->affected_rows() != 0;

		if (empty($flag))
		{
			require_once(SUBSDIR . '/Topic.subs.php');
			markTopicsRead(array($user_info['id'], $topicOptions['id'], $modSettings['maxMsgID'], 0), false);
		}
	}

	// If there's a custom search index, it needs to be modified...
	require_once(SUBSDIR . '/Search.subs.php');
	$searchAPI = findSearchAPI();
	if (is_callable(array($searchAPI, 'postModified')))
		$searchAPI->postModified($msgOptions, $topicOptions, $posterOptions);

	if (isset($msgOptions['subject']))
	{
		// Only update the subject if this was the first message in the topic.
		$request = $db->query('', '
			SELECT id_topic
			FROM {db_prefix}topics
			WHERE id_first_msg = {int:id_first_msg}
			LIMIT 1',
			array(
				'id_first_msg' => $msgOptions['id'],
			)
		);
		if ($db->num_rows($request) == 1)
			updateStats('subject', $topicOptions['id'], $msgOptions['subject']);
		$db->free_result($request);
	}

	// Finally, if we are setting the approved state we need to do much more work :(
	if ($modSettings['postmod_active'] && isset($msgOptions['approved']))
		approvePosts($msgOptions['id'], $msgOptions['approved']);

	return true;
}

/**
 * Approve (or not) some posts... without permission checks...
 *
 * @param array $msgs - array of message ids
 * @param bool $approve = true
 */
function approvePosts($msgs, $approve = true)
{
	$db = database();

	if (!is_array($msgs))
		$msgs = array($msgs);

	if (empty($msgs))
		return false;

	// May as well start at the beginning, working out *what* we need to change.
	$request = $db->query('', '
		SELECT m.id_msg, m.approved, m.id_topic, m.id_board, t.id_first_msg, t.id_last_msg,
			m.body, m.subject, IFNULL(mem.real_name, m.poster_name) AS poster_name, m.id_member,
			t.approved AS topic_approved, b.count_posts
		FROM {db_prefix}messages AS m
			INNER JOIN {db_prefix}topics AS t ON (t.id_topic = m.id_topic)
			INNER JOIN {db_prefix}boards AS b ON (b.id_board = m.id_board)
			LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = m.id_member)
		WHERE m.id_msg IN ({array_int:message_list})
			AND m.approved = {int:approved_state}',
		array(
			'message_list' => $msgs,
			'approved_state' => $approve ? 0 : 1,
		)
	);
	$msgs = array();
	$topics = array();
	$topic_changes = array();
	$board_changes = array();
	$notification_topics = array();
	$notification_posts = array();
	$member_post_changes = array();
	while ($row = $db->fetch_assoc($request))
	{
		// Easy...
		$msgs[] = $row['id_msg'];
		$topics[] = $row['id_topic'];

		// Ensure our change array exists already.
		if (!isset($topic_changes[$row['id_topic']]))
			$topic_changes[$row['id_topic']] = array(
				'id_last_msg' => $row['id_last_msg'],
				'approved' => $row['topic_approved'],
				'replies' => 0,
				'unapproved_posts' => 0,
			);
		if (!isset($board_changes[$row['id_board']]))
			$board_changes[$row['id_board']] = array(
				'posts' => 0,
				'topics' => 0,
				'unapproved_posts' => 0,
				'unapproved_topics' => 0,
			);

		// If it's the first message then the topic state changes!
		if ($row['id_msg'] == $row['id_first_msg'])
		{
			$topic_changes[$row['id_topic']]['approved'] = $approve ? 1 : 0;

			$board_changes[$row['id_board']]['unapproved_topics'] += $approve ? -1 : 1;
			$board_changes[$row['id_board']]['topics'] += $approve ? 1 : -1;

			// Note we need to ensure we announce this topic!
			$notification_topics[] = array(
				'body' => $row['body'],
				'subject' => $row['subject'],
				'name' => $row['poster_name'],
				'board' => $row['id_board'],
				'topic' => $row['id_topic'],
				'msg' => $row['id_first_msg'],
				'poster' => $row['id_member'],
			);
		}
		else
		{
			$topic_changes[$row['id_topic']]['replies'] += $approve ? 1 : -1;

			// This will be a post... but don't notify unless it's not followed by approved ones.
			if ($row['id_msg'] > $row['id_last_msg'])
				$notification_posts[$row['id_topic']][] = array(
					'id' => $row['id_msg'],
					'body' => $row['body'],
					'subject' => $row['subject'],
					'name' => $row['poster_name'],
					'topic' => $row['id_topic'],
				);
		}

		// If this is being approved and id_msg is higher than the current id_last_msg then it changes.
		if ($approve && $row['id_msg'] > $topic_changes[$row['id_topic']]['id_last_msg'])
			$topic_changes[$row['id_topic']]['id_last_msg'] = $row['id_msg'];
		// If this is being unapproved, and it's equal to the id_last_msg we need to find a new one!
		elseif (!$approve)
			// Default to the first message and then we'll override in a bit ;)
			$topic_changes[$row['id_topic']]['id_last_msg'] = $row['id_first_msg'];

		$topic_changes[$row['id_topic']]['unapproved_posts'] += $approve ? -1 : 1;
		$board_changes[$row['id_board']]['unapproved_posts'] += $approve ? -1 : 1;
		$board_changes[$row['id_board']]['posts'] += $approve ? 1 : -1;

		// Post count for the user?
		if ($row['id_member'] && empty($row['count_posts']))
			$member_post_changes[$row['id_member']] = isset($member_post_changes[$row['id_member']]) ? $member_post_changes[$row['id_member']] + 1 : 1;
	}
	$db->free_result($request);

	if (empty($msgs))
		return;

	// Now we have the differences make the changes, first the easy one.
	$db->query('', '
		UPDATE {db_prefix}messages
		SET approved = {int:approved_state}
		WHERE id_msg IN ({array_int:message_list})',
		array(
			'message_list' => $msgs,
			'approved_state' => $approve ? 1 : 0,
		)
	);

	// If we were unapproving find the last msg in the topics...
	if (!$approve)
	{
		$request = $db->query('', '
			SELECT id_topic, MAX(id_msg) AS id_last_msg
			FROM {db_prefix}messages
			WHERE id_topic IN ({array_int:topic_list})
				AND approved = {int:approved}
			GROUP BY id_topic',
			array(
				'topic_list' => $topics,
				'approved' => 1,
			)
		);
		while ($row = $db->fetch_assoc($request))
			$topic_changes[$row['id_topic']]['id_last_msg'] = $row['id_last_msg'];
		$db->free_result($request);
	}

	// ... next the topics...
	foreach ($topic_changes as $id => $changes)
		$db->query('', '
			UPDATE {db_prefix}topics
			SET approved = {int:approved}, unapproved_posts = unapproved_posts + {int:unapproved_posts},
				num_replies = num_replies + {int:num_replies}, id_last_msg = {int:id_last_msg}
			WHERE id_topic = {int:id_topic}',
			array(
				'approved' => $changes['approved'],
				'unapproved_posts' => $changes['unapproved_posts'],
				'num_replies' => $changes['replies'],
				'id_last_msg' => $changes['id_last_msg'],
				'id_topic' => $id,
			)
		);

	// ... finally the boards...
	foreach ($board_changes as $id => $changes)
		$db->query('', '
			UPDATE {db_prefix}boards
			SET num_posts = num_posts + {int:num_posts}, unapproved_posts = unapproved_posts + {int:unapproved_posts},
				num_topics = num_topics + {int:num_topics}, unapproved_topics = unapproved_topics + {int:unapproved_topics}
			WHERE id_board = {int:id_board}',
			array(
				'num_posts' => $changes['posts'],
				'unapproved_posts' => $changes['unapproved_posts'],
				'num_topics' => $changes['topics'],
				'unapproved_topics' => $changes['unapproved_topics'],
				'id_board' => $id,
			)
		);

	// Finally, least importantly, notifications!
	if ($approve)
	{
		if (!empty($notification_topics))
			notifyMembersBoard($notification_topics);
		if (!empty($notification_posts))
			sendApprovalNotifications($notification_posts);

		$db->query('', '
			DELETE FROM {db_prefix}approval_queue
			WHERE id_msg IN ({array_int:message_list})
				AND id_attach = {int:id_attach}',
			array(
				'message_list' => $msgs,
				'id_attach' => 0,
			)
		);
	}
	// If unapproving add to the approval queue!
	else
	{
		$msgInserts = array();
		foreach ($msgs as $msg)
			$msgInserts[] = array($msg);

		$db->insert('ignore',
			'{db_prefix}approval_queue',
			array('id_msg' => 'int'),
			$msgInserts,
			array('id_msg')
		);
	}

	// Update the last messages on the boards...
	updateLastMessages(array_keys($board_changes));

	// Post count for the members?
	if (!empty($member_post_changes))
		foreach ($member_post_changes as $id_member => $count_change)
			updateMemberData($id_member, array('posts' => 'posts ' . ($approve ? '+' : '-') . ' ' . $count_change));

	return true;
}

/**
 * Approve topics?
 * @todo shouldn't this be in topic
 *
 * @param array $topics array of topics ids
 * @param bool $approve = true
 */
function approveTopics($topics, $approve = true)
{
	$db = database();

	if (!is_array($topics))
		$topics = array($topics);

	if (empty($topics))
		return false;

	$approve_type = $approve ? 0 : 1;

	// Just get the messages to be approved and pass through...
	$request = $db->query('', '
		SELECT id_msg
		FROM {db_prefix}messages
		WHERE id_topic IN ({array_int:topic_list})
			AND approved = {int:approve_type}',
		array(
			'topic_list' => $topics,
			'approve_type' => $approve_type,
		)
	);
	$msgs = array();
	while ($row = $db->fetch_assoc($request))
		$msgs[] = $row['id_msg'];
	$db->free_result($request);

	return approvePosts($msgs, $approve);
}

/**
 * A special function for handling the hell which is sending approval notifications.
 *
 * @param $topicData
 */
function sendApprovalNotifications(&$topicData)
{
	global $scripturl, $language, $user_info, $modSettings;

	$db = database();

	// Clean up the data...
	if (!is_array($topicData) || empty($topicData))
		return;

	// Email ahoy
	require_once(SUBSDIR . '/Mail.subs.php');

	// Maillist format?
	$maillist = !empty($modSettings['maillist_enabled']) && !empty($modSettings['pbe_post_enabled']);
	if ($maillist)
		require_once(SUBSDIR . '/Emailpost.subs.php');

	$topics = array();
	$digest_insert = array();
	foreach ($topicData as $topic => $msgs)
	{
		foreach ($msgs as $msgKey => $msg)
		{
			if ($maillist)
			{
				// Convert it to markdown for sending
				pbe_prepare_text($topicData[$topic][$msgKey]['body'], $topicData[$topic][$msgKey]['subject'], '');
			}
			else
			{
				censorText($topicData[$topic][$msgKey]['subject']);
				censorText($topicData[$topic][$msgKey]['body']);
				$topicData[$topic][$msgKey]['subject'] = un_htmlspecialchars($topicData[$topic][$msgKey]['subject']);
				$topicData[$topic][$msgKey]['body'] = trim(un_htmlspecialchars(strip_tags(strtr(parse_bbc($topicData[$topic][$msgKey]['body'], false), array('<br />' => "\n", '</div>' => "\n", '</li>' => "\n", '&#91;' => '[', '&#93;' => ']')))));
			}

			$topics[] = $msg['id'];
			$digest_insert[] = array($msg['topic'], $msg['id'], 'reply', $user_info['id']);
		}
	}

	// These need to go into the digest too...
	$db->insert('',
		'{db_prefix}log_digest',
		array(
			'id_topic' => 'int', 'id_msg' => 'int', 'note_type' => 'string', 'exclude' => 'int',
		),
		$digest_insert,
		array()
	);

	// Find everyone who needs to know about this.
	$members = $db->query('', '
		SELECT
			mem.id_member, mem.email_address, mem.notify_regularity, mem.notify_types, mem.notify_send_body, mem.lngfile,
			ln.sent, mem.id_group, mem.additional_groups, b.member_groups, mem.id_post_group, t.id_member_started,
			ln.id_topic
		FROM {db_prefix}log_notify AS ln
			INNER JOIN {db_prefix}members AS mem ON (mem.id_member = ln.id_member)
			INNER JOIN {db_prefix}topics AS t ON (t.id_topic = ln.id_topic)
			INNER JOIN {db_prefix}boards AS b ON (b.id_board = t.id_board)
		WHERE ln.id_topic IN ({array_int:topic_list})
			AND mem.is_activated = {int:is_activated}
			AND mem.notify_types < {int:notify_types}
			AND mem.notify_regularity < {int:notify_regularity}
		GROUP BY mem.id_member, ln.id_topic, mem.email_address, mem.notify_regularity, mem.notify_types, mem.notify_send_body, mem.lngfile, ln.sent, mem.id_group, mem.additional_groups, b.member_groups, mem.id_post_group, t.id_member_started
		ORDER BY mem.lngfile',
		array(
			'topic_list' => $topics,
			'is_activated' => 1,
			'notify_types' => 4,
			'notify_regularity' => 2,
		)
	);
	$sent = 0;

	$current_language = $user_info['language'];
	while ($row = $db->fetch_assoc($members))
	{
		if ($row['id_group'] != 1)
		{
			$allowed = explode(',', $row['member_groups']);
			$row['additional_groups'] = explode(',', $row['additional_groups']);
			$row['additional_groups'][] = $row['id_group'];
			$row['additional_groups'][] = $row['id_post_group'];

			if (count(array_intersect($allowed, $row['additional_groups'])) == 0)
				continue;
		}

		$needed_language = empty($row['lngfile']) || empty($modSettings['userLanguage']) ? $language : $row['lngfile'];
		if (empty($current_language) || $current_language != $needed_language)
			$current_language = loadLanguage('Post', $needed_language, false);

		$sent_this_time = false;
		// Now loop through all the messages to send.
		foreach ($topicData[$row['id_topic']] as $msg)
		{
			$replacements = array(
				'TOPICSUBJECT' => $topicData[$row['id_topic']]['subject'],
				'POSTERNAME' => un_htmlspecialchars($topicData[$row['id_topic']]['name']),
				'TOPICLINK' => $scripturl . '?topic=' . $row['id_topic'] . '.new;topicseen#new',
				'UNSUBSCRIBELINK' => $scripturl . '?action=notify;topic=' . $row['id_topic'] . '.0',
			);

			$message_type = 'notification_reply';
			// Do they want the body of the message sent too?
			if (!empty($row['notify_send_body']) && empty($modSettings['disallow_sendBody']))
			{
				$message_type .= '_body';
				$replacements['BODY'] = $topicData[$row['id_topic']]['body'];
			}
			if (!empty($row['notify_regularity']))
				$message_type .= '_once';

			// Send only if once is off or it's on and it hasn't been sent.
			if (empty($row['notify_regularity']) || (empty($row['sent']) && !$sent_this_time))
			{
				$emaildata = loadEmailTemplate($message_type, $replacements, $needed_language);
				sendmail($row['email_address'], $emaildata['subject'], $emaildata['body'], null, 'm' . $topicData[$row['id_topic']]['last_id']);
				$sent++;
			}

			$sent_this_time = true;
		}
	}
	$db->free_result($members);

	if (isset($current_language) && $current_language != $user_info['language'])
		loadLanguage('Post');

	// Sent!
	if (!empty($sent))
		$db->query('', '
			UPDATE {db_prefix}log_notify
			SET sent = {int:is_sent}
			WHERE id_topic IN ({array_int:topic_list})
				AND id_member != {int:current_member}',
			array(
				'current_member' => $user_info['id'],
				'topic_list' => $topics,
				'is_sent' => 1,
			)
		);
}

/**
 * Takes an array of board IDs and updates their last messages.
 * If the board has a parent, that parent board is also automatically
 * updated.
 * The columns updated are id_last_msg and last_updated.
 * Note that id_last_msg should always be updated using this function,
 * and is not automatically updated upon other changes.
 *
 * @param array $setboards
 * @param int $id_msg = 0
 */
function updateLastMessages($setboards, $id_msg = 0)
{
	global $board_info, $board;

	$db = database();

	// Please - let's be sane.
	if (empty($setboards))
		return false;

	if (!is_array($setboards))
		$setboards = array($setboards);

	// If we don't know the id_msg we need to find it.
	if (!$id_msg)
	{
		// Find the latest message on this board (highest id_msg.)
		$request = $db->query('', '
			SELECT id_board, MAX(id_last_msg) AS id_msg
			FROM {db_prefix}topics
			WHERE id_board IN ({array_int:board_list})
				AND approved = {int:approved}
			GROUP BY id_board',
			array(
				'board_list' => $setboards,
				'approved' => 1,
			)
		);
		$lastMsg = array();
		while ($row = $db->fetch_assoc($request))
			$lastMsg[$row['id_board']] = $row['id_msg'];
		$db->free_result($request);
	}
	else
	{
		// Just to note - there should only be one board passed if we are doing this.
		foreach ($setboards as $id_board)
			$lastMsg[$id_board] = $id_msg;
	}

	$parent_boards = array();
	// Keep track of last modified dates.
	$lastModified = $lastMsg;
	// Get all the child boards for the parents, if they have some...
	foreach ($setboards as $id_board)
	{
		if (!isset($lastMsg[$id_board]))
		{
			$lastMsg[$id_board] = 0;
			$lastModified[$id_board] = 0;
		}

		if (!empty($board) && $id_board == $board)
			$parents = $board_info['parent_boards'];
		else
			$parents = getBoardParents($id_board);

		// Ignore any parents on the top child level.
		// @todo Why?
		foreach ($parents as $id => $parent)
		{
			if ($parent['level'] != 0)
			{
				// If we're already doing this one as a board, is this a higher last modified?
				if (isset($lastModified[$id]) && $lastModified[$id_board] > $lastModified[$id])
					$lastModified[$id] = $lastModified[$id_board];
				elseif (!isset($lastModified[$id]) && (!isset($parent_boards[$id]) || $parent_boards[$id] < $lastModified[$id_board]))
					$parent_boards[$id] = $lastModified[$id_board];
			}
		}
	}

	// Note to help understand what is happening here. For parents we update the timestamp of the last message for determining
	// whether there are child boards which have not been read. For the boards themselves we update both this and id_last_msg.

	$board_updates = array();
	$parent_updates = array();
	// Finally, to save on queries make the changes...
	foreach ($parent_boards as $id => $msg)
	{
		if (!isset($parent_updates[$msg]))
			$parent_updates[$msg] = array($id);
		else
			$parent_updates[$msg][] = $id;
	}

	foreach ($lastMsg as $id => $msg)
	{
		if (!isset($board_updates[$msg . '-' . $lastModified[$id]]))
			$board_updates[$msg . '-' . $lastModified[$id]] = array(
				'id' => $msg,
				'updated' => $lastModified[$id],
				'boards' => array($id)
			);

		else
			$board_updates[$msg . '-' . $lastModified[$id]]['boards'][] = $id;
	}

	// Now commit the changes!
	foreach ($parent_updates as $id_msg => $boards)
	{
		$db->query('', '
			UPDATE {db_prefix}boards
			SET id_msg_updated = {int:id_msg_updated}
			WHERE id_board IN ({array_int:board_list})
				AND id_msg_updated < {int:id_msg_updated}',
			array(
				'board_list' => $boards,
				'id_msg_updated' => $id_msg,
			)
		);
	}
	foreach ($board_updates as $board_data)
	{
		$db->query('', '
			UPDATE {db_prefix}boards
			SET id_last_msg = {int:id_last_msg}, id_msg_updated = {int:id_msg_updated}
			WHERE id_board IN ({array_int:board_list})',
			array(
				'board_list' => $board_data['boards'],
				'id_last_msg' => $board_data['id'],
				'id_msg_updated' => $board_data['updated'],
			)
		);
	}
}

/**
 * This simple function gets a list of all administrators and sends them an email
 *  to let them know a new member has joined.
 * Called by registerMember() function in subs/Members.subs.php.
 * Email is sent to all groups that have the moderate_forum permission.
 * The language set by each member is being used (if available).
 *
 * @param string $type types supported are 'approval', 'activation', and 'standard'.
 * @param int $memberID
 * @param string $member_name = null
 * @uses the Login language file.
 */
function adminNotify($type, $memberID, $member_name = null)
{
	global $modSettings, $language, $scripturl, $user_info;

	$db = database();

	// If the setting isn't enabled then just exit.
	if (empty($modSettings['notify_new_registration']))
		return;

	// Needed to notify admins, or anyone
	require_once(SUBSDIR . '/Mail.subs.php');

	if ($member_name == null)
	{
		require_once(SUBSDIR . '/Members.subs.php');
		// Get the new user's name....
		$member_info = getBasicMemberData($memberID);
		$member_name = $member_info['real_name'];
	}

	$toNotify = array();
	$groups = array();

	// All membergroups who can approve members.
	$request = $db->query('', '
		SELECT id_group
		FROM {db_prefix}permissions
		WHERE permission = {string:moderate_forum}
			AND add_deny = {int:add_deny}
			AND id_group != {int:id_group}',
		array(
			'add_deny' => 1,
			'id_group' => 0,
			'moderate_forum' => 'moderate_forum',
		)
	);
	while ($row = $db->fetch_assoc($request))
		$groups[] = $row['id_group'];
	$db->free_result($request);

	// Add administrators too...
	$groups[] = 1;
	$groups = array_unique($groups);

	// Get a list of all members who have ability to approve accounts - these are the people who we inform.
	$request = $db->query('', '
		SELECT id_member, lngfile, email_address
		FROM {db_prefix}members
		WHERE (id_group IN ({array_int:group_list}) OR FIND_IN_SET({raw:group_array_implode}, additional_groups) != 0)
			AND notify_types != {int:notify_types}
		ORDER BY lngfile',
		array(
			'group_list' => $groups,
			'notify_types' => 4,
			'group_array_implode' => implode(', additional_groups) != 0 OR FIND_IN_SET(', $groups),
		)
	);

	$current_language = $user_info['language'];
	while ($row = $db->fetch_assoc($request))
	{
		$replacements = array(
			'USERNAME' => $member_name,
			'PROFILELINK' => $scripturl . '?action=profile;u=' . $memberID
		);
		$emailtype = 'admin_notify';

		// If they need to be approved add more info...
		if ($type == 'approval')
		{
			$replacements['APPROVALLINK'] = $scripturl . '?action=admin;area=viewmembers;sa=browse;type=approve';
			$emailtype .= '_approval';
		}

		$emaildata = loadEmailTemplate($emailtype, $replacements, empty($row['lngfile']) || empty($modSettings['userLanguage']) ? $language : $row['lngfile']);

		// And do the actual sending...
		sendmail($row['email_address'], $emaildata['subject'], $emaildata['body'], null, null, false, 0);
	}
	$db->free_result($request);

	if (isset($current_language) && $current_language != $user_info['language'])
		loadLanguage('Login');
}

/**
 * Notifies members who have requested notification for new topics posted on a board of said posts.
 *
 * receives data on the topics to send out notifications to by the passed in array.
 * only sends notifications to those who can *currently* see the topic (it doesn't matter if they could when they requested notification.)
 * loads the Post language file multiple times for each language if the userLanguage setting is set.
 * @param array &$topicData
 */
function notifyMembersBoard(&$topicData)
{
	global $scripturl, $language, $user_info, $modSettings, $webmaster_email;

	$db = database();

	require_once(SUBSDIR . '/Mail.subs.php');

	// Do we have one or lots of topics?
	if (isset($topicData['body']))
		$topicData = array($topicData);

	// Using the post to email functions?
	$maillist = !empty($modSettings['maillist_enabled']) && !empty($modSettings['pbe_post_enabled']);
	if ($maillist)
		require_once(SUBSDIR . '/Emailpost.subs.php');

	// Find out what boards we have... and clear out any rubbish!
	$boards = array();
	foreach ($topicData as $key => $topic)
	{
		if (!empty($topic['board']))
			$boards[$topic['board']][] = $key;
		else
		{
			unset($topic[$key]);
			continue;
		}

		// Using maillist functionality?
		if ($maillist)
		{
			// Convert to markdown markup e.g. styled plain text
			pbe_prepare_text($topicData[$key]['body'], $topicData[$key]['subject'], $topicData[$key]['signature']);
		}
		else
		{
			// Censor the subject and body...
			censorText($topicData[$key]['subject']);
			censorText($topicData[$key]['body']);

			$topicData[$key]['subject'] = un_htmlspecialchars($topicData[$key]['subject']);
			$topicData[$key]['body'] = trim(un_htmlspecialchars(strip_tags(strtr(parse_bbc($topicData[$key]['body'], false), array('<br />' => "\n", '</div>' => "\n", '</li>' => "\n", '&#91;' => '[', '&#93;' => ']')))));
		}
	}

	// Just the board numbers.
	$board_index = array_unique(array_keys($boards));
	if (empty($board_index))
		return;

	// Load the actual board names
	$board_names = array();
	$request = $db->query('', '
		SELECT id_board, name
		FROM {db_prefix}boards
		WHERE id_board IN ({array_int:board_list})',
		array(
			'board_list' => $board_index,
		)
	);
	while ($row = $db->fetch_assoc($request))
		$board_names[$row['id_board']] = $row['name'];
	$db->free_result($request);

	// Yea, we need to add this to the digest queue.
	$digest_insert = array();
	foreach ($topicData as $id => $data)
		$digest_insert[] = array($data['topic'], $data['msg'], 'topic', $user_info['id']);
	$db->insert('',
		'{db_prefix}log_digest',
		array(
			'id_topic' => 'int', 'id_msg' => 'int', 'note_type' => 'string', 'exclude' => 'int',
		),
		$digest_insert,
		array()
	);

	// Find the members with notification on for these boards.
	$members = $db->query('', '
		SELECT
			mem.id_member, mem.email_address, mem.notify_regularity, mem.notify_send_body, mem.lngfile, mem.warning,
			ln.sent, ln.id_board, mem.id_group, mem.additional_groups, b.member_groups, b.id_profile,
			mem.id_post_group
		FROM {db_prefix}log_notify AS ln
			INNER JOIN {db_prefix}boards AS b ON (b.id_board = ln.id_board)
			INNER JOIN {db_prefix}members AS mem ON (mem.id_member = ln.id_member)
		WHERE ln.id_board IN ({array_int:board_list})
			AND mem.id_member != {int:current_member}
			AND mem.is_activated = {int:is_activated}
			AND mem.notify_types != {int:notify_types}
			AND mem.notify_regularity < {int:notify_regularity}
		ORDER BY mem.lngfile',
		array(
			'current_member' => $user_info['id'],
			'board_list' => $board_index,
			'is_activated' => 1,
			'notify_types' => 4,
			'notify_regularity' => 2,
		)
	);
	// While we have members with board notifications
	while ($rowmember = $db->fetch_assoc($members))
	{
		$email_perm = true;
		if (validateAccess($rowmember, $maillist, $email_perm) === false)
			continue;

		$langloaded = loadLanguage('index', empty($rowmember['lngfile']) || empty($modSettings['userLanguage']) ? $language : $rowmember['lngfile'], false);

		// Now loop through all the notifications to send for this board.
		if (empty($boards[$rowmember['id_board']]))
			continue;

		$sentOnceAlready = 0;

		// For each message we need to send (from this board to this member)
		foreach ($boards[$rowmember['id_board']] as $key)
		{
			// Don't notify the guy who started the topic!
			// @todo In this case actually send them a "it's approved hooray" email :P
			if ($topicData[$key]['poster'] == $rowmember['id_member'])
				continue;

			// Setup the string for adding the body to the message, if a user wants it.
			$send_body = $maillist || (empty($modSettings['disallow_sendBody']) && !empty($rowmember['notify_send_body']));

			$replacements = array(
				'TOPICSUBJECT' => $topicData[$key]['subject'],
				'POSTERNAME' => un_htmlspecialchars($topicData[$key]['name']),
				'TOPICLINK' => $scripturl . '?topic=' . $topicData[$key]['topic'] . '.new#new',
				'TOPICLINKNEW' => $scripturl . '?topic=' . $topicData[$key]['topic'] . '.new#new',
				'MESSAGE' => $send_body ? $topicData[$key]['body'] : '',
				'UNSUBSCRIBELINK' => $scripturl . '?action=notifyboard;board=' . $topicData[$key]['board'] . '.0',
				'SIGNATURE' => !empty($topicData[$key]['signature']) ? $topicData[$key]['signature'] : '',
				'BOARDNAME' => $board_names[$topicData[$key]['board']],
			);

			// Figure out which email to send
			$emailtype = '';

			// Send only if once is off or it's on and it hasn't been sent.
			if (!empty($rowmember['notify_regularity']) && !$sentOnceAlready && empty($rowmember['sent']))
				$emailtype = 'notify_boards_once';
			elseif (empty($rowmember['notify_regularity']))
				$emailtype = 'notify_boards';

			if (!empty($emailtype))
			{
				$emailtype .= $send_body ? '_body' : '';
				$emaildata = loadEmailTemplate((($maillist && $email_perm) ? 'pbe_' : '') . $emailtype, $replacements, $langloaded);
				$emailname = (!empty($topicData[$key]['name'])) ? un_htmlspecialchars($topicData[$key]['name']) : null;

				// Maillist style?
				if ($maillist && $email_perm)
				{
					// Add in the from wrapper and trigger sendmail to add in a security key
					$from_wrapper = !empty($modSettings['maillist_sitename_address']) ? $modSettings['maillist_sitename_address'] : (empty($modSettings['maillist_mail_from']) ? $webmaster_email : $modSettings['maillist_mail_from']);
					sendmail($rowmember['email_address'], $emaildata['subject'], $emaildata['body'], $emailname, 't' . $topicData[$key]['topic'], false, 3, null, false, $from_wrapper, $topicData[$key]['topic']);
				}
				else
					sendmail($rowmember['email_address'], $emaildata['subject'], $emaildata['body'], null, null, false, 3);
			}

			$sentOnceAlready = 1;
		}
	}
	$db->free_result($members);

	loadLanguage('index', $user_info['language']);

	// Sent!
	$db->query('', '
		UPDATE {db_prefix}log_notify
		SET sent = {int:is_sent}
		WHERE id_board IN ({array_int:board_list})
			AND id_member != {int:current_member}',
		array(
			'current_member' => $user_info['id'],
			'board_list' => $board_index,
			'is_sent' => 1,
		)
	);
}

/**
 * Get the latest post made on the system
 * - respects approved, recycled, and board permissions
 *
 * @return array
 */
function lastPost()
{
	global $user_info, $scripturl, $modSettings;

	$db = database();

	// Find it by the board - better to order by board than sort the entire messages table.
	$request = $db->query('substring', '
		SELECT ml.poster_time, ml.subject, ml.id_topic, ml.poster_name, SUBSTRING(ml.body, 1, 385) AS body,
			ml.smileys_enabled
		FROM {db_prefix}boards AS b
			INNER JOIN {db_prefix}messages AS ml ON (ml.id_msg = b.id_last_msg)
		WHERE {query_wanna_see_board}' . (!empty($modSettings['recycle_enable']) && $modSettings['recycle_board'] > 0 ? '
			AND b.id_board != {int:recycle_board}' : '') . '
			AND ml.approved = {int:is_approved}
		ORDER BY b.id_msg_updated DESC
		LIMIT 1',
		array(
			'recycle_board' => $modSettings['recycle_board'],
			'is_approved' => 1,
		)
	);
	if ($db->num_rows($request) == 0)
		return array();
	$row = $db->fetch_assoc($request);
	$db->free_result($request);

	// Censor the subject and post...
	censorText($row['subject']);
	censorText($row['body']);

	$row['body'] = strip_tags(strtr(parse_bbc($row['body'], $row['smileys_enabled']), array('<br />' => '&#10;')));
	$row['body'] = shorten_text($row['body'], !empty($modSettings['lastpost_preview_characters']) ? $modSettings['lastpost_preview_characters'] : 128, true);

	// Send the data.
	return array(
		'topic' => $row['id_topic'],
		'subject' => $row['subject'],
		'short_subject' => shorten_text($row['subject'], !empty($modSettings['subject_length']) ? $modSettings['subject_length'] : 24),
		'preview' => $row['body'],
		'time' => standardTime($row['poster_time']),
		'timestamp' => forum_time(true, $row['poster_time']),
		'href' => $scripturl . '?topic=' . $row['id_topic'] . '.new;topicseen#new',
		'link' => '<a href="' . $scripturl . '?topic=' . $row['id_topic'] . '.new;topicseen#new">' . $row['subject'] . '</a>'
	);
}

function getFormMsgSubject($editing, $topic, $first_subject = '')
{
	global $modSettings, $context;

	$db = database();

	if ($editing)
	{
		require_once(SUBSDIR . '/Messages.subs.php');
		// Get the existing message.
		$message = messageDetails((int) $_REQUEST['msg'], $topic);
		// The message they were trying to edit was most likely deleted.
		if ($message === false)
			fatal_lang_error('no_message', false);

		$errors = checkMessagePermissions($message['message']);

		prepareMessageContext($message);

		if (!empty($errors))
			$message['errors'] = $errors;

		return $message;
	}
	else
	{
		// Posting a quoted reply?
		if ((!empty($topic) && !empty($_REQUEST['quote'])) || !empty($_REQUEST['followup']))
		{
			// Make sure they _can_ quote this post, and if so get it.
			$request = $db->query('', '
				SELECT m.subject, IFNULL(mem.real_name, m.poster_name) AS poster_name, m.poster_time, m.body
				FROM {db_prefix}messages AS m
					INNER JOIN {db_prefix}boards AS b ON (b.id_board = m.id_board AND {query_see_board})
					LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = m.id_member)
				WHERE m.id_msg = {int:id_msg}' . (!$modSettings['postmod_active'] || allowedTo('approve_posts') ? '' : '
					AND m.approved = {int:is_approved}') . '
				LIMIT 1',
				array(
					'id_msg' => !empty($_REQUEST['quote']) ? (int) $_REQUEST['quote'] : (int) $_REQUEST['followup'],
					'is_approved' => 1,
				)
			);
			if ($db->num_rows($request) == 0)
				fatal_lang_error('quoted_post_deleted', false);
			list ($form_subject, $mname, $mdate, $form_message) = $db->fetch_row($request);
			$db->free_result($request);

			// Add 'Re: ' to the front of the quoted subject.
			if (trim($context['response_prefix']) != '' && Util::strpos($form_subject, trim($context['response_prefix'])) !== 0)
				$form_subject = $context['response_prefix'] . $form_subject;

			// Censor the message and subject.
			censorText($form_message);
			censorText($form_subject);

			// But if it's in HTML world, turn them into htmlspecialchar's so they can be edited!
			if (strpos($form_message, '[html]') !== false)
			{
				$parts = preg_split('~(\[/code\]|\[code(?:=[^\]]+)?\])~i', $form_message, -1, PREG_SPLIT_DELIM_CAPTURE);
				for ($i = 0, $n = count($parts); $i < $n; $i++)
				{
					// It goes 0 = outside, 1 = begin tag, 2 = inside, 3 = close tag, repeat.
					if ($i % 4 == 0)
						$parts[$i] = preg_replace('~\[html\](.+?)\[/html\]~ise', '\'[html]\' . preg_replace(\'~<br\s?/?' . '>~i\', \'&lt;br /&gt;<br />\', \'$1\') . \'[/html]\'', $parts[$i]);
				}
				$form_message = implode('', $parts);
			}

			$form_message = preg_replace('~<br ?/?' . '>~i', "\n", $form_message);

			// Remove any nested quotes, if necessary.
			if (!empty($modSettings['removeNestedQuotes']))
				$form_message = preg_replace(array('~\n?\[quote.*?\].+?\[/quote\]\n?~is', '~^\n~', '~\[/quote\]~'), '', $form_message);

			// Add a quote string on the front and end.
			$form_message = '[quote author=' . $mname . ' link=topic=' . $topic . '.msg' . (int) $_REQUEST['quote'] . '#msg' . (int) $_REQUEST['quote'] . ' date=' . $mdate . ']' . "\n" . rtrim($form_message) . "\n" . '[/quote]';
		}
		// Posting a reply without a quote?
		elseif (!empty($topic) && empty($_REQUEST['quote']))
		{
			// Get the first message's subject.
			$form_subject = $first_subject;

			// Add 'Re: ' to the front of the subject.
			if (trim($context['response_prefix']) != '' && $form_subject != '' && Util::strpos($form_subject, trim($context['response_prefix'])) !== 0)
				$form_subject = $context['response_prefix'] . $form_subject;

			// Censor the subject.
			censorText($form_subject);

			$form_message = '';
		}
		else
		{
			$form_subject = isset($_GET['subject']) ? $_GET['subject'] : '';
			$form_message = '';
		}
		return array($form_subject, $form_message);
	}
}
