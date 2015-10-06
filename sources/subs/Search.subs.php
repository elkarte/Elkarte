<?php

/**
 * Utility functions for search functionality.
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * This software is a derived product, based on:
 *
 * Simple Machines Forum (SMF)
 * copyright:	2011 Simple Machines (http://www.simplemachines.org)
 * license:  	BSD, See included LICENSE.TXT for terms and conditions.
 *
 * @version 1.1 dev
 *
 */

use ElkArte\Search\Search;

if (!defined('ELK'))
	die('No access...');

// This defines two version types for checking the API's are compatible with this version of the software.
$GLOBALS['search_versions'] = array(
	// This is the forum version but is repeated due to some people rewriting $forum_version.
	'forum_version' => 'ElkArte 1.0',

	// This is the minimum version of ElkArte that an API could have been written for to work.
	// (strtr to stop accidentally updating version on release)
	'search_version' => strtr('ElkArte 1+0=Beta', array('+' => '.', '=' => ' ')),
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