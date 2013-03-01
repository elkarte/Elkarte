<?php

/**
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 1.0 Alpha
 *
 * This file contains functions for dealing with topics. Low-level functions,
 * i.e. database operations needed to perform.
 * These functions do NOT make permissions checks. (they assume those were
 * already made).
 */

if (!defined('ELKARTE'))
	die('No access...');

function installedThemes()
{
	global $smcFunc;

	$request = $smcFunc['db_query']('', '
		SELECT id_theme, variable, value
		FROM {db_prefix}themes
		WHERE variable IN ({string:name}, {string:theme_dir}, {string:theme_templates}, {string:theme_layers})
			AND id_member = {int:no_member}',
		array(
			'name' => 'name',
			'theme_dir' => 'theme_dir',
			'theme_templates' => 'theme_templates',
			'theme_layers' => 'theme_layers',
			'no_member' => 0,
		)
	);
	$themes = array();
	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		if (!isset($themes[$row['id_theme']]))
			$themes[$row['id_theme']] = array(
				'id' => $row['id_theme'],
				'num_default_options' => 0,
				'num_members' => 0,
			);
		$themes[$row['id_theme']][$row['variable']] = $row['value'];
	}
	$smcFunc['db_free_result']($request);
}

/**
 * Retrieve theme directory
 *
 * @param int $id_theme the id of the theme
 */
function themeDirectory($id_theme)
{
	global $smcFunc;

	$request = $smcFunc['db_query']('', '
		SELECT value
		FROM {db_prefix}themes
		WHERE variable = {string:theme_dir}
			AND id_theme = {int:current_theme}
		LIMIT 1',
		array(
			'current_theme' => $id_theme,
			'theme_dir' => 'theme_dir',
		)
	);
	$themeDirectory = $smcFunc['db_fetch_row']($request);
	$smcFunc['db_free_result']($request);

	return $themeDirectory;
}

function themeUrl($id_theme)
{
	global $smcFunc;

	$request = $smcFunc['db_query']('', '
		SELECT value
		FROM {db_prefix}themes
		WHERE variable = {string:theme_url}
			AND id_theme = {int:current_theme}
		LIMIT 1',
		array(
			'current_theme' => $id_theme,
			'theme_url' => 'theme_url',
			)
		);

	list ($theme_url) = $smcFunc['db_fetch_row']($request);
	$smcFunc['db_free_result']($request);

	return $theme_url;
}