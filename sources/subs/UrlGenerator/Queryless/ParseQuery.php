<?php

/**
 * This class takes care of converting a Semantic URL into a Standard one, so that
 * the request parser can do its work and explode everything into an array of values.
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 2.0 dev
 *
 */

namespace ElkArte\UrlGenerator\Queryless;

use ElkArte\UrlGenerator\Abstract_ParseQuery;

class ParseQuery extends Abstract_ParseQuery
{
	/**
	 * Public facing function that converts the query part of the URL from the
	 * semantic format back to the standard ElkArte one
	 *
	 * @param string $query The semantic query
	 * @return string $query The corresponding standard query
	 */
	public function parse($query)
	{
		if (isset($this->parsers[$query[0]]))
		{
			$call = $this->parsers[$query[0]];
		}
		else
		{
			$call = $this->parsers['s'];
		}

		return $this->{$call}($query);
	}

	/**
	 * The standard way to convert it (i.e. do nothing).
	 * This is used when the parse method cannot identify the type of URL
	 * it is facing, so it assumes the URL is a standard one.
	 *
	 * @param string $query The semantic query
	 * @return string $query The corresponding standard query
	 */
	protected function standard($query)
	{
		return $query;
	}

	/**
	 * Boards have to have a "board" parameter, and this method ensures the query
	 * has it.
	 *
	 * @param string $query The semantic query
	 * @return string $query The corresponding standard query
	 */
	protected function board($query)
	{
		return 'board=' . $this->process($query);
	}

	/**
	 * Topics have to have a "topic" parameter, and this method ensures the query
	 * has it.
	 *
	 * @param string $query The semantic query
	 * @return string $query The corresponding standard query
	 */
	protected function topic($query)
	{
		return 'topic=' . $this->process($query);
	}

	/**
	 * This method splits the semantic URL into pieces (exploding at each "/")
	 * and puts more or less everything back together into the standard format.
	 * Some more processing takes care of "-" => ".".
	 *
	 * @param string $query The semantic query
	 * @return string $query The corresponding standard query
	 */
	protected function process($query)
	{
		preg_match('~(?!board|topic),(\d+)\.([^\.]+)\.html(.*)~', $query, $parts);

		$real_query = $parts[1] . '.' . $parts[2] . (!empty($parts[3]) ? $parts[3] : '');

		return $real_query;
	}
}
