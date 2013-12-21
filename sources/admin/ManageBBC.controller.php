<?php

/**
 * Handles administration options for BBC tags.
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 1.0 Beta
 *
 */

if (!defined('ELK'))
	die('No access...');

/**
 * ManageBBC controller handles administration options for BBC tags.
 */
class ManageBBC_Controller extends Action_Controller
{
	/**
	 * BBC settings form
	 *
	 * @var Settings_Form
	 */
	protected $_bbcSettings;

	/**
	 * The BBC admin area
	 * This method is the entry point for index.php?action=admin;area=postsettings;sa=bbc
	 * and it calls a function based on the sub-action, here only display.
	 *
	 * requires admin_forum permissions
	 *
	 * @see Action_Controller::action_index()
	 */
	public function action_index()
	{
		global $context, $txt;

		// We're working with them settings here.
		require_once(SUBSDIR . '/Settings.class.php');

		$subActions = array(
			'display' => array(
				'controller' => $this,
				'function' => 'action_bbcSettings_display',
				'permission' => 'admin_forum')
		);

		// Only one option I'm afraid
		$subAction = isset($_REQUEST['sa']) && isset($subActions[$_REQUEST['sa']]) ? $_REQUEST['sa'] : 'display';
		$context['sub_action'] = $subAction;
		$context['page_title'] = $txt['manageposts_bbc_settings_title'];

		// Initiate and call
		$action = new Action();
		$action->initialize($subActions, 'display');
		$action->dispatch($subAction);
	}

	/**
	 * Administration page in Posts and Topics > BBC.
	 * This method handles displaying and changing which BBC tags are enabled on the forum.
	 *
	 * @uses Admin template, edit_bbc_settings sub-template.
	 */
	public function action_bbcSettings_display()
	{
		global $context, $txt, $modSettings, $scripturl;

		// Initialize the form
		$this->_initBBCSettingsForm();

		$config_vars = $this->_bbcSettings->settings();

		// Make sure a nifty javascript will enable/disable checkboxes, according to BBC globally set or not.
		addInlineJavascript('
			toggleBBCDisabled(\'disabledBBC\', ' . (empty($modSettings['enableBBC']) ? 'true' : 'false') . ');', true);

		call_integration_hook('integrate_modify_bbc_settings', array(&$config_vars));

		// We'll need this forprepare_db() and save_db()
		require_once(SUBSDIR . '/Settings.class.php');

		// Make sure we check the right tags!
		$modSettings['bbc_disabled_disabledBBC'] = empty($modSettings['disabledBBC']) ? array() : explode(',', $modSettings['disabledBBC']);

		// Save page
		if (isset($_GET['save']))
		{
			checkSession();

			// Security: make a pass through all tags and fix them as necessary
			$bbcTags = array();
			foreach (parse_bbc(false) as $tag)
				$bbcTags[] = $tag['tag'];

			if (!isset($_POST['disabledBBC_enabledTags']))
				$_POST['disabledBBC_enabledTags'] = array();
			elseif (!is_array($_POST['disabledBBC_enabledTags']))
				$_POST['disabledBBC_enabledTags'] = array($_POST['disabledBBC_enabledTags']);

			// Work out what is actually disabled!
			$_POST['disabledBBC'] = implode(',', array_diff($bbcTags, $_POST['disabledBBC_enabledTags']));

			// Notify addons and integrations
			call_integration_hook('integrate_save_bbc_settings', array($bbcTags));

			// Save the result
			Settings_Form::save_db($config_vars);

			// And we're out of here!
			redirectexit('action=admin;area=postsettings;sa=bbc');
		}

		// Make sure the template stuff is ready now...
		$context['sub_template'] = 'show_settings';
		$context['page_title'] = $txt['manageposts_bbc_settings_title'];
		$context['post_url'] = $scripturl . '?action=admin;area=postsettings;save;sa=bbc';
		$context['settings_title'] = $txt['manageposts_bbc_settings_title'];

		Settings_Form::prepare_db($config_vars);
	}

	/**
	 * Initializes the form with the current BBC settings of the forum.
	 */
	private function _initBBCSettingsForm()
	{
		// Instantiate the form
		$this->_bbcSettings = new Settings_Form();

		// Initialize it with our settings
		$config_vars = $this->_settings();

		return $this->_bbcSettings->settings($config_vars);
	}

	/**
	 * Return the BBC settings of the forum.
	 */
	private function _settings()
	{
		$config_vars = array(
				array('check', 'enableBBC'),
				array('check', 'enableBBC', 0, 'onchange' => 'toggleBBCDisabled(\'disabledBBC\', !this.checked);'),
				array('check', 'enablePostHTML'),
				array('check', 'autoLinkUrls'),
			'',
				array('bbc', 'disabledBBC'),
		);

		return $config_vars;
	}

	/**
	 * Return the form settings for use in admin search
	 */
	public function settings_search()
	{
		return $this->_settings();
	}
}