<?php

/**
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 1.0 Alpha
 */

if (!defined('ELK'))
	die('No access...');

/**
 * Drafts administration controller.
 * This class allows to modify admin drafts settings for the forum.
 */
class ManageDrafts_Controller extends Action_Controller
{
	/**
	 * Drafts settings form
	 * @var Settings_Form
	 */
	protected $_draftSettings;

	/**
	 * Default method.
	 */
	public function action_index()
	{
		isAllowedTo('admin_forum');
		loadLanguage('Drafts');

		// We're working with them settings here.
		require_once(SUBSDIR . '/Settings.class.php');

		$this->action_draftSettings_display();
	}

	/**
	 * Modify any setting related to drafts.
	 * Requires the admin_forum permission.
	 * Accessed from ?action=admin;area=managedrafts
	 *
	 * @uses Admin template, edit_topic_settings sub-template.
	 */
	public function action_draftSettings_display()
	{
		global $context, $txt, $scripturl;

		isAllowedTo('admin_forum');
		loadLanguage('Drafts');

		// We're working with them settings here.
		require_once(SUBSDIR . '/Settings.class.php');

		// initialize the form
		$this->_initDraftSettingsForm();

		$config_vars = $this->_draftSettings->settings();

		// Get the settings template ready.
		require_once(SUBSDIR . '/Settings.class.php');

		// Setup the template.
		$context['page_title'] = $txt['managedrafts_settings'];
		$context['sub_template'] = 'show_settings';
		$context[$context['admin_menu_name']]['tab_data'] = array(
			'title' => $txt['drafts'],
			'help' => '',
			'description' => $txt['managedrafts_settings_description'],
		);

		// Saving them ?
		if (isset($_GET['save']))
		{
			checkSession();

			// Protect them from themselves.
			$_POST['drafts_autosave_frequency'] = $_POST['drafts_autosave_frequency'] < 30 ? 30 : $_POST['drafts_autosave_frequency'];
			Settings_Form::save_db($config_vars);
			redirectexit('action=admin;area=managedrafts');
		}

		// some javascript to enable / disable the frequency input box
		$context['settings_post_javascript'] = '
			var autosave = document.getElementById(\'drafts_autosave_enabled\');
			createEventListener(autosave)
			autosave.addEventListener(\'change\', toggle);
			toggle();

			function toggle()
			{
				var select_elem = document.getElementById(\'drafts_autosave_frequency\');
				select_elem.disabled = !autosave.checked;
			}
		';

		// Final settings...
		$context['post_url'] = $scripturl . '?action=admin;area=managedrafts;save';
		$context['settings_title'] = $txt['managedrafts_settings'];

		// Prepare the settings...
		Settings_Form::prepare_db($config_vars);
	}

	/**
	 * Initialize drafts settings with the current forum settings
	 */
	private function _initDraftSettingsForm()
	{
		global $txt;

		// instantiate the form
		$this->_draftSettings = new Settings_Form();

		loadLanguage('Drafts');

		// Here are all the draft settings, a bit lite for now, but we can add more :P
		$config_vars = array(
			// Draft settings ...
			array('check', 'drafts_post_enabled'),
			array('check', 'drafts_pm_enabled'),
			array('int', 'drafts_keep_days', 'postinput' => $txt['days_word'], 'subtext' => $txt['drafts_keep_days_subnote']),
			'',
			array('check', 'drafts_autosave_enabled', 'subtext' => $txt['drafts_autosave_enabled_subnote']),
			array('int', 'drafts_autosave_frequency', 'postinput' => $txt['manageposts_seconds'], 'subtext' => $txt['drafts_autosave_frequency_subnote']),
		);

		return $this->_draftSettings->settings($config_vars);
	}

	/**
	 * Returns all admin drafts settings in config_vars format.
	 * Used by admin search.
	 * @deprecated
	 */
	public function settings()
	{
		global $txt;

		loadLanguage('Drafts');

		// Here are all the draft settings, a bit lite for now, but we can add more :P
		$config_vars = array(
			// Draft settings ...
			array('check', 'drafts_post_enabled'),
			array('check', 'drafts_pm_enabled'),
			array('int', 'drafts_keep_days', 'postinput' => $txt['days_word'], 'subtext' => $txt['drafts_keep_days_subnote']),
			'',
			array('check', 'drafts_autosave_enabled', 'subtext' => $txt['drafts_autosave_enabled_subnote']),
			array('int', 'drafts_autosave_frequency', 'postinput' => $txt['manageposts_seconds'], 'subtext' => $txt['drafts_autosave_frequency_subnote']),
		);

		return $config_vars;
	}
}