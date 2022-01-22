<?php

/**
 * This file contains the database work for languages.
 *
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * @version 2.0 dev
 *
 */

use ElkArte\Util;
use ElkArte\XmlArray;

/**
 * How many languages?
 *
 * - Callback for the list in action_edit().
 *
 * @package Languages
 */
function list_getNumLanguages()
{
	return count(getLanguages());
}

/**
 * Fetch the actual language information.
 *
 * What it does:
 *
 * - Callback for $listOptions['get_items']['function'] in action_edit.
 * - Determines which languages are available by looking for the "index.{language}.php" file.
 * - Also figures out how many users are using a particular language.
 *
 * @package Languages
 */
function list_getLanguages()
{
	global $settings, $language, $txt;

	$db = database();

	$languages = array();
	// Keep our old entries.
	$old_txt = $txt;
	$backup_actual_theme_dir = $settings['actual_theme_dir'];
	$backup_base_theme_dir = !empty($settings['base_theme_dir']) ? $settings['base_theme_dir'] : '';

	// Override these for now.
	$settings['actual_theme_dir'] = $settings['base_theme_dir'] = $settings['default_theme_dir'];
	$all_languages = getLanguages();

	// Put them back.
	$settings['actual_theme_dir'] = $backup_actual_theme_dir;
	if (!empty($backup_base_theme_dir))
	{
		$settings['base_theme_dir'] = $backup_base_theme_dir;
	}
	else
	{
		unset($settings['base_theme_dir']);
	}

	// Get the language files and data...
	foreach ($all_languages as $lang)
	{
		// Load the file to get the character set.
		require($lang['location']);

		$languages[$lang['filename']] = array(
			'id' => basename($lang['filename'], '.php'),
			'count' => 0,
			'char_set' => 'UTF-8',
			'default' => $language == $lang['name'] || ($language == '' && strtolower($lang['name']) == 'english'),
			'locale' => $txt['lang_locale'],
			'name' => Util::ucwords(strtr($lang['name'], array('_' => ' ', '-utf8' => ''))),
		);
	}

	// Work out how many people are using each language.
	$db->fetchQuery('
		SELECT 
			lngfile, COUNT(*) AS num_users
		FROM {db_prefix}members
		GROUP BY lngfile',
		array()
	)->fetch_callback(
		function ($row) use (&$languages, $language) {
			// Default?
			if (empty($row['lngfile']) || !isset($languages[$row['lngfile']]))
			{
				$row['lngfile'] = $language;
			}

			if (!isset($languages[$row['lngfile']]) && isset($languages['english']))
			{
				$languages['english']['count'] += $row['num_users'];
			}
			elseif (isset($languages[$row['lngfile']]))
			{
				$languages[$row['lngfile']]['count'] += $row['num_users'];
			}
		}
	);

	// Restore the current users language.
	$txt = $old_txt;

	// Return how many we have.
	return $languages;
}

/**
 * Gets a list of available languages from the mother ship
 *
 * - Will return a subset if searching, otherwise all available
 *
 * @return array
 * @package Languages
 */
function list_getLanguagesList()
{
	global $context, $txt, $scripturl;

	// We're going to use this URL.
	// @todo no we are not, this needs to be changed - again
	$url = 'http://download.elkarte.net/fetch_language.php?version=' . urlencode(strtr(FORUM_VERSION, array('ElkArte ' => '')));

	// Load the class file and stick it into an array.
	$language_list = new XmlArray(fetch_web_data($url), true);

	// Check that the site responded and that the language exists.
	if (!$language_list->exists('languages'))
	{
		$context['langfile_error'] = 'no_response';
	}
	elseif (!$language_list->exists('languages/language'))
	{
		$context['langfile_error'] = 'no_files';
	}
	else
	{
		$language_list = $language_list->path('languages[0]');
		$lang_files = $language_list->set('language');
		$languages = array();
		foreach ($lang_files as $file)
		{
			// Were we searching?
			if (!empty($context['elk_search_term']) && strpos($file->fetch('name'), Util::strtolower($context['elk_search_term'])) === false)
			{
				continue;
			}

			$languages[] = array(
				'id' => $file->fetch('id'),
				'name' => Util::ucwords($file->fetch('name')),
				'version' => $file->fetch('version'),
				'utf8' => $txt['yes'],
				'description' => $file->fetch('description'),
				'install_link' => '<a href="' . $scripturl . '?action=admin;area=languages;sa=downloadlang;did=' . $file->fetch('id') . ';' . $context['session_var'] . '=' . $context['session_id'] . '">' . $txt['add_language_elk_install'] . '</a>',
			);
		}
		if (empty($languages))
		{
			$context['langfile_error'] = 'no_files';
		}
		else
		{
			return $languages;
		}
	}
}

/**
 * Finds installed language files of type lang
 *
 * @param string $lang
 *
 * @return array|bool
 */
function findPossiblePackages($lang)
{
	$db = database();

	$request = $db->query('', '
		SELECT 
			id_install, filename
		FROM {db_prefix}log_packages
		WHERE package_id LIKE {string:contains_lang}
			AND install_state = {int:installed}',
		array(
			'contains_lang' => 'elk_' . $lang . '_contribs:elk_' . $lang . '',
			'installed' => 1,
		)
	);
	$file_name = '';
	if ($request->num_rows() > 0)
	{
		list ($pid, $file_name) = $request->fetch_row();
	}
	$request->free_result();

	if (!empty($pid))
	{
		return array($pid, $file_name);
	}

	return false;
}
