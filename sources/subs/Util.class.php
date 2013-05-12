<?php

/**
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 1.0 Alpha
 *
 */

/**
 * Legacy utility functions, such as to handle multi byte strings
 * Note: some of these might be deprecated or removed in the future.
 */
class Util
{
	static function entity_fix($string)
	{
		$num = $string[0] === 'x' ? hexdec(substr($string, 1)) : (int) $string;
		return $num < 0x20 || $num > 0x10FFFF || ($num >= 0xD800 && $num <= 0xDFFF) || $num === 0x202E || $num === 0x202D ? '' : '&#' . $num . ';';
	}

	static function htmlspecialchars ($string, $quote_style = ENT_COMPAT, $charset = 'UTF-8')
	{
		global $smcFunc, $modSettings;

		$ent_check = empty($modSettings['disableEntityCheck']) ? array('preg_replace_callback(\'~(&#(\d{1,7}|x[0-9a-fA-F]{1,6});)~\', \'entity_fix__callback\', ', ')') : array('', '');

		$htmlspec = create_function('$string, $quote_style = ENT_COMPAT, $charset = \'UTF-8\'', '
			global $smcFunc;
			return ' . strtr($ent_check[0], array('&' => '&amp;')) . 'htmlspecialchars($string, $quote_style, \'UTF-8\')' . $ent_check[1] . ';'
		);

		return $htmlspec($string, $quote_style, $charset);
	}

	static function htmltrim($string)
	{
		global $smcFunc, $modSettings;

		// Preg_replace space characters
		$space_chars = '\x{A0}\x{AD}\x{2000}-\x{200F}\x{201F}\x{202F}\x{3000}\x{FEFF}';

		$ent_check = empty($modSettings['disableEntityCheck']) ? array('preg_replace_callback(\'~(&#(\d{1,7}|x[0-9a-fA-F]{1,6});)~\', \'entity_fix__callback\', ', ')') : array('', '');

		$htmltrim = create_function('$string', '
			global $smcFunc;
			return preg_replace(\'~^(?:[ \t\n\r\x0B\x00' . $space_chars . ']|&nbsp;)+|(?:[ \t\n\r\x0B\x00' . $space_chars . ']|&nbsp;)+$~u\', \'\', ' . implode('$string', $ent_check) . ');');

		return $htmltrim($string);
	}

	static function strpos($haystack, $needle, $offset = 0)
	{
		global $smcFunc, $modSettings;

		$ent_check = empty($modSettings['disableEntityCheck']) ? array('preg_replace_callback(\'~(&#(\d{1,7}|x[0-9a-fA-F]{1,6});)~\', \'entity_fix__callback\', ', ')') : array('', '');

		$strpos_func = create_function('$haystack, $needle, $offset = 0', '
			global $smcFunc;
			$haystack_arr = preg_split(\'~(&#' . (empty($modSettings['disableEntityCheck']) ? '\d{1,7}' : '021') . ';|&quot;|&amp;|&lt;|&gt;|&nbsp;|.)~u\', ' . implode('$haystack', $ent_check) . ', -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
			$haystack_size = count($haystack_arr);
			if (strlen($needle) === 1)
			{
				$result = array_search($needle, array_slice($haystack_arr, $offset));
				return is_int($result) ? $result + $offset : false;
			}
			else
			{
				$needle_arr = preg_split(\'~(&#' . (empty($modSettings['disableEntityCheck']) ? '\d{1,7}' : '021') . ';|&quot;|&amp;|&lt;|&gt;|&nbsp;|.)~u\', ' . implode('$needle', $ent_check) . ', -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
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
			}');

		return $strpos_func($haystack, $needle);
	}

	static function substr($string, $start, $length = null)
	{
		global $smcFunc, $modSettings;

		$ent_check = empty($modSettings['disableEntityCheck']) ? array('preg_replace_callback(\'~(&#(\d{1,7}|x[0-9a-fA-F]{1,6});)~\', \'entity_fix__callback\', ', ')') : array('', '');

		$substr_func = create_function('$string, $start, $length = null', '
			global $smcFunc;
			$ent_arr = preg_split(\'~(&#' . (empty($modSettings['disableEntityCheck']) ? '\d{1,7}' : '021') . ';|&quot;|&amp;|&lt;|&gt;|&nbsp;|.)~u\', ' . implode('$string', $ent_check) . ', -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
			return $length === null ? implode(\'\', array_slice($ent_arr, $start)) : implode(\'\', array_slice($ent_arr, $start, $length));');
		return $substr_func($string, $start, $length);
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
		global $modSettings, $smcFunc;

		// Set a list of common functions.
		$ent_list = empty($modSettings['disableEntityCheck']) ? '&(#\d{1,7}|quot|amp|lt|gt|nbsp);' : '&(#021|quot|amp|lt|gt|nbsp);';
		$ent_check = empty($modSettings['disableEntityCheck']) ? array('preg_replace_callback(\'~(&#(\d{1,7}|x[0-9a-fA-F]{1,6});)~\', \'entity_fix__callback\', ', ')') : array('', '');

		if (empty($modSettings['disableEntityCheck']))
			$string = implode($string, $ent_check);
		else
		{
			preg_match('~^(' . $ent_list . '|.){' . $smcFunc['strlen'](substr($string, 0, $length)) . '}~u', $string, $matches);
			$string = $matches[0];
			while (strlen($string) > $length)
				$string = preg_replace('~(?:' . $ent_list . '|.)$~u', '', $string);
		}
		return $string;
	}

	static function ucfirst($string)
	{
		global $smcFunc;

		return $smcFunc['strtoupper']($smcFunc['substr']($string, 0, 1)) . $smcFunc['substr']($string, 1);
	}

	static function ucwords($string)
	{
		global $smcFunc;
		$words = preg_split('~([\s\r\n\t]+)~', $string, -1, PREG_SPLIT_DELIM_CAPTURE);
		for ($i = 0, $n = count($words); $i < $n; $i += 2)
			$words[$i] = $smcFunc['ucfirst']($words[$i]);
		return implode('', $words);
	}

	static function strlen($string)
	{
		global $smcFunc, $modSettings;

		$ent_list = empty($modSettings['disableEntityCheck']) ? '&(#\d{1,7}|quot|amp|lt|gt|nbsp);' : '&(#021|quot|amp|lt|gt|nbsp);';
		$ent_check = empty($modSettings['disableEntityCheck']) ? array('preg_replace_callback(\'~(&#(\d{1,7}|x[0-9a-fA-F]{1,6});)~\', \'entity_fix__callback\', ', ')') : array('', '');

		return strlen(preg_replace('~' . $ent_list . '|.~u' . '', '_', implode($string, $ent_check)));
	}
}