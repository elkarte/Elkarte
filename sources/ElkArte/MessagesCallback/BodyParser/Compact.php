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
 * copyright:	2011 Simple Machines (http://www.simplemachines.org)
 *
 * @version 2.0 dev
 *
 */

namespace ElkArte\MessagesCallback\BodyParser;

/**
 * Compact
 * A body parser that cuts the text to a certain length and highlight the words
 * passed to the constructor.
 */
class Compact implements BodyParserInterface
{
	/**
	 * An array of words that can be highlighted in the message (somehow)
	 * @var string[]
	 */
	protected $_searchArray = array();

	/**
	 * If there is something to highlight or not
	 * @var bool
	 */
	protected $_highlight = false;

	/**
	 * The BBCode parser
	 * @var Object
	 */
	protected $_bbc_parser = null;

	/**
	 * If highlight should happen on whole rods or part of them
	 * @var bool
	 */
	protected $_use_partial_words = false;

	/**
	 * {@inheritdoc }
	 */
	public function __construct($highlight, $use_partial_words)
	{
		$this->_searchArray = $highlight;
		$this->_use_partial_words = $use_partial_words;
		$this->_highlight = !empty($highlight);
		$this->_bbc_parser = \BBC\ParserWrapper::instance();
	}

	/**
	 * {@inheritdoc }
	 */
	public function getSearchArray()
	{
		return $this->_searchArray;
	}

	/**
	 * {@inheritdoc }
	 */
	public function prepare($body, $smileys_enabled)
	{
		// Set the number of characters before and after the searched keyword.
		$charLimit = 50;

		$body = censor($body);
		$body = strtr($body, array("\n" => ' ', '<br />' => "\n"));
		$body = $this->_bbc_parser->parseMessage($body, $smileys_enabled);
		$body = strip_tags(strtr($body, array('</div>' => '<br />', '</li>' => '<br />')), '<br>');

		if (\ElkArte\Util::strlen($body) > $charLimit)
		{
			if (!$this->_highlight)
			{
				$body = \ElkArte\Util::substr($body, 0, $charLimit) . '<strong>...</strong>';
			}
			else
			{
				$matchString = '';
				$force_partial_word = false;
				foreach ($this->_searchArray as $keyword)
				{
					$keyword = un_htmlspecialchars($keyword);
					$keyword = preg_replace_callback('~(&amp;#(\d{1,7}|x[0-9a-fA-F]{1,6});)~', 'entity_fix__callback', strtr($keyword, array('\\\'' => '\'', '&' => '&amp;')));
					if (preg_match('~[\'\.,/@%&;:(){}\[\]_\-+\\\\]$~', $keyword) != 0 || preg_match('~^[\'\.,/@%&;:(){}\[\]_\-+\\\\]~', $keyword) != 0)
						$force_partial_word = true;
					$matchString .= strtr(preg_quote($keyword, '/'), array('\*' => '.+?')) . '|';
				}
				$matchString = un_htmlspecialchars(substr($matchString, 0, -1));

				$body = un_htmlspecialchars(strtr($body, array('&nbsp;' => ' ', '<br />' => "\n", '&#91;' => '[', '&#93;' => ']', '&#58;' => ':', '&#64;' => '@')));

				if ($this->_use_partial_words || $force_partial_word)
					preg_match_all('/([^\s\W]{' . $charLimit . '}[\s\W]|[\s\W].{0,' . $charLimit . '}?|^)(' . $matchString . ')(.{0,' . $charLimit . '}[\s\W]|[^\s\W]{0,' . $charLimit . '})/isu', $body, $matches);
				else
					preg_match_all('/([^\s\W]{' . $charLimit . '}[\s\W]|[\s\W].{0,' . $charLimit . '}?[\s\W]|^)(' . $matchString . ')([\s\W].{0,' . $charLimit . '}[\s\W]|[\s\W][^\s\W]{0,' . $charLimit . '})/isu', $body, $matches);

				$body = '';
				foreach ($matches[0] as $match)
				{
					$match = strtr(htmlspecialchars($match, ENT_QUOTES, 'UTF-8'), array("\n" => '&nbsp;'));
					$body .= '<strong>&hellip;&hellip;</strong>&nbsp;' . $match . '&nbsp;<strong>&hellip;&hellip;</strong>';
				}
			}

			// Re-fix the international characters.
			$body = preg_replace_callback('~(&amp;#(\d{1,7}|x[0-9a-fA-F]{1,6});)~', 'entity_fix__callback', $body);
		}
		return $body;
	}
}
