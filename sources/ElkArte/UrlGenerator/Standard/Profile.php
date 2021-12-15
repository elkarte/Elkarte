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

namespace ElkArte\UrlGenerator\Standard;

class Profile extends Standard
{
	/**
	 * {@inheritdoc }
	 */
	protected $_types = ['profile'];

	/**
	 * {@inheritdoc }
	 */
	public function generate($params)
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
				continue;
			}

			// A sprintf token (%1$d %2$s etc) should be left alone
			if (preg_match('~%\d\$[ds]~', $v) !== 0)
			{
				$args[$k] = $k . '=' . $v;
				continue;
			}

			$args[$k] = $k . '=' . urlencode($v);

		}

		$args = $this->getHash($args);

		return implode($this->_separator, $args);
	}
}
