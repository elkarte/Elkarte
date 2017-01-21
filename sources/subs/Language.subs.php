<?php

/**
 * This file contains the database work for languages.
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 1.1 beta 4
 *
 */

/**
 * Removes the given language from all members..
 *
 * @package Languages
 * @param int $lang_id
 */
function removeLanguageFromMember($lang_id)
{
	$db = database();

	$db->query('', '
		UPDATE {db_prefix}members
		SET lngfile = {string:empty_string}
		WHERE lngfile = {string:current_language}',
		array(
			'empty_string' => '',
			'current_language' => $lang_id,
		)
	);
}

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
		$settings['base_theme_dir'] = $backup_base_theme_dir;
	else
		unset($settings['base_theme_dir']);

	// Get the language files and data...
	foreach ($all_languages as $lang)
	{
		// Load the file to get the character set.
		require($lang['location']);

		$languages[$lang['filename']] = array(
			'id' => $lang['filename'],
			'count' => 0,
			'char_set' => 'UTF-8',
			'default' => $language == $lang['filename'] || ($language == '' && $lang['filename'] == 'english'),
			'locale' => $txt['lang_locale'],
			'name' => Util::ucwords(strtr($lang['filename'], array('_' => ' ', '-utf8' => ''))),
		);
	}

	// Work out how many people are using each language.
	$request = $db->query('', '
		SELECT lngfile, COUNT(*) AS num_users
		FROM {db_prefix}members
		GROUP BY lngfile',
		array(
		)
	);
	while ($row = $db->fetch_assoc($request))
	{
		// Default?
		if (empty($row['lngfile']) || !isset($languages[$row['lngfile']]))
			$row['lngfile'] = $language;

		if (!isset($languages[$row['lngfile']]) && isset($languages['english']))
			$languages['english']['count'] += $row['num_users'];
		elseif (isset($languages[$row['lngfile']]))
			$languages[$row['lngfile']]['count'] += $row['num_users'];
	}
	$db->free_result($request);

	// Restore the current users language.
	$txt = $old_txt;

	// Return how many we have.
	return $languages;
}

/**
 * This function cleans language entries to/from display.
 *
 * @package Languages
 * @param string $string
 * @param boolean $to_display
 */
