<?php

/**
 * Queryless representation of board URLs
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 2.0 dev
 *
 */

namespace ElkArte\UrlGenerator\Queryless;

class Board extends Standard
{
	/**
	 * {@inheritdoc }
	 */
	protected $_separator = ';';

	/**
	 * {@inheritdoc }
	 */
	protected $_types = ['board'];

	/**
	 * {@inheritdoc }
	 */
	public function generate($params)
	{
		$url = 'board,' . $params['board'] . (!empty($params['start']) ? '.' . $params['start'] : '.0') . '.html';
		unset($params['name'], $params['board'], $params['start']);

		return $url . $this->generateQuery($params);
	}
}
