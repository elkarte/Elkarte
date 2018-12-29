<?php

/**
 * Standard representation of topic URLs
 *
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * @version 2.0 dev
 *
 */

namespace ElkArte\UrlGenerator\Standard;

class Topic extends Standard
{
	/**
	 * {@inheritdoc }
	 */
	protected $_types = ['topic'];

	/**
	 * {@inheritdoc }
	 */
	public function generate($params)
	{
		$params['topic'] = $params['topic'] . '.' . $params['start'];
		unset($params['start'], $params['subject']);

		return $this->generateQuery($params);
	}
}
