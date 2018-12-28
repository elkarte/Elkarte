<?php

/**
 * Semantic representation of topic URLs
 *
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 2.0 dev
 *
 */

namespace ElkArte\UrlGenerator\Queryless;

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
		$url = 'topic,' . $params['topic'] . (!empty($params['start']) ? '.' . $params['start'] : '.0') . '.html';
		unset($params['subject'], $params['topic'], $params['start']);

		return $url . $this->generateQuery($params);
	}
}
