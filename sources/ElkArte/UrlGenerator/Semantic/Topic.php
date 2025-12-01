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
		$subject = isset($params['subject']) && $params['subject'] !== null ? (string) $params['subject'] : '';

		// Simple, safe slug: trim, collapse whitespace to '-', fallback to 'topic' if empty
		$subject = trim($subject);
		$slug = $subject === '' ? 'topic' : preg_replace('~\s+~u', '-', $subject);
		$slug = trim($slug, '-');

		$topic = isset($params['topic']) ? (int) $params['topic'] : 0;
		$has_start = isset($params['start']) && $params['start'] !== '' && $params['start'] !== null;
		$start = $has_start ? (int) $params['start'] : null;

		$url = 't/' . rawurlencode($slug) . '-' . $topic . ($has_start && $start !== 0 ? '/page-' . $start : '');
		unset($params['subject'], $params['topic'], $params['start']);

		return $url . $this->generateQuery($params);
	}
}
