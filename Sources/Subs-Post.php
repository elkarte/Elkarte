<?php

/**
 * @name      Dialogo Forum
 * @copyright Dialogo Forum contributors
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

if (!defined('DIALOGO'))
	die('Hacking attempt...');

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
	global $user_info, $modSettings, $smcFunc, $context;

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

	// The regular expression non breaking space has many versions.
	$non_breaking_space = $context['utf8'] ? '\x{A0}' : '\xA0';

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
				$parts[$i] = preg_replace('~(\A|\n)/me(?: |&nbsp;)([^\n]*)(?:\z)?~i', '$1[me=' . $user_info['name'] . ']$2[/me]', $parts[$i]);

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
				'~\[table\](?![\s' . $non_breaking_space . ']*\[tr\])~s' . ($context['utf8'] ? 'u' : '') => '[table][tr]',
				// Find [tr]s not followed by [td].
				'~\[tr\](?![\s' . $non_breaking_space . ']*\[td\])~s' . ($context['utf8'] ? 'u' : '') => '[tr][td]',
				// Find [/td]s not followed by something valid.
				'~\[/td\](?![\s' . $non_breaking_space . ']*(?:\[td\]|\[/tr\]|\[/table\]))~s' . ($context['utf8'] ? 'u' : '') => '[/td][/tr]',
				// Find [/tr]s not followed by something valid.
				'~\[/tr\](?![\s' . $non_breaking_space . ']*(?:\[tr\]|\[/table\]))~s' . ($context['utf8'] ? 'u' : '') => '[/tr][/table]',
				// Find [/td]s incorrectly followed by [/table].
				'~\[/td\][\s' . $non_breaking_space . ']*\[/table\]~s' . ($context['utf8'] ? 'u' : '') => '[/td][/tr][/table]',
				// Find [table]s, [tr]s, and [/td]s (possibly correctly) followed by [td].
				'~\[(table|tr|/td)\]([\s' . $non_breaking_space . ']*)\[td\]~s' . ($context['utf8'] ? 'u' : '') => '[$1]$2[_td_]',
				// Now, any [td]s left should have a [tr] before them.
				'~\[td\]~s' => '[tr][td]',
				// Look for [tr]s which are correctly placed.
				'~\[(table|/tr)\]([\s' . $non_breaking_space . ']*)\[tr\]~s' . ($context['utf8'] ? 'u' : '') => '[$1]$2[_tr_]',
				// Any remaining [tr]s should have a [table] before them.
				'~\[tr\]~s' => '[table][tr]',
				// Look for [/td]s followed by [/tr].
				'~\[/td\]([\s' . $non_breaking_space . ']*)\[/tr\]~s' . ($context['utf8'] ? 'u' : '') => '[/td]$1[_/tr_]',
				// Any remaining [/tr]s should have a [/td].
				'~\[/tr\]~s' => '[/td][/tr]',
				// Look for properly opened [li]s which aren't closed.
				'~\[li\]([^\[\]]+?)\[li\]~s' => '[li]$1[_/li_][_li_]',
				'~\[li\]([^\[\]]+?)\[/list\]~s' => '[_li_]$1[_/li_][/list]',
				'~\[li\]([^\[\]]+?)$~s' => '[li]$1[/li]',
				// Lists - find correctly closed items/lists.
				'~\[/li\]([\s' . $non_breaking_space . ']*)\[/list\]~s' . ($context['utf8'] ? 'u' : '') => '[_/li_]$1[/list]',
				// Find list items closed and then opened.
				'~\[/li\]([\s' . $non_breaking_space . ']*)\[li\]~s' . ($context['utf8'] ? 'u' : '') => '[_/li_]$1[_li_]',
				// Now, find any [list]s or [/li]s followed by [li].
				'~\[(list(?: [^\]]*?)?|/li)\]([\s' . $non_breaking_space . ']*)\[li\]~s' . ($context['utf8'] ? 'u' : '') => '[$1]$2[_li_]',
				// Allow for sub lists.
				'~\[/li\]([\s' . $non_breaking_space . ']*)\[list\]~' . ($context['utf8'] ? 'u' : '') => '[_/li_]$1[list]',
				'~\[/list\]([\s' . $non_breaking_space . ']*)\[li\]~' . ($context['utf8'] ? 'u' : '') => '[/list]$1[_li_]',
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
	}

	// Put it back together!
	if (!$previewing)
		$message = strtr(implode('', $parts), array('  ' => '&nbsp; ', "\n" => '<br />', $context['utf8'] ? "\xC2\xA0" : "\xA0" => '&nbsp;'));
	else
		$message = strtr(implode('', $parts), array('  ' => '&nbsp; ', $context['utf8'] ? "\xC2\xA0" : "\xA0" => '&nbsp;'));

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
	global $smcFunc;

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
			$parts[$i] = preg_replace('~\[time\](\d{0,10})\[/time\]~ie', '\'[time]\' . timeformat(\'$1\', false) . \'[/time]\'', $parts[$i]);
		}
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
	global $modSettings, $sourcedir;

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

	// Fix each type of tag.
	foreach ($fixArray as $param)
		fixTag($message, $param['tag'], $param['protocols'], $param['embeddedUrl'], $param['hasEqualSign'], !empty($param['hasExtra']));

	// Now fix possible security problems with images loading links automatically...
	$message = preg_replace('~(\[img.*?\])(.+?)\[/img\]~eis', '\'$1\' . preg_replace(\'~action(=|%3d)(?!dlattach)~i\', \'action-\', \'$2\') . \'[/img]\'', $message);

	// Limit the size of images posted?
	if (!empty($modSettings['max_image_width']) || !empty($modSettings['max_image_height']))
	{
		// We'll need this for image processing
		require_once($sourcedir . '/Subs-Attachments.php');

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

/**
 * Spell checks the post for typos ;).
 * It uses the pspell library, which MUST be installed.
 * It has problems with internationalization.
 * It is accessed via ?action=spellcheck.
 */
