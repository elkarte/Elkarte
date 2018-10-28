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

namespace ElkArte\UrlGenerator\Standard;

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
		return $query;
	}
}
