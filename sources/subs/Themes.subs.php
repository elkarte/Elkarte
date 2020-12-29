<?php

/**
 * This file contains functions for dealing with topics. Low-level functions,
 * i.e. database operations needed to perform.
 * These functions do NOT make permissions checks. (they assume those were
 * already made).
 *
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * @version 2.0 dev
 *
 */

use ElkArte\User;
use ElkArte\Util;

/**
 * Retrieve all installed themes
 */
function installedThemes()
{
	$db = database();

	$themes = array();
	$db->fetchQuery('
		SELECT 
			id_theme, variable, value
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
	)->fetch_callback(
		function ($row) use (&$themes) {
			if (!isset($themes[$row['id_theme']]))
			{
				$themes[$row['id_theme']] = array(
					'id' => $row['id_theme'],
					'num_default_options' => 0,
					'num_members' => 0,
				);
			}

			$themes[$row['id_theme']][$row['variable']] = $row['value'];
		}
	);

	return $themes;
}

/**
 * Retrieve theme directory
 *
 * @param int $id_theme the id of the theme
 * @return string
 * @throws \ElkArte\Exceptions\Exception
 */
function themeDirectory($id_theme)
{
	$db = database();

	$request = $db->query('', '
		SELECT 
			value
		FROM {db_prefix}themes
		WHERE variable = {string:theme_dir}
			AND id_theme = {int:current_theme}
		LIMIT 1',
		array(
			'current_theme' => $id_theme,
			'theme_dir' => 'theme_dir',
		)
	);
	list ($themeDirectory) = $request->fetch_row();
	$request->free_result();

	return $themeDirectory;
}

/**
 * Retrieve theme URL
 *
 * @param int $id_theme id of the theme
 *
 * @return string
 * @throws \ElkArte\Exceptions\Exception
 */
function themeUrl($id_theme)
{
	$db = database();

	$request = $db->query('', '
		SELECT 
			value
		FROM {db_prefix}themes
		WHERE variable = {string:theme_url}
			AND id_theme = {int:current_theme}
		LIMIT 1',
		array(
			'current_theme' => $id_theme,
			'theme_url' => 'theme_url',
		)
	);
	list ($theme_url) = $request->fetch_row();
	$request->free_result();

	return $theme_url;
}

/**
 * Validates a theme name
 *
 * @param mixed[] $indexes
 * @param mixed[] $value_data
 *
 * @return array
 * @throws \Exception
 */
function validateThemeName($indexes, $value_data)
{
	$db = database();

	$themes = array();
	$db->fetchQuery('
		SELECT 
			id_theme, value
		FROM {db_prefix}themes
		WHERE id_member = {int:no_member}
			AND variable = {string:theme_dir}
			AND (' . implode(' OR ', $value_data['query']) . ')',
		array_merge($value_data['params'], array(
			'no_member' => 0,
			'theme_dir' => 'theme_dir',
			'index_compare_explode' => 'value LIKE \'%' . implode('\' OR value LIKE \'%', $indexes) . '\'',
		))
	)->fetch_callback(
		function ($row) use (&$themes, $indexes) {
			// Find the right one.
			foreach ($indexes as $index)
			{
				if (strpos($row['value'], $index) !== false)
				{
					$themes[$row['id_theme']] = $index;
				}
			}
		}
	);

	return $themes;
}

/**
 * Get a basic list of themes
 *
 * @param int|int[] $themes
 * @return array
 * @throws \Exception
 */
function getBasicThemeInfos($themes)
{
	$db = database();

	$themelist = array();

	$db->fetchQuery('
		SELECT 
			id_theme, value
		FROM {db_prefix}themes
		WHERE id_member = {int:no_member}
			AND variable = {string:name}
			AND id_theme IN ({array_int:theme_list})',
		array(
			'theme_list' => (array) $themes,
			'no_member' => 0,
			'name' => 'name',
		)
	)->fetch_callback(
		function ($row) use (&$themelist) {
			$themelist[$row['id_theme']] = $row['value'];
		}
	);

	return $themelist;
}

/**
 * Gets a list of all themes from the database
 *
 * @return array $themes
 * @throws \Exception
 */