function cleanLangString($string, $to_display = true)
{
	// If going to display we make sure it doesn't have any HTML in it - etc.
	$new_string = '';
	if ($to_display)
	{
		// Are we in a string (0 = no, 1 = single quote, 2 = parsed)
		$in_string = 0;
		$is_escape = false;
		$str_len = strlen($string);
		for ($i = 0; $i < $str_len; $i++)
		{
			// Handle escapes first.
			if ($string[$i] == '\\')
			{
				// Toggle the escape.
				$is_escape = !$is_escape;

				// If we're now escaped don't add this string.
				if ($is_escape)
					continue;
			}
			// Special case - parsed string with line break etc?
			elseif (($string[$i] == 'n' || $string[$i] == 't') && $in_string == 2 && $is_escape)
			{
				// Put the escape back...
				$new_string .= $string[$i] == 'n' ? "\n" : "\t";
				$is_escape = false;
				continue;
			}
			// Have we got a single quote?
			elseif ($string[$i] == '\'')
			{
				// Already in a parsed string, or escaped in a linear string, means we print it - otherwise something special.
				if ($in_string != 2 && ($in_string != 1 || !$is_escape))
				{
					// Is it the end of a single quote string?
					if ($in_string == 1)
						$in_string = 0;
					// Otherwise it's the start!
					else
						$in_string = 1;

					// Don't actually include this character!
					continue;
				}
			}
			// Otherwise a double quote?
			elseif ($string[$i] == '"')
			{
				// Already in a single quote string, or escaped in a parsed string, means we print it - otherwise something special.
				if ($in_string != 1 && ($in_string != 2 || !$is_escape))
				{
					// Is it the end of a double quote string?
					if ($in_string == 2)
						$in_string = 0;
					// Otherwise it's the start!
					else
						$in_string = 2;

					// Don't actually include this character!
					continue;
				}
			}
			// A join/space outside of a string is simply removed.
			elseif ($in_string == 0 && (empty($string[$i]) || $string[$i] == '.'))
				continue;
			// Start of a variable?
			elseif ($in_string == 0 && $string[$i] == '$')
			{
				// Find the whole of it!
				preg_match('~([\$A-Za-z0-9\'\[\]_-]+)~', substr($string, $i), $matches);
				if (!empty($matches[1]))
				{
					// Come up with some pseudo thing to indicate this is a var.
					// @todo Do better than this, please!
					$new_string .= '{%' . $matches[1] . '%}';

					// We're not going to re-parse this.
					$i += strlen($matches[1]) - 1;
				}

				continue;
			}
			// Right, if we're outside of a string we have DANGER, DANGER!
			elseif ($in_string == 0)
			{
				continue;
			}

			// Actually add the character to the string!
			$new_string .= $string[$i];

			// If anything was escaped it ain't any longer!
			$is_escape = false;
		}

		// Un-html then re-html the whole thing!
		$new_string = Util::htmlspecialchars(un_htmlspecialchars($new_string));
	}
	else
	{
		// Keep track of what we're doing...
		$in_string = 0;

		// This is for deciding whether to HTML a quote.
		$in_html = false;
		$str_len = strlen($string);
		for ($i = 0; $i < $str_len; $i++)
		{
			// We don't do parsed strings apart from for breaks.
			if ($in_string == 2)
			{
				$in_string = 0;
				$new_string .= '"';
			}

			// Not in a string yet?
			if ($in_string != 1)
			{
				$in_string = 1;
				$new_string .= ($new_string ? ' . ' : '') . '\'';
			}

			// Is this a variable?
			if ($string[$i] == '{' && $string[$i + 1] == '%' && $string[$i + 2] == '$')
			{
				// Grab the variable.
				preg_match('~\{%([\$A-Za-z0-9\'\[\]_-]+)%\}~', substr($string, $i), $matches);
				if (!empty($matches[1]))
				{
					if ($in_string == 1)
						$new_string .= '\' . ';
					elseif ($new_string)
						$new_string .= ' . ';

					$new_string .= $matches[1];
					$i += strlen($matches[1]) + 3;
					$in_string = 0;
				}

				continue;
			}
			// Is this a lt sign?
			elseif ($string[$i] == '<')
			{
				// Probably HTML?
				if ($string[$i + 1] != ' ')
					$in_html = true;
				// Assume we need an entity...
				else
				{
					$new_string .= '&lt;';
					continue;
				}
			}
			// What about gt?
			elseif ($string[$i] == '>')
			{
				// Will it be HTML?
				if ($in_html)
					$in_html = false;
				// Otherwise we need an entity...
				else
				{
					$new_string .= '&gt;';
					continue;
				}
			}
			// Is it a slash? If so escape it...
			if ($string[$i] == '\\')
				$new_string .= '\\';
			// The infamous double quote?
			elseif ($string[$i] == '"')
			{
				// If we're in HTML we leave it as a quote - otherwise we entity it.
				if (!$in_html)
				{
					$new_string .= '&quot;';
					continue;
				}
			}
			// A single quote?
			elseif ($string[$i] == '\'')
			{
				// Must be in a string so escape it.
				$new_string .= '\\';
			}

			// Finally add the character to the string!
			$new_string .= $string[$i];
		}

		// If we ended as a string then close it off.
		if ($in_string == 1)
			$new_string .= '\'';
		elseif ($in_string == 2)
			$new_string .= '"';
	}

	return $new_string;
}

/**
 * Gets a list of available languages from the mother ship
 *
 * - Will return a subset if searching, otherwise all available
 *
 * @package Languages
 * @return string
 */
function list_getLanguagesList()
{
	global $context, $txt, $scripturl;

	// We're going to use this URL.
	// @todo no we are not, this needs to be changed - again
	$url = 'http://download.elkarte.net/fetch_language.php?version=' . urlencode(strtr(FORUM_VERSION, array('ElkArte ' => '')));

	// Load the class file and stick it into an array.
	$language_list = new Xml_Array(fetch_web_data($url), true);

	// Check that the site responded and that the language exists.
	if (!$language_list->exists('languages'))
		$context['langfile_error'] = 'no_response';
	elseif (!$language_list->exists('languages/language'))
		$context['langfile_error'] = 'no_files';
	else
	{
		$language_list = $language_list->path('languages[0]');
		$lang_files = $language_list->set('language');
		$languages = array();
		foreach ($lang_files as $file)
		{
			// Were we searching?
			if (!empty($context['elk_search_term']) && strpos($file->fetch('name'), Util::strtolower($context['elk_search_term'])) === false)
				continue;

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
			$context['langfile_error'] = 'no_files';
		else
			return $languages;
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
	if ($db->num_rows($request) > 0)
	{
		list ($pid, $file_name) = $db->fetch_row($request);
	}
	$db->free_result($request);

	if (!empty($pid))
		return array($pid, $file_name);
	else
		return false;
}
