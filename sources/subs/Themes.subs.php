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

/**
 * Retrieve all installed themes
 */
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

	return $themes;
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
	list($themeDirectory) = $smcFunc['db_fetch_row']($request);
	$smcFunc['db_free_result']($request);

	return $themeDirectory;
}

/**
 * Retrieve theme URL
 *
 * @param int $id_theme id of the theme
 */
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

/**
 * validates a theme name
 *
 * @param string $indexes
 * @param array $value_data
 * @return type
 */
function validateThemeName($indexes, $value_data)
{
	global $smcFunc;

	$request = $smcFunc['db_query']('', '
		SELECT id_theme, value
		FROM {db_prefix}themes
		WHERE id_member = {int:no_member}
			AND variable = {string:theme_dir}
			AND (' . implode(' OR ', $value_data['query']) . ')',
		array_merge($value_data['params'], array(
			'no_member' => 0,
			'theme_dir' => 'theme_dir',
			'index_compare_explode' => 'value LIKE \'%' . implode('\' OR value LIKE \'%', $indexes) . '\'',
		))
	);
	$themes = array();
	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		// Find the right one.
		foreach ($indexes as $index)
			if (strpos($row['value'], $index) !== false)
				$themes[$row['id_theme']] = $index;
	}
	$smcFunc['db_free_result']($request);

	return $themes;
}

/**
 * Get a basic list of themes
 *
 * @param array $themes
 * @return array
 */
function getBasicThemeInfos($themes)
{
	global $smcFunc;

	$themelist = array();

	$request = $smcFunc['db_query']('', '
		SELECT id_theme, value
		FROM {db_prefix}themes
		WHERE id_member = {int:no_member}
			AND variable = {string:name}
			AND id_theme IN ({array_int:theme_list})',
		array(
			'theme_list' => array_keys($themes),
			'no_member' => 0,
			'name' => 'name',
		)
	);
	while ($row = $smcFunc['db_fetch_assoc']($request))
		$themelist[$themes[$row['id_theme']]] = $row['value'];

	$smcFunc['db_free_result']($request);

	return $themelist;
}

/**
 * Gets a list of all themes from the database
 * @return array $themes
 */
function getCustomThemes()
{
	global $smcFunc, $settings, $txt;

	$request = $smcFunc['db_query']('', '
		SELECT id_theme, variable, value
		FROM {db_prefix}themes
		WHERE id_theme != {int:default_theme}
			AND id_member = {int:no_member}
			AND variable IN ({string:name}, {string:theme_dir})',
		array(
			'default_theme' => 1,
			'no_member' => 0,
			'name' => 'name',
			'theme_dir' => 'theme_dir',
		)
	);

	// Manually add in the default
	$themes = array(
		1 => array(
			'name' => $txt['dvc_default'],
			'theme_dir' => $settings['default_theme_dir'],
		),
	);
	while ($row = $smcFunc['db_fetch_assoc']($request))
		$themes[$row['id_theme']][$row['variable']] = $row['value'];
	$smcFunc['db_free_result']($request);

	return $themes;
}

/**
 * Returns all named and installed themes paths as an array of theme name => path
 *
 * @param array $theme_list
 */
function getThemesPathbyID($theme_list = array())
{
	global $smcFunc, $modSettings;

	// Nothing passed then we use the defaults
	if (empty($theme_list))
		$theme_list = explode(',', $modSettings['knownThemes']);

	if (!is_array($theme_list))
		$theme_list = array($theme_list);

	// Load up any themes we need the paths for
	$request = $smcFunc['db_query']('', '
		SELECT id_theme, variable, value
		FROM {db_prefix}themes
		WHERE (id_theme = {int:default_theme} OR id_theme IN ({array_int:known_theme_list}))
			AND variable IN ({string:name}, {string:theme_dir})',
		array(
			'known_theme_list' => $theme_list,
			'default_theme' => 1,
			'name' => 'name',
			'theme_dir' => 'theme_dir',
		)
	);
	$theme_paths = array();
	while ($row = $smcFunc['db_fetch_assoc']($request))
		$theme_paths[$row['id_theme']][$row['variable']] = $row['value'];
	$smcFunc['db_free_result']($request);

	return $theme_paths;
}

/**
 * Load the installed themes
 * (minimum data)
 *
 * @param array $knownThemes available themes
 */
function loadThemes($knownThemes)
{
	global $smcFunc;

	// Load up all the themes.
	$request = $smcFunc['db_query']('', '
		SELECT id_theme, value AS name
		FROM {db_prefix}themes
		WHERE variable = {string:name}
			AND id_member = {int:no_member}
		ORDER BY id_theme',
		array(
			'no_member' => 0,
			'name' => 'name',
		)
	);
	$themes = array();
	while ($row = $smcFunc['db_fetch_assoc']($request))
		$themes[] = array(
			'id' => $row['id_theme'],
			'name' => $row['name'],
			'known' => in_array($row['id_theme'], $knownThemes),
		);
	$smcFunc['db_free_result']($request);
}