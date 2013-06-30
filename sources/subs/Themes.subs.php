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

if (!defined('ELK'))
	die('No access...');

/**
 * Retrieve all installed themes
 */
function installedThemes()
{
	$db = database();

	$request = $db->query('', '
		SELECT id_theme, variable, value
		FROM {db_prefix}themes
		WHERE variable IN ({string:name}, {string:theme_dir}, {string:theme_url}, {string:images_url}, {string:theme_templates}, {string:theme_layers})
			AND id_member = {int:no_member}',
		array(
			'name' => 'name',
			'theme_dir' => 'theme_dir',
			'theme_url' => 'theme_url',
			'images_url' => 'images_url',
			'theme_templates' => 'theme_templates',
			'theme_layers' => 'theme_layers',
			'no_member' => 0,
		)
	);
	$themes = array();
	while ($row = $db->fetch_assoc($request))
	{
		if (!isset($themes[$row['id_theme']]))
			$themes[$row['id_theme']] = array(
				'id' => $row['id_theme'],
				'num_default_options' => 0,
				'num_members' => 0,
			);
		$themes[$row['id_theme']][$row['variable']] = $row['value'];
	}
	$db->free_result($request);

	return $themes;
}

/**
 * Retrieve theme directory
 *
 * @param int $id_theme the id of the theme
 */
