<?php

/**
 * Queryless representation of board URLs
 *
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * @version 2.0 dev
 *
 */

namespace ElkArte\UrlGenerator\Queryless;

/**
 * Class Board
 *
 * @package ElkArte\UrlGenerator\Queryless
 */
class Board extends Standard
{
	/** {@inheritDoc} */
	protected $_separator = ';';

	/** {@inheritDoc} */
	protected $_types = ['board'];

	/**
	 * {@inheritDoc}
	 */
	public function generate($params)
	{
		$url = 'board,' . $params['board'] . (empty($params['start']) ? '.0' : '.' . $params['start']) . '.html';
		unset($params['name'], $params['board'], $params['start']);

		return $url . $this->generateQuery($params);
	}
}
