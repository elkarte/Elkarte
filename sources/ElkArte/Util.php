<?php

/**
 * Utility functions, such as to handle multi byte strings
 *
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * @version 2.0 dev
 *
 */

namespace ElkArte;

/**
 * Utility functions, such as to handle multi byte strings
 * Note: some of these might be deprecated or removed in the future.
 */
class Util
{
	protected static  $_entity_check_reg = '~(&#(\d{1,7}|x[0-9a-fA-F]{1,6});)~';

	/**
	 * Converts invalid / disallowed / out of range entities to nulls
	 *
	 * @param string $string
	 *
	 * @return string
	 */
	public static function entity_fix($string)
	{
		$num = $string[0] === 'x' ? hexdec(substr($string, 1)) : (int) $string;

		// We don't allow control characters, characters out of range, byte markers, etc
		if ($num < 0x20 || $num > 0x10FFFF || ($num >= 0xD800 && $num <= 0xDFFF) || $num == 0x202D || $num == 0x202E)
		{
			return '';
		}
		else
		{
			return '&#' . $num . ';';
		}
	}

	/**
	 * Performs an htmlspecialchars on a string, using UTF-8 character set
	 * Optionally performs an entity_fix to null any invalid character entities from the string
	 *
	 * @param string $string
	 * @param int $quote_style integer or constant representation of one
	 * @param string $charset only UTF-8 allowed
	 * @param bool $double true will allow double encoding, false will not encode existing html entities,
	 *
	 * @return string
	 */
	public static function htmlspecialchars($string, $quote_style = ENT_COMPAT, $charset = 'UTF-8', $double = false)
	{
		global $modSettings;

		if (empty($string))
		{
			return $string;
		}

		if (empty($modSettings['disableEntityCheck']))
		{
			$check = preg_replace_callback('~(&amp;#(\d{1,7}|x[0-9a-fA-F]{1,6});)~', 'entity_fix__callback', htmlspecialchars($string, $quote_style, $charset, $double));
		}
		else
		{
			$check = htmlspecialchars($string, $quote_style, $charset, $double);
		}

		return $check;
	}

	/**
	 * Adds html entities to the array/variable.  Uses two underscores to guard against overloading.
	 *
	 * What it does:
	 *
	 * - Adds entities (&quot;, &lt;, &gt;) to the array or string var.
	 * - Importantly, does not effect keys, only values.
	 * - Calls itself recursively if necessary.
	 * - Does not go deeper than 25 to prevent loop exhaustion
	 *
	 * @param array|string $var The string or array of strings to add entities
	 * @param int $level = 0 The current level we're at within the array (if called recursively)
	 *
	 * @return array|string The string or array of strings with entities added
	 */
	public static function htmlspecialchars__recursive($var, $level = 0)
	{
		if (!is_array($var))
		{
			return Util::htmlspecialchars($var, ENT_QUOTES);
		}

		// Add the htmlspecialchars to every element.
		foreach ($var as $k => $v)
		{
			$var[$k] = $level > 25 ? null : Util::htmlspecialchars__recursive($v, $level + 1);
		}

		return $var;
	}

	/**
	 * Trims tabs, newlines, carriage returns, spaces, vertical tabs and null bytes
	 * and any number of space characters from the start and end of a string
	 *
	 * - Optionally performs an entity_fix to null any invalid character entities from the string
	 *
	 * @param string $string
	 *
	 * @return string
	 */
	public static function htmltrim($string)
	{
		global $modSettings;

		// Preg_replace for any kind of whitespace or invisible separator
		// and invisible control characters and unused code points
		$space_chars = '\p{Z}\p{C}';

		if (empty($modSettings['disableEntityCheck']))
		{
			$check = preg_replace('~^(?:[' . $space_chars . ']|&nbsp;)+|(?:[' . $space_chars . ']|&nbsp;)+$~u', '', preg_replace_callback(self::$_entity_check_reg, 'entity_fix__callback', $string));
		}
		else
		{
			$check = preg_replace('~^(?:[' . $space_chars . ']|&nbsp;)+|(?:[' . $space_chars . ']|&nbsp;)+$~u', '', $string);
		}

		return $check;
	}