function themeDirectory($id_theme)
{
	$db = database();

	$request = $db->query('', '
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
	list($themeDirectory) = $db->fetch_row($request);
	$db->free_result($request);

	return $themeDirectory;
}

/**
 * Retrieve theme URL
 *
 * @param int $id_theme id of the theme
 */
function themeUrl($id_theme)
{
	$db = database();

	$request = $db->query('', '
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

	list ($theme_url) = $db->fetch_row($request);
	$db->free_result($request);

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
	$db = database();

	$request = $db->query('', '
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
	while ($row = $db->fetch_assoc($request))
	{
		// Find the right one.
		foreach ($indexes as $index)
			if (strpos($row['value'], $index) !== false)
				$themes[$row['id_theme']] = $index;
	}
	$db->free_result($request);

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
	$db = database();

	$themelist = array();

	$request = $db->query('', '
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
	while ($row = $db->fetch_assoc($request))
		$themelist[$themes[$row['id_theme']]] = $row['value'];

	$db->free_result($request);

	return $themelist;
}

/**
 * Gets a list of all themes from the database
 * @return array $themes
 */
function getCustomThemes()
{
	global $settings, $txt;

	$db = database();

	$request = $db->query('', '
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
	while ($row = $db->fetch_assoc($request))
		$themes[$row['id_theme']][$row['variable']] = $row['value'];
	$db->free_result($request);

	return $themes;
}

/**
 * Returns all named and installed themes paths as an array of theme name => path
 *
 * @param array $theme_list
 */
function getThemesPathbyID($theme_list = array())
{
	global $modSettings;

	$db = database();

	// Nothing passed then we use the defaults
	if (empty($theme_list))
		$theme_list = explode(',', $modSettings['knownThemes']);

	if (!is_array($theme_list))
		$theme_list = array($theme_list);

	// Load up any themes we need the paths for
	$request = $db->query('', '
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
	while ($row = $db->fetch_assoc($request))
		$theme_paths[$row['id_theme']][$row['variable']] = $row['value'];
	$db->free_result($request);

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
	$db = database();

	// Load up all the themes.
	$request = $db->query('', '
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
	while ($row = $db->fetch_assoc($request))
		$themes[] = array(
			'id' => $row['id_theme'],
			'name' => $row['name'],
			'known' => in_array($row['id_theme'], $knownThemes),
		);
	$db->free_result($request);

	return $themes;
}

/**
 * Generates a file listing for a given directory
 *
 * @param type $path
 * @param type $relative
 * @return type
 */
function get_file_listing($path, $relative)
{
	global $scripturl, $txt, $context;

	// Is it even a directory?
	if (!is_dir($path))
		fatal_lang_error('error_invalid_dir', 'critical');

	$dir = dir($path);
	$entries = array();
	while ($entry = $dir->read())
		$entries[] = $entry;
	$dir->close();

	natcasesort($entries);

	$listing1 = array();
	$listing2 = array();

	foreach ($entries as $entry)
	{
		// Skip all dot files, including .htaccess.
		if (substr($entry, 0, 1) == '.' || $entry == 'CVS')
			continue;

		if (is_dir($path . '/' . $entry))
			$listing1[] = array(
				'filename' => $entry,
				'is_writable' => is_writable($path . '/' . $entry),
				'is_directory' => true,
				'is_template' => false,
				'is_image' => false,
				'is_editable' => false,
				'href' => $scripturl . '?action=admin;area=theme;th=' . $_GET['th'] . ';' . $context['session_var'] . '=' . $context['session_id'] . ';sa=browse;directory=' . $relative . $entry,
				'size' => '',
			);
		else
		{
			$size = filesize($path . '/' . $entry);
			if ($size > 2048 || $size == 1024)
				$size = comma_format($size / 1024) . ' ' . $txt['themeadmin_edit_kilobytes'];
			else
				$size = comma_format($size) . ' ' . $txt['themeadmin_edit_bytes'];

			$listing2[] = array(
				'filename' => $entry,
				'is_writable' => is_writable($path . '/' . $entry),
				'is_directory' => false,
				'is_template' => preg_match('~\.template\.php$~', $entry) != 0,
				'is_image' => preg_match('~\.(jpg|jpeg|gif|bmp|png)$~', $entry) != 0,
				'is_editable' => is_writable($path . '/' . $entry) && preg_match('~\.(php|pl|css|js|vbs|xml|xslt|txt|xsl|html|htm|shtm|shtml|asp|aspx|cgi|py)$~', $entry) != 0,
				'href' => $scripturl . '?action=admin;area=theme;th=' . $_GET['th'] . ';' . $context['session_var'] . '=' . $context['session_id'] . ';sa=edit;filename=' . $relative . $entry,
				'size' => $size,
				'last_modified' => standardTime(filemtime($path . '/' . $entry)),
			);
		}
	}

	return array_merge($listing1, $listing2);
}

/**
 * Updates the pathes for a theme. Used to fix invalid pathes.
 * @param array $setValues
 */
function updateThemePath($setValues)
{
	$db = database;

	$db->insert('replace',
		'{db_prefix}themes',
		array('id_theme' => 'int', 'id_member' => 'int', 'variable' => 'string-255', 'value' => 'string-65534'),
		$setValues,
		array('id_theme', 'variable', 'id_member')
	);
	
}

/**
 * Counts the theme options configured for guests
 * @return array
 */
function countConfiguredGuestOptions()
{
	$db = database();

	$themes = array();

	$request = $db->query('', '
		SELECT id_theme, COUNT(*) AS value
		FROM {db_prefix}themes
		WHERE id_member = {int:guest_member}
		GROUP BY id_theme',
		array(
			'guest_member' => -1,
		)
	);
	while ($row = $db->fetch_assoc($request))
		$themes[] = $row;
	$db->free_result($request);

	return($themes);
}

/**
 * Counts the theme options configured for members
 * @return array
 */
function countConfiguredMemberOptions()
{
	$db = database();

	$themes = array();

	// Need to make sure we don't do custom fields.
	$customFields = loadCustomFields();

	$customFieldsQuery = empty($customFields) ? '' : ('AND variable NOT IN ({array_string:custom_fields})');

	$request = $db->query('themes_count', '
		SELECT COUNT(DISTINCT id_member) AS value, id_theme
		FROM {db_prefix}themes
		WHERE id_member > {int:no_member}
			' . $customFieldsQuery . '
		GROUP BY id_theme',
		array(
			'no_member' => 0,
			'custom_fields' => empty($customFields) ? array() : $customFields,
		)
	);
	while ($row = $db->fetch_assoc($request))
		$themes[] = $row;
	$db->free_result($request);

	return $themes;
}

/**
 * Deletes all outdated options from the themes table
 *
 * @param bol $default_theme -> true is default, false for all custom themes
 * @param bol $membergroups -> true is for members, false for guests
 * @param array $old_settings
 */
function removeThemeOptions($default_theme, $membergroups, $old_settings)
{
	$db = database();
	
	// Which theme's option should we clean? 
	$default = ($default_theme = true ? '=' : '!='); 

	// Guest or regular membergroups?
	if ($membergroups === false )
		$mem_param = array('operator' => '=', 'id' => -1);
	else
		$mem_param = array('operator' => '>', 'id' => 0);
	
	$db->query('', '
		DELETE FROM {db_prefix}themes
		WHERE id_theme '. $default . ' {int:default_theme}
			AND id_member ' . $mem_param['operator'] . ' {int:guest_member}
			AND variable IN ({array_string:old_settings})',
		array(
			'default_theme' => 1,
			'guest_member' => $mem_param['id'],
			'old_settings' => $old_settings,
		)
	);
}

/**
 * Remove a specific option from the themes table
 *
 * @param int $theme
 * @param string $options
 */
function removeThemeOption($theme, $options)
{
	$db = database();

	$db->query('', '
		DELETE FROM {db_prefix}themes
		WHERE variable = {string:option}
			AND id_member > {int:no_member}
			AND id_theme = {int:current_theme}',
		array(
			'no_member' => 0,
			'current_theme' => $theme,
			'option' => $options,
		)
	);
}

/**
 * Update the default options for our users.
 * @param  array $setValues
 */
function updateThemeOptions($setValues)
{
	$db = database();

	$db->insert('replace',
		'{db_prefix}themes',
		array('id_member' => 'int', 'id_theme' => 'int', 'variable' => 'string-255', 'value' => 'string-65534'),
		$setValues,
		array('id_theme', 'variable', 'id_member')
	);
}

/**
 * Add predefined options to the themes table.
 *
 * @param int $id_theme
 * @param string $options
 * @param mixed $value
 */
function addThemeOptions($id_theme, $options, $value)
{
	$db = database();

	$db->query('substring', '
		INSERT INTO {db_prefix}themes
			(id_member, id_theme, variable, value)
		SELECT id_member, {int:current_theme}, SUBSTRING({string:option}, 1, 255), SUBSTRING({string:value}, 1, 65534)
		FROM {db_prefix}members',
		array(
			'current_theme' => $id_theme,
			'option' => $options,
			'value' => (is_array($value) ? implode(',', $value) : $value),
		)
	);
}

/**
 * Loads all the custom profile fields.
 *
 * @return array
 */
function loadCustomFields()
{
	$db = database();

	$request = $db->query('', '
		SELECT col_name
		FROM {db_prefix}custom_fields',
		array(
		)
	);
	$customFields = array();
	while ($row = $db->fetch_assoc($request))
		$customFields[] = $row['col_name'];
	$db->free_result($request);

	return $customFields;
}

/**
 * Deletes a theme from the database.
 *
 * @param int $id
 */
function deleteTheme($id)
{
	$db = database();

	// Make sure we never ever delete the default theme!
	if ($id === 1)
		fatal_lang_error('no_access', false);
	
	$db->query('', '
		DELETE FROM {db_prefix}themes
		WHERE id_theme = {int:current_theme}',
		array(
			'current_theme' => $id,
		)
	);

	// Update the members ...
	$db->query('', '
		UPDATE {db_prefix}members
		SET id_theme = {int:default_theme}
		WHERE id_theme = {int:current_theme}',
		array(
			'default_theme' => 0,
			'current_theme' => $id,
		)
	);

	// ... and the boards table.
	$db->query('', '
		UPDATE {db_prefix}boards
		SET id_theme = {int:default_theme}
		WHERE id_theme = {int:current_theme}',
		array(
			'default_theme' => 0,
			'current_theme' => $id,
		)
	);
}

/**
 * Get the next free id for the theme.
 *
 * @return int
 */
function nextTheme()
{
	$db = database();

	// Find the newest id_theme.
	$result = $db->query('', '
		SELECT MAX(id_theme)
		FROM {db_prefix}themes',
		array(
		)
	);
	list ($id_theme) = $db->fetch_row($result);
	$db->free_result($result);

	// This will be theme number...
	$id_theme++;

	return $id_theme;
}