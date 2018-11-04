<?php

/**
 * This class takes care of converting a Semantic URL into a Standard one, so that
 * the request parser can do its work and explode everything into an array of values.
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 2.0 dev
 *
 */

namespace ElkArte\UrlGenerator;

abstract class Abstract_ParseQuery
{
	/**
	 * Holds the special types of URLs we know
	 *
	 * @var string[]
	 */
	protected $parsers = ['b' => 'board', 't' => 'topic', 'p' => 'profile', 's' => 'standard'];

	/**
	 * The character to use as parameters separator
	 *
	 * @var string
	 */
	protected $separator = ';';

	/**
	 * Public facing function that converts the query part of the URL from the
	 * semantic format back to the standard ElkArte one
	 *
	 * @param string $query The semantic query
	 * @return string $query The corresponding standard query
	 */
	abstract public function parse($query);
}
