<?php

/**
 * This class handles display, edit, save, of forum settings.
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * This file contains code covered by:
 * copyright:    2011 Simple Machines (http://www.simplemachines.org)
 * license:    BSD, See included LICENSE.TXT for terms and conditions.
 *
 * @version 1.1 beta 3
 *
 *
 * Adding options to one of the setting screens isn't hard.
 * Call prepareDBSettingsContext;
 * The basic format for a checkbox is:
 *    array('check', 'nameInModSettingsAndSQL'),
 * And for a text box:
 *    array('text', 'nameInModSettingsAndSQL')
 * (NOTE: You have to add an entry for this at the bottom!)
 *
 * In the above examples, it will look for $txt['nameInModSettingsAndSQL'] as the description,
 * and $helptxt['nameInModSettingsAndSQL'] as the help popup description.
 *
 * Here's a quick explanation of how to add a new item:
 *
 * - A text input box.  For textual values.
 *     array('text', 'nameInModSettingsAndSQL', 'OptionalInputBoxWidth'),
 * - A text input box.  For numerical values.
 *     array('int', 'nameInModSettingsAndSQL', 'OptionalInputBoxWidth'),
 * - A text input box.  For floating point values.
 *     array('float', 'nameInModSettingsAndSQL', 'OptionalInputBoxWidth'),
 * - A large text input box. Used for textual values spanning multiple lines.
 *     array('large_text', 'nameInModSettingsAndSQL', 'OptionalNumberOfRows'),
 * - A check box.  Either one or zero. (boolean)
 *     array('check', 'nameInModSettingsAndSQL'),
 * - A selection box.  Used for the selection of something from a list.
 *     array('select', 'nameInModSettingsAndSQL', array('valueForSQL' => $txt['displayedValue'])),
 *     Note that just saying array('first', 'second') will put 0 in the SQL for 'first'.
 * - A password input box. Used for passwords, no less!
 *     array('password', 'nameInModSettingsAndSQL', 'OptionalInputBoxWidth'),
 * - A permission - for picking groups who have a permission.
 *     array('permissions', 'manage_groups'),
 * - A BBC selection box.
 *     array('bbc', 'sig_bbc'),
 *
 * For each option:
 *  - type (see above), variable name, size/possible values.
 *    OR make type '' for an empty string for a horizontal rule.
 *  - SET preinput - to put some HTML prior to the input box.
 *  - SET postinput - to put some HTML following the input box.
 *  - SET invalid - to mark the data as invalid.
 *  - PLUS you can override label and help parameters by forcing their keys in the array, for example:
 *    array('text', 'invalid label', 3, 'label' => 'Actual Label')
 */

/**
 * Settings Form class.
 * This class handles display, edit, save, of forum settings.
 * It is used by the various admin areas which set their own settings,
 * and it is available for addons administration screens.
 *
 */
class Settings_Form
{
	/**
	 * Configuration variables and values, for this settings form.
	 * @var array
	 */
	protected $_config_vars;

	/**
	 * Helper method, it sets up the context for the settings which will be saved
	 * to the settings.php file
	 *
	 * What it does:
	 * - The basic usage of the six numbered key fields are
	 * - array(0 ,1, 2, 3, 4, 5
	 *    0 variable name - the name of the saved variable
	 *    1 label - the text to show on the settings page
	 *    2 saveto - file or db, where to save the variable name - value pair
	 *    3 type - type of data to display int, float, text, check, select, password
	 *    4 size - false or field size, if type is select, this needs to be an array of
	 *                select options
	 *    5 help - '' or helptxt variable name
	 *  )
	 * - The following named keys are also permitted
	 *  'disabled' =>
	 *  'postinput' =>
	 *  'preinput' =>
	 *    'subtext' =>
	 */
	public function prepare_file()
	{
		global $context, $modSettings;

		$context['config_vars'] = array();
		$defines = array(
			'boarddir',
			'sourcedir',
			'cachedir',
		);

		$safe_strings = array(
			'mtitle',
			'mmessage',
			'mbname',
		);
		$new_settings = array();
		foreach ($this->_config_vars as $identifier => $config_var)
		{
			if (!is_array($config_var) || !isset($config_var[1]))
			{
				$context['config_vars'][] = $config_var;
			}
			else
			{
				$varname = $config_var[0];
				global ${$varname};

				// Rewrite the definition a bit.
				$new_setting = array(
					$config_var[3],
					$config_var[0],
					'text_label' => $config_var[1],
				);
				if (isset($config_var[4]))
				{
					$new_setting[2] = $config_var[4];
				}
				if (isset($config_var[5]))
				{
					$new_setting['helptext'] = $config_var[5];
				}

				// Special value needed from the settings file?
				if ($config_var[2] == 'file')
				{
					$value = in_array($varname, $defines) ? constant(strtoupper($varname)) : $$varname;
					if (in_array($varname, $safe_strings))
					{
						$new_setting['mask'] = 'nohtml';
					}
				}
				$new_settings[] = $new_setting;
				$modSettings[$config_var[0]] = $value;
			}
		}
		if (!empty($new_settings))
		{
			self::prepare_db($new_settings);
		}

		// Two tokens because saving these settings requires both save and save_db
		createToken('admin-ssc');
	}

