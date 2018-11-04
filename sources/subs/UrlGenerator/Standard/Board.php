<?php

/**
 * Standard representation of board URLs
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 2.0 dev
 *
 */

namespace ElkArte\UrlGenerator\Standard;

class Board extends Standard
{
	/**
	 * {@inheritdoc }
	 */
	protected $_types = ['board'];

	/**
	 * {@inheritdoc }
	 */
	public function generate($params)
	{
		$params['board'] = $params['board'] . '.' . $params['start'];
		unset($params['start'], $params['name']);

		return $this->generateQuery($params);
	}
}
