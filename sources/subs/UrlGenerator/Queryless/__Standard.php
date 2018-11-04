<?php

/**
 * Semantic representation of any URL that doesn't have a custom builder
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 2.0 dev
 *
 */

namespace ElkArte\UrlGenerator\Semantic;

use ElkArte\UrlGenerator\Abstract_Url_Generator;

class Standard extends Abstract_Url_Generator
{
	/**
	 * {@inheritdoc }
	 */
	protected $_types = ['standard'];

	/**
	 * {@inheritdoc }
	 */
	public function generate($params)
	{
		return $this->generateQuery($params);
	}

	/**
	 * {@inheritdoc }
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
				$args[] = $v;
			}
			else
			{
				$args[] = $k . '=' . $v;
			}
		}

		return implode($this->_separator, $args);
	}
}
