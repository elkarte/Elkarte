<?php

/**
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 1.0 Alpha
 *
 */

if (!defined('ELKARTE'))
	die('No access...');

/**
 * ManageAddonSettings controller handles administration settings added
 * in the common area for all addons in admin panel.
 * Some addons will define their own areas, but for simple cases,
 * when you have only a setting or two, this area will allow you
 * to hook into it seamlessly, and your additions will be sent
 * to admin search and otherwise benefit from admin areas security,
 * checks and display.
 */
class ManageAddonSettings_Controller
{
	/**
	 * General addon settings form.
	 * @var Settings_Form
	 */
	protected $_addonSettings;

	/**
	 * This my friend, is for all the mod authors out there.
	 */
	function action_index()
	{
		global $context, $txt, $scripturl, $modSettings, $settings;

		$context['page_title'] = $txt['admin_modifications'];

		$subActions = array(
			'general' => array($this, 'action_addonSettings_display'),
			'hooks' => array($this, 'action_hooks'),
			// Mod authors, once again, if you have a whole section to add do it AFTER this line, and keep a comma at the end.
		);

		// Make it easier for mods to add new areas.
		call_integration_hook('integrate_modify_modifications', array(&$subActions));

		$subAction = $_REQUEST['sa'];

		// Set up action/subaction stuff.
		$action = new Action();
		$action->initialize($subActions);

		// @FIXME
		// $this->loadGeneralSettingParameters($subActions, 'general');
		isAllowedTo('admin_forum');

		loadLanguage('Help');
		loadLanguage('ManageSettings');

		$context['sub_template'] = 'show_settings';
		$context['sub_action'] = $subAction;
		// END $this->loadGeneralSettingParameters();

		// Load up all the tabs...
		$context[$context['admin_menu_name']]['tab_data'] = array(
			'title' => $txt['admin_modifications'],
			'help' => 'modsettings',
			'description' => $txt['modification_settings_desc'],
			'tabs' => array(
				'general' => array(
				),
				'hooks' => array(
				),
			),
		);

		// Call the right function for this sub-action.
		$action->dispatch($subAction);
	}

	/**
	 * If you have a general mod setting to add stick it here.
	 */
	function action_addonSettings_display()
	{
		global $txt, $scripturl, $context, $settings, $sc, $modSettings;

		// initialize the form
		$this->_initAddonSettingsForm();

		$config_vars = $this->_addonSettings->settings();

		// Saving?
		if (isset($_GET['save']))
		{
			checkSession();

			$save_vars = $config_vars;

			call_integration_hook('integrate_save_general_mod_settings', array(&$save_vars));

			Settings_Form::save_db($save_vars);

			redirectexit('action=admin;area=modsettings;sa=general');
		}

		Settings_Form::prepare_db($config_vars);
	}

	/**
	 * Initialize the customSettings form with any custom admin settings for or from add-ons.
	 */
	function _initAddonSettingsForm()
	{
		global $context;

		// We're working with them settings.
		require_once(SUBSDIR . '/Settings.class.php');

		// instantiate the form
		$this->_addonSettings = new Settings_Form();

		// initialize it with our existing settings. If any.
		$config_vars = array();

		// Add new settings with a nice hook.
		call_integration_hook('integrate_general_mod_settings', array(&$config_vars));

		if (empty($config_vars))
		{
			$context['settings_save_dont_show'] = true;
			$context['settings_message'] = '<div class="centertext">' . $txt['modification_no_misc_settings'] . '</div>';
		}

		$context['post_url'] = $scripturl . '?action=admin;area=modsettings;save;sa=general';
		$context['settings_title'] = $txt['mods_cat_modifications_misc'];

		return $this->_addonSettings->settings($config_vars);
	}

	/**
	 * Retrieve any custom admin settings for or from add-ons.
	 * This method is used by admin search.
	 * @deprecated
	 */
	function settings()
	{
		$config_vars = array();

		// Add new settings with a nice hook.
		call_integration_hook('integrate_general_mod_settings', array(&$config_vars));

		return $config_vars;
	}

