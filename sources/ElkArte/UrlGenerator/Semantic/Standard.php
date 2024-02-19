<?php

/**
 * Semantic representation of any URL that doesn't have a custom builder
 *
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * @version 2.0 dev
 *
 */

namespace ElkArte\UrlGenerator\Semantic;

use ElkArte\UrlGenerator\AbstractUrlGenerator;

/**
 * Class Standard
 *
 * @package ElkArte\UrlGenerator\Semantic
 */
class Standard extends AbstractUrlGenerator
{
	/**
	 * {@inheritDoc}
	 */
	protected $_types = ['standard'];

	/**
	 * {@inheritDoc}
	 */
	public function generate($params)
	{
		return $this->generateQuery($params);
	}

	/**
	 * {@inheritDoc}
	 */
	protected function generateQuery($params)
	{
		$args = array();
		foreach ($params as $k => $v)
		{
			if (is_int($k))
			{
				if ($v === '')
				{
					continue;
				}

				$args[$k] = $v;
			}
			else
			{
				$args[$k] = $k . '=' . $v;
			}
		}

		$args = $this->getHash($args);

		return implode($this->_separator, $args);
	}
}
