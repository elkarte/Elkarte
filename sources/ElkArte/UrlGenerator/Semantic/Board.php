<?php

/**
 * Semantic representation of board URLs
 *
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * @version 2.0 dev
 *
 */

namespace ElkArte\UrlGenerator\Semantic;

class Board extends Standard
{
	/** {@inheritDoc} */
	protected $_types = ['board'];

	/**
	 * {@inheritDoc}
	 */
	public function generate($params)
	{
		// Safely build a slug from the board name; guard against null
		$name = isset($params['name']) && $params['name'] !== null ? (string) $params['name'] : '';
		$name = trim($name);
		$slug = $name === '' ? 'board' : preg_replace('~\s+~u', '-', $name);
		$slug = trim($slug, '-');

		$board_id = isset($params['board']) ? (int) $params['board'] : 0;
		$has_start = isset($params['start']) && $params['start'] !== '' && $params['start'] !== null;
		$start = $has_start ? (int) $params['start'] : null;

		$url = 'b/' . rawurlencode($slug) . '-' . $board_id . ($has_start && $start !== 0 ? '/page-' . $start : '');
		unset($params['name'], $params['board'], $params['start']);

		return $url . $this->generateQuery($params);
	}
}
