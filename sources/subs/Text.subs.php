<?php

/**
 * All of those text parsing functions
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * This software is a derived product, based on:
 *
 * Simple Machines Forum (SMF)
 * copyright:	2011 Simple Machines (http://www.simplemachines.org)
 * license:		BSD, See included LICENSE.TXT for terms and conditions.
 *
 * @version 1.0 Beta 2
 *
 */

if (!defined('ELK'))
	die('No access...');

/**
 * Replace all vulgar words with respective proper words. (substring or whole words..)
 * What this function does:
 *  - it censors the passed string.
 *  - if the theme setting allow_no_censored is on, and the theme option
 *    show_no_censored is enabled, does not censor, unless force is also set.
 *  - it caches the list of censored words to reduce parsing.
 *
 * Returns the censored text
 *
 * @param string $text
 * @param bool $force = false
 */
function censorText(&$text, $force = false)
{
	global $modSettings, $options, $settings;
	static $censor_vulgar = null, $censor_proper = null;

	if ((!empty($options['show_no_censored']) && $settings['allow_no_censored'] && !$force) || empty($modSettings['censor_vulgar']) || trim($text) === '')
		return $text;

	// If they haven't yet been loaded, load them.
	if ($censor_vulgar == null)
	{
		$censor_vulgar = explode("\n", $modSettings['censor_vulgar']);
		$censor_proper = explode("\n", $modSettings['censor_proper']);

		// Quote them for use in regular expressions.
		if (!empty($modSettings['censorWholeWord']))
		{
			for ($i = 0, $n = count($censor_vulgar); $i < $n; $i++)
			{
				$censor_vulgar[$i] = str_replace(array('\\\\\\*', '\\*', '&', '\''), array('[*]', '[^\s]*?', '&amp;', '&#039;'), preg_quote($censor_vulgar[$i], '/'));
				$censor_vulgar[$i] = '/(?<=^|\W)' . $censor_vulgar[$i] . '(?=$|\W)/u' . (empty($modSettings['censorIgnoreCase']) ? '' : 'i');

				// @todo I'm thinking the old way is some kind of bug and this is actually fixing it.
				//if (strpos($censor_vulgar[$i], '\'') !== false)
					//$censor_vulgar[$i] = str_replace('\'', '&#039;', $censor_vulgar[$i]);
			}
		}
	}

	// Censoring isn't so very complicated :P.
	if (empty($modSettings['censorWholeWord']))
		$text = empty($modSettings['censorIgnoreCase']) ? str_ireplace($censor_vulgar, $censor_proper, $text) : str_replace($censor_vulgar, $censor_proper, $text);
	else
		$text = preg_replace($censor_vulgar, $censor_proper, $text);

	return $text;
}

/**
 * Parse smileys in the passed message.
 *
 * The smiley parsing function which makes pretty faces appear :).
 * If custom smiley sets are turned off by smiley_enable, the default set of smileys will be used.
 * These are specifically not parsed in code tags [url=mailto:Dad@blah.com]
 * Caches the smileys from the database or array in memory.
 * Doesn't return anything, but rather modifies message directly.
 *
 * @param string $message
 */
