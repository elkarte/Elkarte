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

/**
 * BodyParserInterface
 * An interface defining the methods needed by "body parsers"
 * to be used in the "message callbacks".
 */
interface BodyParserInterface
{
	/**
	 * @param string[] $highlight An array of terms to highlight
	 * @param bool $use_partial_words If highlighting match partial words or only
	 *              whole words.
	 */
	public function __construct($highlight, $use_partial_words);

	/**
	 * Parses a body (i.e. a text) and returns the HTML.
	 *
	 * @param string $body Text to parse.
	 * @param bool $smileys_enabled If convert smiley to images.
	 *
	 * @return string
	 */
	public function prepare($body, $smileys_enabled);

	/**
	 * Returns the content of $highlight passed to the constructor.
	 *
	 * @return string[]
	 */
	public function getSearchArray();
}
