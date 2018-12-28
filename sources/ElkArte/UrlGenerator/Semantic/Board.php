<?php

/**
 * Semantic representation of board URLs
 *
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause  (see accompanying LICENSE.txt file)
 *
 * @version 2.0 dev
 *
 */

namespace ElkArte\UrlGenerator\Semantic;

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
		$url = 'b/' . urlencode(strtr($params['name'], ' ', '-')) . '-' . $params['board'] . (!empty($params['start']) ? '/page-' . $params['start'] : '');
		unset($params['name'], $params['board'], $params['start']);

		return $url . $this->generateQuery($params);
	}
}