function getCustomThemes()
{
	global $settings, $txt;

	$db = database();

	// Manually add in the default
	$themes = array(
		1 => array(
			'name' => $txt['dvc_default'],
			'theme_dir' => $settings['default_theme_dir'],
		),
	);

	$db->fetchQuery('
		SELECT 
			id_theme, variable, value
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
	)->fetch_callback(
		function ($row) use (&$themes) {
			$themes[$row['id_theme']][$row['variable']] = $row['value'];
		}
	);

	return $themes;
}

/**
 * Returns all named and installed themes paths as an array of theme name => path
 *
 * @param int[] $theme_list
 *
 * @return array
 * @throws \Exception
 */
function getThemesPathbyID($theme_list = array())
{
	global $modSettings;

	$db = database();

	// Nothing passed then we use the defaults
	if (empty($theme_list))
	{
		$theme_list = explode(',', $modSettings['knownThemes']);
	}

	if (!is_array($theme_list))
	{
		$theme_list = array($theme_list);
	}

	// Load up any themes we need the paths for
	$theme_paths = array();
	$db->fetchQuery('
		SELECT 
			id_theme, variable, value
		FROM {db_prefix}themes
		WHERE (id_theme = {int:default_theme} OR id_theme IN ({array_int:known_theme_list}))
			AND variable IN ({string:name}, {string:theme_dir})',
		array(
			'known_theme_list' => $theme_list,
			'default_theme' => 1,
			'name' => 'name',
			'theme_dir' => 'theme_dir',
		)
	)->fetch_callback(
		function ($row) use (&$theme_paths) {
			$theme_paths[$row['id_theme']][$row['variable']] = $row['value'];
		}
	);

	return $theme_paths;
}

/**
 * Load the installed themes
 * (minimum data)
 *
 * @param int[] $knownThemes available themes
 *
 * @return array
 * @throws \ElkArte\Exceptions\Exception
 */
function loadThemes($knownThemes)
{
	$db = database();

	// Load up all the themes.
	$themes = array();
	$db->query('', '
		SELECT 
			id_theme, value AS name
		FROM {db_prefix}themes
		WHERE variable = {string:name}
			AND id_member = {int:no_member}
		ORDER BY id_theme',
		array(
			'no_member' => 0,
			'name' => 'name',
		)
	)->fetch_callback(
		function ($row) use (&$themes, $knownThemes) {
			$themes[] = array(
				'id' => $row['id_theme'],
				'name' => $row['name'],
				'known' => in_array($row['id_theme'], $knownThemes),
			);
		}
	);

	return $themes;
}

/**
 * Load all themes that a package is installed in
 *
 * @param int $id id of the package we are checking
 *
 * @return array
 * @throws \Exception
 */
function loadThemesAffected($id)
{
	$db = database();

	$themes = array();
	$db->fetchQuery('
		SELECT 
			themes_installed
		FROM {db_prefix}log_packages
		WHERE id_install = {int:install_id}
		LIMIT 1',
		array(
			'install_id' => $id,
		)
	)->fetch_callback(
		function ($row) use (&$themes) {
			$themes = explode(',', $row['themes_installed']);
		}
	);

	return $themes;
}

/**
 * Generates a file listing for a given directory
 *
 * @param string $path
 * @param string $relative
 *
 * @return array
 * @throws \ElkArte\Exceptions\Exception error_invalid_dir
 */
function get_file_listing($path, $relative)
{
	global $scripturl, $context;

	// Only files with these extensions will be deemed editable
	$editable = 'php|pl|css|js|vbs|xml|xslt|txt|xsl|html|htm|shtm|shtml|asp|aspx|cgi|py';

	// Is it even a directory?
	if (!is_dir($path))
	{
		throw new \ElkArte\Exceptions\Exception('error_invalid_dir', 'critical');
	}

	// Read this directory's contents
	$entries = array();
	$dir = dir($path);
	while (($entry = $dir->read()))
	{
		$entries[] = $entry;
	}
	$dir->close();

	// Sort it so it looks natural to the user
	natcasesort($entries);

	$listing1 = array();
	$listing2 = array();

	foreach ($entries as $entry)
	{
		// Skip all dot files, including .htaccess.
		if (substr($entry, 0, 1) === '.' || $entry === 'CVS')
		{
			continue;
		}

		// A directory entry
		if (is_dir($path . '/' . $entry))
		{
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
		}
		// A file entry has some more checks
		else
		{
			$size = byte_format(filesize($path . '/' . $entry));

			$writable = is_writable($path . '/' . $entry);

			$listing2[] = array(
				'filename' => $entry,
				'is_writable' => $writable,
				'is_directory' => false,
				'is_template' => preg_match('~\.template\.php$~', $entry) != 0,
				'is_image' => preg_match('~\.(jpg|jpeg|gif|bmp|png|ico)$~', $entry) != 0,
				'is_editable' => $writable && preg_match('~\.(' . $editable . ')$~', $entry) != 0,
				'href' => $scripturl . '?action=admin;area=theme;th=' . $_GET['th'] . ';' . $context['session_var'] . '=' . $context['session_id'] . ';sa=edit;filename=' . $relative . $entry,
				'size' => $size,
				'last_modified' => standardTime(filemtime($path . '/' . $entry)),
			);
		}
	}

	return array_merge($listing1, $listing2);
}

