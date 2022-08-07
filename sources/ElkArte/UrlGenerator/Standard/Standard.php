<?php

/**
 * Standard representation of any URL that doesn't have a custom builder
 *
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * @version 2.0 dev
 *
 */

namespace ElkArte\UrlGenerator\Standard;

use ElkArte\UrlGenerator\AbstractUrlGenerator;

/**
 * Class Standard
 *
 * @package ElkArte\UrlGenerator\Standard
 */
class Standard extends AbstractUrlGenerator
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

				$args[$k] = $v;
				continue;
			}

			// A substitution token like $1 $2{stuff}, should be left alone
			if (is_string($v) && $v !== '')
			{
				// A sprintf token (%1$d %2$s etc) should be left alone, as should
				// a substitution token like $1 $2{stuff}
				if (($v[0] === '$' && preg_match('~^\$\d({.*})?$~m', $v) !== 0)
					|| ($v[0] === '%' && preg_match('~^%\d\$[ds]$~m', $v) !== 0))
				{
					$args[$k] = $k . '=' . $v;
					continue;
				}
			}

			$args[$k] = $k . '=' . urlencode($v);
		}

		$args = $this->getHash($args);

		return implode($this->_separator, $args);
	}
}
