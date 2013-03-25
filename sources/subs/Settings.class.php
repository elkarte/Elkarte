<?php

/**
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
 * @version 1.0 Alpha
 *
 * This class handles admin settings.
 *
 * Adding options to one of the setting screens isn't hard. Call prepareDBSettingsContext;
 * The basic format for a checkbox is:
 * 		array('check', 'nameInModSettingsAndSQL'),
 * And for a text box:
 * 		array('text', 'nameInModSettingsAndSQL')
 * (NOTE: You have to add an entry for this at the bottom!)
 *
 * In these cases, it will look for $txt['nameInModSettingsAndSQL'] as the description,
 * and $helptxt['nameInModSettingsAndSQL'] as the help popup description.
 *
 * Here's a quick explanation of how to add a new item:
 *
 * - A text input box.  For textual values.
 * 		array('text', 'nameInModSettingsAndSQL', 'OptionalInputBoxWidth'),
 * - A text input box.  For numerical values.
 * 		array('int', 'nameInModSettingsAndSQL', 'OptionalInputBoxWidth'),
 * - A text input box.  For floating point values.
 * 		array('float', 'nameInModSettingsAndSQL', 'OptionalInputBoxWidth'),
 * - A large text input box. Used for textual values spanning multiple lines.
 * 		array('large_text', 'nameInModSettingsAndSQL', 'OptionalNumberOfRows'),
 * - A check box.  Either one or zero. (boolean)
 * 		array('check', 'nameInModSettingsAndSQL'),
 * - A selection box.  Used for the selection of something from a list.
 * 		array('select', 'nameInModSettingsAndSQL', array('valueForSQL' => $txt['displayedValue'])),
 * 		Note that just saying array('first', 'second') will put 0 in the SQL for 'first'.
 * - A password input box. Used for passwords, no less!
 * 		array('password', 'nameInModSettingsAndSQL', 'OptionalInputBoxWidth'),
 * - A permission - for picking groups who have a permission.
 * 		array('permissions', 'manage_groups'),
 * - A BBC selection box.
 * 		array('bbc', 'sig_bbc'),
 *
 * For each option:
 * 	- type (see above), variable name, size/possible values.
 * 	  OR make type '' for an empty string for a horizontal rule.
 *  - SET preinput - to put some HTML prior to the input box.
 *  - SET postinput - to put some HTML following the input box.
 *  - SET invalid - to mark the data as invalid.
 *  - PLUS you can override label and help parameters by forcing their keys in the array, for example:
 *  	array('text', 'invalidlabel', 3, 'label' => 'Actual Label')
 *
 */

if (!defined('ELKARTE'))
	die('No access...');

class Settings_Form
{