/**
 * Counts the theme options configured for guests
 *
 * @return array
 * @throws \Exception
 */
function countConfiguredGuestOptions()
{
	$db = database();

	return $db->fetchQuery('
		SELECT 
			id_theme, COUNT(*) AS value
		FROM {db_prefix}themes
		WHERE id_member = {int:guest_member}
		GROUP BY id_theme',
		array(
			'guest_member' => -1,
		)
	)->fetch_all();
}

/**
 * Counts the theme options configured for guests
 *
 * @param int $current_theme
 * @param int $current_member
 *
 * @return array
 * @throws \ElkArte\Exceptions\Exception
 */
function availableThemes($current_theme, $current_member)
{
	global $modSettings, $settings, $txt, $language;

	$db = database();

	$available_themes = array();
	if (!empty($modSettings['knownThemes']))
	{
		$db->fetchQuery('
			SELECT 
				id_theme, variable, value
			FROM {db_prefix}themes
			WHERE variable IN ({string:name}, {string:theme_url}, {string:theme_dir}, {string:images_url}, {string:disable_user_variant})' . (!allowedTo('admin_forum') ? '
				AND id_theme IN ({array_string:known_themes})' : '') . '
				AND id_theme != {int:default_theme}
				AND id_member = {int:no_member}',
			array(
				'default_theme' => 0,
				'name' => 'name',
				'no_member' => 0,
				'theme_url' => 'theme_url',
				'theme_dir' => 'theme_dir',
				'images_url' => 'images_url',
				'disable_user_variant' => 'disable_user_variant',
				'known_themes' => !empty($modSettings['theme_allow']) || allowedTo('admin_forum') ? explode(',', $modSettings['knownThemes']) : array($modSettings['theme_guests']),
			)
		)->fetch_callback(
			function ($row) use (&$available_themes, $current_theme) {
				if (!isset($available_themes[$row['id_theme']]))
				{
					$available_themes[$row['id_theme']] = array(
						'id' => $row['id_theme'],
						'selected' => $current_theme == $row['id_theme'],
						'num_users' => 0
					);
				}

				$available_themes[$row['id_theme']][$row['variable']] = $row['value'];
			}
		);
	}

	// Okay, this is a complicated problem: the default theme is 1, but they aren't allowed to access 1!
	if (!isset($available_themes[$modSettings['theme_guests']]))
	{
		$available_themes[0] = array(
			'num_users' => 0
		);
		$guest_theme = 0;
	}
	else
	{
		$guest_theme = $modSettings['theme_guests'];
	}

	$db->fetchQuery('
		SELECT 
			id_theme, COUNT(*) AS the_count
		FROM {db_prefix}members
		GROUP BY id_theme
		ORDER BY id_theme DESC',
		array()
	)->fetch_callback(
		function ($row) use (&$available_themes, $guest_theme) {
			global $modSettings;

			// Figure out which theme it is they are REALLY using.
			if (!empty($modSettings['knownThemes']) && !in_array($row['id_theme'], explode(',', $modSettings['knownThemes'])))
			{
				$row['id_theme'] = $guest_theme;
			}
			elseif (empty($modSettings['theme_allow']))
			{
				$row['id_theme'] = $guest_theme;
			}

			if (isset($available_themes[$row['id_theme']]))
			{
				$available_themes[$row['id_theme']]['num_users'] += $row['the_count'];
			}
			else
			{
				$available_themes[$guest_theme]['num_users'] += $row['the_count'];
			}
		}
	);

	// Get any member variant preferences.
	$variant_preferences = array();
	if ($current_member > 0)
	{
		$db->fetchQuery('
			SELECT 
				id_theme, value
			FROM {db_prefix}themes
			WHERE variable = {string:theme_variant}
				AND id_member IN ({array_int:id_member})
			ORDER BY id_member ASC',
			array(
				'theme_variant' => 'theme_variant',
				'id_member' => isset($_REQUEST['sa']) && $_REQUEST['sa'] == 'pick' ? array(-1, $current_member) : array(-1),
			)
		)->fetch_callback(
			function ($row) use (&$variant_preferences) {
				$variant_preferences[$row['id_theme']] = $row['value'];
			}
		);
	}

	// Save the setting first.
	$current_images_url = $settings['images_url'];
	$current_theme_variants = !empty($settings['theme_variants']) ? $settings['theme_variants'] : array();

	foreach ($available_themes as $id_theme => $theme_data)
	{
		// Don't try to load the forum or board default theme's data... it doesn't have any!
		if ($id_theme == 0)
		{
			continue;
		}

		// The thumbnail needs the correct path.
		$settings['images_url'] = &$theme_data['images_url'];
		$theme_thumbnail_href = $theme_data['images_url'] . '/thumbnail.png';

		if (file_exists($theme_data['theme_dir'] . '/languages/' . User::$info->language . '/Settings.' . User::$info->language . '.php'))
		{
			include($theme_data['theme_dir'] . '/languages/' . User::$info->language . '/Settings.' . User::$info->language . '.php');
		}
		elseif (file_exists($theme_data['theme_dir'] . '/languages/' . $language . '/Settings.' . $language . '.php'))
		{
			include($theme_data['theme_dir'] . '/languages/' . $language . '/Settings.' . $language . '.php');
		}
		else
		{
			$txt['theme_description'] = '';
		}

		$available_themes[$id_theme]['thumbnail_href'] = str_replace('{images_url}', $settings['images_url'], $theme_thumbnail_href);
		$available_themes[$id_theme]['description'] = $txt['theme_description'];

		// Are there any variants?
		if (file_exists($theme_data['theme_dir'] . '/index.template.php') && (empty($theme_data['disable_user_variant']) || allowedTo('admin_forum')))
		{
			$file_contents = implode('', file($theme_data['theme_dir'] . '/index.template.php'));
			if (preg_match('~\'theme_variants\'\s*=>(.+?\)),$~sm', $file_contents, $matches))
			{
				$settings['theme_variants'] = array();

				// Fill settings up.
				eval('global $settings; $settings[\'theme_variants\'] = ' . $matches[1] . ';');

				call_integration_hook('integrate_init_theme', array($id_theme, &$settings));

				if (!empty($settings['theme_variants']))
				{
					theme()->getTemplates()->loadLanguageFile('Settings');

					$available_themes[$id_theme]['variants'] = array();
					foreach ($settings['theme_variants'] as $variant)
					{
						$available_themes[$id_theme]['variants'][$variant] = array(
							'label' => isset($txt['variant_' . $variant]) ? $txt['variant_' . $variant] : $variant,
							'thumbnail' => !file_exists($theme_data['theme_dir'] . '/images/thumbnail.png') || file_exists($theme_data['theme_dir'] . '/images/thumbnail_' . $variant . '.png') ? $theme_data['images_url'] . '/thumbnail_' . $variant . '.png' : ($theme_data['images_url'] . '/thumbnail.png'),
						);
					}

					$available_themes[$id_theme]['selected_variant'] = isset($_GET['vrt']) ? $_GET['vrt'] : (!empty($variant_preferences[$id_theme]) ? $variant_preferences[$id_theme] : (!empty($settings['default_variant']) ? $settings['default_variant'] : $settings['theme_variants'][0]));
					if (!isset($available_themes[$id_theme]['variants'][$available_themes[$id_theme]['selected_variant']]['thumbnail']))
					{
						$available_themes[$id_theme]['selected_variant'] = $settings['theme_variants'][0];
					}

					$available_themes[$id_theme]['thumbnail_href'] = $available_themes[$id_theme]['variants'][$available_themes[$id_theme]['selected_variant']]['thumbnail'];

					// Allow themes to override the text.
					$available_themes[$id_theme]['pick_label'] = isset($txt['variant_pick']) ? $txt['variant_pick'] : $txt['theme_pick_variant'];
				}
			}
		}
	}

	// Then return it.
	$settings['images_url'] = $current_images_url;
	$settings['theme_variants'] = $current_theme_variants;

	return array($available_themes, $guest_theme);
}

