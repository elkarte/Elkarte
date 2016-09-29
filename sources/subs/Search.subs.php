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
 * @version 1.1 beta 3
 *
 */

use \ElkArte\Search\Search;

// This defines two version types for checking the API's are compatible with this version of the software.
$GLOBALS['search_versions'] = array(
	// This is the forum version but is repeated due to some people rewriting $forum_version.
	'forum_version' => 'ElkArte 1.1',

	// This is the minimum version of ElkArte that an API could have been written for to work.
	// (strtr to stop accidentally updating version on release)
	'search_version' => strtr('ElkArte 1+1=Beta', array('+' => '.', '=' => ' ')),
);

/**
 * Creates a search API and returns the object.
 *
 * @package Search
 * @deprecated since 1.1 - please use the Search class
 */
function findSearchAPI()
{
	Elk_Autoloader::getInstance()->register(SUBSDIR . '/Search', '\\ElkArte\\Search');
	$search = new Search();
	return $search->findSearchAPI();
}