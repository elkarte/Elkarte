<?php

/**
 * @name      Elkarte Forum
 * @copyright Elkarte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * This software is a derived product, based on:
 *
 * Simple Machines Forum (SMF)
 * copyright:	2011 Simple Machines (http://www.simplemachines.org)
 * license:  	BSD, See included LICENSE.TXT for terms and conditions.
 *
 * @version 1.0 Alpha
 *
 * Utility functions for search functionality.
 *
 */

if (!defined('ELKARTE'))
	die('Hacking attempt...');

// This defines two version types for checking the API's are compatible with this version of the software.
$GLOBALS['search_versions'] = array(
	// This is the forum version but is repeated due to some people rewriting $forum_version.
	'forum_version' => 'ELKARTE 1.0 Alpha',
	// This is the minimum version of ELKARTE that an API could have been written for to work. (strtr to stop accidentally updating version on release)
	'search_version' => strtr('ELKARTE 1+0=Alpha', array('+' => '.', '=' => ' ')),
);

/**
 * Creates a search API and returns the object.
 *
 */
function findSearchAPI()
{
	global $modSettings, $search_versions, $searchAPI, $txt;

	require_once(SUBSDIR . '/Package.subs.php');

	// Search has a special database set.
	db_extend('search');

	// Load up the search API we are going to use.
	$modSettings['search_index'] = empty($modSettings['search_index']) ? 'standard' : $modSettings['search_index'];
	if (!file_exists(SOURCEDIR . '/SearchAPI-' . ucwords($modSettings['search_index']) . '.class.php'))
		fatal_lang_error('search_api_missing');
	require_once(SOURCEDIR . '/SearchAPI-' . ucwords($modSettings['search_index']) . '.class.php');

	// Create an instance of the search API and check it is valid for this version of the software.
	$search_class_name = $modSettings['search_index'] . '_search';
	$searchAPI = new $search_class_name();

	// An invalid Search API.
	if (!$searchAPI || ($searchAPI->supportsMethod('isValid') && !$searchAPI->isValid()) || !matchPackageVersion($search_versions['forum_version'], $searchAPI->min_smf_version . '-' . $searchAPI->version_compatible))
	{
		// Log the error.
		loadLanguage('Errors');
		log_error(sprintf($txt['search_api_not_compatible'], 'SearchAPI-' . ucwords($modSettings['search_index']) . '.class.php'), 'critical');

		require_once(SOURCEDIR . '/SearchAPI-Standard.class.php');
		$searchAPI = new Standard_Search();
	}

	return $searchAPI;
}