function parsesmileys(&$message)
{
	global $modSettings, $txt, $user_info;
	static $smileyPregSearch = null, $smileyPregReplacements = array(), $callback;

	$db = database();

	// No smiley set at all?!
	if ($user_info['smiley_set'] == 'none' || trim($message) == '')
		return;

	// If smileyPregSearch hasn't been set, do it now.
	if (empty($smileyPregSearch))
	{
		// Use the default smileys if it is disabled. (better for "portability" of smileys.)
		if (empty($modSettings['smiley_enable']))
		{
			$smileysfrom = array('>:D', ':D', '::)', '>:(', ':))', ':)', ';)', ';D', ':(', ':o', '8)', ':P', '???', ':-[', ':-X', ':-*', ':\'(', ':-\\', '^-^', 'O0', 'C:-)', 'O:)');
			$smileysto = array('evil.gif', 'cheesy.gif', 'rolleyes.gif', 'angry.gif', 'laugh.gif', 'smiley.gif', 'wink.gif', 'grin.gif', 'sad.gif', 'shocked.gif', 'cool.gif', 'tongue.gif', 'huh.gif', 'embarrassed.gif', 'lipsrsealed.gif', 'kiss.gif', 'cry.gif', 'undecided.gif', 'azn.gif', 'afro.gif', 'police.gif', 'angel.gif');
			$smileysdescs = array('', $txt['icon_cheesy'], $txt['icon_rolleyes'], $txt['icon_angry'], $txt['icon_laugh'], $txt['icon_smiley'], $txt['icon_wink'], $txt['icon_grin'], $txt['icon_sad'], $txt['icon_shocked'], $txt['icon_cool'], $txt['icon_tongue'], $txt['icon_huh'], $txt['icon_embarrassed'], $txt['icon_lips'], $txt['icon_kiss'], $txt['icon_cry'], $txt['icon_undecided'], '', '', '', $txt['icon_angel']);
		}
		else
		{
			// Load the smileys in reverse order by length so they don't get parsed wrong.
			if (($temp = cache_get_data('parsing_smileys', 480)) == null)
			{
				$result = $db->query('', '
					SELECT code, filename, description
					FROM {db_prefix}smileys
					ORDER BY LENGTH(code) DESC',
					array(
					)
				);
				$smileysfrom = array();
				$smileysto = array();
				$smileysdescs = array();
				while ($row = $db->fetch_assoc($result))
				{
					$smileysfrom[] = $row['code'];
					$smileysto[] = htmlspecialchars($row['filename']);
					$smileysdescs[] = $row['description'];
				}
				$db->free_result($result);

				cache_put_data('parsing_smileys', array($smileysfrom, $smileysto, $smileysdescs), 480);
			}
			else
				list ($smileysfrom, $smileysto, $smileysdescs) = $temp;
		}

		// The non-breaking-space is a complex thing...
		$non_breaking_space = '\x{A0}';

		// This smiley regex makes sure it doesn't parse smileys within code tags (so [url=mailto:David@bla.com] doesn't parse the :D smiley)
		$smileyPregReplacements = array();
		$searchParts = array();
		$smileys_path = htmlspecialchars($modSettings['smileys_url'] . '/' . $user_info['smiley_set'] . '/');

		for ($i = 0, $n = count($smileysfrom); $i < $n; $i++)
		{
			$specialChars = htmlspecialchars($smileysfrom[$i], ENT_QUOTES);
			$smileyCode = '<img src="' . $smileys_path . $smileysto[$i] . '" alt="' . strtr($specialChars, array(':' => '&#58;', '(' => '&#40;', ')' => '&#41;', '$' => '&#36;', '[' => '&#091;')). '" title="' . strtr(htmlspecialchars($smileysdescs[$i]), array(':' => '&#58;', '(' => '&#40;', ')' => '&#41;', '$' => '&#36;', '[' => '&#091;')) . '" class="smiley" />';

			$smileyPregReplacements[$smileysfrom[$i]] = $smileyCode;

			$searchParts[] = preg_quote($smileysfrom[$i], '~');
			if ($smileysfrom[$i] != $specialChars)
			{
				$smileyPregReplacements[$specialChars] = $smileyCode;
				$searchParts[] = preg_quote($specialChars, '~');
			}
		}

		$smileyPregSearch = '~(?<=[>:\?\.\s' . $non_breaking_space . '[\]()*\\\;]|^)(' . implode('|', $searchParts) . ')(?=[^[:alpha:]0-9]|$)~';
		$callback = new ParseSmileysReplacement;
		$callback->replacements = $smileyPregReplacements;
	}

	// Replace away!
	// @todo When support changes to PHP 5.3+, this can be changed this to "use" keyword and simpifly this.
	$message = preg_replace_callback($smileyPregSearch, array($callback, 'callback'), $message);
}

/**
 * Smiley Replacment Callback.
 *
 * This is needed until ELK supports PHP 5.3+ and we can change to "use"
 */
class ParseSmileysReplacement
{
	/**
	 * Our callback that does the actual smiley replacments.
	 *
	 * @param string[] $matches
	 */
	public function callback($matches)
	{
		if (isset($this->replacements[$matches[0]]))
			return $this->replacements[$matches[0]];
		else
			return '';
	}
}