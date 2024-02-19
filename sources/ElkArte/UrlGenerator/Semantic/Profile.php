<?php

/**
 * Semantic representation of profile URLs
 *
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * @version 2.0 dev
 *
 */

namespace ElkArte\UrlGenerator\Semantic;

/**
 * Class Profile
 *
 * @package ElkArte\UrlGenerator\Semantic
 */
class Profile extends Standard
{
	/**
	 * {@inheritDoc}
	 */
	protected $_types = ['profile'];

	/**
	 * {@inheritDoc}
	 */
	public function generate($params)
	{
		$url = 'p/' . urlencode(strtr($params['name'], ' ', '-')) . '-' . $params['u'];
		unset($params['name'], $params['u'], $params['action']);

		return $url . $this->_separator . $this->generateQuery($params);
	}
}
