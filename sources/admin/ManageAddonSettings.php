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
	public function action_index()
	{
		global $context, $txt;

		$context['page_title'] = $txt['admin_modifications'];

		$subActions = array(
			'general' => array($this, 'action_addonSettings_display'),
			'hooks' => array($this, 'action_hooks'),
			// Mod authors, once again, if you have a whole section to add do it AFTER this line, and keep a comma at the end.
		);

		// Make it easier for mods to add new areas.
		call_integration_hook('integrate_modify_modifications', array(&$subActions));

		// Pick the correct sub-action.
		if (isset($_REQUEST['sa']) && isset($subActions[$_REQUEST['sa']]))
			$subAction = $_REQUEST['sa'];
		else
			$subAction = 'general';

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
	public function action_addonSettings_display()
	{
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
	public function _initAddonSettingsForm()
	{
		global $context, $txt, $scripturl;

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
	public function settings()
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
	public function action_hooks()
	{
		global $scripturl, $context, $txt, $modSettings, $settings;

		require_once(SUBSDIR . '/ManageAddonSettings.subs.php');

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
				'function' => 'list_integration_hooks_data',
			),
			'get_count' => array(
				'function' => 'list_integration_hooks_count',
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
	public function loadGeneralSettingParameters($subActions = array(), $defaultAction = '')
	{
		global $context;

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