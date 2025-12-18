<?php

/**
 * Standard representation of any URL that doesn't have a custom builder
 *
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * @version 2.0 Beta 1
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
	/** {@inheritDoc} */
	protected $_types = ['standard'];

	/**
	 * {@inheritDoc}
	 */
	public function generate($params)
	{
		return $this->generateQuery($params);
	}

	/**
	 * Generates a query string based on the given parameters.
	 *
	 * The method processes the input array $params and constructs a query string
	 * by encoding the values and handling specific formatting rules for special
	 * tokens (e.g., substitution tokens or sprintf tokens). The result is a string
	 * that concatenates the processed parameters with the appropriate separator.
	 *
	 * @param array $params An associative array of query parameters, where keys represent
	 *                      parameter names and values represent their corresponding values.
	 *                      Integer keys are treated as is, and special tokens in string values
	 *                      are preserved during processing.
	 * @return string The generated query string. If the $params input is not an array or
	 *                contains no valid entries, an empty string is returned.
	 */
	protected function generateQuery($params): string
	{
		if (!is_array($params))
		{
			return '';
		}

		$args = [];
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
				// A sprintf token (%1$d %2$s etc.) should be left alone, as should
				// a substitution token like $1 $2{stuff}
				if (($v[0] === '$' && preg_match('~^\$\d({.*})?$~m', $v) !== 0)
					|| ($v[0] === '%' && preg_match('~^%\d\$[ds]$~m', $v) !== 0))
				{
					$args[$k] = $k . '=' . $v;
					continue;
				}
			}

			if ($v === null)
			{
				continue;
			}

			$args[$k] = $k . '=' . urlencode($v);
		}

		$args = $this->getHash($args);

		return (!empty($args) ? $this->_separator : '') . implode($this->_separator, $args);
	}
}