	/**
	 * Trim a string including the HTML space, character 160.  Uses two underscores to guard against overloading.
	 *
	 * What it does:
	 *
	 * - Trims a string or an array using html characters as well.
	 * - Remove spaces (32), tabs (9), returns (13, 10, and 11), nulls (0), and hard spaces. (160)
	 * - Does not effect keys, only values.
	 * - May call itself recursively if needed.
	 * - Does not go deeper than 25 to prevent loop exhaustion
	 *
	 * @param array|string $var The string or array of strings to trim
	 * @param int $level = 0 How deep we're at within the array (if called recursively)
	 *
	 * @return mixed[]|string The trimmed string or array of trimmed strings
	 */
	public static function htmltrim__recursive($var, $level = 0)
	{
		// Remove spaces (32), tabs (9), returns (13, 10, and 11), nulls (0), and hard spaces. (160)
		if (!is_array($var))
		{
			return self::htmltrim($var);
		}

		// Go through all the elements and remove the whitespace.
		foreach ($var as $k => $v)
		{
			$var[$k] = $level > 25 ? null : self::htmltrim__recursive($v, $level + 1);
		}

		return $var;
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
	 *
	 * @return bool|mixed
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
				{
					return $right ? ($count - $offset - $needle_size + 1) : $offset;
				}

				$result = array_search($needle_arr[0], array_slice($haystack_arr, ++$offset));
			}