	/**
	 * Helper function, it sets up the context for the settings.
	 * - The basic usage of the six numbered key fields are
	 * - array (0 ,1, 2, 3, 4, 5
	 *		0 variable name - the name of the saved variable
	 *		1 label - the text to show on the settings page
	 *		2 saveto - file or db, where to save the variable name - value pair
	 *		3 type - type of data to save, int, float, text, check
	 *		4 size - false or field size
	 *		5 help - '' or helptxt variable name
	 *	)
	 *
	 * the following named keys are also permitted
	 * 'disabled' => 'postinput' => 'preinput' =>
	 *
	 * @param array $config_vars
	 */
	static function prepareServerSettingsContext(&$config_vars)
	{
		global $context, $modSettings;

		$context['config_vars'] = array();
		$defines = array(
			'boarddir',
			'sourcedir',
			'cachedir',
		);
		foreach ($config_vars as $identifier => $config_var)
		{
			if (!is_array($config_var) || !isset($config_var[1]))
				$context['config_vars'][] = $config_var;
			else
			{
				$varname = $config_var[0];
				global $$varname;

				// Set the subtext in case it's part of the label.
				// @todo Temporary. Preventing divs inside label tags.
				$divPos = strpos($config_var[1], '<div');
				$subtext = '';
				if ($divPos !== false)
				{
					$subtext = preg_replace('~</?div[^>]*>~', '', substr($config_var[1], $divPos));
					$config_var[1] = substr($config_var[1], 0, $divPos);
				}

				$context['config_vars'][$config_var[0]] = array(
					'label' => $config_var[1],
					'help' => isset($config_var[5]) ? $config_var[5] : '',
					'type' => $config_var[3],
					'size' => empty($config_var[4]) ? 0 : $config_var[4],
					'data' => isset($config_var[4]) && is_array($config_var[4]) && $config_var[3] != 'select' ? $config_var[4] : array(),
					'name' => $config_var[0],
					'value' => $config_var[2] == 'file' ? (in_array($varname, $defines) ? constant(strtoupper($varname)): htmlspecialchars($$varname)) : (isset($modSettings[$config_var[0]]) ? htmlspecialchars($modSettings[$config_var[0]]) : (in_array($config_var[3], array('int', 'float')) ? 0 : '')),
					'disabled' => !empty($context['settings_not_writable']) || !empty($config_var['disabled']),
					'invalid' => false,
					'subtext' => !empty($config_var['subtext']) ? $config_var['subtext'] : $subtext,
					'javascript' => '',
					'preinput' => !empty($config_var['preinput']) ? $config_var['preinput'] : '',
					'postinput' => !empty($config_var['postinput']) ? $config_var['postinput'] : '',
				);

				// If this is a select box handle any data.
				if (!empty($config_var[4]) && is_array($config_var[4]))
				{
					// If it's associative
					$config_values = array_values($config_var[4]);
					if (isset($config_values[0]) && is_array($config_values[0]))
						$context['config_vars'][$config_var[0]]['data'] = $config_var[4];
					else
					{
						foreach ($config_var[4] as $key => $item)
							$context['config_vars'][$config_var[0]]['data'][] = array($key, $item);
					}
				}
			}
		}

		// Two tokens because saving these settings requires both saveSettings and saveDBSettings
		createToken('admin-ssc');
		createToken('admin-dbsc');
	}