	/**
	 * Helper method, it sets up the context for database settings.
	 *
	 * @param mixed[] $config_vars
	 */
	public static function prepare_db(&$config_vars)
	{
		global $txt, $helptxt, $context, $modSettings;
		static $known_rules = null;

		if ($known_rules === null)
		{
			$known_rules = array(
				'nohtml' => 'htmlspecialchars_decode[' . ENT_NOQUOTES . ']',
			);
		}

		loadLanguage('Help');

		$context['config_vars'] = array();
		$inlinePermissions = array();
		$bbcChoice = array();
		foreach ($config_vars as $config_var)
		{
			// HR?
			if (!is_array($config_var))
			{
				$context['config_vars'][] = $config_var;
			}
			else
			{
				// If it has no name it doesn't have any purpose!
				if (empty($config_var[1]))
				{
					continue;
				}

				// Special case for inline permissions
				if ($config_var[0] == 'permissions' && allowedTo('manage_permissions'))
				{
					$inlinePermissions[] = $config_var;
				}
				elseif ($config_var[0] == 'permissions')
				{
					continue;
				}

				// Are we showing the BBC selection box?
				if ($config_var[0] == 'bbc')
				{
					$bbcChoice[] = $config_var[1];
				}

				$context['config_vars'][$config_var[1]] = array(
					'label' => isset($config_var['text_label']) ? $config_var['text_label'] : (isset($txt[$config_var[1]]) ? $txt[$config_var[1]] : (isset($config_var[3]) && !is_array($config_var[3]) ? $config_var[3] : '')),
					'help' => isset($config_var['helptext']) ? $config_var['helptext'] : (isset($helptxt[$config_var[1]]) ? $config_var[1] : ''),
					'type' => $config_var[0],
					'size' => !empty($config_var[2]) && !is_array($config_var[2]) ? $config_var[2] : (in_array($config_var[0], array('int', 'float')) ? 6 : 0),
					'data' => array(),
					'name' => $config_var[1],
					'value' => isset($modSettings[$config_var[1]]) ? ($config_var[0] == 'select' ? $modSettings[$config_var[1]] : htmlspecialchars($modSettings[$config_var[1]], ENT_COMPAT, 'UTF-8')) : (in_array($config_var[0], array('int', 'float')) ? 0 : ''),
					'disabled' => false,
					'invalid' => !empty($config_var['invalid']),
					'javascript' => '',
					'var_message' => !empty($config_var['message']) && isset($txt[$config_var['message']]) ? $txt[$config_var['message']] : '',
					'preinput' => isset($config_var['preinput']) ? $config_var['preinput'] : '',
					'postinput' => isset($config_var['postinput']) ? $config_var['postinput'] : '',
					'icon' => isset($config_var['icon']) ? $config_var['icon'] : '',
				);

				// If this is a select box handle any data.
				if (!empty($config_var[2]) && is_array($config_var[2]))
				{
					// If we allow multiple selections, we need to adjust a few things.
					if ($config_var[0] == 'select' && !empty($config_var['multiple']))
					{
						$context['config_vars'][$config_var[1]]['name'] .= '[]';
						$context['config_vars'][$config_var[1]]['value'] = !empty($context['config_vars'][$config_var[1]]['value']) ? Util::unserialize($context['config_vars'][$config_var[1]]['value']) : array();
					}

					// If it's associative
					if (isset($config_var[2][0]) && is_array($config_var[2][0]))
					{
						$context['config_vars'][$config_var[1]]['data'] = $config_var[2];
					}
					else
					{
						foreach ($config_var[2] as $key => $item)
						{
							$context['config_vars'][$config_var[1]]['data'][] = array($key, $item);
						}
					}
				}

				// Revert masks if necessary
				if (isset($config_var['mask']))
				{
					$rules = array();

					if (!is_array($config_var['mask']))
					{
						$config_var['mask'] = array($config_var['mask']);
					}
					foreach ($config_var['mask'] as $key => $mask)
					{
						if (isset($known_rules[$mask]))
						{
							$rules[$config_var[1]][] = $known_rules[$mask];
						}
						elseif ($key == 'custom' && isset($mask['revert']))
						{
							$rules[$config_var[1]][] = $mask['revert'];
						}
					}

					if (!empty($rules))
					{
						$rules[$config_var[1]] = implode('|', $rules[$config_var[1]]);

						$validator = new Data_Validator();
						$validator->sanitation_rules($rules);
						$validator->validate(array($config_var[1] => $context['config_vars'][$config_var[1]]['value']));

						$context['config_vars'][$config_var[1]]['value'] = $validator->{$config_var[1]};
					}
				}

				// Finally allow overrides - and some final cleanups.
				self::allowOverrides($config_var);
			}
		}

		// If we have inline permissions we need to prep them.
		self::init_inline_permissions($inlinePermissions, isset($context['permissions_excluded']) ? $context['permissions_excluded'] : array());

		// What about any BBC selection boxes?
		self::initBbcChoices($bbcChoice);

		call_integration_hook('integrate_prepare_db_settings', array(&$config_vars));
		createToken('admin-dbsc');
	}

