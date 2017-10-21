<?php

/**
 * Utility functions for search functionality.
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * This file contains code covered by:
 * copyright:	2011 Simple Machines (http://www.simplemachines.org)
 * license:  	BSD, See included LICENSE.TXT for terms and conditions.
 *
 * @version 1.1
 *
 */

use \ElkArte\Search\Search;

/**
 * Creates a search API and returns the object.
 *
 * @package Search
 * @deprecated since 1.1 - please use the Search class
 */
function findSearchAPI()
{
	$search = new Search();
	return $search->findSearchAPI();
}