/**
 * Counts the theme options configured for members
 *
 * @return array
 * @throws \ElkArte\Exceptions\Exception
 */
function countConfiguredMemberOptions()
{
	$db = database();

	return $db->query('themes_count', '
		SELECT 
			COUNT(DISTINCT id_member) AS value, id_theme
		FROM {db_prefix}themes
		WHERE id_member > {int:no_member}
		GROUP BY id_theme',
		array(
			'no_member' => 0,
		)
	)->fetch_all();
}

/**
 * Deletes all outdated options from the themes table
 *
 * @param int|string $theme : if int to remove option from a specific theme,
 *              if string it can be:
 *               - 'default' => to remove from the default theme
 *               - 'custom' => to remove from all the custom themes
 *               - 'all' => to remove from both default and custom
 * @param int|string $membergroups : if int a specific member
 *              if string a "group" of members and it can assume the following values:
 *               - 'guests' => obviously guests,
 *               - 'members' => all members with custom settings (i.e. id_member > 0)
 *               - 'non_default' => guests and members with custom settings (i.e. id_member != 0)
 *               - 'all' => any record
 * @param string[]|string $old_settings can be a string or an array of strings. If empty deletes all settings.
 * @throws \ElkArte\Exceptions\Exception
 */
function removeThemeOptions($theme, $membergroups, $old_settings = '')
{
	$db = database();

	$query_param = array();

	// The default theme is 1 (id_theme = 1)
	if ($theme === 'default')
	{
		$query_param = array('theme_operator' => '=', 'theme' => 1);
	}
	// All the themes that are not the default one (id_theme != 1)
	// @todo 'non_default' would be more explicative, though it could be confused with the one in $membergroups
	elseif ($theme === 'custom')
	{
		$query_param = array('theme_operator' => '!=', 'theme' => 1);
	}
	// If numeric means a specific theme
	elseif (is_numeric($theme))
	{
		$query_param = array('theme_operator' => '=', 'theme' => (int) $theme);
	}

	// Guests means id_member = -1
	if ($membergroups === 'guests')
	{
		$query_param += array('member_operator' => '=', 'member' => -1);
	}
	// Members means id_member > 0
	elseif ($membergroups === 'members')
	{
		$query_param += array('member_operator' => '>', 'member' => 0);
	}
	// Non default settings id_member != 0 (that is different from id_member > 0)
	elseif ($membergroups === 'non_default')
	{
		$query_param += array('member_operator' => '!=', 'member' => 0);
	}
	// all it's all
	elseif ($membergroups === 'all')
	{
		$query_param += array('member_operator' => '', 'member' => 0);
	}
	// If it is a number, then it means a specific member (id_member = (int))
	elseif (is_numeric($membergroups))
	{
		$query_param += array('member_operator' => '=', 'member' => (int) $membergroups);
	}

	// If array or string set up the query accordingly
	if (is_array($old_settings))
	{
		$var = 'variable IN ({array_string:old_settings})';
	}
	elseif (!empty($old_settings))
	{
		$var = 'variable = {string:old_settings}';
	}
	// If empty then means any setting
	else
	{
		$var = '1=1';
	}

	$db->query('', '
		DELETE FROM {db_prefix}themes
		WHERE ' . $var . ($membergroups === 'all' ? '' : '
			AND id_member {raw:member_operator} {int:member}') . ($theme === 'all' ? '' : '
			AND id_theme {raw:theme_operator} {int:theme}'),
		array_merge(
			$query_param,
			array(
				'old_settings' => $old_settings
			)
		)
	);
}

