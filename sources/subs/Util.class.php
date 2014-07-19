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
	 * Performs an htmlspecialchars on a string, using UTF-8 characterset
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
			$check = preg_replace('~^(?:[ \t\n\r\x0B\x00' . $space_chars . ']|&nbsp;)+|(?:[ \t\n\r\x0B\x00]|&nbsp;)+$~u', '', preg_replace_callback('~(&#(\d{1,7}|x[0-9a-fA-F]{1,6});)~', 'entity_fix__callback', $string));
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
	 */
	public static function strpos($haystack, $needle, $offset = 0)
	{
		global $modSettings;

		$haystack_check = empty($modSettings['disableEntityCheck']) ? preg_replace_callback('~(&#(\d{1,7}|x[0-9a-fA-F]{1,6});)~', 'entity_fix__callback', $haystack) : $haystack;
		$haystack_arr = preg_split('~(&#' . (empty($modSettings['disableEntityCheck']) ? '\d{1,7}' : '021') . ';|&quot;|&amp;|&lt;|&gt;|&nbsp;|.)~u', $haystack_check, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);

		// Single character search, lets go
		if (strlen($needle) === 1)
		{
			$result = array_search($needle, array_slice($haystack_arr, $offset));
			return is_int($result) ? $result + $offset : false;
		}
		else
		{
			$needle_check = empty($modSettings['disableEntityCheck']) ? preg_replace_callback('~(&#(\d{1,7}|x[0-9a-fA-F]{1,6});)~', 'entity_fix__callback', $needle) : $needle;
			$needle_arr = preg_split('~(&#' . (empty($modSettings['disableEntityCheck']) ? '\d{1,7}' : '021') . ';|&quot;|&amp;|&lt;|&gt;|&nbsp;|.)~u', $needle_check, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
			$needle_size = count($needle_arr);

			$result = array_search($needle_arr[0], array_slice($haystack_arr, $offset));
			while ((int) $result === $result)
			{
				$offset += $result;
				if (array_slice($haystack_arr, $offset, $needle_size) === $needle_arr)
					return $offset;

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
			$ent_arr = preg_split('~(&#\d{1,7};|&quot;|&amp;|&lt;|&gt;|&nbsp;|.)~u', preg_replace_callback('~(&#(\d{1,7}|x[0-9a-fA-F]{1,6});)~', 'entity_fix__callback', $string), -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
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
			$string = preg_replace_callback('~(&#(\d{1,7}|x[0-9a-fA-F]{1,6});)~', 'entity_fix__callback', $string);
		else
		{
			preg_match('~^(' . $ent_list . '|.){' . Util::strlen(substr($string, 0, $length)) . '}~u', $string, $matches);
			$string = $matches[0];
			while (strlen($string) > $length)
				$string = preg_replace('~(?:' . $ent_list . '|.)$~u', '', $string);
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
			return strlen(preg_replace('~' . $ent_list . '|.~u', '_', preg_replace_callback('~(&#(\d{1,7}|x[0-9a-fA-F]{1,6});)~', 'entity_fix__callback', $string)));
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