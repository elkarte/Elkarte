<?php

/**
 * Utility functions, such as to handle multi byte strings
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 1.1 beta 3
 *
 */

/**
 * Utility functions, such as to handle multi byte strings
 * Note: some of these might be deprecated or removed in the future.
 */
class Util
{
	static protected $_entity_check_reg = '~(&#(\d{1,7}|x[0-9a-fA-F]{1,6});)~';

	/**
	 * Converts invalid / disallowed / out of range entities to nulls
	 *
	 * @param string $string
	 */
	public static function entity_fix($string)
	{
		$num = $string[0] === 'x' ? hexdec(substr($string, 1)) : (int) $string;

		// We don't allow control characters, characters out of range, byte markers, etc
		if ($num < 0x20 || $num > 0x10FFFF || ($num >= 0xD800 && $num <= 0xDFFF) || $num == 0x202D || $num == 0x202E)
			return '';
		else
			return '&#' . $num . ';';
	}

	/**
	 * Performs an htmlspecialchars on a string, using UTF-8 character set
	 * Optionally performs an entity_fix to null any invalid character entities from the string
	 *
	 * @param string $string
	 * @param int $quote_style integer or constant representation of one
	 * @param string $charset only UTF-8 allowed
	 * @param bool $double true will allow double encoding, false will not encode existing html entities,
	 */
	public static function htmlspecialchars($string, $quote_style = ENT_COMPAT, $charset = 'UTF-8', $double = false)
	{
		global $modSettings;

		if (empty($string))
			return $string;

		if (empty($modSettings['disableEntityCheck']))
			$check = preg_replace_callback('~(&amp;#(\d{1,7}|x[0-9a-fA-F]{1,6});)~', 'entity_fix__callback', htmlspecialchars($string, $quote_style, $charset, $double));
		else
			$check = htmlspecialchars($string, $quote_style, $charset, $double);

		return $check;
	}

	/**
	 * Trims tabs, newlines, carriage returns, spaces, vertical tabs and null bytes
	 * and any number of space characters from the start and end of a string
	 *
	 * - Optionally performs an entity_fix to null any invalid character entities from the string
	 *
	 * @param string $string
	 */
	public static function htmltrim($string)
	{
		global $modSettings;

		// Preg_replace space characters
		$space_chars = '\x{A0}\x{AD}\x{2000}-\x{200F}\x{201F}\x{202F}\x{3000}\x{FEFF}';

		if (empty($modSettings['disableEntityCheck']))
			$check = preg_replace('~^(?:[ \t\n\r\x0B\x00' . $space_chars . ']|&nbsp;)+|(?:[ \t\n\r\x0B\x00]|&nbsp;)+$~u', '', preg_replace_callback(self::$_entity_check_reg, 'entity_fix__callback', $string));
		else
			$check = preg_replace('~^(?:[ \t\n\r\x0B\x00' . $space_chars . ']|&nbsp;)+|(?:[ \t\n\r\x0B\x00]|&nbsp;)+$~u', '', $string);

		return $check;
	}

