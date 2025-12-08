<?php

/**
 * Semantic representation of any URL that doesn't have a custom builder
 *
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * @version 2.0 Beta 1
 *
 */

namespace ElkArte\UrlGenerator\Semantic;

/**
 * Class Standard
 *
 * @package ElkArte\UrlGenerator\Semantic
 */
class Standard extends \ElkArte\UrlGenerator\Standard\Standard
{
	/**
	 * {@inheritDoc}
	 */
	protected $_types = ['standard'];

	/**
	 * {@inheritDoc}
	 */
	public function generate($params)
	{
		// Delegate to the parent implementation that includes proper encoding and token handling
		return $this->generateQuery($params);
	}
}
