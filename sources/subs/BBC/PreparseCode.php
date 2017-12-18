<?php

/**
 * This class contains those functions pertaining to preparsing BBC data
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * This file contains code covered by:
 * copyright:    2011 Simple Machines (http://www.simplemachines.org)
 * license:    BSD, See included LICENSE.TXT for terms and conditions.
 *
 * @version 2.0-dev
 *
 */

namespace BBC;

/**
 * Class PreparseCode
 *
 * @package BBC
 */
class PreparseCode
{
	/** The regular expression non breaking space */
	const NBS = '\x{A0}';
	/** @var string the message to preparse */
	public $message = '';
	/** @var bool if this is just a preview */
	protected $previewing = false;
	/** @var array the code blocks that we want to protect */
	public $code_blocks = array();
	/** @var PreparseCode */
	public static $instance;

	/**
	 * PreparseCode constructor.
	 */
	public function __construct()
	{
	}

	/**
	 * Takes a message and parses it, returning the prepared message as a reference
	 * for use by parse_bbc.
	 *
	 * What it does:
	 *   - Cleans up links (javascript, etc.)
	 *   - Fixes improperly constructed lists [lists]
	 *   - Repairs improperly constructed tables, row, headers, etc
	 *   - Protects code sections
	 *   - Checks for proper quote open / closing
	 *   - Processes /me tag
	 *   - Converts color tags to ones parse_bbc will understand
	 *   - Removes empty tags outside of code blocks
	 *   - Won't convert \n's and a few other things if previewing is true.
	 *
	 * @param string $message
	 * @param boolean $previewing
	 */
	public function preparsecode(&$message, $previewing = false)
	{
		// Load passed values to the class
		$this->message = $message;
		$this->previewing = $previewing;

		// This line makes all languages *theoretically* work even with the wrong charset ;).
		$this->message = preg_replace('~&amp;#(\d{4,5}|[2-9]\d{2,4}|1[2-9]\d);~', '&#$1;', $this->message);

		// Clean up after nobbc ;).
		$this->message = preg_replace_callback('~\[nobbc\](.+?)\[/nobbc\]~i', array($this, '_preparsecode_nobbc_callback'), $this->message);

		// Remove \r's... they're evil!
		$this->message = strtr($this->message, array("\r" => ''));

		// You won't believe this - but too many periods upsets apache it seems!
		$this->message = preg_replace('~\.{100,}~', '...', $this->message);

		// Remove Trailing Quotes
		$this->_trimTrailingQuotes();

		// Validate code blocks are properly closed.
		$this->_validateCodeBlocks();

		// Protect CODE blocks from further processing
		$this->_tokenizeCodeBlocks();

		//  Now that we've fixed all the code tags, let's fix the img and url tags...
		$this->_fixTags();

		// Replace /me.+?\n with [me=name]dsf[/me]\n.
		$this->_itsAllAbout();

		// Make sure list and table tags are lowercase.
		$this->message = preg_replace_callback('~\[([/]?)(list|li|table|tr|td|th)((\s[^\]]+)*)\]~i', array($this, '_preparsecode_lowertags_callback'), $this->message);

		// Don't leave any lists that were never opened or closed
		$this->_validateLists();

		// Attempt to repair common BBC input mistakes
		$this->_fixMistakes();

		// Remove empty bbc tags
		$this->message = preg_replace('~\[[bisu]\]\s*\[/[bisu]\]~', '', $this->message);
		$this->message = preg_replace('~\[quote\]\s*\[/quote\]~', '', $this->message);

		// Fix color tags of many forms so they parse properly
		$this->message = preg_replace('~\[color=(?:#[\da-fA-F]{3}|#[\da-fA-F]{6}|[A-Za-z]{1,20}|rgb\(\d{1,3}, ?\d{1,3}, ?\d{1,3}\))\]\s*\[/color\]~', '', $this->message);

		// Font tags with multiple fonts (copy&paste in the WYSIWYG by some browsers).
		$this->message = preg_replace_callback('~\[font=([^\]]*)\](.*?(?:\[/font\]))~s', array($this, '_preparsecode_font_callback'), $this->message);

		// Allow integration to do further processing on protected code block message
		call_integration_hook('integrate_preparse_tokenized_code', array(&$this->message, $previewing, $this->code_blocks));

		// Put it back together!
		$this->_restoreCodeBlocks();

		// Allow integration to do further processing
		call_integration_hook('integrate_preparse_code', array(&$this->message, 0, $previewing));

		// Safe Spacing
		if (!$previewing)
		{
			$this->message = strtr($this->message, array('  ' => '&nbsp; ', "\n" => '<br />', "\xC2\xA0" => '&nbsp;'));
		}
		else
		{
			$this->message = strtr($this->message, array('  ' => '&nbsp; ', "\xC2\xA0" => '&nbsp;'));
		}

		// Now we're going to do full scale table checking...
		$this->_preparseTable();

		// Quickly clean up things that will slow our parser (which are common in posted code.)
		$message = strtr($this->message, array('[]' => '&#91;]', '[&#039;' => '&#91;&#039;'));
	}

