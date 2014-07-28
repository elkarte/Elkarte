<?php

/**
 * Legacy utility functions, such as to handle multi byte strings
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 1.0 Release Candidate 2
 *
 */

if (!defined('ELK'))
	die('No access...');

/**
 * Legacy utility functions, such as to handle multi byte strings
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
		return $num < 0x20 || $num > 0x10FFFF || ($num >= 0xD800 && $num <= 0xDFFF) || $num === 0x202E || $num === 0x202D ? '' : '&#' . $num . ';';
	}

	/**
	 * Performs an htmlspecialchars on a string, using UTF-8 character set
	 * Optionally performs an entity_fix to null any invalid character entities from the string
	 *
	 * @param string $string
	 * @param string $quote_style
	 * @param string $charset only UTF-8 allowed
	 * @param bool $double true will allow double encoding, false will not encode existing html entities,
	 */
	public static function htmlspecialchars($string, $quote_style = ENT_COMPAT, $charset = 'UTF-8', $double = false)
	{
		global $modSettings;

		if (empty($modSettings['disableEntityCheck']))
			$check = preg_replace_callback('~(&amp;#(\d{1,7}|x[0-9a-fA-F]{1,6});)~', 'entity_fix__callback', htmlspecialchars($string, $quote_style, $charset, $double));
		else
			$check = htmlspecialchars($string, $quote_style, $charset, $double);

		return $check;
	}

	/**
	 * Trims tabs, newlines, carriage returns, spaces, vertical tabs and null bytes
	 * and any number of space characters from the start and end of a string
	 * Optionally performs an entity_fix to null any invalid character entities from the string
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
	 * Optionally performs an entity_fix to null any invalid character entities from the string before the search
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
	 * Optionally performs an entity_fix to null any invalid character entities from the string before the operation
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
	 * prefers to use mb_ functions if available, otherwise will use charset substitution tables
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
	 * prefers to use mb_ functions if available, otherwise will use charset substitution tables
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
	 * Optionally performs an entity_fix to null any invalid character entities from the string prior to the length check
	 * Use this when the number of actual characters (&nbsp; = 6 not 1) must be <= length not the displayable,
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
	 * - shortens a text string to a given visual length
	 * - considers certain html entities as 1 in length, &amp; &nbsp; etc
	 * - optionally adds ending ellipsis that honor length or are appended
	 * - optionally attempts to break the string on a word boundary approximately at the allowed length
	 * - if using cutword and the resulting length is < len minus buffer then it is truncated to length plus an ellipsis.
	 * - respects internationalization characters, html spacing and entities as one character.
	 * - returns the shortened string.
	 * - does not account for html tags, ie <b>test</b> is 11 characters not 4
	 *
	 * @param string $text
	 * @param int $length
	 * @param bool $cutword try to cut at a word boundary
	 * @param |bool $ellipsis characters to add at the end of a cut string
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
	 * Adds slashes to the array/variable.
	 * What it does:
	 * - returns the var, as an array or string, with escapes as required.
	 * - importantly escapes all keys and values!
	 * - calls itself recursively if necessary.
	 *
	 * @todo not used, consider removing
	 * @deprecated since 1.0
	 *
	 * @param mixed[]|string $var
	 * @return array|string
	 */
	public static function escapestring_recursive($var)
	{
		if (!is_array($var))
			return addslashes($var);

		// Reindex the array with slashes.
		$new_var = array();

		// Add slashes to every element, even the indexes!
		foreach ($var as $k => $v)
			$new_var[addslashes($k)] = Util::escapestring_recursive($v);

		return $new_var;
	}

	/**
	 * Remove slashes recursively.
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
	public function urldecode_recursive($var, $level = 0)
	{
		if (!is_array($var))
			return urldecode($var);

		// Reindex the array...
		$new_var = array();

		// Add the htmlspecialchars to every element.
		foreach ($var as $k => $v)
			$new_var[urldecode($k)] = $level > 25 ? null : urldecode_recursive($v, $level + 1);

		return $new_var;
	}

	/**
	 * Unescapes any array or variable.
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
	public function unescapestring_recursive($var)
	{
		$db = database();

		if (!is_array($var))
		return $db->unescape_string($var);

		// Reindex the array without slashes, this time.
		$new_var = array();

		// Strip the slashes from every element.
		foreach ($var as $k => $v)
			$new_var[$db->unescape_string($k)] = unescapestring_recursive($v);

		return $new_var;
	}
}