			return false;
		}
	}

	/**
	 * Converts a multi-byte string to lowercase
	 *
	 * - Prefers to use mb_ functions if available, otherwise will use charset substitution tables
	 *
	 * @param string $string
	 *
	 * @return string
	 */
	public static function strtolower($string)
	{
		if (function_exists('mb_strtolower'))
		{
			return mb_strtolower($string, 'UTF-8');
		}
		else
		{
			require_once(SUBSDIR . '/Charset.subs.php');

			return utf8_strtolower($string);
		}
	}

	/**
	 * Cuts off a multi-byte string at a certain length
	 *
	 * - Optionally performs an entity_fix to null any invalid character entities from the string prior to the length
	 * check
	 * - Use this when the number of actual characters (&nbsp; = 6 not 1) must be <= length not the displayable,
	 * for example db field compliance to avoid overflow
	 *
	 * @param string $string
	 * @param int $length
	 *
	 * @return string
	 */
	public static function truncate($string, $length)
	{
		global $modSettings;

		// Set a list of common functions.
		$ent_list = empty($modSettings['disableEntityCheck']) ? '&(#\d{1,7}|quot|amp|lt|gt|nbsp);' : '&(#021|quot|amp|lt|gt|nbsp);';

		if (empty($modSettings['disableEntityCheck']))
		{
			$string = preg_replace_callback(self::$_entity_check_reg, 'entity_fix__callback', $string);
		}

		preg_match('~^(' . $ent_list . '|.){' . self::strlen(substr($string, 0, $length)) . '}~u', $string, $matches);
		$string = $matches[0];
		while (strlen($string) > $length)
		{
			$string = preg_replace('~(?:' . $ent_list . '|.)$~u', '', $string);
		}

		return $string;
	}

	/**
	 * Returns the length of multi-byte string
	 *
	 * @param string $string
	 *
	 * @return int
	 */
	public static function strlen($string)
	{
		global $modSettings;

		if (empty($modSettings['disableEntityCheck']))
		{
			$ent_list = '&(#\d{1,7}|quot|amp|lt|gt|nbsp);';
			if (function_exists('mb_strlen'))
			{
				return mb_strlen(preg_replace('~' . $ent_list . '|.~u', '_', $string), 'UTF-8');
			}
			else
			{
				return strlen(preg_replace('~' . $ent_list . '|.~u', '_', preg_replace_callback(self::$_entity_check_reg, 'entity_fix__callback', $string)));
			}
		}
		else
		{
			$ent_list = '&(#021|quot|amp|lt|gt|nbsp);';

			return strlen(preg_replace('~' . $ent_list . '|.~u', '_', $string));
		}
	}

	/**
	 * Shorten a string of text
	 *
	 * What it does:
	 *
	 * - Shortens a text string to a given visual length
	 * - Considers certain html entities as 1 in length, &amp; &nbsp; etc
	 * - Optionally adds ending ellipsis that honor length or are appended
	 * - Optionally attempts to break the string on a word boundary approximately at the allowed length
	 * - If using cutword and the resulting length is < len minus buffer then it is truncated to length plus an
	 * ellipsis.
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
	 *
	 * @return string
	 */
	public static function shorten_text($string, $length = 384, $cutword = false, $ellipsis = '...', $exact = true, $buffer = 12)
	{
		// Does len include the ellipsis or are the ellipsis appended
		$ending = !empty($ellipsis) && $exact ? self::strlen($ellipsis) : 0;

		// If its to long, cut it down to size
		if (self::strlen($string) > $length)
		{
			// Try to cut on a word boundary
			if ($cutword)
			{
				$string = self::substr($string, 0, $length - $ending);
				$space_pos = self::strpos($string, ' ', 0, true);

				// Always one clown in the audience who likes long words or not using the spacebar
				if (!empty($space_pos) && ($length - $space_pos <= $buffer))
				{
					$string = self::substr($string, 0, $space_pos);
				}

				$string = rtrim($string) . ($ellipsis !== '' ? $ellipsis : '');
			}
			else
			{
				$string = self::substr($string, 0, $length - $ending) . ($ellipsis !== '' ? $ellipsis : '');
			}
		}

		return $string;
	}

	/**
	 * Perform a substr operation on multi-byte strings
	 *
	 * - Optionally performs an entity_fix to null any invalid character entities from the string before the operation
	 *
	 * @param string $string
	 * @param string $start
	 * @param int|null $length
	 *
	 * @return string
	 */
	public static function substr($string, $start, $length = null)
	{
		global $modSettings;

		if (empty($modSettings['disableEntityCheck']))
		{
			$ent_arr = preg_split('~(&#\d{1,7};|&quot;|&amp;|&lt;|&gt;|&nbsp;|.)~u', preg_replace_callback(self::$_entity_check_reg, 'entity_fix__callback', $string), -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
		}
		else
		{
			$ent_arr = preg_split('~(&#021;|&quot;|&amp;|&lt;|&gt;|&nbsp;|.)~u', $string, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
		}

		return $length === null ? implode('', array_slice($ent_arr, $start)) : implode('', array_slice($ent_arr, $start, $length));
	}

	/**
	 * Truncate a string up to a number of characters while preserving whole words and HTML tags
	 *
	 * This function is an adaption of the cake php function truncate in utility string.php (MIT)
	 *
	 * @param string $string text to truncate.
	 * @param int $length length of returned string
	 * @param string $ellipsis characters to add at the end of cut string, like ...
	 * @param bool $exact If to account for the $ellipsis length in returned string length
	 *
	 * @return string Trimmed string.
	 */
	public static function shorten_html($string, $length = 384, $ellipsis = '...', $exact = true)
	{
		// If its shorter than the maximum length, while accounting for html tags, simply return
		if (self::strlen(preg_replace('~<.*?>~', '', $string)) <= $length)
		{
			return $string;
		}

		// Start off empty
		$total_length = $exact ? self::strlen($ellipsis) : 0;
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
				{
					array_unshift($open_tags, $tag[2]);
				}
				// Closing tag
				elseif (preg_match('~<\/([\w]+)[^>]*>~s', $tag[0], $close_tag))
				{
					// Remove its starting tag
					$pos = array_search($close_tag[1], $open_tags);
					if ($pos !== false)
					{
						array_splice($open_tags, $pos, 1);
					}
				}
			}

			// Add this (opening or closing) tag to $truncate
			$truncate .= $tag[1];

			// Calculate the length of the actual tag content, accounts for html entities as a single characters
			$content_length = self::strlen($tag[3]);

			// Have we exceeded the allowed length limit, only add in what we are allowed
			if ($content_length + $total_length > $length)
			{
				// The number of characters which we can still return
				$remaining = $length - $total_length;
				$truncate .= self::substr($tag[3], 0, $remaining);
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
			{
				break;
			}
		}

		// Our truncated string up to the last space
		$space_pos = self::strpos($truncate, ' ', 0, true);
		$space_pos = empty($space_pos) ? $length : $space_pos;
		$truncate_check = self::substr($truncate, 0, $space_pos);

		// Make sure this would not cause a cut in the middle of a tag
		$lastOpenTag = (int) self::strpos($truncate_check, '<', 0, true);
		$lastCloseTag = (int) self::strpos($truncate_check, '>', 0, true);
		if ($lastOpenTag > $lastCloseTag)
		{
			// Find the last full open tag in our truncated string, its what was being cut
			preg_match_all('~<[\w]+[^>]*>~s', $truncate, $lastTagMatches);
			$last_tag = array_pop($lastTagMatches[0]);

			// Set the space to just after the last tag
			$space_pos = self::strpos($truncate, $last_tag, 0, true) + strlen($last_tag);
			$space_pos = empty($space_pos) ? $length : $space_pos;
		}

		// Look at what we are going to cut off the end of our truncated string
		$bits = self::substr($truncate, $space_pos);

		// Does it cut a tag off, if so we need to know so it can be added back at the cut point
		preg_match_all('~<\/([a-z]+)>~', $bits, $dropped_tags, PREG_SET_ORDER);
		if (!empty($dropped_tags))
		{
			if (!empty($open_tags))
			{
				foreach ($dropped_tags as $closing_tag)
				{
					if (!in_array($closing_tag[1], $open_tags))
					{
						array_unshift($open_tags, $closing_tag[1]);
					}
				}
			}
			else
			{
				foreach ($dropped_tags as $closing_tag)
				{
					$open_tags[] = $closing_tag[1];
				}
			}
		}

		// Cut it
		$truncate = self::substr($truncate, 0, $space_pos);

		// Dot dot dot
		$truncate .= $ellipsis;

		// Finally close any html tags that were left open
		foreach ($open_tags as $tag)
		{
			$truncate .= '</' . $tag . '>';
		}

		return $truncate;
	}

	/**
	 * Converts the first character of each work in a multi-byte string to uppercase
	 *
	 * @param string $string
	 *
	 * @return string
	 */
	public static function ucwords($string)
	{
		$words = preg_split('~([\s\r\n\t]+)~', $string, -1, PREG_SPLIT_DELIM_CAPTURE);
		for ($i = 0, $n = count($words); $i < $n; $i += 2)
		{
			$words[$i] = self::ucfirst($words[$i]);
		}

		return implode('', $words);
	}

	/**
	 * Converts the first character of a multi-byte string to uppercase
	 *
	 * @param string $string
	 *
	 * @return string
	 */
	public static function ucfirst($string)
	{
		return self::strtoupper(self::substr($string, 0, 1)) . self::substr($string, 1);
	}

	/**
	 * Converts a multi-byte string to uppercase
	 *
	 * Prefers to use mb_ functions if available, otherwise will use charset substitution tables
	 *
	 * @param string $string
	 *
	 * @return string
	 */
	public static function strtoupper($string)
	{
		if (function_exists('mb_strtoupper'))
		{
			return mb_strtoupper($string, 'UTF-8');
		}
		else
		{
			require_once(SUBSDIR . '/Charset.subs.php');

			return utf8_strtoupper($string);
		}
	}

	/**
	 * Wrappers for unserialize
	 * What it does:
	 *
	 * @param string $string The string to unserialize
	 * @param string[] $options Optional.  Additionally, it doesn't allow to use the option:
	 *                          allowed_classes => true, that is reverted to false.
	 * @return mixed
	 */
	public static function unserialize($string, $options = array())
	{
		$options['allowed_classes'] = false;
		if (self::is_serialized($string))
		{
			return unserialize($string, $options);
		}

		return '';
	}

	/**
	 * Determine if a string is serialized
	 *
	 * - avoids the notice/warning it could raise
	 *
	 * @param string $string
	 * @return bool
	 */
	public static function is_serialized($string)
	{
		$check = false;

		// Easy cases
		if (!is_string($string) || $string === '')
		{
			return false;
		}

		// Attempt to unserialize, mask errors
		set_error_handler(function () { /* ignore errors */ });
		try
		{
			if (unserialize($string, ['allowed_classes' => false]) !== false)
			{
				$check = true;
			}
		}
		catch (\Throwable $e)
		{
			/* do nothing */
		}
		finally
		{
			restore_error_handler();
		}

		return $check;
	}

	/**
	 * Provide a PHP 8.1 version of strftime
	 *
	 * @param string $format of the date/time to return
	 * @param int|null $timestamp to convert
	 * @return string|false
	 */
	public static function strftime(string $format, int $timestamp = null)
	{
		if (function_exists('strftime') && (PHP_VERSION_ID < 80100))
		{
			return \strftime($format, $timestamp);
		}

		if (is_null($timestamp))
		{
			$timestamp = time();
		}

		$date_equivalents = array (
			'%a' => 'D',
			'%A' => 'l',
			'%d' => 'd',
			'%e' => 'j',
			'%j' => 'z',
			'%u' => 'N',
			'%w' => 'w',
			// Week
			'%U' => 'W', // Week Number of the given year
			'%V' => 'W',
			'%W' => 'W',
			// Month
			'%b' => 'M',
			'%B' => 'F',
			'%h' => 'M',
			'%m' => 'm',
			// Year
			'%C' => 'y', // Two digit representation of the century
			'%g' => 'y',
			'%G' => 'y',
			'%y' => 'y',
			'%Y' => 'Y',
			// Time
			'%H' => 'H',
			'%k' => 'G',
			'%I' => 'h',
			'%l' => 'g',
			'%M' => 'i',
			'%p' => 'A',
			'%P' => 'a',
			'%r' => 'H:i:s a',
			'%R' => 'H:i',
			'%S' => 's',
			'%T' => 'H:i:s',
			'%X' => 'h:i:s', // Preferred time representation based upon locale
			'%z' => 'O',
			'%Z' => 'T',
			// Time and Date Stamps
			'%c' => 'c',
			'%D' => 'm/d/y',
			'%F' => 'y/m/d',
			'%s' => 'U',
			'%x' => '', // Locale based date representation
			// Misc
			'%n' => "\n",
			'%t' => "\t",
			'%%' => '%',
		);

		return preg_replace_callback(
			'/%[A-Za-z]{1}/',
			function($matches) use ($timestamp, $date_equivalents)
			{
				$new_format = str_replace(array_keys($date_equivalents), array_values($date_equivalents), $matches[0]);
				return date($new_format, $timestamp);
			},
			$format
		);
	}

	/**
	 * Provide a PHP 8.1 version of gmstrftime
	 *
	 * @param string $format of the date/time to return
	 * @param int|null $timestamp to convert
	 * @return string|false
	 */
	public static function gmstrftime(string $format, int $timestamp = null)
	{
		if (function_exists('gmstrftime') && (PHP_VERSION_ID < 80100))
		{
			return \gmstrftime($format, $timestamp);
		}

		return self::strftime($format, $timestamp);
	}
}