	/**
 	* Helper function, it sets up the context for database settings.
 	*
 	* @param array $config_vars
 	*/
	static function prepareDBSettingContext(&$config_vars)
	{
		global $txt, $helptxt, $context, $modSettings;

		loadLanguage('Help');

		$context['config_vars'] = array();
		$inlinePermissions = array();
		$bbcChoice = array();
		foreach ($config_vars as $config_var)
		{
			// HR?
			if (!is_array($config_var))
				$context['config_vars'][] = $config_var;
			else
			{
				// If it has no name it doesn't have any purpose!
				if (empty($config_var[1]))
					continue;

				// Special case for inline permissions
				if ($config_var[0] == 'permissions' && allowedTo('manage_permissions'))
					$inlinePermissions[] = $config_var[1];
				elseif ($config_var[0] == 'permissions')
					continue;

				// Are we showing the BBC selection box?
				if ($config_var[0] == 'bbc')
					$bbcChoice[] = $config_var[1];

				$context['config_vars'][$config_var[1]] = array(
					'label' => isset($config_var['text_label']) ? $config_var['text_label'] : (isset($txt[$config_var[1]]) ? $txt[$config_var[1]] : (isset($config_var[3]) && !is_array($config_var[3]) ? $config_var[3] : '')),
					'help' => isset($helptxt[$config_var[1]]) ? $config_var[1] : '',
					'type' => $config_var[0],
					'size' => !empty($config_var[2]) && !is_array($config_var[2]) ? $config_var[2] : (in_array($config_var[0], array('int', 'float')) ? 6 : 0),
					'data' => array(),
					'name' => $config_var[1],
					'value' => isset($modSettings[$config_var[1]]) ? ($config_var[0] == 'select' ? $modSettings[$config_var[1]] : htmlspecialchars($modSettings[$config_var[1]])) : (in_array($config_var[0], array('int', 'float')) ? 0 : ''),
					'disabled' => false,
					'invalid' => !empty($config_var['invalid']),
					'javascript' => '',
					'var_message' => !empty($config_var['message']) && isset($txt[$config_var['message']]) ? $txt[$config_var['message']] : '',
					'preinput' => isset($config_var['preinput']) ? $config_var['preinput'] : '',
					'postinput' => isset($config_var['postinput']) ? $config_var['postinput'] : '',
				);

				// If this is a select box handle any data.
				if (!empty($config_var[2]) && is_array($config_var[2]))
				{
					// If we allow multiple selections, we need to adjust a few things.
					if ($config_var[0] == 'select' && !empty($config_var['multiple']))
					{
						$context['config_vars'][$config_var[1]]['name'] .= '[]';
						$context['config_vars'][$config_var[1]]['value'] = unserialize($context['config_vars'][$config_var[1]]['value']);
					}

					// If it's associative
					if (isset($config_var[2][0]) && is_array($config_var[2][0]))
						$context['config_vars'][$config_var[1]]['data'] = $config_var[2];
					else
					{
						foreach ($config_var[2] as $key => $item)
							$context['config_vars'][$config_var[1]]['data'][] = array($key, $item);
					}
				}

				// Finally allow overrides - and some final cleanups.
				foreach ($config_var as $k => $v)
				{
					if (!is_numeric($k))
					{
						if (substr($k, 0, 2) == 'on')
							$context['config_vars'][$config_var[1]]['javascript'] .= ' ' . $k . '="' . $v . '"';
						else
							$context['config_vars'][$config_var[1]][$k] = $v;
					}

					// See if there are any other labels that might fit?
					if (isset($txt['setting_' . $config_var[1]]))
						$context['config_vars'][$config_var[1]]['label'] = $txt['setting_' . $config_var[1]];
					elseif (isset($txt['groups_' . $config_var[1]]))
						$context['config_vars'][$config_var[1]]['label'] = $txt['groups_' . $config_var[1]];
				}

				// Set the subtext in case it's part of the label.
				// @todo Temporary. Preventing divs inside label tags.
				$divPos = strpos($context['config_vars'][$config_var[1]]['label'], '<div');
				if ($divPos !== false)
				{
					$context['config_vars'][$config_var[1]]['subtext'] = preg_replace('~</?div[^>]*>~', '', substr($context['config_vars'][$config_var[1]]['label'], $divPos));
					$context['config_vars'][$config_var[1]]['label'] = substr($context['config_vars'][$config_var[1]]['label'], 0, $divPos);
				}
			}
		}

		// If we have inline permissions we need to prep them.
		if (!empty($inlinePermissions) && allowedTo('manage_permissions'))
		{
			// we'll need to initialize inline permissions sub-form
			require_once(SUBSDIR . '/Permission.subs.php');
			InlinePermissions_Form::init_inline_permissions($inlinePermissions, isset($context['permissions_excluded']) ? $context['permissions_excluded'] : array());
		}

		// What about any BBC selection boxes?
		if (!empty($bbcChoice))
		{
			// What are the options, eh?
			$temp = parse_bbc(false);
			$bbcTags = array();
			foreach ($temp as $tag)
				$bbcTags[] = $tag['tag'];

			$bbcTags = array_unique($bbcTags);
			$totalTags = count($bbcTags);

			// The number of columns we want to show the BBC tags in.
			$numColumns = isset($context['num_bbc_columns']) ? $context['num_bbc_columns'] : 3;

			// Start working out the context stuff.
			$context['bbc_columns'] = array();
			$tagsPerColumn = ceil($totalTags / $numColumns);

			$col = 0; $i = 0;
			foreach ($bbcTags as $tag)
			{
				if ($i % $tagsPerColumn == 0 && $i != 0)
					$col++;

				$context['bbc_columns'][$col][] = array(
					'tag' => $tag,
					// @todo  'tag_' . ?
					'show_help' => isset($helptxt[$tag]),
				);

				$i++;
			}

			// Now put whatever BBC options we may have into context too!
			$context['bbc_sections'] = array();
			foreach ($bbcChoice as $bbc)
			{
				$context['bbc_sections'][$bbc] = array(
					'title' => isset($txt['bbc_title_' . $bbc]) ? $txt['bbc_title_' . $bbc] : $txt['bbcTagsToUse_select'],
					'disabled' => empty($modSettings['bbc_disabled_' . $bbc]) ? array() : $modSettings['bbc_disabled_' . $bbc],
					'all_selected' => empty($modSettings['bbc_disabled_' . $bbc]),
				);
			}
		}

		call_integration_hook('integrate_prepare_db_settings', array(&$config_vars));
		createToken('admin-dbsc');
	}