/**
 * Update the default options for our users.
 *
 * @param mixed[] $setValues in the order: id_theme, id_member, variable name, value
 * @throws \Exception
 */
function updateThemeOptions($setValues)
{
	$db = database();

	$db->insert('replace',
		'{db_prefix}themes',
		array('id_theme' => 'int', 'id_member' => 'int', 'variable' => 'string-255', 'value' => 'string-65534'),
		$setValues,
		array('id_theme', 'variable', 'id_member')
	);
}

/**
 * Add predefined options to the themes table.
 *
 * @param int $id_theme
 * @param string $options
 * @param string[]|string $value
 * @throws \ElkArte\Exceptions\Exception
 */
function addThemeOptions($id_theme, $options, $value)
{
	$db = database();

	$db->fetchQuery('
		INSERT INTO {db_prefix}themes
			(id_member, id_theme, variable, value)
		SELECT 
			id_member, {int:current_theme}, SUBSTRING({string:option}, 1, 255), SUBSTRING({string:value}, 1, 65534)
		FROM {db_prefix}members',
		array(
			'current_theme' => $id_theme,
			'option' => $options,
			'value' => (is_array($value) ? implode(',', $value) : $value),
		)
	);
}

/**
 * Deletes a theme from the database.
 *
 * @param int $id
 *
 * @throws \ElkArte\Exceptions\Exception no_access
 */