	/**
	 * Perform a strpos search on a multi-byte string
	 *
	 * - Optionally performs an entity_fix to null any invalid character entities from the string before the search
	 *
	 * @param string $haystack what to search in
	 * @param string $needle what is being looked for
	 * @param int $offset where to start, assumed 0
	 * @param bool $right set to true to mimic strrpos functions
	 */
	public static function strpos($haystack, $needle, $offset = 0, $right = false)
	{
		global $modSettings;

		$haystack_check = empty($modSettings['disableEntityCheck']) ? preg_replace_callback(self::$_entity_check_reg, 'entity_fix__callback', $haystack) : $haystack;
		$haystack_arr = preg_split('~(&#' . (empty($modSettings['disableEntityCheck']) ? '\d{1,7}' : '021') . ';|&quot;|&amp;|&lt;|&gt;|&nbsp;|.)~u', $haystack_check, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
		$count = 0;

		// From the right side, like mb_strrpos instead
		if ($right)
		{
			$haystack_arr = array_reverse($haystack_arr);
			$count = count($haystack_arr) - 1;
		}

		// Single character search, lets go
		if (strlen($needle) === 1)
		{
			$result = array_search($needle, array_slice($haystack_arr, $offset));
			return is_int($result) ? ($right ? $count - ($result + $offset) : $result + $offset) : false;
		}
		else
		{
			$needle_check = empty($modSettings['disableEntityCheck']) ? preg_replace_callback(self::$_entity_check_reg, 'entity_fix__callback', $needle) : $needle;
			$needle_arr = preg_split('~(&#' . (empty($modSettings['disableEntityCheck']) ? '\d{1,7}' : '021') . ';|&quot;|&amp;|&lt;|&gt;|&nbsp;|.)~u', $needle_check, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
			$needle_arr = $right ? array_reverse($needle_arr) : $needle_arr;
			$needle_size = count($needle_arr);

			$result = array_search($needle_arr[0], array_slice($haystack_arr, $offset));
			while ((int) $result === $result)
			{
				$offset += $result;
				if (array_slice($haystack_arr, $offset, $needle_size) === $needle_arr)
					return $right ? ($count - $offset - $needle_size + 1) : $offset;

				$result = array_search($needle_arr[0], array_slice($haystack_arr, ++$offset));
			}

			return false;
		}
	}

	/**
	 * Perform a substr operation on multi-byte strings
	 *
	 * - Optionally performs an entity_fix to null any invalid character entities from the string before the operation
	 *
	 * @param string $string
	 * @param string $start
	 * @param int|null $length
	 */
	public static function substr($string, $start, $length = null)
	{
		global $modSettings;

		if (empty($modSettings['disableEntityCheck']))
			$ent_arr = preg_split('~(&#\d{1,7};|&quot;|&amp;|&lt;|&gt;|&nbsp;|.)~u', preg_replace_callback(self::$_entity_check_reg, 'entity_fix__callback', $string), -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
		else
			$ent_arr = preg_split('~(&#021;|&quot;|&amp;|&lt;|&gt;|&nbsp;|.)~u', $string, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);

		return $length === null ? implode('', array_slice($ent_arr, $start)) : implode('', array_slice($ent_arr, $start, $length));
	}

	/**
	 * Converts a multi-byte string to lowercase
	 *
	 * - Prefers to use mb_ functions if available, otherwise will use charset substitution tables
	 *
	 * @param string $string
	 */
	public static function strtolower($string)
	{
		if (function_exists('mb_strtolower'))
			return mb_strtolower($string, 'UTF-8');
		else
		{
			require_once(SUBSDIR . '/Charset.subs.php');
			return utf8_strtolower($string);
		}
	}

	/**
	 * Converts a multi-byte string to uppercase
	 *
	 * Prefers to use mb_ functions if available, otherwise will use charset substitution tables
	 *
	 * @param string $string
	 */
	public static function strtoupper($string)
	{
		if (function_exists('mb_strtoupper'))
			return mb_strtoupper($string, 'UTF-8');
		else
		{
			require_once(SUBSDIR . '/Charset.subs.php');
			return utf8_strtoupper($string);
		}
	}

	/**
	 * Cuts off a multi-byte string at a certain length
	 *
	 * - Optionally performs an entity_fix to null any invalid character entities from the string prior to the length check
	 * - Use this when the number of actual characters (&nbsp; = 6 not 1) must be <= length not the displayable,
	 * for example db field compliance to avoid overflow
	 *
	 * @param string $string
	 * @param int $length
	 */
	public static function truncate($string, $length)
	{
		global $modSettings;

		// Set a list of common functions.
		$ent_list = empty($modSettings['disableEntityCheck']) ? '&(#\d{1,7}|quot|amp|lt|gt|nbsp);' : '&(#021|quot|amp|lt|gt|nbsp);';

		if (empty($modSettings['disableEntityCheck']))
			$string = preg_replace_callback(self::$_entity_check_reg, 'entity_fix__callback', $string);

		preg_match('~^(' . $ent_list . '|.){' . Util::strlen(substr($string, 0, $length)) . '}~u', $string, $matches);
		$string = $matches[0];
		while (strlen($string) > $length)
			$string = preg_replace('~(?:' . $ent_list . '|.)$~u', '', $string);

		return $string;
	}

	/**
	 * Shorten a string of text
	 *
	 * What it does:
	 * - Shortens a text string to a given visual length
	 * - Considers certain html entities as 1 in length, &amp; &nbsp; etc
	 * - Optionally adds ending ellipsis that honor length or are appended
	 * - Optionally attempts to break the string on a word boundary approximately at the allowed length
	 * - If using cutword and the resulting length is < len minus buffer then it is truncated to length plus an ellipsis.
	 * - Respects internationalization characters, html spacing and entities as one character.
	 * - Returns the shortened string.
	 * - Does not account for html tags, ie <b>test</b> is 11 characters not 4
	 *
	 * @param string $string The string to shorten
	 * @param int $length The length to cut the string to
	 * @param bool $cutword try to cut at a word boundary
	 * @param string $ellipsis characters to add at the end of a cut string
	 * @param bool $exact set true to include ellipsis in the allowed length, false will append instead
	 * @param int $buffer maximum length underflow to allow when cutting on a word boundary
	 */
	public static function shorten_text($string, $length = 384, $cutword = false, $ellipsis = '...', $exact = true, $buffer = 12)
	{
		// Does len include the ellipsis or are the ellipsis appended
		$ending = !empty($ellipsis) && $exact ? Util::strlen($ellipsis) : 0;

		// If its to long, cut it down to size
		if (Util::strlen($string) > $length)
		{
			// Try to cut on a word boundary
			if ($cutword)
			{
				$string = Util::substr($string, 0, $length - $ending);
				$space_pos = Util::strpos($string, ' ', 0, true);

				// Always one clown in the audience who likes long words or not using the spacebar
				if (!empty($space_pos) && ($length - $space_pos <= $buffer))
					$string = Util::substr($string, 0, $space_pos);

				$string = rtrim($string) . ($ellipsis ? $ellipsis : '');
			}
			else
				$string = Util::substr($string, 0, $length - $ending) . ($ellipsis ? $ellipsis : '');
		}

		return $string;
	}

	/**
	 * Truncate a string up to a number of characters while preserving whole words and HTML tags
	 *
	 * This function is an adaption of the cake php function truncate in utility string.php (MIT)
	 *
	 * @param string $string text to truncate.
	 * @param integer $length length of returned string
	 * @param string $ellipsis characters to add at the end of cut string, like ...
	 * @param boolean $exact If to account for the $ellipsis length in returned string length
	 *
	 * @return string Trimmed string.
	 */
	public static function shorten_html($string, $length = 384, $ellipsis = '...', $exact = true)
	{
		// If its shorter than the maximum length, while accounting for html tags, simply return
		if (Util::strlen(preg_replace('~<.*?>~', '', $string)) <= $length)
			return $string;

		// Start off empty
		$total_length = $exact ? Util::strlen($ellipsis) : 0;
		$open_tags = array();
		$truncate = '';

		// Group all html open and closing tags, [1] full tag with <> [2] basic tag name [3] tag content
		preg_match_all('~(<\/?([\w+]+)[^>]*>)?([^<>]*)~', $string, $tags, PREG_SET_ORDER);

		// Walk down the stack of tags
		foreach ($tags as $tag)
		{
			// If this tag has content
			if (!preg_match('/img|br|input|hr|area|base|basefont|col|frame|isindex|link|meta|param/s', $tag[2]))
			{
				// Opening tag add the closing tag to the top of the stack
				if (preg_match('~<[\w]+[^>]*>~s', $tag[0]))
					array_unshift($open_tags, $tag[2]);
				// Closing tag
				elseif (preg_match('~<\/([\w]+)[^>]*>~s', $tag[0], $close_tag))
				{
					// Remove its starting tag
					$pos = array_search($close_tag[1], $open_tags);
					if ($pos !== false)
						array_splice($open_tags, $pos, 1);
				}
			}

			// Add this (opening or closing) tag to $truncate
			$truncate .= $tag[1];

			// Calculate the length of the actual tag content, accounts for html entities as a single characters
			$content_length = Util::strlen($tag[3]);

			// Have we exceeded the allowed length limit, only add in what we are allowed
			if ($content_length + $total_length > $length)
			{
				// The number of characters which we can still return
				$remaining = $length - $total_length;
				$truncate .= Util::substr($tag[3], 0, $remaining);
				break;
			}
			// Still room to go so add the tag content and continue
			else
			{
				$truncate .= $tag[3];
				$total_length += $content_length;
			}

			// Are we there yet?
			if ($total_length >= $length)
				break;
		}

		// Our truncated string up to the last space
		$space_pos = Util::strpos($truncate, ' ', 0, true);
		$space_pos = empty($space_pos) ? $length : $space_pos;
		$truncate_check = Util::substr($truncate, 0, $space_pos);

		// Make sure this would not cause a cut in the middle of a tag
		$lastOpenTag = (int) Util::strpos($truncate_check, '<', 0, true);
		$lastCloseTag = (int) Util::strpos($truncate_check, '>', 0, true);
		if ($lastOpenTag > $lastCloseTag)
		{
			// Find the last full open tag in our truncated string, its what was being cut
			preg_match_all('~<[\w]+[^>]*>~s', $truncate, $lastTagMatches);
			$last_tag = array_pop($lastTagMatches[0]);

			// Set the space to just after the last tag
			$space_pos = Util::strpos($truncate, $last_tag, 0, true) + strlen($last_tag);
			$space_pos = empty($space_pos) ? $length : $space_pos;
		}

		// Look at what we are going to cut off the end of our truncated string
		$bits = Util::substr($truncate, $space_pos);

		// Does it cut a tag off, if so we need to know so it can be added back at the cut point
		preg_match_all('~<\/([a-z]+)>~', $bits, $dropped_tags, PREG_SET_ORDER);
		if (!empty($dropped_tags))
		{
			if (!empty($open_tags))
			{
				foreach ($dropped_tags as $closing_tag)
				{
					if (!in_array($closing_tag[1], $open_tags))
						array_unshift($open_tags, $closing_tag[1]);
				}
			}
			else
			{
				foreach ($dropped_tags as $closing_tag)
					$open_tags[] = $closing_tag[1];
			}
		}

		// Cut it
		$truncate = Util::substr($truncate, 0, $space_pos);

		// Dot dot dot
		$truncate .= $ellipsis;

		// Finally close any html tags that were left open
		foreach ($open_tags as $tag)
			$truncate .= '</' . $tag . '>';

		return $truncate;
	}

	/**
	 * Converts the first character of a multi-byte string to uppercase
	 *
	 * @param string $string
	 */
	public static function ucfirst($string)
	{
		return Util::strtoupper(Util::substr($string, 0, 1)) . Util::substr($string, 1);
	}

	/**
	 * Converts the first character of each work in a multi-byte string to uppercase
	 *
	 * @param string $string
	 */
	public static function ucwords($string)
	{
		$words = preg_split('~([\s\r\n\t]+)~', $string, -1, PREG_SPLIT_DELIM_CAPTURE);
		for ($i = 0, $n = count($words); $i < $n; $i += 2)
			$words[$i] = Util::ucfirst($words[$i]);
		return implode('', $words);
	}

	/**
	 * Returns the length of multi-byte string
	 *
	 * @param string $string
	 */
	public static function strlen($string)
	{
		global $modSettings;

		if (empty($modSettings['disableEntityCheck']))
		{
			$ent_list = '&(#\d{1,7}|quot|amp|lt|gt|nbsp);';
			if (function_exists('mb_strlen'))
				return mb_strlen(preg_replace('~' . $ent_list . '|.~u', '_', $string), 'UTF-8');
			else
				return strlen(preg_replace('~' . $ent_list . '|.~u', '_', preg_replace_callback(self::$_entity_check_reg, 'entity_fix__callback', $string)));
		}
		else
		{
			$ent_list = '&(#021|quot|amp|lt|gt|nbsp);';
			return strlen(preg_replace('~' . $ent_list . '|.~u', '_', $string));
		}
	}

	/**
	 * Remove slashes recursively.
	 *
	 * What it does:
	 * - removes slashes, recursively, from the array or string var.
	 * - effects both keys and values of arrays.
	 * - calls itself recursively to handle arrays of arrays.
	 *
	 * @todo not used, consider removing
	 * @deprecated since 1.0
	 *
	 * @param mixed[]|string $var
	 * @param int $level = 0
	 * @return array|string
	 */
	public static function stripslashes_recursive($var, $level = 0)
	{
		if (!is_array($var))
			return stripslashes($var);

		// Reindex the array without slashes, this time.
		$new_var = array();

		// Strip the slashes from every element.
		foreach ($var as $k => $v)
			$new_var[stripslashes($k)] = $level > 25 ? null : stripslashes_recursive($v, $level + 1);

		return $new_var;
	}

	/**
	 * Removes url stuff from the array/variable.
	 *
	 * What it does:
	 * - takes off url encoding (%20, etc.) from the array or string var.
	 * - importantly, does it to keys too!
	 * - calls itself recursively if there are any sub arrays.
	 *
	 * @todo not used, consider removing
	 * @deprecated since 1.0
	 *
	 * @param mixed[]|string $var
	 * @param int $level = 0
	 * @return array|string
	 */
	public static function urldecode_recursive($var, $level = 0)
	{
		if (!is_array($var))
			return urldecode($var);

		// Reindex the array...
		$new_var = array();

		// Add the htmlspecialchars to every element.
		foreach ($var as $k => $v)
			$new_var[urldecode($k)] = $level > 25 ? null : self::urldecode_recursive($v, $level + 1);

		return $new_var;
	}

	/**
	 * Unescapes any array or variable.
	 *
	 * What it does:
	 * - unescapes, recursively, from the array or string var.
	 * - effects both keys and values of arrays.
	 * - calls itself recursively to handle arrays of arrays.
	 *
	 * @todo not used, consider removing
	 * @deprecated since 1.0
	 *
	 * @param mixed[]|string $var
	 * @return array|string
	 */
	public static function unescapestring_recursive($var)
	{
		$db = database();

		if (!is_array($var))
		return $db->unescape_string($var);

		// Reindex the array without slashes, this time.
		$new_var = array();

		// Strip the slashes from every element.
		foreach ($var as $k => $v)
			$new_var[$db->unescape_string($k)] = self::unescapestring_recursive($v);

		return $new_var;
	}

	/**
	 * Wrappers for unserialize
	 * What it does:
	 * - if using PHP < 7 it will use ext/safe_unserialize
	 * - if using PHP > 7 will use the built in unserialize
	 *
	 * @param string $string The string to unserialize
	 * @param string[] $options Optional, mimic the PHP 7+ option,
	 *                          see PHP documentation for the details
	 *                          additionally, it doesn't allow to use the option:
	 *                            allowed_classes => true
	 *                          that is reverted to false.
	 * @return array|string
	 */
	public static function unserialize($string, $options = array())
	{
		static $function = null;

		if ($function === null)
		{
			if (version_compare(PHP_VERSION, '7', '>='))
			{
				$function = 'unserialize';
			}
			else
			{
				require_once(EXTDIR . '/serialize.php');
				$function = 'ElkArte\\ext\\upgradephp\\safe_unserialize';
			}
		}

		if (!isset($options['allowed_classes']) || $options['allowed_classes'] === true)
		{
			$options['allowed_classes'] = false;
		}

		return @$function($string, $options);
	}
}