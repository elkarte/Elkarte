<?php

/**
 * Part of the files dealing with preparing the content for display posts
 * via callbacks (Display, PM, Search).
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

namespace ElkArte\MessagesCallback\BodyParser;

use BBC\ParserWrapper;
use ElkArte\Helper\Util;

/**
 * Class Compact
 *
 * Implements the BodyParserInterface.
 * Parses and prepares the body of a message by transforming and highlighting/shortening it.
 *
 * @package YourPackage
 */
class Compact implements BodyParserInterface
{
	/** @var bool If there is something to highlight or not */
	protected $_highlight = false;

	/** @var bool If to highlight words in words */
	protected $force_partial_word = false;

	/**
	 * {@inheritDoc}
	 * @param string[] $highlight An array of words that can be highlighted in the message (somehow)
	 * @param bool $use_partial_words If highlight should happen on whole rods or part of them
	 */
	public function __construct(protected $_searchArray, protected $_use_partial_words)
	{
		$this->_highlight = !empty($this->_searchArray);
	}

	/**
	 * {@inheritDoc}
	 */
	public function getSearchArray()
	{
		return $this->_searchArray;
	}

	/**
	 * {@inheritDoc}
	 */
	public function prepare($body, $smileys_enabled)
	{
		$charLimit = 50;

		// Prepare the message with censored words, parsed BBCodes, etc.
		$body = $this->transformBody($body, $smileys_enabled);

		// If result is long enough to warrant highlighting, get to work
		if (Util::strlen($body) > $charLimit)
		{
			$body = $this->highlightOrShortenBody($body, $charLimit);
		}

		// Re-fix the international characters.
		return $this->fixInternationalCharacters($body);
	}

	/**
	 * Transforms the body of a message.
	 *
	 * @param string $body The body of the message.
	 * @param bool $smileys_enabled Whether smileys should be enabled or not.
	 *
	 * @return string The transformed body of the message with censored words, parsed BBCodes,
	 *                converted line breaks, and stripped HTML tags except for <br>.
	 */
	private function transformBody($body, $smileys_enabled)
	{
		$bbc_parser = ParserWrapper::instance();
		$body = censor($body);
		$body = strtr($body, ["\n" => ' ', '<br />' => "\n"]);
		$body = $bbc_parser->parseMessage($body, $smileys_enabled);

		return strip_tags(strtr($body, ['</div>' => '<br />', '</li>' => '<br />']), '<br>');
	}

	/**
	 * Highlights search strings or shortens the body of a message.
	 *
	 * - If highlighting is disabled, it shortens the body by removing characters beyond the specified character
	 * limit and adds an ellipsis.
	 * - If highlighting is enabled, it tries to highlight the message body based on the specified character limit.
	 *
	 * @param string $body The body of the message to be highlighted or shortened.
	 * @param int $charLimit The maximum number of characters allowed in the message body.
	 * @return string The highlighted or shortened message body.
	 */
	private function highlightOrShortenBody($body, $charLimit)
	{
		if (!$this->_highlight)
		{
			$body = Util::substr($body, 0, $charLimit) . '<strong>...</strong>';
		}
		else
		{
			$body = $this->highlightBody($body, $charLimit);
		}

		return $body;
	}

	/**
	 * Highlights the matched body strings if possible.
	 *
	 * @param string $body The body to be highlighted.
	 * @param int $charLimit The character limit for highlighting.
	 * @return string The highlighted body.
	 */
	private function highlightBody($body, $charLimit)
	{
		$matchString = $this->getMatchString();
		$body = un_htmlspecialchars(strtr($body, ['&nbsp;' => ' ', '<br />' => "\n", '&#91;' => '[', '&#93;' => ']', '&#58;' => ':', '&#64;' => '@']));

		if ($this->_use_partial_words || $this->force_partial_word)
		{
			$matches = $this->getMatches($body, '/([^\s\W]{' . $charLimit . '}[\s\W]|[\s\W].{0,' . $charLimit . '}?|^)(' . $matchString . ')(.{0,' . $charLimit . '}[\s\W]|[^\s\W]{0,' . $charLimit . '})/isu');
		}
		else
		{
			$matches = $this->getMatches($body, '/([^\s\W]{' . $charLimit . '}[\s\W]|[\s\W].{0,' . $charLimit . '}?[\s\W]|^)(' . $matchString . ')([\s\W].{0,' . $charLimit . '}[\s\W]|[\s\W][^\s\W]{0,' . $charLimit . '})/isu');
		}

		// Search term not found in the body, just show a short snip ...
		if (empty($matches[0]))
		{
			$body = Util::shorten_html($body, 500, '<strong>&hellip;&hellip;</strong>', false);
		}
		else
		{
			$body = $this->getHighlightedBody($matches);
		}

		return $body;
	}

	/**
	 * Returns the match string used for searching.
	 *
	 * @return string The match string.
	 */
	private function getMatchString()
	{
		$matchString = '';
		$this->force_partial_word = false;

		foreach ($this->_searchArray as $keyword)
		{
			$keyword = un_htmlspecialchars($keyword);
			$keyword = preg_replace_callback('~(&amp;#(\d{1,7}|x[0-9a-fA-F]{1,6});)~', 'entity_fix__callback', strtr($keyword, ['\\\'' => "'", '&' => '&amp;']));

			if (preg_match('~[\'.,/@%&;:(){}\[\]_\-+\\\\]$~', $keyword) === 1
				|| preg_match('~^[\'.,/@%&;:(){}\[\]_\-+\\\\]~', $keyword) === 1)
			{
				$this->force_partial_word = true;
			}

			$matchString .= strtr(preg_quote($keyword, '/'), ['\*' => '.+?']) . '|';
		}

		return un_htmlspecialchars(substr($matchString, 0, -1));
	}

	/**
	 * Returns an array of all matches found in the body text based on a given pattern.
	 *
	 * @param string $body The body text to search for matches in.
	 * @param string $pattern The regular expression pattern used for matching.
	 * @return array Returns an array containing all matches found in the body text.
	 */
	private function getMatches($body, $pattern)
	{
		preg_match_all($pattern, $body, $matches);

		return $matches;
	}

	/**
	 * Returns the highlighted body text by wrapping the matched substrings with strong tags and adding ellipsis before and after each match.
	 *
	 * @param array $matches An array of matches found in the body text.
	 * @return string Returns the body text with highlighted matches.
	 */
	private function getHighlightedBody($matches)
	{
		$body = '';

		foreach ($matches[0] as $match)
		{
			$match = strtr(htmlspecialchars($match, ENT_QUOTES, 'UTF-8'), ["\n" => '&nbsp;']);
			$body .= '<strong>&hellip;&hellip;</strong>&nbsp;' . $match . '&nbsp;<strong>&hellip;&hellip;</strong>';
		}

		return $body;
	}

	/**
	 * Fixes international characters in the given body text by replacing HTML entities with their equivalent character.
	 *
	 * @param string $body The body text with international characters to be fixed.
	 * @return string Returns the body text with the fixed international characters.
	 */
	private function fixInternationalCharacters($body)
	{
		return preg_replace_callback('~(&amp;#(\d{1,7}|x[0-9a-fA-F]{1,6});)~', 'entity_fix__callback', $body);
	}
}