function deleteTheme($id)
{
	$db = database();

	// Make sure we never ever delete the default theme!
	if ($id === 1)
	{
		throw new \ElkArte\Exceptions\Exception('no_access', false);
	}

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
		SET 
			id_theme = {int:default_theme}
		WHERE id_theme = {int:current_theme}',
		array(
			'default_theme' => 0,
			'current_theme' => $id,
		)
	);

	// ... and the boards table.
	$db->query('', '
		UPDATE {db_prefix}boards
		SET 
			id_theme = {int:default_theme}
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
 * @throws \ElkArte\Exceptions\Exception
 */
function nextTheme()
{
	$db = database();

	// Find the newest id_theme.
	$result = $db->query('', '
		SELECT 
			MAX(id_theme)
		FROM {db_prefix}themes',
		array()
	);
	list ($id_theme) = $result->fetch_row();
	$result->free_result();

	// This will be theme number...
	$id_theme++;

	return $id_theme;
}

/**
 * Adds a new theme to the database.
 *
 * @param mixed[] $details
 * @throws \Exception
 */
function addTheme($details)
{
	$db = database();

	$db->insert('insert',
		'{db_prefix}themes',
		array('id_theme' => 'int', 'variable' => 'string-255', 'value' => 'string-65534'),
		$details,
		array('id_theme', 'variable')
	);
}

/**
 * Get the name of a theme
 *
 * @param int $id
 * @return string
 * @throws \ElkArte\Exceptions\Exception
 */
function getThemeName($id)
{
	$db = database();

	$result = $db->query('', '
		SELECT 
			value
		FROM {db_prefix}themes
		WHERE id_theme = {int:current_theme}
			AND id_member = {int:no_member}
			AND variable = {string:name}
		LIMIT 1',
		array(
			'current_theme' => $id,
			'no_member' => 0,
			'name' => 'name',
		)
	);
	list ($theme_name) = $result->fetch_row();
	$result->free_result();

	return $theme_name;
}

/**
 * Deletes all variants from a given theme id.
 *
 * @param int $id
 * @throws \ElkArte\Exceptions\Exception
 */
function deleteVariants($id)
{
	$db = database();

	$db->query('', '
		DELETE FROM {db_prefix}themes
		WHERE id_theme = {int:current_theme}
			AND variable = {string:theme_variant}',
		array(
			'current_theme' => $id,
			'theme_variant' => 'theme_variant',
		)
	);
}

/**
 * Loads all of the theme variable/value pairs for a member or group of members
 * If supplied a variable array it will only load / return those values
 *
 * @param int|int[] $theme
 * @param int|int[]|null $memID
 * @param mixed[] $options
 * @param string[] $variables
 *
 * @return array|mixed[]
 * @throws \Exception
 */
function loadThemeOptionsInto($theme, $memID = null, $options = array(), $variables = array())
{
	$db = database();

	$variables = is_array($variables) ? $variables : array($variables);

	// @todo the ORDER BY may or may not be necessary:
	// I have the feeling that *sometimes* the default order may be a bit messy,
	// and considering this function is not use in frequently accessed areas the
	// overhead for an ORDER BY should be acceptable
	$db->fetchQuery('
		SELECT 
			variable, value
		FROM {db_prefix}themes
		WHERE id_theme IN ({array_int:current_theme})' . ($memID === null ? '' : (is_array($memID) ? '
			AND id_member IN ({array_int:guest_member})' : '
			AND id_member = {int:guest_member}')) . (!empty($variables) ? '
			AND variable IN ({array_string:variables})' : '') . '
		ORDER BY id_theme ASC' . ($memID === null ? '' : ', id_member ASC'),
		array(
			'current_theme' => is_array($theme) ? $theme : array($theme),
			'guest_member' => $memID,
			'variables' => $variables,
		)
	)->fetch_callback(
		function ($row) use (&$options) {
			$options[$row['variable']] = $row['value'];
		}
	);

	return $options;
}

/**
 * Used when installing a theme that is based off an existing theme (an therefore is dependant on)
 * Returns based-on theme directory values needed by the install function in ManageThemes.controller
 *
 * @param string $based_on name of theme this is based on, will do a LIKE search
 * @param bool $explicit_images Don't worry its not like it sounds !
 * @return mixed[]
 * @throws \ElkArte\Exceptions\Exception
 * @todo may be merged with something else?
 */
function loadBasedOnTheme($based_on, $explicit_images = false)
{
	$db = database();

	$request = $db->query('', '
		SELECT 
			th.value AS base_theme_dir, th2.value AS base_theme_url' . (!empty($explicit_images) ? '' : ', th3.value AS images_url') . '
		FROM {db_prefix}themes AS th
			INNER JOIN {db_prefix}themes AS th2 ON (th2.id_theme = th.id_theme
				AND th2.id_member = {int:no_member}
				AND th2.variable = {string:theme_url})' . (!empty($explicit_images) ? '' : '
			INNER JOIN {db_prefix}themes AS th3 ON (th3.id_theme = th.id_theme
				AND th3.id_member = {int:no_member}
				AND th3.variable = {string:images_url})') . '
		WHERE th.id_member = {int:no_member}
			AND (th.value LIKE {string:based_on} OR th.value LIKE {string:based_on_path})
			AND th.variable = {string:theme_dir}
		LIMIT 1',
		array(
			'no_member' => 0,
			'theme_url' => 'theme_url',
			'images_url' => 'images_url',
			'theme_dir' => 'theme_dir',
			'based_on' => '%/' . $based_on,
			'based_on_path' => '%\\' . $based_on,
		)
	);
	$temp = $request->fetch_assoc();
	$request->free_result();

	return $temp;
}

/**
 * Builds a theme-info.xml file for use when a new theme is installed by copying
 * an existing theme
 *
 * @param string $name
 * @param string $version
 * @param string $theme_dir
 * @param mixed[] $theme_values
 */
function write_theme_info($name, $version, $theme_dir, $theme_values)
{
	$xml_info = '<' . '?xml version="1.0"?' . '>
	<theme-info xmlns="https://www.elkarte.net/xml/theme-info" xmlns:elk="https://www.elkarte.net/">
		<!-- For the id, always use something unique - put your name, a colon, and then the package name. -->
		<id>elk:' . Util::strtolower(str_replace(array(' '), '_', $name)) . '</id>
		<version>' . $version . '</version>
		<!-- Theme name, used purely for aesthetics. -->
		<name>' . $name . '</name>
		<!-- Author: your email address or contact information. The name attribute is optional. -->
		<author name="Your Name">info@youremailaddress.tld</author>
		<!-- Website... where to get updates and more information. -->
		<website>http://www.yourdomain.tld/</website>
		<!-- Template layers to use, defaults to "html,body". -->
		<layers>' . (empty($theme_values['theme_layers']) ? 'html,body' : $theme_values['theme_layers']) . '</layers>
		<!-- Templates to load on startup. Default is "index". -->
		<templates>' . (empty($theme_values['theme_templates']) ? 'index' : $theme_values['theme_templates']) . '</templates>
		<!-- Base this theme off another? Default is blank, or no. It could be "default". -->
		<based-on></based-on>
	</theme-info>';

	// Now write it.
	file_put_contents($theme_dir . '/theme_info.xml', $xml_info);
}