	/**
	 * Generates a list of integration hooks for display
	 * Accessed through ?action=admin;area=modsettings;sa=hooks;
	 * Allows for removal or disabing of selected hooks
	 */
	function action_hooks()
	{
		global $scripturl, $context, $txt, $modSettings, $settings;

		$context['filter_url'] = '';
		$context['current_filter'] = '';
		$currentHooks = get_integration_hooks();
		if (isset($_GET['filter']) && in_array($_GET['filter'], array_keys($currentHooks)))
		{
			$context['filter_url'] = ';filter=' . $_GET['filter'];
			$context['current_filter'] = $_GET['filter'];
		}

		if (!empty($modSettings['handlinghooks_enabled']))
		{
			if (!empty($_REQUEST['do']) && isset($_REQUEST['hook']) && isset($_REQUEST['function']))
			{
				checkSession('request');
				validateToken('admin-hook', 'request');

				if ($_REQUEST['do'] == 'remove')
					remove_integration_function($_REQUEST['hook'], urldecode($_REQUEST['function']));
				else
				{
					if ($_REQUEST['do'] == 'disable')
					{
						// It's a hack I know...but I'm way too lazy!!!
						$function_remove = $_REQUEST['function'];
						$function_add = $_REQUEST['function'] . ']';
					}
					else
					{
						$function_remove = $_REQUEST['function'] . ']';
						$function_add = $_REQUEST['function'];
					}
					$file = !empty($_REQUEST['includedfile']) ? urldecode($_REQUEST['includedfile']) : '';

					remove_integration_function($_REQUEST['hook'], $function_remove, $file);
					add_integration_function($_REQUEST['hook'], $function_add, $file);

					redirectexit('action=admin;area=modsettings;sa=hooks' . $context['filter_url']);
				}
			}
		}

		$list_options = array(
			'id' => 'list_integration_hooks',
			'title' => $txt['hooks_title_list'],
			'items_per_page' => 20,
			'base_href' => $scripturl . '?action=admin;area=modsettings;sa=hooks' . $context['filter_url'] . ';' . $context['session_var'] . '=' . $context['session_id'],
			'default_sort_col' => 'hook_name',
			'get_items' => array(
				'function' => 'get_integration_hooks_data',
			),
			'get_count' => array(
				'function' => 'get_integration_hooks_count',
			),
			'no_items_label' => $txt['hooks_no_hooks'],
			'columns' => array(
				'hook_name' => array(
					'header' => array(
						'value' => $txt['hooks_field_hook_name'],
					),
					'data' => array(
						'db' => 'hook_name',
					),
					'sort' =>  array(
						'default' => 'hook_name',
						'reverse' => 'hook_name DESC',
					),
				),
				'function_name' => array(
					'header' => array(
						'value' => $txt['hooks_field_function_name'],
					),
					'data' => array(
						'function' => create_function('$data', '
							global $txt;

							if (!empty($data[\'included_file\']))
								return $txt[\'hooks_field_function\'] . \': \' . $data[\'real_function\'] . \'<br />\' . $txt[\'hooks_field_included_file\'] . \': \' . $data[\'included_file\'];
							else
								return $data[\'real_function\'];
						'),
					),
					'sort' =>  array(
						'default' => 'function_name',
						'reverse' => 'function_name DESC',
					),
				),
				'file_name' => array(
					'header' => array(
						'value' => $txt['hooks_field_file_name'],
					),
					'data' => array(
						'db' => 'file_name',
					),
					'sort' =>  array(
						'default' => 'file_name',
						'reverse' => 'file_name DESC',
					),
				),
				'status' => array(
					'header' => array(
						'value' => $txt['hooks_field_hook_exists'],
						'style' => 'width:3%;',
					),
					'data' => array(
						'function' => create_function('$data', '
							global $txt, $settings, $scripturl, $context;

							$change_status = array(\'before\' => \'\', \'after\' => \'\');
							if ($data[\'can_be_disabled\'] && $data[\'status\'] != \'deny\')
							{
								$change_status[\'before\'] = \'<a href="\' . $scripturl . \'?action=admin;area=modsettings;sa=hooks;do=\' . ($data[\'enabled\'] ? \'disable\' : \'enable\') . \';hook=\' . $data[\'hook_name\'] . \';function=\' . $data[\'real_function\'] . (!empty($data[\'included_file\']) ? \';includedfile=\' . urlencode($data[\'included_file\']) : \'\') . $context[\'filter_url\'] . \';\' . $context[\'admin-hook_token_var\'] . \'=\' . $context[\'admin-hook_token\'] . \';\' . $context[\'session_var\'] . \'=\' . $context[\'session_id\'] . \'" onclick="return confirm(\' . javaScriptEscape($txt[\'quickmod_confirm\']) . \');">\';
								$change_status[\'after\'] = \'</a>\';
							}
							return $change_status[\'before\'] . \'<img src="\' . $settings[\'images_url\'] . \'/admin/post_moderation_\' . $data[\'status\'] . \'.png" alt="\' . $data[\'img_text\'] . \'" title="\' . $data[\'img_text\'] . \'" />\' . $change_status[\'after\'];
						'),
						'class' => 'centertext',
					),
					'sort' =>  array(
						'default' => 'status',
						'reverse' => 'status DESC',
					),
				),
			),
			'additional_rows' => array(
				array(
					'position' => 'after_title',
					'value' => $txt['hooks_disable_instructions'] . '<br />
						' . $txt['hooks_disable_legend'] . ':
										<ul style="list-style: none;">
						<li><img src="' . $settings['images_url'] . '/admin/post_moderation_allow.png" alt="' . $txt['hooks_active'] . '" title="' . $txt['hooks_active'] . '" /> ' . $txt['hooks_disable_legend_exists'] . '</li>
						<li><img src="' . $settings['images_url'] . '/admin/post_moderation_moderate.png" alt="' . $txt['hooks_disabled'] . '" title="' . $txt['hooks_disabled'] . '" /> ' . $txt['hooks_disable_legend_disabled'] . '</li>
						<li><img src="' . $settings['images_url'] . '/admin/post_moderation_deny.png" alt="' . $txt['hooks_missing'] . '" title="' . $txt['hooks_missing'] . '" /> ' . $txt['hooks_disable_legend_missing'] . '</li>
					</ul>'
				),
			),
		);

		if (!empty($modSettings['handlinghooks_enabled']))
		{
			createToken('admin-hook', 'request');

			$list_options['columns']['remove'] = array(
				'header' => array(
					'value' => $txt['hooks_button_remove'],
					'style' => 'width:3%',
				),
				'data' => array(
					'function' => create_function('$data', '
						global $txt, $settings, $scripturl, $context;

						if (!$data[\'hook_exists\'])
							return \'
							<a href="\' . $scripturl . \'?action=admin;area=modsettings;sa=hooks;do=remove;hook=\' . $data[\'hook_name\'] . \';function=\' . urlencode($data[\'function_name\']) . $context[\'filter_url\'] . \';\' . $context[\'admin-hook_token_var\'] . \'=\' . $context[\'admin-hook_token\'] . \';\' . $context[\'session_var\'] . \'=\' . $context[\'session_id\'] . \'" onclick="return confirm(\' . javaScriptEscape($txt[\'quickmod_confirm\']) . \');">
								<img src="\' . $settings[\'images_url\'] . \'/icons/quick_remove.png" alt="\' . $txt[\'hooks_button_remove\'] . \'" title="\' . $txt[\'hooks_button_remove\'] . \'" />
							</a>\';
					'),
					'class' => 'centertext',
				),
			);
			$list_options['form'] = array(
				'href' => $scripturl . '?action=admin;area=modsettings;sa=hooks' . $context['filter_url'] . ';' . $context['session_var'] . '=' . $context['session_id'],
				'name' => 'list_integration_hooks',
			);
		}


		require_once(SUBSDIR . '/List.subs.php');
		createList($list_options);

		$context['page_title'] = $txt['hooks_title_list'];
		$context['sub_template'] = 'show_list';
		$context['default_list'] = 'list_integration_hooks';
	}

	/**
	 * This function makes sure the requested subaction does exist,
	 *  if it doesn't, it sets a default action or.
	 *
	 * @param array $subActions = array() An array containing all possible subactions.
	 * @param string $defaultAction = '' the default action to be called if no valid subaction was found.
	 */
	function loadGeneralSettingParameters($subActions = array(), $defaultAction = '')
	{
		global $context, $txt;

		// You need to be an admin to edit settings!
		isAllowedTo('admin_forum');

		loadLanguage('Help');
		loadLanguage('ManageSettings');

		$context['sub_template'] = 'show_settings';

		// By default do the basic settings.
		$_REQUEST['sa'] = isset($_REQUEST['sa']) && isset($subActions[$_REQUEST['sa']]) ? $_REQUEST['sa'] : (!empty($defaultAction) ? $defaultAction : array_pop($temp = array_keys($subActions)));
		$context['sub_action'] = $_REQUEST['sa'];
	}
}

/**
 * Gets all of the files in a directory and its chidren directories
 *
 * @param type $dir_path
 * @return array
 */
function get_files_recursive($dir_path)
{
	$files = array();

	if ($dh = opendir($dir_path))
	{
		while (($file = readdir($dh)) !== false)
		{
			if ($file != '.' && $file != '..')
			{
				if (is_dir($dir_path . '/' . $file))
					$files = array_merge($files, get_files_recursive($dir_path . '/' . $file));
				else
					$files[] = array('dir' => $dir_path, 'name' => $file);
			}
		}
	}
	closedir($dh);

	return $files;
}

/**
 * Callback function for the integration hooks list (list_integration_hooks)
 * Gets all of the hooks in the system and their status
 * Would be better documented if Ema was not lazy
 *
 * @param type $start
 * @param type $per_page
 * @param type $sort
 * @return array
 */
function get_integration_hooks_data($start, $per_page, $sort)
{
	global $settings, $txt, $context, $scripturl, $modSettings;

	$hooks = $temp_hooks = get_integration_hooks();
	$hooks_data = $temp_data = $hook_status = array();

	$files = get_files_recursive(SOURCEDIR);
	if (!empty($files))
	{
		foreach ($files as $file)
		{
			if (is_file($file['dir'] . '/' . $file['name']) && substr($file['name'], -4) === '.php')
			{
				$fp = fopen($file['dir'] . '/' . $file['name'], 'rb');
				$fc = fread($fp, filesize($file['dir'] . '/' . $file['name']));
				fclose($fp);

				foreach ($temp_hooks as $hook => $functions)
				{
					foreach ($functions as $function_o)
					{
						$hook_name = str_replace(']', '', $function_o);
						if (strpos($hook_name, '::') !== false)
						{
							$function = explode('::', $hook_name);
							$function = $function[1];
						}
						else
							$function = $hook_name;
						$function = explode(':', $function);
						$function = $function[0];

						if (substr($hook, -8) === '_include')
						{
							$hook_status[$hook][$function]['exists'] = file_exists(strtr(trim($function), array('BOARDDIR' => BOARDDIR, 'SOURCEDIR' => SOURCEDIR, '$themedir' => $settings['theme_dir'])));
							// I need to know if there is at least one function called in this file.
							$temp_data['include'][basename($function)] = array('hook' => $hook, 'function' => $function);
							unset($temp_hooks[$hook][$function_o]);
						}
						elseif (strpos(str_replace(' (', '(', $fc), 'function ' . trim($function) . '(') !== false)
						{
							$hook_status[$hook][$hook_name]['exists'] = true;
							$hook_status[$hook][$hook_name]['in_file'] = $file['name'];
							// I want to remember all the functions called within this file (to check later if they are enabled or disabled and decide if the integrare_*_include of that file can be disabled too)
							$temp_data['function'][$file['name']][] = $function_o;
							unset($temp_hooks[$hook][$function_o]);
						}
					}
				}
			}
		}
	}

	$sort_types = array(
		'hook_name' => array('hook', SORT_ASC),
		'hook_name DESC' => array('hook', SORT_DESC),
		'function_name' => array('function', SORT_ASC),
		'function_name DESC' => array('function', SORT_DESC),
		'file_name' => array('file_name', SORT_ASC),
		'file_name DESC' => array('file_name', SORT_DESC),
		'status' => array('status', SORT_ASC),
		'status DESC' => array('status', SORT_DESC),
	);

	$sort_options = $sort_types[$sort];
	$sort = array();
	$hooks_filters = array();

	foreach ($hooks as $hook => $functions)
	{
		$hooks_filters[] = '<option ' . ($context['current_filter'] == $hook ? 'selected="selected" ' : '') . 'onclick="window.location = \'' . $scripturl . '?action=admin;area=modsettings;sa=hooks;filter=' . $hook . '\';">' . $hook . '</option>';
		foreach ($functions as $function)
		{
			$enabled = strstr($function, ']') === false;
			$function = str_replace(']', '', $function);

			// This is a not an include and the function is included in a certain file (if not it doesn't exists so don't care)
			if (substr($hook, -8) !== '_include' && isset($hook_status[$hook][$function]['in_file']))
			{
				$current_hook = isset($temp_data['include'][$hook_status[$hook][$function]['in_file']]) ? $temp_data['include'][$hook_status[$hook][$function]['in_file']] : '';
				$enabled = false;

				// Checking all the functions within this particular file
				// if any of them is enable then the file *must* be included and the integrate_*_include hook cannot be disabled
				foreach ($temp_data['function'][$hook_status[$hook][$function]['in_file']] as $func)
					$enabled = $enabled || strstr($func, ']') !== false;

				if (!$enabled &&  !empty($current_hook))
					$hook_status[$current_hook['hook']][$current_hook['function']]['enabled'] = true;
			}
		}
	}

	if (!empty($hooks_filters))
		addInlineJavascript('
			var hook_name_header = document.getElementById(\'header_list_integration_hooks_hook_name\');
			hook_name_header.innerHTML += ' . JavaScriptEscape('
				<select style="margin-left:15px;">
					<option>---</option>
					<option onclick="window.location = \'' . $scripturl . '?action=admin;area=modsettings;sa=hooks\';">' . $txt['hooks_reset_filter'] . '</option>' . implode('', $hooks_filters) . '
				</select>'). ';', true);

	$temp_data = array();
	$id = 0;

	foreach ($hooks as $hook => $functions)
	{
		if (empty($context['filter']) || (!empty($context['filter']) && $context['filter'] == $hook))
		{
			foreach ($functions as $function)
			{
				$enabled = strstr($function, ']') === false;
				$function = str_replace(']', '', $function);
				$hook_exists = !empty($hook_status[$hook][$function]['exists']);
				$file_name = isset($hook_status[$hook][$function]['in_file']) ? $hook_status[$hook][$function]['in_file'] : ((substr($hook, -8) === '_include') ? 'zzzzzzzzz' : 'zzzzzzzza');
				$sort[] = $$sort_options[0];

				if (strpos($function, '::') !== false)
				{
					$function = explode('::', $function);
					$function = $function[1];
				}
				$exploded = explode(':', $function);

				$temp_data[] = array(
					'id' => 'hookid_' . $id++,
					'hook_name' => $hook,
					'function_name' => $function,
					'real_function' => $exploded[0],
					'included_file' => isset($exploded[1]) ? strtr(trim($exploded[1]), array('BOARDDIR' => BOARDDIR, 'SOURCEDIR' => SOURCEDIR, '$themedir' => $settings['theme_dir'])) : '',
					'file_name' => (isset($hook_status[$hook][$function]['in_file']) ? $hook_status[$hook][$function]['in_file'] : ''),
					'hook_exists' => $hook_exists,
					'status' => $hook_exists ? ($enabled ? 'allow' : 'moderate') : 'deny',
					'img_text' => $txt['hooks_' . ($hook_exists ? ($enabled ? 'active' : 'disabled') : 'missing')],
					'enabled' => $enabled,
					'can_be_disabled' => !empty($modSettings['handlinghooks_enabled']) && !isset($hook_status[$hook][$function]['enabled']),
				);
			}
		}
	}

	array_multisort($sort, $sort_options[1], $temp_data);

	$counter = 0;
	$start++;

	foreach ($temp_data as $data)
	{
		if (++$counter < $start)
			continue;
		elseif ($counter == $start + $per_page)
			break;

		$hooks_data[] = $data;
	}

	return $hooks_data;
}

/**
 * Simply returns the total count of integraion hooks
 * Used but the intergation hooks list function (list_integration_hooks)
 *
 * @global type $context
 * @return int
 */
function get_integration_hooks_count()
{
	global $context;

	$hooks = get_integration_hooks();
	$hooks_count = 0;

	$context['filter'] = false;
	if (isset($_GET['filter']))
		$context['filter'] = $_GET['filter'];

	foreach ($hooks as $hook => $functions)
	{
		if (empty($context['filter']) || (!empty($context['filter']) && $context['filter'] == $hook))
			$hooks_count += count($functions);
	}

	return $hooks_count;
}

/**
 * Parses modSettings to create integration hook array
 *
 * @staticvar type $integration_hooks
 * @return type
 */
function get_integration_hooks()
{
	global $modSettings;
	static $integration_hooks;

	if (!isset($integration_hooks))
	{
		$integration_hooks = array();
		foreach ($modSettings as $key => $value)
		{
			if (!empty($value) && substr($key, 0, 10) === 'integrate_')
				$integration_hooks[$key] = explode(',', $value);
		}
	}

	return $integration_hooks;
}