function action_spellcheck()
{
	global $txt, $context, $smcFunc;

	// A list of "words" we know about but pspell doesn't.
	$known_words = array('dialogo', 'php', 'mysql', 'www', 'gif', 'jpeg', 'png', 'http', 'grandia', 'terranigma', 'rpgs');

	loadLanguage('Post');
	loadTemplate('Post');

	// Okay, this looks funny, but it actually fixes a weird bug.
	ob_start();
	$old = error_reporting(0);

	// See, first, some windows machines don't load pspell properly on the first try.  Dumb, but this is a workaround.
	pspell_new('en');

	// Next, the dictionary in question may not exist. So, we try it... but...
	$pspell_link = pspell_new($txt['lang_dictionary'], $txt['lang_spelling'], '', strtr($context['character_set'], array('iso-' => 'iso', 'ISO-' => 'iso')), PSPELL_FAST | PSPELL_RUN_TOGETHER);

	// Most people don't have anything but English installed... So we use English as a last resort.
	if (!$pspell_link)
		$pspell_link = pspell_new('en', '', '', '', PSPELL_FAST | PSPELL_RUN_TOGETHER);

	error_reporting($old);
	ob_end_clean();

	if (!isset($_POST['spellstring']) || !$pspell_link)
		die;

	// Construct a bit of Javascript code.
	$context['spell_js'] = '
		var txt = {"done": "' . $txt['spellcheck_done'] . '"};
		var mispstr = ' . ($_POST['fulleditor'] === 'true' ? 'window.opener.spellCheckGetText(spell_fieldname)' : 'window.opener.document.forms[spell_formname][spell_fieldname].value') . ';
		var misps = Array(';

	// Get all the words (Javascript already separated them).
	$alphas = explode("\n", strtr($_POST['spellstring'], array("\r" => '')));

	$found_words = false;
	for ($i = 0, $n = count($alphas); $i < $n; $i++)
	{
		// Words are sent like 'word|offset_begin|offset_end'.
		$check_word = explode('|', $alphas[$i]);

		// If the word is a known word, or spelled right...
		if (in_array($smcFunc['strtolower']($check_word[0]), $known_words) || pspell_check($pspell_link, $check_word[0]) || !isset($check_word[2]))
			continue;

		// Find the word, and move up the "last occurance" to here.
		$found_words = true;

		// Add on the javascript for this misspelling.
		$context['spell_js'] .= '
			new misp("' . strtr($check_word[0], array('\\' => '\\\\', '"' => '\\"', '<' => '', '&gt;' => '')) . '", ' . (int) $check_word[1] . ', ' . (int) $check_word[2] . ', [';

		// If there are suggestions, add them in...
		$suggestions = pspell_suggest($pspell_link, $check_word[0]);
		if (!empty($suggestions))
		{
			// But first check they aren't going to be censored - no naughty words!
			foreach ($suggestions as $k => $word)
				if ($suggestions[$k] != censorText($word))
					unset($suggestions[$k]);

			if (!empty($suggestions))
				$context['spell_js'] .= '"' . implode('", "', $suggestions) . '"';
		}

		$context['spell_js'] .= ']),';
	}

	// If words were found, take off the last comma.
	if ($found_words)
		$context['spell_js'] = substr($context['spell_js'], 0, -1);

	$context['spell_js'] .= '
		);';

	// And instruct the template system to just show the spellcheck sub template.
	$context['template_layers'] = array();
	$context['sub_template'] = 'spellcheck';
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
function sendNotifications($topics, $type, $exclude = array(), $members_only = array())
{
	global $txt, $scripturl, $language, $user_info;
	global $modSettings, $sourcedir, $context, $smcFunc;

	// Can't do it if there's no topics.
	if (empty($topics))
		return;

	// It must be an array - it must!
	if (!is_array($topics))
		$topics = array($topics);

	// Email functions will be helpful here
	require_once($sourcedir . '/Subs-Mail.php');

	// Get the subject and body...
	$result = $smcFunc['db_query']('', '
		SELECT mf.subject, ml.body, ml.id_member, t.id_last_msg, t.id_topic,
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
	while ($row = $smcFunc['db_fetch_assoc']($result))
	{
		// Clean it up.
		censorText($row['subject']);
		censorText($row['body']);
		$row['subject'] = un_htmlspecialchars($row['subject']);
		$row['body'] = trim(un_htmlspecialchars(strip_tags(strtr(parse_bbc($row['body'], false, $row['id_last_msg']), array('<br />' => "\n", '</div>' => "\n", '</li>' => "\n", '&#91;' => '[', '&#93;' => ']')))));

		$topicData[$row['id_topic']] = array(
			'subject' => $row['subject'],
			'body' => $row['body'],
			'last_id' => $row['id_last_msg'],
			'topic' => $row['id_topic'],
			'name' => $user_info['name'],
			'exclude' => '',
		);
	}
	$smcFunc['db_free_result']($result);

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
	$smcFunc['db_insert']('',
		'{db_prefix}log_digest',
		array(
			'id_topic' => 'int', 'id_msg' => 'int', 'note_type' => 'string', 'exclude' => 'int',
		),
		$digest_insert,
		array()
	);

	// Find the members with notification on for this topic.
	$members = $smcFunc['db_query']('', '
		SELECT
			mem.id_member, mem.email_address, mem.notify_regularity, mem.notify_types, mem.notify_send_body, mem.lngfile,
			ln.sent, mem.id_group, mem.additional_groups, b.member_groups, mem.id_post_group, t.id_member_started,
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
			'current_member' => $user_info['id'],
			'topic_list' => $topics,
			'notify_types' => $type == 'reply' ? '4' : '3',
			'notify_regularity' => 2,
			'is_activated' => 1,
			'members_only' => is_array($members_only) ? $members_only : array($members_only),
		)
	);
	$sent = 0;
	$current_language = '';
	while ($row = $smcFunc['db_fetch_assoc']($members))
	{
		// Don't do the excluded...
		if ($topicData[$row['id_topic']]['exclude'] == $row['id_member'])
			continue;

		// Easier to check this here... if they aren't the topic poster do they really want to know?
		if ($type != 'reply' && $row['notify_types'] == 2 && $row['id_member'] != $row['id_member_started'])
			continue;

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

		$message_type = 'notification_' . $type;
		$replacements = array(
			'TOPICSUBJECT' => $topicData[$row['id_topic']]['subject'],
			'POSTERNAME' => un_htmlspecialchars($topicData[$row['id_topic']]['name']),
			'TOPICLINK' => $scripturl . '?topic=' . $row['id_topic'] . '.new;topicseen#new',
			'UNSUBSCRIBELINK' => $scripturl . '?action=notify;topic=' . $row['id_topic'] . '.0',
		);

		if ($type == 'remove')
			unset($replacements['TOPICLINK'], $replacements['UNSUBSCRIBELINK']);
		// Do they want the body of the message sent too?
		if (!empty($row['notify_send_body']) && $type == 'reply' && empty($modSettings['disallow_sendBody']))
		{
			$message_type .= '_body';
			$replacements['MESSAGE'] = $topicData[$row['id_topic']]['body'];
		}
		if (!empty($row['notify_regularity']) && $type == 'reply')
			$message_type .= '_once';

		// Send only if once is off or it's on and it hasn't been sent.
		if ($type != 'reply' || empty($row['notify_regularity']) || empty($row['sent']))
		{
			$emaildata = loadEmailTemplate($message_type, $replacements, $needed_language);
			sendmail($row['email_address'], $emaildata['subject'], $emaildata['body'], null, 'm' . $topicData[$row['id_topic']]['last_id']);
			$sent++;
		}
	}
	$smcFunc['db_free_result']($members);

	if (isset($current_language) && $current_language != $user_info['language'])
		loadLanguage('Post');

	// Sent!
	if ($type == 'reply' && !empty($sent))
		$smcFunc['db_query']('', '
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

	// For approvals we need to unsend the exclusions (This *is* the quickest way!)
	if (!empty($sent) && !empty($exclude))
	{
		foreach ($topicData as $id => $data)
			if ($data['exclude'])
				$smcFunc['db_query']('', '
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
	global $user_info, $txt, $modSettings, $smcFunc, $context, $sourcedir;

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
		$request = $smcFunc['db_query']('', '
			SELECT approved
			FROM {db_prefix}topics
			WHERE id_topic = {int:id_topic}
			LIMIT 1',
			array(
				'id_topic' => $topicOptions['id'],
			)
		);
		list ($topicOptions['is_approved']) = $smcFunc['db_fetch_row']($request);
		$smcFunc['db_free_result']($request);
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
			$request = $smcFunc['db_query']('', '
				SELECT member_name, email_address
				FROM {db_prefix}members
				WHERE id_member = {int:id_member}
				LIMIT 1',
				array(
					'id_member' => $posterOptions['id'],
				)
			);
			// Couldn't find the current poster?
			if ($smcFunc['db_num_rows']($request) == 0)
			{
				trigger_error('createPost(): Invalid member id ' . $posterOptions['id'], E_USER_NOTICE);
				$posterOptions['id'] = 0;
				$posterOptions['name'] = $txt['guest_title'];
				$posterOptions['email'] = '';
			}
			else
				list ($posterOptions['name'], $posterOptions['email']) = $smcFunc['db_fetch_row']($request);
			$smcFunc['db_free_result']($request);
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
	call_integration_hook('integrate_create_post', array($msgOptions, $topicOptions, $posterOptions, $message_columns, $message_parameters));

	// Insert the post.
	$smcFunc['db_insert']('',
		'{db_prefix}messages',
		$message_columns,
		$message_parameters,
		array('id_msg')
	);
	$msgOptions['id'] = $smcFunc['db_insert_id']('{db_prefix}messages', 'id_msg');

	// Something went wrong creating the message...
	if (empty($msgOptions['id']))
		return false;

	// Fix the attachments.
	if (!empty($msgOptions['attachments']))
		$smcFunc['db_query']('', '
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

		call_integration_hook('integrate_before_create_topic', array($msgOptions, $topicOptions, $posterOptions, $topic_columns, $topic_parameters));

		$smcFunc['db_insert']('',
			'{db_prefix}topics',
			$topic_columns,
			$topic_parameters,
			array('id_topic')
		);
		$topicOptions['id'] = $smcFunc['db_insert_id']('{db_prefix}topics', 'id_topic');

		// The topic couldn't be created for some reason.
		if (empty($topicOptions['id']))
		{
			// We should delete the post that did work, though...
			$smcFunc['db_query']('', '
				DELETE FROM {db_prefix}messages
				WHERE id_msg = {int:id_msg}',
				array(
					'id_msg' => $msgOptions['id'],
				)
			);

			return false;
		}

		// Fix the message with the topic.
		$smcFunc['db_query']('', '
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

		call_integration_hook('integrate_modify_topic', array($topics_columns, $update_parameters, $msgOptions, $topicOptions, $posterOptions));

		// Update the number of replies and the lock/sticky status.
		$smcFunc['db_query']('', '
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
	$smcFunc['db_query']('', '
		UPDATE {db_prefix}messages
		SET id_msg_modified = {int:id_msg}
		WHERE id_msg = {int:id_msg}',
		array(
			'id_msg' => $msgOptions['id'],
		)
	);

	// Increase the number of posts and topics on the board.
	if ($msgOptions['approved'])
		$smcFunc['db_query']('', '
			UPDATE {db_prefix}boards
			SET num_posts = num_posts + 1' . ($new_topic ? ', num_topics = num_topics + 1' : '') . '
			WHERE id_board = {int:id_board}',
			array(
				'id_board' => $topicOptions['board'],
			)
		);
	else
	{
		$smcFunc['db_query']('', '
			UPDATE {db_prefix}boards
			SET unapproved_posts = unapproved_posts + 1' . ($new_topic ? ', unapproved_topics = unapproved_topics + 1' : '') . '
			WHERE id_board = {int:id_board}',
			array(
				'id_board' => $topicOptions['board'],
			)
		);

		// Add to the approval queue too.
		$smcFunc['db_insert']('',
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
			$smcFunc['db_query']('', '
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

			$flag = $smcFunc['db_affected_rows']() != 0;
		}

		if (empty($flag))
		{
			require_once($sourcedir . '/Subs-Topic.php');
			markTopicsRead(array($posterOptions['id'], $topicOptions['id'], $msgOptions['id']), false);
		}
	}

	// If there's a custom search index, it may need updating...
	require_once($sourcedir . '/Search.php');
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
	global $user_info, $modSettings, $smcFunc, $context, $sourcedir;

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
			$request = $smcFunc['db_query']('', '
				SELECT body
				FROM {db_prefix}messages
				WHERE id_msg = {int:id_msg}',
				array(
					'id_msg' => $msgOptions['id'],
				)
			);
			list ($msgOptions['old_body']) = $smcFunc['db_fetch_row']($request);
			$smcFunc['db_free_result']($request);
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

	call_integration_hook('integrate_modify_post', array($messages_columns, $update_parameters, $msgOptions, $topicOptions, $posterOptions, $messageInts));

	foreach ($messages_columns as $var => $val)
	{
		$messages_columns[$var] = $var . ' = {' . (in_array($var, $messageInts) ? 'int' : 'string') . ':var_' . $var . '}';
		$update_parameters['var_' . $var] = $val;
	}

	// Nothing to do?
	if (empty($messages_columns))
		return true;

	// Change the post.
	$smcFunc['db_query']('', '
		UPDATE {db_prefix}messages
		SET ' . implode(', ', $messages_columns) . '
		WHERE id_msg = {int:id_msg}',
		$update_parameters
	);

	// Lock and or sticky the post.
	if ($topicOptions['sticky_mode'] !== null || $topicOptions['lock_mode'] !== null || $topicOptions['poll'] !== null)
	{
		$smcFunc['db_query']('', '
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
		$smcFunc['db_query']('', '
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

		$flag = $smcFunc['db_affected_rows']() != 0;

		if (empty($flag))
		{
			require_once($sourcedir . '/Subs-Topic.php');
			markTopicsRead(array($user_info['id'], $topicOptions['id'], $modSettings['maxMsgID']), false);
		}
	}

	// If there's a custom search index, it needs to be modified...
	require_once($sourcedir . '/Search.php');
	$searchAPI = findSearchAPI();
	if (is_callable(array($searchAPI, 'postModified')))
		$searchAPI->postModified($msgOptions, $topicOptions, $posterOptions);

	if (isset($msgOptions['subject']))
	{
		// Only update the subject if this was the first message in the topic.
		$request = $smcFunc['db_query']('', '
			SELECT id_topic
			FROM {db_prefix}topics
			WHERE id_first_msg = {int:id_first_msg}
			LIMIT 1',
			array(
				'id_first_msg' => $msgOptions['id'],
			)
		);
		if ($smcFunc['db_num_rows']($request) == 1)
			updateStats('subject', $topicOptions['id'], $msgOptions['subject']);
		$smcFunc['db_free_result']($request);
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
	global $sourcedir, $smcFunc;

	if (!is_array($msgs))
		$msgs = array($msgs);

	if (empty($msgs))
		return false;

	// May as well start at the beginning, working out *what* we need to change.
	$request = $smcFunc['db_query']('', '
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
	while ($row = $smcFunc['db_fetch_assoc']($request))
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
	$smcFunc['db_free_result']($request);

	if (empty($msgs))
		return;

	// Now we have the differences make the changes, first the easy one.
	$smcFunc['db_query']('', '
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
		$request = $smcFunc['db_query']('', '
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
		while ($row = $smcFunc['db_fetch_assoc']($request))
			$topic_changes[$row['id_topic']]['id_last_msg'] = $row['id_last_msg'];
		$smcFunc['db_free_result']($request);
	}

	// ... next the topics...
	foreach ($topic_changes as $id => $changes)
		$smcFunc['db_query']('', '
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
		$smcFunc['db_query']('', '
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
		{
			require_once($sourcedir . '/Post.php');
			notifyMembersBoard($notification_topics);
		}
		if (!empty($notification_posts))
			sendApprovalNotifications($notification_posts);

		$smcFunc['db_query']('', '
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

		$smcFunc['db_insert']('ignore',
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
	global $smcFunc;

	if (!is_array($topics))
		$topics = array($topics);

	if (empty($topics))
		return false;

	$approve_type = $approve ? 0 : 1;

	// Just get the messages to be approved and pass through...
	$request = $smcFunc['db_query']('', '
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
	while ($row = $smcFunc['db_fetch_assoc']($request))
		$msgs[] = $row['id_msg'];
	$smcFunc['db_free_result']($request);

	return approvePosts($msgs, $approve);
}

/**
 * A special function for handling the hell which is sending approval notifications.
 *
 * @param $topicData
 */
function sendApprovalNotifications(&$topicData)
{
	global $txt, $scripturl, $language, $user_info;
	global $modSettings, $sourcedir, $context, $smcFunc;

	// Clean up the data...
	if (!is_array($topicData) || empty($topicData))
		return;

	// Email ahoy
	require_once($sourcedir . '/Subs-Mail.php');

	$topics = array();
	$digest_insert = array();
	foreach ($topicData as $topic => $msgs)
		foreach ($msgs as $msgKey => $msg)
		{
			censorText($topicData[$topic][$msgKey]['subject']);
			censorText($topicData[$topic][$msgKey]['body']);
			$topicData[$topic][$msgKey]['subject'] = un_htmlspecialchars($topicData[$topic][$msgKey]['subject']);
			$topicData[$topic][$msgKey]['body'] = trim(un_htmlspecialchars(strip_tags(strtr(parse_bbc($topicData[$topic][$msgKey]['body'], false), array('<br />' => "\n", '</div>' => "\n", '</li>' => "\n", '&#91;' => '[', '&#93;' => ']')))));

			$topics[] = $msg['id'];
			$digest_insert[] = array($msg['topic'], $msg['id'], 'reply', $user_info['id']);
		}

	// These need to go into the digest too...
	$smcFunc['db_insert']('',
		'{db_prefix}log_digest',
		array(
			'id_topic' => 'int', 'id_msg' => 'int', 'note_type' => 'string', 'exclude' => 'int',
		),
		$digest_insert,
		array()
	);

	// Find everyone who needs to know about this.
	$members = $smcFunc['db_query']('', '
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
	while ($row = $smcFunc['db_fetch_assoc']($members))
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
	$smcFunc['db_free_result']($members);

	if (isset($current_language) && $current_language != $user_info['language'])
		loadLanguage('Post');

	// Sent!
	if (!empty($sent))
		$smcFunc['db_query']('', '
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
	global $board_info, $board, $modSettings, $smcFunc;

	// Please - let's be sane.
	if (empty($setboards))
		return false;

	if (!is_array($setboards))
		$setboards = array($setboards);

	// If we don't know the id_msg we need to find it.
	if (!$id_msg)
	{
		// Find the latest message on this board (highest id_msg.)
		$request = $smcFunc['db_query']('', '
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
		while ($row = $smcFunc['db_fetch_assoc']($request))
			$lastMsg[$row['id_board']] = $row['id_msg'];
		$smcFunc['db_free_result']($request);
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
		$smcFunc['db_query']('', '
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
		$smcFunc['db_query']('', '
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
 * Called by registerMember() function in Subs-Members.php.
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
	global $txt, $modSettings, $language, $scripturl, $user_info, $context, $smcFunc;

	// If the setting isn't enabled then just exit.
	if (empty($modSettings['notify_new_registration']))
		return;

	// Needed to notify admins, or anyone
	require_once($sourcedir . '/Subs-Mail.php');

	if ($member_name == null)
	{
		// Get the new user's name....
		$request = $smcFunc['db_query']('', '
			SELECT real_name
			FROM {db_prefix}members
			WHERE id_member = {int:id_member}
			LIMIT 1',
			array(
				'id_member' => $memberID,
			)
		);
		list ($member_name) = $smcFunc['db_fetch_row']($request);
		$smcFunc['db_free_result']($request);
	}

	$toNotify = array();
	$groups = array();

	// All membergroups who can approve members.
	$request = $smcFunc['db_query']('', '
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
	while ($row = $smcFunc['db_fetch_assoc']($request))
		$groups[] = $row['id_group'];
	$smcFunc['db_free_result']($request);

	// Add administrators too...
	$groups[] = 1;
	$groups = array_unique($groups);

	// Get a list of all members who have ability to approve accounts - these are the people who we inform.
	$request = $smcFunc['db_query']('', '
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
	while ($row = $smcFunc['db_fetch_assoc']($request))
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
	$smcFunc['db_free_result']($request);

	if (isset($current_language) && $current_language != $user_info['language'])
		loadLanguage('Login');
}