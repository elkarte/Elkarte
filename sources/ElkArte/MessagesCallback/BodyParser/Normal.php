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
 * Normal
 * A body parser that shows the whole text passed without highlighting.
 */
class Normal implements BodyParserInterface
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
		$body = censor($body);

		// Run BBC interpreter on the message.
		return $this->_bbc_parser->parseMessage($body, $smileys_enabled);
	}
}
