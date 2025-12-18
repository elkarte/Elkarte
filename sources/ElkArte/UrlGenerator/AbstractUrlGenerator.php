<?php

/**
 * The base class that defines the methods needed to build a URL
 *
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * @version 2.0 Beta 1
 *
 */

namespace ElkArte\UrlGenerator;

/**
 * Class AbstractUrlGenerator
 *
 * @package ElkArte\UrlGenerator
 */
abstract class AbstractUrlGenerator
{
	/** @var string The piece of glue between different parameters of the URL */
	protected $_separator = ';';

	/** @var string[] The type of URLs this class supports */
	protected $_types = [];

	/**
	 * Allows changing the URL parameters separator
	 *
	 * @param string $separator The separator character
	 */
	public function setSeparator($separator): void
	{
		$this->_separator = $separator;
	}

	/**
	 * The types of URL supported by the generator
	 */
	public function getTypes()
	{
		return $this->_types;
	}

	/**
	 * Actually builds the URL (only the query part).
	 *
	 * @param array $params The parameters of the URL
	 */
	abstract public function generate($params);

	/**
	 * If a hash #key is defined, ensures it's at the end of the url
	 *
	 * @param array $args
	 * @return array
	 */
	public function getHash($args): array
	{
		if (isset($args['hash']))
		{
			$fragment = $args['hash'];
			if ($fragment !== null && $fragment !== '')
			{
				// Process a hash argument by trimming and prefixing it with a '#'
				$fragment = (string) $fragment;
				$fragment = ltrim($fragment, '#');
				$args[] = '#' . $fragment;
			}
			unset($args['hash']);
		}

		return $args;
	}
}
