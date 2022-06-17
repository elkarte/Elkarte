<?php

/**
 *
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * This file contains code covered by:
 * copyright: 2011 Simple Machines (http://www.simplemachines.org)
 *
 * @version 2.0 dev
 *
 */

namespace BBC;

/**
 * Class MarkdownParser.  Converts markdown syntax to BBC codes.
 *
 * @package BBC
 */
class MarkdownParser
{
	/**
	 * MarkdownParser constructor.
	 */
	public function __construct()
	{
		// Maybe needed
	}

	/**
	 * Calls the functions to parse the handful of allowable Markdown tags
	 *
	 * @param string $data
	 *
	 * @return string
	 */
	public function parse($data)
	{
		// Start tag search and replace
		$data = $this->boldTags($data);
		$data = $this->italicTags($data);
		$data = $this->strikeTags($data);
		$data = $this->ruleTags($data);
		$data = $this->quoteTags($data);

		return $data;
	}

	/**
	 * Convert > Text to [quote]Text[/quote] tags
	 *
	 * @param string $data
	 *
	 * @return string
	 */
	protected function quoteTags($data)
	{
		if (strpos($data, '>') !== false)
		{
			// Much simpler to deal with newlines than breaks
			$data = str_replace('<br />', "\n", $data);

			$data = preg_replace('~(?:\n)(&gt;|>)(.*)~u', '&#8203;[quote]$2[/quote]', $data);
			$data = preg_replace('~' . preg_quote('[/quote]&#8203;[quote]') . '~', "\n", $data);

			return str_replace(["\n", '&#8203;'], ['<br />', ''], $data);
		}

		return $data;
	}

	/**
	 * Convert **Text** or __Text__ tags to [b]Text[/b] tag.
	 *
	 * @param string $data
	 *
	 * @return string
	 */
	protected function boldTags($data)
	{
		if (strpos($data, '**') !== false)
		{
			$data = $this->doubleTagConvert('*', 'b', $data);
		}

		if (strpos($data, '__') !== false)
		{
			$data = $this->doubleTagConvert('_', 'b', $data);
		}

		return $data;
	}

	/**
	 * Convert *Text* or _Text_ to [i]Text[/i] tag.
	 *
	 * @param string $data
	 *
	 * @return string
	 */
	protected function italicTags($data)
	{
		if (strpos($data, '*') !== false)
		{
			$data = $this->tagConvert('*', 'i', $data);
		}

		if (strpos($data, '_') !== false)
		{
			$data = $this->tagConvert('_', 'i', $data);
		}

		return $data;
	}

	/**
	 * Convert ~~Text~~ to [s]Text[/s] tag.
	 *
	 * @param string $data
	 *
	 * @return string
	 */
	protected function strikeTags($data)
	{
		if (strpos($data, '~~') !== false)
		{
			$data = $this->doubleTagConvert('~', 's', $data);
		}

		return $data;
	}

	/**
	 * Convert --- ___ *** to [hr] tag
	 *
	 * @param string $data
	 *
	 * @return string
	 */
	protected function ruleTags($data)
	{
		// convert rule
		$data = preg_replace('~(^|\n|<br \/>)---+?(^|\n|<br \/>)~', '$1[hr]', $data);
		$data = preg_replace('~(^|\n|<br \/>)___+?(^|\n|<br \/>)~', '$1[hr]', $data);
		return preg_replace('~(^|\n|<br \/>)\*\*\*+?(^|\n|<br \/>)~', '$1[hr]', $data);
	}

	/**
	 * Given a markdown tag, such as *, will search for exactly 2 '*'s, followed by text followed
	 * by exactly 2 '*'s  *text* will not match **text** will match ***text*** will not match
	 *
	 * @param string $md markdown tag to search for, like *
	 * @param string $bbc bbc tag to replace with
	 * @param string $data the string to search / replace
	 *
	 * @return string
	 */
	private function doubleTagConvert($md, $bbc, $data)
	{
		$md = preg_quote($md, '~');

		// (^|\s|<br \/>) Group 1 match BeginOfLine or WhiteSpaceCharacter or <br />
		// ([*]) Group 2 match a single * character
		// \2 Find results of Group 2 again
		// (?!\2) NegativeLookahead do not find Group 2 results again e.g. *** is not valid
		// (.+?) Group 3 Any Character, except \n, one or more times (ungreedy)
		// \2 Find Group 2 results again
		// \2 Find Group 2 results again
		// (?!\2) NegativeLookahead do not find Group 2 results, e.g. no ***
		// (\s|$|<br \/>) Group 4 match WhiteSpaceCharacter or EndOfLine or <br />
		$regex = '~(^|\s|<br \/>)([' . $md . '])\2(?!\2)(.+?)\2\2(?!\2)(\s|$|<br \/>)~sm';

		return preg_replace($regex, '$1[' . $bbc . ']$3[/' . $bbc . ']$4', $data);
	}

	/**
	 * Same as doubleTagConvert but only allows for a single markdown character
	 *
	 * @param string $md markdown tag to search for, like *
	 * @param string $bbc bbc tag to replace with
	 * @param string $data the string to search / replace
	 *
	 * @return string
	 */
	private function tagConvert($md, $bbc, $data)
	{
		$md = preg_quote($md, '~');
		$regex = '~(^|\s|<br \/>)([' . $md . '])(?!\2)(.+?)\2(?!\2)(\s|$|<br \/>)~sm';

		return preg_replace($regex, '$1[' . $bbc . ']$3[/' . $bbc . ']$4', $data);
	}

	/**
	 * Convert `Text` to [icode]Text[/icode]
	 *
	 * Should be called at beginning of bbc processing to prevent conversion of BBC tags inside `'s
	 *
	 * @param string $data
	 *
	 * @return string
	 */
	public function inlineCodeTags($data)
	{
		// code block
		if (strpos($data, '```') !== false)
		{
			$data = preg_replace_callback('~```\s*<br \/>([\s\S]+?(?=<br \/>```))<br \/>```~', static function ($match) {
				return '[code]' . strtr($match[1], ['[' => '&#91;', ']' => '&#93;']) . '[/code]';
			}, $data);
		}

		// code line
		if (strpos($data, '`') !== false)
		{
			$data = preg_replace_callback('~`((?!`|\n|<br />).*?)`~', static function ($match) {
				return '[icode]'. strtr($match[1], ['[' => '&#91;', ']' => '&#93;']) . '[/icode]';
			}, $data);
		}

		return $data;
	}
}
