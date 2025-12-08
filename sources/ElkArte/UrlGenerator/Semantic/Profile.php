<?php

/**
 * Semantic representation of profile URLs
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
		// Safely build a slug from the display name; guard against null
		$name = isset($params['name']) ? (string) $params['name'] : '';
		$name = trim($name);
		$slug = $name === '' ? 'member' : preg_replace('~\s+~u', '-', $name);
		$slug = trim($slug, '-');

		// Ensure member id is an integer
		$uid = isset($params['u']) ? (int) $params['u'] : 0;

		$url = 'p/' . rawurlencode($slug) . '-' . $uid;
		unset($params['name'], $params['u'], $params['action']);

		return $url . $this->generateQuery($params);
	}
}
