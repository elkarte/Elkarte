<?php

/**
 * Handles administration settings added in the common area for all addons.
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 1.1 dev
 *
 */

if (!defined('ELK'))
	die('No access...');

/**
 * AddonSettings controller handles administration settings added
 * in the common area for all addons in admin panel.
 *
 * What it does:
 *  - Some addons will define their own areas, but for simple cases,
 * when you have only a setting or two, this area will allow you
 * to hook into it seamlessly, and your additions will be sent
 * to admin search and otherwise benefit from admin areas security,
 * checks and display.
 *
 * @package AddonSettings
 */
class AddonSettings_Controller extends Action_Controller
{
	/**
	 * General addon settings form.
	 * @var Settings_Form
	 */
	protected $_addonSettings;

	/**
	 * This, my friend, is for all the authors of addons out there.
	 *
	 * @see Action_Controller::action_index()
	 */
	public function action_index()
	{
		global $context, $txt;

		loadLanguage('Help');
		loadLanguage('ManageSettings');

		// Our tidy subActions array
		$subActions = array(
			'general' => array($this, 'action_addonSettings_display', 'permission' => 'admin_forum'),
		);

		// @FIXME
		// $this->loadGeneralSettingParameters($subActions, 'general');
		$context['page_title'] = $txt['admin_modifications'];
		$context['sub_template'] = 'show_settings';
		// END $this->loadGeneralSettingParameters();

		// Load up all the tabs...
		$context[$context['admin_menu_name']]['tab_data'] = array(
			'title' => $txt['admin_modifications'],
			'help' => 'addonsettings',
			'description' => $txt['modification_settings_desc'],
			'tabs' => array(
				'general' => array(
				),
			),
		);

		// Set up the action controller
		$action = new Action('modify_modifications');

		// Pick the correct sub-action, call integrate_sa_modify_modifications
		$subAction = $action->initialize($subActions, 'general');
		$context['sub_action'] = $subAction;

		// Call the right function for this sub-action.
		$action->dispatch($subAction);
	}

	/**
	 * If you have a general mod setting to add stick it here.
	 */
	public function action_addonSettings_display()
	{
		// Initialize the form
		$this->_initAddonSettingsForm();

		// Initialize settings
		$config_vars = $this->_addonSettings->settings();

		// Saving?
		if (isset($this->_req->query->save))
		{
			checkSession();

			call_integration_hook('integrate_save_general_mod_settings');

			Settings_Form::save_db($config_vars);

			redirectexit('action=admin;area=addonsettings;sa=general');
		}

		Settings_Form::prepare_db($config_vars);
	}

	/**
	 * Initialize the customSettings form with any custom admin settings for or from addons.
	 */
	public function _initAddonSettingsForm()
	{
		global $context, $txt, $scripturl;

		// instantiate the form
		$this->_addonSettings = new Settings_Form();

		// initialize it with our existing settings. If any.
		$config_vars = $this->_settings();

		if (empty($config_vars))
		{
			$context['settings_save_dont_show'] = true;
			$context['settings_message'] = '<div class="centertext">' . $txt['modification_no_misc_settings'] . '</div>';
		}

		$context['post_url'] = $scripturl . '?action=admin;area=addonsettings;save;sa=general';
		$context['settings_title'] = $txt['mods_cat_modifications_misc'];

		return $this->_addonSettings->settings($config_vars);
	}

	/**
	 * Retrieve any custom admin settings for or from addons.
	 */
	private function _settings()
	{
		$config_vars = array();

		// Add new settings with a nice hook.
		call_integration_hook('integrate_general_mod_settings', array(&$config_vars));

		return $config_vars;
	}

	/**
	 * Public method to return admin settings for search
	 */
	public function settings_search()
	{
		return $this->_settings();
	}

	/**
	 * This function makes sure the requested subaction does exist,
	 * if it doesn't, it sets a default action or.
	 *
	 * @param mixed[] $subActions An array containing all possible subactions.
	 * @param string $defaultAction the default action to be called if no valid subaction was found.
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
		if (isset($this->_req->query->sa, $subActions[$this->_req->query->sa]))
			$sa = $this->_req->query->sa;
		elseif (!empty($defaultAction))
			$sa = $defaultAction;
		else
		{
			$keys = array_keys($subActions);
			$sa = array_pop($keys);
		}

		$context['sub_action'] = $sa;
	}
}