	/**
 	* Helper function for saving database settings.
 	*
 	* @param array $config_vars
 	*/
	static function saveDBSettings(&$config_vars)
	{
		global $context;

		validateToken('admin-dbsc');

		$inlinePermissions = array();
		foreach ($config_vars as $var)
		{
			if (!isset($var[1]) || (!isset($_POST[$var[1]]) && $var[0] != 'check' && $var[0] != 'permissions' && ($var[0] != 'bbc' || !isset($_POST[$var[1] . '_enabledTags']))))
				continue;

			// Checkboxes!
			elseif ($var[0] == 'check')
				$setArray[$var[1]] = !empty($_POST[$var[1]]) ? '1' : '0';
			// Select boxes!
			elseif ($var[0] == 'select' && in_array($_POST[$var[1]], array_keys($var[2])))
				$setArray[$var[1]] = $_POST[$var[1]];
			elseif ($var[0] == 'select' && !empty($var['multiple']) && array_intersect($_POST[$var[1]], array_keys($var[2])) != array())
			{
				// For security purposes we validate this line by line.
				$options = array();
				foreach ($_POST[$var[1]] as $invar)
				if (in_array($invar, array_keys($var[2])))
				$options[] = $invar;

				$setArray[$var[1]] = serialize($options);
			}
			// Integers!
			elseif ($var[0] == 'int')
			$setArray[$var[1]] = (int) $_POST[$var[1]];
			// Floating point!
			elseif ($var[0] == 'float')
			$setArray[$var[1]] = (float) $_POST[$var[1]];
			// Text!
			elseif ($var[0] == 'text' || $var[0] == 'large_text')
			$setArray[$var[1]] = $_POST[$var[1]];
			// Passwords!
			elseif ($var[0] == 'password')
			{
				if (isset($_POST[$var[1]][1]) && $_POST[$var[1]][0] == $_POST[$var[1]][1])
				$setArray[$var[1]] = $_POST[$var[1]][0];
			}
			// BBC.
			elseif ($var[0] == 'bbc')
			{

				$bbcTags = array();
				foreach (parse_bbc(false) as $tag)
				$bbcTags[] = $tag['tag'];

				if (!isset($_POST[$var[1] . '_enabledTags']))
					$_POST[$var[1] . '_enabledTags'] = array();
				elseif (!is_array($_POST[$var[1] . '_enabledTags']))
					$_POST[$var[1] . '_enabledTags'] = array($_POST[$var[1] . '_enabledTags']);

				$setArray[$var[1]] = implode(',', array_diff($bbcTags, $_POST[$var[1] . '_enabledTags']));
			}
			// Permissions?
			elseif ($var[0] == 'permissions')
				$inlinePermissions[] = $var[1];
		}

		if (!empty($setArray))
			updateSettings($setArray);

		// If we have inline permissions we need to save them.
		if (!empty($inlinePermissions) && allowedTo('manage_permissions'))
		{
			// we'll need to save inline permissions
			require_once(SUBSDIR . '/Permission.subs.php');
			InlinePermissions_Form::save_inline_permissions($inlinePermissions);
		}
	}
}

/**
 * Show a collapsible box to set a specific permission.
 * The function is called by templates to show a list of permissions settings.
 * Calls the template function template_inline_permissions().
 *
 * @param string $permission
 */
function theme_inline_permissions($permission)
{
	global $context;

	$context['current_permission'] = $permission;
	$context['member_groups'] = $context[$permission];

	template_inline_permissions();
}