	/**
	 * Trim dangling quotes
	 */
	private function _trimTrailingQuotes()
	{
		// Trim off trailing quotes - these often happen by accident.
		while (substr($this->message, -7) === '[quote]')
		{
			$this->message = trim(substr($this->message, 0, -7));
		}

		// Trim off leading ones as well
		while (substr($this->message, 0, 8) === '[/quote]')
		{
			$this->message = trim(substr($this->message, 8));
		}
	}

	/**
	 * Find all code blocks, work out whether we'd be parsing them,
	 * then ensure they are all closed.
	 */
	private function _validateCodeBlocks()
	{
		$in_tag = false;
		$had_tag = false;
		$code_open = false;

		if (preg_match_all('~(\[(/)*code(?:=[^\]]+)?\])~is', $this->message, $matches))
		{
			foreach ($matches[0] as $index => $dummy)
			{
				// Closing?
				if (!empty($matches[2][$index]))
				{
					// If it's closing and we're not in a tag we need to open it...
					if (!$in_tag)
					{
						$code_open = true;
					}

					// Either way we ain't in one any more.
					$in_tag = false;
				}
				// Opening tag...
				else
				{
					$had_tag = true;

					// If we're in a tag don't do nought!
					if (!$in_tag)
					{
						$in_tag = true;
					}
				}
			}
		}

		// If we have an open code tag, close it.
		if ($in_tag)
		{
			$this->message .= '[/code]';
		}

		// Open any ones that need to be open, only if we've never had a tag.
		if ($code_open && !$had_tag)
		{
			$this->message = '[code]' . $this->message;
		}
	}

	/**
	 * Protects code blocks from preparse by replacing them with %%token%% values
	 */
	private function _tokenizeCodeBlocks()
	{
		// Split up the message on the code start/end tags/
		$parts = preg_split('~(\[/code\]|\[code(?:=[^\]]+)?\])~i', $this->message, -1, PREG_SPLIT_DELIM_CAPTURE);

		// Token generator
		$tokenizer = new \Token_Hash();

		// Separate all code blocks
		for ($i = 0, $n = count($parts); $i < $n; $i++)
		{
			// It goes 0 = outside, 1 = begin tag, 2 = inside, 3 = close tag, repeat.
			if ($i % 4 === 0 && isset($parts[$i + 3]))
			{
				// Create a unique key to put in place of the code block
				$key = $tokenizer->generate_hash(8);

				// Save what is there [code]stuff[/code]
				$this->code_blocks['%%' . $key . '%%'] = $parts[$i + 1] . $parts[$i + 2] . $parts[$i + 3];

				// Replace the code block with %%$key%% so its protected from further preparsecode processing
				$parts[$i + 1] = '%%';
				$parts[$i + 2] = $key;
				$parts[$i + 3] = '%%';
			}
		}

		// The message with code blocks as %%tokens%%
		$this->message = implode('', $parts);
	}

