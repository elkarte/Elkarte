<?php

/**
 * Standard representation of board URLs
 *
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * @version 2.0 Beta 1
 *
 */

namespace ElkArte\UrlGenerator\Standard;

class Board extends Standard
{
	/** {@inheritDoc} */
	protected $_types = ['board'];

	/**
	 * {@inheritDoc}
	 */
	public function generate($params)
	{
		$params['board'] .= (empty($params['start']) ? '' : '.' . $params['start']);
		unset($params['start'], $params['name']);

		return $this->generateQuery($params);
	}
}
