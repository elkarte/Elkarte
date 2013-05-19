<?php

/**
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 1.0 Alpha
 *
 */

if (!defined('ELKARTE'))
	die('No access...');

/**
 * Legacy utility functions, such as to handle multi byte strings
 * Note: some of these might be deprecated or removed in the future.
 */
class Util
{
	/**
 	* Compatibility function: it initializes $smcFunc array with utility methods.
 	*/
	static function compat_init()
	{
		global $smcFunc;

		$smcFunc += array(
			'entity_fix' => create_function('$string', 'return Util::entity_fix($string);'),
			'htmlspecialchars' => create_function('$string, $quote_style = ENT_COMPAT, $charset = \'UTF-8\'', 'return Util::htmlspecialchars($string);'),
			'htmltrim' => create_function('$string', 'return Util::htmltrim($string);'),
			'strlen' => create_function('$string', 'return Util::strlen($string);'),
			'strpos' => create_function('$haystack, $needle, $offset = 0', 'return Util::strpos($haystack, $needle);'),
			'substr' => create_function('$string, $start, $length = null', 'return Util::substr($string, $start);'),
			'strtolower' => create_function('$string', 'return Util::strtolower($string);'),
			'strtoupper' => create_function('$string', 'return Util::strtoupper($string);'),
			'truncate' => create_function('$string, $length', 'return Util::truncate($string, $length);'),
			'ucfirst' => create_function('$string', 'return Util::ucfirst($string);'),
			'ucwords' => create_function('$string', 'return Util::ucwords($string);'),
		);
	}

	static function entity_fix($string)
	{
		$num = $string[0] === 'x' ? hexdec(substr($string, 1)) : (int) $string;
		return $num < 0x20 || $num > 0x10FFFF || ($num >= 0xD800 && $num <= 0xDFFF) || $num === 0x202E || $num === 0x202D ? '' : '&#' . $num . ';';
	}

	static function htmlspecialchars($string, $quote_style = ENT_COMPAT, $charset = 'UTF-8')
	{
		global $modSettings;

		if (empty($modSettings['disableEntityCheck']))
			$check = preg_replace_callback('~(&amp;#(\d{1,7}|x[0-9a-fA-F]{1,6});)~', 'entity_fix__callback', htmlspecialchars($string, $quote_style, 'UTF-8'));
		else
			$check = htmlspecialchars($string, $quote_style, 'UTF-8');

		return $check;
	}

	static function htmltrim($string)
	{
		global $modSettings;

		// Preg_replace space characters
		$space_chars = '\x{A0}\x{AD}\x{2000}-\x{200F}\x{201F}\x{202F}\x{3000}\x{FEFF}';

		if (empty($modSettings['disableEntityCheck']))
			$check = preg_replace('~^(?:[ \t\n\r\x0B\x00' . $space_chars . ']|&nbsp;)+|(?:[ \t\n\r\x0B\x00' . ']|&nbsp;)+$~u', '', preg_replace_callback('~(&#(\d{1,7}|x[0-9a-fA-F]{1,6});)~', 'entity_fix__callback', $string));
		else
			$check = preg_replace('~^(?:[ \t\n\r\x0B\x00' . $space_chars . ']|&nbsp;)+|(?:[ \t\n\r\x0B\x00' . ']|&nbsp;)+$~u', '', $string);

		return $check;
	}

	static function strpos($haystack, $needle, $offset = 0)
	{
		global $modSettings;

		$ent_check = empty($modSettings['disableEntityCheck']) ? array('preg_replace_callback(\'~(&#(\d{1,7}|x[0-9a-fA-F]{1,6});)~\', \'entity_fix__callback\', ', ')') : array('', '');

		$haystack_check = empty($modSettings['disableEntityCheck']) ? preg_replace_callback('~(&#(\d{1,7}|x[0-9a-fA-F]{1,6});)~', 'entity_fix__callback', $haystack) : $haystack;
		$haystack_arr = preg_split('~(&#' . (empty($modSettings['disableEntityCheck']) ? '\d{1,7}' : '021') . ';|&quot;|&amp;|&lt;|&gt;|&nbsp;|.)~u', $haystack_check, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
		$haystack_size = count($haystack_arr);
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

	static function substr($string, $start, $length = null)
	{
		global $modSettings;

		if (empty($modSettings['disableEntityCheck']))
			$ent_arr = preg_split('~(&#\d{1,7};|&quot;|&amp;|&lt;|&gt;|&nbsp;|.)~u', preg_replace_callback('~(&#(\d{1,7}|x[0-9a-fA-F]{1,6});)~', 'entity_fix__callback', $string), -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
		else
			$ent_arr = preg_split('~(&#021;|&quot;|&amp;|&lt;|&gt;|&nbsp;|.)~u', $string, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);

		return $length === null ? implode('', array_slice($ent_arr, $start)) : implode('', array_slice($ent_arr, $start, $length));
	}

	static function strtolower($string)
	{
		if (function_exists('mb_strtolower'))
			return mb_strtolower($string, 'UTF-8');
		else
		{
			require_once(SUBSDIR . '/Charset.subs.php');
			return utf8_strtolower($string);
		}
	}

	static function strtoupper($string)
	{
		if (function_exists('mb_strtoupper'))
			return mb_strtoupper($string, 'UTF-8');
		else
		{
			require_once(SUBSDIR . '/Charset.subs.php');
			return utf8_strtoupper($string);
		}
	}

	static function truncate($string, $length)
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

	static function ucfirst($string)
	{
		return Util::strtoupper(Util::substr($string, 0, 1)) . Util::substr($string, 1);
	}

	static function ucwords($string)
	{
		$words = preg_split('~([\s\r\n\t]+)~', $string, -1, PREG_SPLIT_DELIM_CAPTURE);
		for ($i = 0, $n = count($words); $i < $n; $i += 2)
			$words[$i] = Util::ucfirst($words[$i]);
		return implode('', $words);
	}

	static function strlen($string)
	{
		global $modSettings;

		if (empty($modSettings['disableEntityCheck']))
		{
			$ent_list = '&(#\d{1,7}|quot|amp|lt|gt|nbsp);';
			return strlen(preg_replace('~' . $ent_list . '|.~u' . '', '_', preg_replace_callback('~(&#(\d{1,7}|x[0-9a-fA-F]{1,6});)~', 'entity_fix__callback', $string)));
		}
		else
		{
			$ent_list = '&(#021|quot|amp|lt|gt|nbsp);';
			return strlen(preg_replace('~' . $ent_list . '|.~u' . '', '_', $string));
		}
	}
}