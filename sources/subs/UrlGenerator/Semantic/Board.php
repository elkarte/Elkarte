<?php

/**
 * Dummy
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 2.0 dev
 *
 */

namespace ElkArte\UrlGenerator\Semantic;

class Board extends Standard
{
	protected $_types = ['board'];

	public function generate($params)
	{
		$url = 'b/' . urlencode(strtr($params['name'], ' ', '-')) . '-' . $params['board'] . (!empty($params['start']) ? '/page-' . $params['start'] : '');
		unset($params['name'], $params['board'], $params['start']);

		return $url . $this->generateQuery($params);
	}
}
