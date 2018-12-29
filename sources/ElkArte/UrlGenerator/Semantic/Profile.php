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

class Profile extends Standard
{
	/**
	 * {@inheritdoc }
	 */
	protected $_types = ['profile'];

	/**
	 * {@inheritdoc }
	 */
	public function generate($params)
	{
		$url = 'p/' . urlencode(strtr($params['name'], ' ', '-')) . '-' . $params['u'];
		unset($params['name'], $params['u'], $params['action']);

		return $url . $this->generateQuery($params);
	}
}
