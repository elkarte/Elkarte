<?php

/**
 * Semantic representation of topic URLs
 *
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * @version 2.0 dev
 *
 */

namespace ElkArte\UrlGenerator\Semantic;

/**
 * Class Topic
 *
 * @package ElkArte\UrlGenerator\Semantic
 */
class Topic extends Standard
{
	/** {@inheritDoc} */
	protected $_types = ['topic'];

	/**
	 * {@inheritDoc}
	 */
	public function generate($params)
	{
		$url = 't/' . urlencode(str_replace(' ', '-', $params['subject'])) . '-' . $params['topic'] . (empty($params['start']) ? '' : '/page-' . $params['start']);
		unset($params['subject'], $params['topic'], $params['start']);

		return $url . $this->generateQuery($params);
	}
}
