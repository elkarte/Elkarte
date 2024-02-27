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

/**
 * Class Normal
 *
 * Represents a normal body parser implementation.
 * Implementing the BodyParserInterface, this class does not provide functionality to highlight words in a message.
 * instead all it does is run censor and parser.
 */
class Normal implements BodyParserInterface
{
	/** @var bool If there is something to highlight or not */
	protected $_highlight = false;

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
		$body = censor($body);

		// Run BBC interpreter on the message.
		return ParserWrapper::instance()->parseMessage($body, $smileys_enabled);
	}
}
