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
		// Detect sprintf/substitution tokens so they survive for later sprintf()
		$name = $params['name'] ?? '';
		$u = $params['u'] ?? 0;

		$isNameToken = is_string($name) && $name !== '' && $name[0] === '%' && preg_match('~^%\d\$s$~m', $name) === 1;
		$isUidToken = is_string($u) && $u !== '' && $u[0] === '%' && preg_match('~^%\d\$d$~m', $u) === 1;

		if ($isNameToken)
		{
			$slug = $name; // keep token as-is (do NOT encode)
		}
		else
		{
			// Safely build a slug from the display name; guard against null
			$name = (string) $name;
			$name = trim($name);
			$slug = $name === '' ? 'member' : preg_replace('~\s+~u', '-', $name);
			$slug = trim($slug, '-');
			$slug = rawurlencode($slug);
		}

		$uid = $isUidToken ? $u : (int) $u;

		$url = 'p/' . $slug . '-' . $uid;
		unset($params['name'], $params['u'], $params['action']);

		return $url . $this->generateQuery($params);
	}
}