	/**
	 * Initialize inline permissions settings.
	 *
	 * @param string[] $permissions
	 * @param int[]    $excluded_groups = array()
	 */
	private static function init_inline_permissions($permissions, $excluded_groups = array())
	{
		global $context, $modSettings;

		if (empty($permissions))
		{
			return;
		}

		$permissionsForm = new Inline_Permissions_Form;
		$permissionsForm->setExcludedGroups($excluded_groups);
		$permissionsForm->setPermissions($permissions);

		return $permissionsForm->init();
	}

	/**
	 * @param mixed[] $config_var
	 */
	private static function allowOverrides($config_var)
	{
		global $context, $modSettings;

		foreach ($config_var as $k => $v)
		{
			if (!is_numeric($k))
			{
				if (substr($k, 0, 2) == 'on')
				{
					$context['config_vars'][$config_var[1]]['javascript'] .= ' ' . $k . '="' . $v . '"';
				}
				else
				{
					$context['config_vars'][$config_var[1]][$k] = $v;
				}
			}

			// See if there are any other labels that might fit?
			if (isset($txt['setting_' . $config_var[1]]))
			{
				$context['config_vars'][$config_var[1]]['label'] = $txt['setting_' . $config_var[1]];
			}
			elseif (isset($txt['groups_' . $config_var[1]]))
			{
				$context['config_vars'][$config_var[1]]['label'] = $txt['groups_' . $config_var[1]];
			}
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

	/**
	 * Initialize a list of available BB codes.
	 *
	 * @param string[] $bbcChoice
	 */
	private static function initBbcChoices($bbcChoice)
	{
		global $txt, $helptxt, $context, $modSettings;

		if (empty($bbcChoice))
		{
			return;
		}

		// What are the options, eh?
		$codes = \BBC\ParserWrapper::getInstance()->getCodes();
		$bbcTags = $codes->getTags();

		$bbcTags = array_unique($bbcTags);
		$totalTags = count($bbcTags);

		// The number of columns we want to show the BBC tags in.
		$numColumns = isset($context['num_bbc_columns']) ? $context['num_bbc_columns'] : 3;

		// Start working out the context stuff.
		$context['bbc_columns'] = array();
		$tagsPerColumn = ceil($totalTags / $numColumns);

		$col = 0;
		$i = 0;
		foreach ($bbcTags as $tag)
		{
			if ($i % $tagsPerColumn == 0 && $i != 0)
			{
				$col++;
			}

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

	/**
	 * This method saves the settings.
	 *
	 * It will put them in Settings.php or in the settings table.
	 *
	 * What it does:
	 * - Used to save those settings set from ?action=admin;area=serversettings.
	 * - Requires the admin_forum permission.
	 * - Contains arrays of the types of data to save into Settings.php.
	 */
	public function save()
	{
		validateToken('admin-ssc');

		// Fix the darn stupid cookiename! (more may not be allowed, but these for sure!)
		if (isset($_POST['cookiename']))
		{
			$_POST['cookiename'] = preg_replace('~[,;\s\.$]+~u', '', $_POST['cookiename']);
		}

		// Fix the forum's URL if necessary.
		if (isset($_POST['boardurl']))
		{
			if (substr($_POST['boardurl'], -10) == '/index.php')
			{
				$_POST['boardurl'] = substr($_POST['boardurl'], 0, -10);
			}
			elseif (substr($_POST['boardurl'], -1) == '/')
			{
				$_POST['boardurl'] = substr($_POST['boardurl'], 0, -1);
			}

			$_POST['boardurl'] = addProtocol($_POST['boardurl'], array('http://', 'https://', 'file://'));
		}

		// Any passwords?
		$config_passwords = array(
			'db_passwd',
			'ssi_db_passwd',
			'cache_password',
		);

		// All the strings to write.
		$config_strs = array(
			'mtitle',
			'mmessage',
			'language',
			'mbname',
			'boardurl',
			'cookiename',
			'webmaster_email',
			'db_name',
			'db_user',
			'db_server',
			'db_prefix',
			'ssi_db_user',
			'cache_accelerator',
			'cache_memcached',
			'cache_uid',
		);

		$safe_strings = array(
			'mtitle',
			'mmessage',
			'mbname',
		);

		// All the numeric variables.
		$config_ints = array(
			'cache_enable',
		);

		// All the checkboxes.
		$config_bools = array(
			'db_persist',
			'db_error_send',
			'maintenance',
		);

		// Now sort everything into a big array, and figure out arrays and etc.
		$new_settings = array();
		foreach ($config_passwords as $config_var)
		{
			if (isset($_POST[$config_var][1]) && $_POST[$config_var][0] == $_POST[$config_var][1])
			{
				$new_settings[$config_var] = '\'' . addcslashes($_POST[$config_var][0], '\'\\') . '\'';
			}
		}

		foreach ($config_strs as $config_var)
		{
			if (isset($_POST[$config_var]))
			{
				if (in_array($config_var, $safe_strings))
				{
					$new_settings[$config_var] = '\'' . addcslashes(Util::htmlspecialchars($_POST[$config_var], ENT_QUOTES), '\'\\') . '\'';
				}
				else
				{
					$new_settings[$config_var] = '\'' . addcslashes($_POST[$config_var], '\'\\') . '\'';
				}
			}
		}

		foreach ($config_ints as $config_var)
		{
			if (isset($_POST[$config_var]))
			{
				$new_settings[$config_var] = (int) $_POST[$config_var];
			}
		}

		foreach ($config_bools as $key)
		{
			// Check boxes need to be part of this settings form
			if ($this->_array_value_exists__recursive($key, $this->settings()))
			{
				if (!empty($_POST[$key]))
				{
					$new_settings[$key] = '1';
				}
				else
				{
					$new_settings[$key] = '0';
				}
			}
		}

		// Save the relevant settings in the Settings.php file.
		require_once(SUBSDIR . '/Admin.subs.php');
		Settings_Form::save_file($new_settings);

		// Now loop through the remaining (database-based) settings.
		$new_settings = array();
		foreach ($this->_config_vars as $config_var)
		{
			// We just saved the file-based settings, so skip their definitions.
			if (!is_array($config_var) || $config_var[2] == 'file')
			{
				continue;
			}

			// Rewrite the definition a bit.
			$new_settings[] = array($config_var[3], $config_var[0]);
		}

		// Save the new database-based settings, if any.
		if (!empty($new_settings))
		{
			Settings_Form::save_db($new_settings);
		}
	}

	/**
	 * Helper method for saving database settings.
	 *
	 * @param mixed[]        $config_vars
	 * @param mixed[]|object $post_object
	 */
	public static function save_db(&$config_vars, $post_object = null)
	{
		static $known_rules = null;

		// Just look away if you have a weak stomach
		if ($post_object !== null && is_object($post_object))
		{
			$_POST = array_replace($_POST, (array) $post_object);
		}

		if ($known_rules === null)
		{
			$known_rules = array(
				'nohtml' => 'Util::htmlspecialchars[' . ENT_QUOTES . ']',
				'email' => 'valid_email',
				'url' => 'valid_url',
			);
		}

		validateToken('admin-dbsc');

		$inlinePermissions = array();
		foreach ($config_vars as $var)
		{
			if (!isset($var[1]) || (!isset($_POST[$var[1]]) && $var[0] != 'check' && $var[0] != 'permissions' && ($var[0] != 'bbc' || !isset($_POST[$var[1] . '_enabledTags']))))
			{
				continue;
			}

			// Checkboxes!
			elseif ($var[0] == 'check')
			{
				$setArray[$var[1]] = !empty($_POST[$var[1]]) ? '1' : '0';
			}
			// Select boxes!
			elseif ($var[0] == 'select' && in_array($_POST[$var[1]], array_keys($var[2])))
			{
				$setArray[$var[1]] = $_POST[$var[1]];
			}
			elseif ($var[0] == 'select' && !empty($var['multiple']) && array_intersect($_POST[$var[1]], array_keys($var[2])) != array())
			{
				// For security purposes we validate this line by line.
				$options = array();
				foreach ($_POST[$var[1]] as $invar)
				{
					if (in_array($invar, array_keys($var[2])))
					{
						$options[] = $invar;
					}
				}

				$setArray[$var[1]] = serialize($options);
			}
			// Integers!
			elseif ($var[0] == 'int')
			{
				$setArray[$var[1]] = (int) $_POST[$var[1]];
			}
			// Floating point!
			elseif ($var[0] == 'float')
			{
				$setArray[$var[1]] = (float) $_POST[$var[1]];
			}
			// Text!
			elseif ($var[0] == 'text' || $var[0] == 'color' || $var[0] == 'large_text')
			{
				if (isset($var['mask']))
				{
					$rules = array();

					if (!is_array($var['mask']))
					{
						$var['mask'] = array($var['mask']);
					}
					foreach ($var['mask'] as $key => $mask)
					{
						if (isset($known_rules[$mask]))
						{
							$rules[$var[1]][] = $known_rules[$mask];
						}
						elseif ($key == 'custom' && isset($mask['apply']))
						{
							$rules[$var[1]][] = $mask['apply'];
						}
					}

					if (!empty($rules))
					{
						$rules[$var[1]] = implode('|', $rules[$var[1]]);

						$validator = new Data_Validator();
						$validator->sanitation_rules($rules);
						$validator->validate($_POST);

						$setArray[$var[1]] = $validator->{$var[1]};
					}
				}
				else
				{
					$setArray[$var[1]] = $_POST[$var[1]];
				}
			}
			// Passwords!
			elseif ($var[0] == 'password')
			{
				if (isset($_POST[$var[1]][1]) && $_POST[$var[1]][0] == $_POST[$var[1]][1])
				{
					$setArray[$var[1]] = $_POST[$var[1]][0];
				}
			}
			// BBC.
			elseif ($var[0] == 'bbc')
			{
				$codes = \BBC\ParserWrapper::getInstance()->getCodes();
				$bbcTags = $codes->getTags();

				if (!isset($_POST[$var[1] . '_enabledTags']))
				{
					$_POST[$var[1] . '_enabledTags'] = array();
				}
				elseif (!is_array($_POST[$var[1] . '_enabledTags']))
				{
					$_POST[$var[1] . '_enabledTags'] = array($_POST[$var[1] . '_enabledTags']);
				}

				$setArray[$var[1]] = implode(',', array_diff($bbcTags, $_POST[$var[1] . '_enabledTags']));
			}
			// Permissions?
			elseif ($var[0] == 'permissions')
			{
				$inlinePermissions[] = $var;
			}
		}

		if (!empty($setArray))
		{
			updateSettings($setArray);
		}

		// If we have inline permissions we need to save them.
		if (!empty($inlinePermissions) && allowedTo('manage_permissions'))
		{
			// we'll need to save inline permissions
			require_once(SUBSDIR . '/Permission.subs.php');
			InlinePermissions_Form::save_inline_permissions($inlinePermissions);
		}
	}

	/**
	 * Update the Settings.php file.
	 *
	 * Typically this method is used from admin screens, just like this entire class.
	 * They're also available for addons and integrations.
	 *
	 * What it does:
	 * - updates the Settings.php file with the changes supplied in config_vars.
	 * - expects config_vars to be an associative array, with the keys as the
	 *   variable names in Settings.php, and the values the variable values.
	 * - does not escape or quote values.
	 * - preserves case, formatting, and additional options in file.
	 * - writes nothing if the resulting file would be less than 10 lines
	 *   in length (sanity check for read lock.)
	 * - check for changes to db_last_error and passes those off to a separate handler
	 * - attempts to create a backup file and will use it should the writing of the
	 *   new settings file fail
	 *
	 * @param mixed[] $config_vars
	 */
	public static function save_file($config_vars)
	{
		global $context;

		// Some older code is trying to updating the db_last_error,
		// then don't mess around with Settings.php
		if (count($config_vars) === 1 && isset($config_vars['db_last_error']))
		{
			require_once(SUBSDIR . '/Admin.subs.php');

			updateDbLastError($config_vars['db_last_error']);

			return;
		}

		// When was Settings.php last changed?
		$last_settings_change = filemtime(BOARDDIR . '/Settings.php');

		// Load the settings file.
		$settingsArray = trim(file_get_contents(BOARDDIR . '/Settings.php'));

		// Break it up based on \r or \n, and then clean out extra characters.
		if (strpos($settingsArray, "\n") !== false)
		{
			$settingsArray = explode("\n", $settingsArray);
		}
		elseif (strpos($settingsArray, "\r") !== false)
		{
			$settingsArray = explode("\r", $settingsArray);
		}
		else
		{
			return;
		}

		// Presumably, the file has to have stuff in it for this function to be called :P.
		if (count($settingsArray) < 10)
		{
			return;
		}

		// remove any /r's that made there way in here
		foreach ($settingsArray as $k => $dummy)
		{
			$settingsArray[$k] = strtr($dummy, array("\r" => '')) . "\n";
		}

		// go line by line and see whats changing
		for ($i = 0, $n = count($settingsArray); $i < $n; $i++)
		{
			// Don't trim or bother with it if it's not a variable.
			if (substr($settingsArray[$i], 0, 1) != '$')
			{
				continue;
			}

			$settingsArray[$i] = trim($settingsArray[$i]) . "\n";

			// Look through the variables to set....
			foreach ($config_vars as $var => $val)
			{
				// be sure someone is not updating db_last_error this with a group
				if ($var === 'db_last_error')
				{
					updateDbLastError($val);
					unset($config_vars[$var]);
				}
				elseif (strncasecmp($settingsArray[$i], '$' . $var, 1 + strlen($var)) == 0)
				{
					$comment = strstr(substr(un_htmlspecialchars($settingsArray[$i]), strpos(un_htmlspecialchars($settingsArray[$i]), ';')), '#');
					$settingsArray[$i] = '$' . $var . ' = ' . $val . ';' . ($comment == '' ? '' : "\t\t" . rtrim($comment)) . "\n";

					// This one's been 'used', so to speak.
					unset($config_vars[$var]);
				}
			}

			// End of the file ... maybe
			if (substr(trim($settingsArray[$i]), 0, 2) == '?' . '>')
			{
				$end = $i;
			}
		}

		// This should never happen, but apparently it is happening.
		if (empty($end) || $end < 10)
		{
			$end = count($settingsArray) - 1;
		}

		// Still more variables to go?  Then lets add them at the end.
		if (!empty($config_vars))
		{
			if (trim($settingsArray[$end]) == '?' . '>')
			{
				$settingsArray[$end++] = '';
			}
			else
			{
				$end++;
			}

			// Add in any newly defined vars that were passed
			foreach ($config_vars as $var => $val)
			{
				$settingsArray[$end++] = '$' . $var . ' = ' . $val . ';' . "\n";
			}
		}
		else
		{
			$settingsArray[$end] = trim($settingsArray[$end]);
		}

		// Sanity error checking: the file needs to be at least 12 lines.
		if (count($settingsArray) < 12)
		{
			return;
		}

		// Try to avoid a few pitfalls:
		//  - like a possible race condition,
		//  - or a failure to write at low diskspace
		//
		// Check before you act: if cache is enabled, we can do a simple write test
		// to validate that we even write things on this filesystem.
		if ((!defined('CACHEDIR') || !file_exists(CACHEDIR)) && file_exists(BOARDDIR . '/cache'))
		{
			$tmp_cache = BOARDDIR . '/cache';
		}
		else
		{
			$tmp_cache = CACHEDIR;
		}

		$test_fp = @fopen($tmp_cache . '/settings_update.tmp', 'w+');
		if ($test_fp)
		{
			fclose($test_fp);
			$written_bytes = file_put_contents($tmp_cache . '/settings_update.tmp', 'test', LOCK_EX);
			@unlink($tmp_cache . '/settings_update.tmp');

			if ($written_bytes !== 4)
			{
				// Oops. Low disk space, perhaps. Don't mess with Settings.php then.
				// No means no. :P
				return;
			}
		}

		// Protect me from what I want! :P
		clearstatcache();
		if (filemtime(BOARDDIR . '/Settings.php') === $last_settings_change)
		{
			// Save the old before we do anything
			$settings_backup_fail = !@is_writable(BOARDDIR . '/Settings_bak.php') || !@copy(BOARDDIR . '/Settings.php', BOARDDIR . '/Settings_bak.php');
			$settings_backup_fail = !$settings_backup_fail ? (!file_exists(BOARDDIR . '/Settings_bak.php') || filesize(BOARDDIR . '/Settings_bak.php') === 0) : $settings_backup_fail;

			// Write out the new
			$write_settings = implode('', $settingsArray);
			$written_bytes = file_put_contents(BOARDDIR . '/Settings.php', $write_settings, LOCK_EX);

			// Survey says ...
			if ($written_bytes !== strlen($write_settings) && !$settings_backup_fail)
			{
				// Well this is not good at all, lets see if we can save this
				$context['settings_message'] = 'settings_error';

				if (file_exists(BOARDDIR . '/Settings_bak.php'))
				{
					@copy(BOARDDIR . '/Settings_bak.php', BOARDDIR . '/Settings.php');
				}
			}
			// And ensure we are going to read the correct file next time
			if (function_exists('opcache_invalidate'))
			{
				opcache_invalidate(BOARDDIR . '/Settings.php');
			}
		}
	}

	/**
	 * Method which retrieves or sets new configuration variables.
	 *
	 * If the $config_vars parameter is sent, the method tries to update
	 * the internal configuration of the Settings_Form instance.
	 *
	 * If the $config_vars parameter is not sent (is null), the method
	 * simply returns the current configuration set.
	 *
	 *  The array is formed of:
	 *  - either, variable name, description, type (constant), size/possible values, helptext.
	 *  - either, an empty string for a horizontal rule.
	 *  - or, a string for a titled section.
	 *
	 * @param mixed[]|null $config_vars = null array of config vars, if null the method returns the current
	 *                                  configuration
	 */
	public function settings($config_vars = null)
	{
		if (is_null($config_vars))
		{
			// Simply return the config vars we have
			return $this->_config_vars;
		}
		else
		{
			// We got presents :P
			$this->_config_vars = is_array($config_vars) ? $config_vars : array($config_vars);

			return $this->_config_vars;
		}
	}

	/**
	 * Recursively checks if a value exists in an array
	 *
	 * @param string  $needle
	 * @param mixed[] $haystack
	 *
	 * @return boolean
	 */
	private function _array_value_exists__recursive($needle, $haystack)
	{
		foreach ($haystack as $item)
		{
			if ($item == $needle || (is_array($item) && $this->_array_value_exists__recursive($needle, $item)))
			{
				return true;
			}
		}

		return false;
	}
}
