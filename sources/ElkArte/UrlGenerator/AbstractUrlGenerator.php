<?php

/**
 * The base class that defines the methods needed to build an URL
 *
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * @version 2.0 dev
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
	/**
	 * The piece of glue between different parameters of the URL
	 */
	protected $_separator = ';';

	/**
	 * The type of URLs this class supports
	 */
	protected $_types = array();

	/**
	 * Allows to change the URL parameters separator
	 *
	 * @param string $separator The separator character
	 */
	public function setSeparator($separator)
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
	 * Actually builds the URL (only the query part.
	 *
	 * @param mixed[] $params The parameters of the URL
	 */
	abstract public function generate($params);
}