	/**
	 * Fix any URLs posted - ie. remove 'javascript:'.
	 *
	 * - Fix the img and url tags...
	 * - Fixes links in message and returns nothing.
	 */
	private function _fixTags()
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
		);

		// Integration may want to add to this array
		call_integration_hook('integrate_fixtags', array(&$fixArray, &$this->message));

		// Fix each type of tag.
		foreach ($fixArray as $param)
		{
			$this->_fixTag($param['tag'], $param['protocols'], $param['embeddedUrl'], $param['hasEqualSign'], !empty($param['hasExtra']));
		}

		// Now fix possible security problems with images loading links automatically...
		$this->message = preg_replace_callback('~(\[img.*?\])(.+?)\[/img\]~is', array($this, '_fixTags_img_callback'), $this->message);

		// Limit the size of images posted?
		if (!empty($modSettings['max_image_width']) || !empty($modSettings['max_image_height']))
		{
			$this->resizeBBCImages();
		}
	}

	/**
	 * Fix a specific class of tag - ie. url with =.
	 *
	 * - Used by fixTags, fixes a specific tag's links.
	 *
	 * @param string   $myTag - the tag
	 * @param string[] $protocols - http, https or ftp
	 * @param bool     $embeddedUrl = false - whether it *can* be set to something
	 * @param bool     $hasEqualSign = false, whether it *is* set to something
	 * @param bool     $hasExtra = false - whether it can have extra cruft after the begin tag.
	 */
	private function _fixTag($myTag, $protocols, $embeddedUrl = false, $hasEqualSign = false, $hasExtra = false)
	{
		global $boardurl, $scripturl;

		$replaces = array();

		// Ensure it has a domain name, use the site name if needed
		if (preg_match('~^([^:]+://[^/]+)~', $boardurl, $match) != 0)
		{
			$domain_url = $match[1];
		}
		else
		{
			$domain_url = $boardurl . '/';
		}

		if ($hasEqualSign)
		{
			preg_match_all('~\[(' . $myTag . ')=([^\]]*?)\](?:(.+?)\[/(' . $myTag . ')\])?~is', $this->message, $matches);
		}
		else
		{
			preg_match_all('~\[(' . $myTag . ($hasExtra ? '(?:[^\]]*?)' : '') . ')\](.+?)\[/(' . $myTag . ')\]~is', $this->message, $matches);
		}

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
				{
					break;
				}
			}

			// Http url checking?
			if (!$found && $protocols[0] === 'http')
			{
				if (substr($replace, 0, 1) === '/' && substr($replace, 0, 2) !== '//')
				{
					$replace = $domain_url . $replace;
				}
				elseif (substr($replace, 0, 1) === '?')
				{
					$replace = $scripturl . $replace;
				}
				elseif (substr($replace, 0, 1) === '#' && $embeddedUrl)
				{
					$replace = '#' . preg_replace('~[^A-Za-z0-9_\-#]~', '', substr($replace, 1));
					$this_tag = 'iurl';
					$this_close = 'iurl';
				}
				elseif (substr($replace, 0, 2) === '//')
				{
					$replace = $protocols[0] . ':' . $replace;
				}
				else
				{
					$replace = $protocols[0] . '://' . $replace;
				}
			}
			// FTP URL Checking
			elseif (!$found && $protocols[0] === 'ftp')
			{
				$replace = $protocols[0] . '://' . preg_replace('~^(?!ftps?)[^:]+://~', '', $replace);
			}
			elseif (!$found)
			{
				$replace = $protocols[0] . '://' . $replace;
			}

			// Build a replacement array that is considered safe and proper
			if ($hasEqualSign && $embeddedUrl)
			{
				$replaces[$matches[0][$k]] = '[' . $this_tag . '=' . $replace . ']' . (empty($matches[4][$k]) ? '' : $matches[3][$k] . '[/' . $this_close . ']');
			}
			elseif ($hasEqualSign)
			{
				$replaces['[' . $matches[1][$k] . '=' . $matches[2][$k] . ']'] = '[' . $this_tag . '=' . $replace . ']';
			}
			elseif ($embeddedUrl)
			{
				$replaces['[' . $matches[1][$k] . ']' . $matches[2][$k] . '[/' . $matches[3][$k] . ']'] = '[' . $this_tag . '=' . $replace . ']' . $matches[2][$k] . '[/' . $this_close . ']';
			}
			else
			{
				$replaces['[' . $matches[1][$k] . ']' . $matches[2][$k] . '[/' . $matches[3][$k] . ']'] = '[' . $this_tag . ']' . $replace . '[/' . $this_close . ']';
			}
		}

		foreach ($replaces as $k => $v)
		{
			if ($k == $v)
			{
				unset($replaces[$k]);
			}
		}

		// Update as needed
		if (!empty($replaces))
		{
			$this->message = strtr($this->message, $replaces);
		}
	}

	/**
	 * Updates BBC img tags in a message so that the width / height respect the forum settings.
	 *
	 * - Will add the width/height attrib if needed, or update existing ones if they break the rules
	 */
	public function resizeBBCImages()
	{
		global $modSettings;

		// We'll need this for image processing
		require_once(SUBSDIR . '/Attachments.subs.php');

		// Find all the img tags - with or without width and height.
		preg_match_all('~\[img(\s+width=\d+)?(\s+height=\d+)?(\s+width=\d+)?\](.+?)\[/img\]~is', $this->message, $matches, PREG_PATTERN_ORDER);

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
				{
					$desired_width = (int) (($desired_height * $width) / $height);
				}
				// Scale if to the height.
				elseif (!empty($width))
				{
					$desired_height = (int) (($desired_width * $height) / $width);
				}
			}

			// If the width and height are fine, just continue along...
			if ($desired_width <= $modSettings['max_image_width'] && $desired_height <= $modSettings['max_image_height'])
			{
				continue;
			}

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
		{
			$this->message = strtr($this->message, $replaces);
		}
	}

	/**
	 * Replace /me with the users name, including inside footnotes
	 */
	private function _itsAllAbout()
	{
		global $user_info;

		$me_regex = '~(\A|\n)/me(?: |&nbsp;)([^\n]*)(?:\z)?~i';
		$footnote_regex = '~(\[footnote\])/me(?: |&nbsp;)([^\n]*?)(\[\/footnote\])~i';

		if (preg_match('~[\[\]\\"]~', $user_info['name']) !== false)
		{
			$this->message = preg_replace($me_regex, '$1[me=&quot;' . $user_info['name'] . '&quot;]$2[/me]', $this->message);
			$this->message = preg_replace($footnote_regex, '$1[me=&quot;' . $user_info['name'] . '&quot;]$2[/me]$3', $this->message);
		}
		else
		{
			$this->message = preg_replace($me_regex, '$1[me=' . $user_info['name'] . ']$2[/me]', $this->message);
			$this->message = preg_replace($footnote_regex, '$1[me=' . $user_info['name'] . ']$2[/me]$3', $this->message);
		}
	}

	/**
	 * Make sure lists have open and close tags
	 */
	private function _validateLists()
	{
		$list_open = substr_count($this->message, '[list]') + substr_count($this->message, '[list ');
		$list_close = substr_count($this->message, '[/list]');

		if ($list_close - $list_open > 0)
		{
			$this->message = str_repeat('[list]', $list_close - $list_open) . $this->message;
		}

		if ($list_open - $list_close > 0)
		{
			$this->message = $this->message . str_repeat('[/list]', $list_open - $list_close);
		}
	}

	/**
	 * Repair a few *cough* common mistakes from user input and from wizzy cut/paste
	 */
	private function _fixMistakes()
	{
		$mistake_fixes = array(
			// Find [table]s not followed by [tr].
			'~\[table\](?![\s' . self::NBS . ']*\[tr\])~su' => '[table][tr]',
			// Find [tr]s not followed by [td] or [th]
			'~\[tr\](?![\s' . self::NBS . ']*\[t[dh]\])~su' => '[tr][td]',
			// Find [/td] and [/th]s not followed by something valid.
			'~\[/t([dh])\](?![\s' . self::NBS . ']*(?:\[t[dh]\]|\[/tr\]|\[/table\]))~su' => '[/t$1][/tr]',
			// Find [/tr]s not followed by something valid.
			'~\[/tr\](?![\s' . self::NBS . ']*(?:\[tr\]|\[/table\]))~su' => '[/tr][/table]',
			// Find [/td] [/th]s incorrectly followed by [/table].
			'~\[/t([dh])\][\s' . self::NBS . ']*\[/table\]~su' => '[/t$1][/tr][/table]',
			// Find [table]s, [tr]s, and [/td]s (possibly correctly) followed by [td].
			'~\[(table|tr|/td)\]([\s' . self::NBS . ']*)\[td\]~su' => '[$1]$2[_td_]',
			// Now, any [td]s left should have a [tr] before them.
			'~\[td\]~s' => '[tr][td]',
			// Look for [tr]s which are correctly placed.
			'~\[(table|/tr)\]([\s' . self::NBS . ']*)\[tr\]~su' => '[$1]$2[_tr_]',
			// Any remaining [tr]s should have a [table] before them.
			'~\[tr\]~s' => '[table][tr]',
			// Look for [/td]s or [/th]s followed by [/tr].
			'~\[/t([dh])\]([\s' . self::NBS . ']*)\[/tr\]~su' => '[/t$1]$2[_/tr_]',
			// Any remaining [/tr]s should have a [/td].
			'~\[/tr\]~s' => '[/td][/tr]',
			// Look for properly opened [li]s which aren't closed.
			'~\[li\]([^\[\]]+?)\[li\]~s' => '[li]$1[_/li_][_li_]',
			'~\[li\]([^\[\]]+?)\[/list\]~s' => '[_li_]$1[_/li_][/list]',
			'~\[li\]([^\[\]]+?)$~s' => '[li]$1[/li]',
			// Lists - find correctly closed items/lists.
			'~\[/li\]([\s' . self::NBS . ']*)\[/list\]~su' => '[_/li_]$1[/list]',
			// Find list items closed and then opened.
			'~\[/li\]([\s' . self::NBS . ']*)\[li\]~su' => '[_/li_]$1[_li_]',
			// Now, find any [list]s or [/li]s followed by [li].
			'~\[(list(?: [^\]]*?)?|/li)\]([\s' . self::NBS . ']*)\[li\]~su' => '[$1]$2[_li_]',
			// Allow for sub lists.
			'~\[/li\]([\s' . self::NBS . ']*)\[list\]~u' => '[_/li_]$1[list]',
			'~\[/list\]([\s' . self::NBS . ']*)\[li\]~u' => '[/list]$1[_li_]',
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
		{
			$this->message = preg_replace(array_keys($mistake_fixes), $mistake_fixes, $this->message);
		}
	}

	/**
	 * Replace our token-ized message with the saved code blocks
	 */
	private function _restoreCodeBlocks()
	{
		if (!empty($this->code_blocks))
		{
			$this->message = str_replace(array_keys($this->code_blocks), array_values($this->code_blocks), $this->message);
		}
	}

	/**
	 * Validates and corrects table structure
	 *
	 * What it does
	 *   - Checks tables for correct tag order / nesting
	 *   - Adds in missing closing tags, removes excess closing tags
	 *   - Although it prevents markup error, it can mess-up the intended (abiet wrong) layout
	 * driving the post author in to a furious rage
	 *
	 */
	private function _preparseTable()
	{
		$table_check = $this->message;
		$table_offset = 0;
		$table_array = array();

		// Define the allowable tags after a give tag
		$table_order = array(
			'table' => array('tr'),
			'tr' => array('td', 'th'),
			'td' => array('table'),
			'th' => array(''),
		);

		// Find all closing tags (/table /tr /td etc)
		while (preg_match('~\[(/)*(table|tr|td|th)\]~', $table_check, $matches) === 1)
		{
			// Keep track of where this is.
			$offset = strpos($table_check, $matches[0]);
			$remove_tag = false;

			// Is it opening?
			if ($matches[1] != '/')
			{
				// If the previous table tag isn't correct simply remove it.
				if ((!empty($table_array) && !in_array($matches[2], $table_order[$table_array[0]])) || (empty($table_array) && $matches[2] !== 'table'))
				{
					$remove_tag = true;
				}
				// Record this was the last tag.
				else
				{
					array_unshift($table_array, $matches[2]);
				}
			}
			// Otherwise is closed!
			else
			{
				// Only keep the tag if it's closing the right thing.
				if (empty($table_array) || ($table_array[0] != $matches[2]))
				{
					$remove_tag = true;
				}
				else
				{
					array_shift($table_array);
				}
			}

			// Removing?
			if ($remove_tag)
			{
				$this->message = substr($this->message, 0, $table_offset + $offset) . substr($this->message, $table_offset + strlen($matches[0]) + $offset);

				// We've lost some data.
				$table_offset -= strlen($matches[0]);
			}

			// Remove everything up to here.
			$table_offset += $offset + strlen($matches[0]);
			$table_check = substr($table_check, $offset + strlen($matches[0]));
		}

		// Close any remaining table tags.
		foreach ($table_array as $tag)
		{
			$this->message .= '[/' . $tag . ']';
		}
	}

	/**
	 * This is very simple, and just removes things done by preparsecode.
	 *
	 * @param string $message
	 */
	public function un_preparsecode($message)
	{
		// Protect CODE blocks from further processing
		$this->message = $message;
		$this->_tokenizeCodeBlocks();

		// Pass integration the tokenized message and array
		call_integration_hook('integrate_unpreparse_code', array(&$this->message, &$this->code_blocks, 0));

		// Restore the code blocks
		$this->_restoreCodeBlocks();

		// Change breaks back to \n's and &nsbp; back to spaces.
		return preg_replace('~<br( /)?' . '>~', "\n", str_replace('&nbsp;', ' ', $this->message));
	}

	/**
	 * Ensure tags inside of nobbc do not get parsed by converting the markers to html entities
	 *
	 * @param string[] $matches
	 */
	private function _preparsecode_nobbc_callback($matches)
	{
		return '[nobbc]' . strtr($matches[1], array('[' => '&#91;', ']' => '&#93;', ':' => '&#58;', '@' => '&#64;')) . '[/nobbc]';
	}

	/**
	 * Use only the primary (first) font face when multiple are supplied
	 *
	 * @param string[] $matches
	 */
	private function _preparsecode_font_callback($matches)
	{
		$fonts = explode(',', $matches[1]);
		$font = trim(un_htmlspecialchars($fonts[0]), ' "\'');

		return '[font=' . $font . ']' . $matches[2];
	}

	/**
	 * Takes a tag and changes it to lowercase
	 *
	 * @param string[] $matches
	 */
	private function _preparsecode_lowertags_callback($matches)
	{
		return '[' . $matches[1] . strtolower($matches[2]) . $matches[3] . ']';
	}

	/**
	 * Ensure image tags do not load anything by themselves (security)
	 *
	 * @param string[] $matches
	 */
	private function _fixTags_img_callback($matches)
	{
		return $matches[1] . preg_replace('~action(=|%3d)(?!dlattach)~i', 'action-', $matches[2]) . '[/img]';
	}

	/**
	 * Find and return PreparseCode instance if it exists,
	 * or create a new instance
	 *
	 * @return PreparseCode
	 */
	public static function instance()
	{
		if (self::$instance === null)
		{
			self::$instance = new PreparseCode;
		}

		return self::$instance;
	}
}
