<?php

/**
 * Handles the Security and Moderation pages in the admin panel.  This includes
 * bad behavior, anti spam, security and moderation settings
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * This software is a derived product, based on:
 *
 * Simple Machines Forum (SMF)
 * copyright:	2011 Simple Machines (http://www.simplemachines.org)
 * license:		BSD, See included LICENSE.TXT for terms and conditions.
 *
 * @version 1.0 Beta
 *
 */

if (!defined('ELK'))
	die('No access...');

/**
 * ManageSecurity controller handles the Security and Moderation
 * pages in admin panel.
 */
class ManageSecurity_Controller extends Action_Controller
{
	/**
	 * Bad Behavior settings form.
	 * @var Settings_Form
	 */
	protected $_bbSettings;

	/**
	 * Security settings form.
	 * @var Settings_Form
	 */
	protected $_securitySettings;

	/**
	 * Moderation settings form.
	 * @var Settings_Form
	 */
	protected $_moderationSettings;

	/**
	 * Spam settings form.
	 * @var Settings_Form
	 */
	protected $_spamSettings;

	/**
	 * This function passes control through to the relevant security tab.
	 *
	 * @see Action_Controller::action_index()
	 */
	public function action_index()
	{
		global $context, $txt;

		loadLanguage('Help');
		loadLanguage('ManageSettings');

		$subActions = array(
			'general' => array($this, 'action_securitySettings_display', 'permission' => 'admin_forum'),
			'spam' => array($this, 'action_spamSettings_display', 'permission' => 'admin_forum'),
			'badbehavior' => array($this, 'action_bbSettings_display', 'permission' => 'admin_forum'),
			'moderation' => array($this, 'action_moderationSettings_display', 'enabled' => in_array('w', $context['admin_features']), 'permission' => 'admin_forum'),
		);

		call_integration_hook('integrate_modify_security', array(&$subActions));

		// By default do the basic settings.
		$subAction = isset($_REQUEST['sa']) && isset($subActions[$_REQUEST['sa']]) ? $_REQUEST['sa'] : 'general';

		$context['sub_action'] = $subAction;
		$context['page_title'] = $txt['admin_security_moderation'];
		$context['sub_template'] = 'show_settings';

		// Load up all the tabs...
		$context[$context['admin_menu_name']]['tab_data'] = array(
			'title' => $txt['admin_security_moderation'],
			'help' => 'securitysettings',
			'description' => $txt['security_settings_desc'],
			'tabs' => array(
				'general' => array(
				),
				'spam' => array(
					'description' => $txt['antispam_Settings_desc'] ,
				),
				'badbehavior' => array(
					'description' => $txt['badbehavior_desc'] ,
				),
				'moderation' => array(
				),
			),
		);

		// Call the right function for this sub-action.
		$action = new Action();
		$action->initialize($subActions, 'general');
		$action->dispatch($subAction);
	}

	/**
	 * Handle settings regarding general security of the site.
	 * Uses a settings form for security options.
	 */
	public function action_securitySettings_display()
	{
		global $txt, $scripturl, $context;

		// Initialize the form
		$this->_initSecuritySettingsForm();

		// Retrieve the current config settings
		$config_vars = $this->_securitySettings->settings();

		// Saving?
		if (isset($_GET['save']))
		{
			checkSession();

			Settings_Form::save_db($config_vars);

			call_integration_hook('integrate_save_general_security_settings');

			writeLog();
			redirectexit('action=admin;area=securitysettings;sa=general');
		}

		$context['post_url'] = $scripturl . '?action=admin;area=securitysettings;save;sa=general';
		$context['settings_title'] = $txt['mods_cat_security_general'];

		Settings_Form::prepare_db($config_vars);
	}

	/**
	 * Initializes security settings admin screen data.
	 */
	private function _initSecuritySettingsForm()
	{
		// We're working with them settings.
		require_once(SUBSDIR . '/Settings.class.php');

		// Instantiate the form
		$this->_securitySettings = new Settings_Form();

		// Initialize it with our settings
		$config_vars = $this->_securitySettings();

		return $this->_securitySettings->settings($config_vars);
	}

	/**
	 * Allows to display and eventually change the moderation settings of the forum.
	 * Uses the moderation settings form.
	 */
	public function action_moderationSettings_display()
	{
		global $txt, $scripturl, $context, $modSettings;

		// Initialize the form
		$this->_initModerationSettingsForm();

		// Retrieve the current config settings
		$config_vars = $this->_moderationSettings->settings();

		// Cannot use moderation if post moderation is not enabled.
		if (!$modSettings['postmod_active'])
			unset($config_vars['moderate']);

		// We're working with them settings.
		require_once(SUBSDIR . '/Settings.class.php');

		// Saving?
		if (isset($_GET['save']))
		{
			checkSession();

			// Make sure these don't have an effect.
			if ($modSettings['warning_settings'][0] != 1)
			{
				$_POST['warning_watch'] = 0;
				$_POST['warning_moderate'] = 0;
				$_POST['warning_mute'] = 0;
			}
			else
			{
				$_POST['warning_watch'] = min($_POST['warning_watch'], 100);
				$_POST['warning_moderate'] = $modSettings['postmod_active'] ? min($_POST['warning_moderate'], 100) : 0;
				$_POST['warning_mute'] = min($_POST['warning_mute'], 100);
			}

			// Fix the warning setting array!
			$_POST['warning_settings'] = '1,' . min(100, (int) $_POST['user_limit']) . ',' . min(100, (int) $_POST['warning_decrement']);
			$save_vars = $config_vars;
			$save_vars[] = array('text', 'warning_settings');
			unset($save_vars['rem1'], $save_vars['rem2']);

			call_integration_hook('integrate_save_moderation_settings', array(&$save_vars));

			Settings_Form::save_db($save_vars);
			redirectexit('action=admin;area=securitysettings;sa=moderation');
		}

		// We actually store lots of these together - for efficiency.
		list ($modSettings['warning_enable'], $modSettings['user_limit'], $modSettings['warning_decrement']) = explode(',', $modSettings['warning_settings']);

		$context['post_url'] = $scripturl . '?action=admin;area=securitysettings;save;sa=moderation';
		$context['settings_title'] = $txt['moderation_settings'];

		Settings_Form::prepare_db($config_vars);
	}

	/**
	 * Initialize moderation settings form with the current configuration options.
	 *
	 * @return array
	 */
	private function _initModerationSettingsForm()
	{
		// We're working with them settings.
		require_once(SUBSDIR . '/Settings.class.php');

		// Instantiate the form
		$this->_moderationSettings = new Settings_Form();

		// Initialize it with our settings
		$config_vars = $this->_moderationSettings();

		return $this->_moderationSettings->settings($config_vars);
	}

	/**
	 * Handles admin security spam settings.
	 * Displays a page with settings and eventually allows the admin to change them.
	 */
	public function action_spamSettings_display()
	{
		global $txt, $scripturl, $context, $modSettings;

		// Initialize the form
		$this->_initSpamSettingsForm();

		// Retrieve the current config settings
		$config_vars = $this->_spamSettings->settings();

		// Saving?
		if (isset($_GET['save']))
		{
			checkSession();

			// Fix PM settings.
			$_POST['pm_spam_settings'] = (int) $_POST['max_pm_recipients'] . ',' . (int) $_POST['pm_posts_verification'] . ',' . (int) $_POST['pm_posts_per_hour'];

			// Guest requiring verification!
			if (empty($_POST['posts_require_captcha']) && !empty($_POST['guests_require_captcha']))
				$_POST['posts_require_captcha'] = -1;

			$save_vars = $config_vars;
			unset($save_vars['pm1'], $save_vars['pm2'], $save_vars['pm3'], $save_vars['guest_verify']);

			$save_vars[] = array('text', 'pm_spam_settings');

			call_integration_hook('integrate_save_spam_settings', array(&$save_vars));

			// Now save.
			Settings_Form::save_db($save_vars);
			cache_put_data('verificationQuestionIds', null, 300);
			redirectexit('action=admin;area=securitysettings;sa=spam');
		}

		// Add in PM spam settings on the fly
		list ($modSettings['max_pm_recipients'], $modSettings['pm_posts_verification'], $modSettings['pm_posts_per_hour']) = explode(',', $modSettings['pm_spam_settings']);

		// And the same for guests requiring verification.
		$modSettings['guests_require_captcha'] = !empty($modSettings['posts_require_captcha']);
		$modSettings['posts_require_captcha'] = !isset($modSettings['posts_require_captcha']) || $modSettings['posts_require_captcha'] == -1 ? 0 : $modSettings['posts_require_captcha'];

		// Some minor javascript for the guest post setting.
		if ($modSettings['posts_require_captcha'])
			addInlineJavascript('document.getElementById(\'guests_require_captcha\').disabled = true;', true);

		$context['post_url'] = $scripturl . '?action=admin;area=securitysettings;save;sa=spam';
		$context['settings_title'] = $txt['antispam_Settings'];
		Settings_Form::prepare_db($config_vars);
	}

	/**
	 * Initializes spam settings with the current configuration saved.
	 */
	private function _initSpamSettingsForm()
	{
		// We're working with them settings.
		require_once(SUBSDIR . '/Settings.class.php');
		require_once(SUBSDIR . '/VerificationControls.class.php');

		// Instantiate the form
		$this->_spamSettings = new Settings_Form();

		// Initialize it with our settings
		$config_vars = $this->_spamSettings();

		return $this->_spamSettings->settings($config_vars);
	}

	/**
	 * Change the way bad behavior ... well behaves
	 */
	public function action_bbSettings_display()
	{
		global $txt, $scripturl, $context, $modSettings, $boardurl;

		// Initialize the form
		$this->_initBBSettingsForm();

		// Our callback templates are here
		loadTemplate('BadBehavior');

		// Any errors to display?
		if ($context['invalid_badbehavior_httpbl_key'])
		{
			$context['settings_message'][] = $txt['badbehavior_httpbl_key_invalid'];
			$context['error_type'] = 'warning';
		}

		// Have we blocked anything in the last 7 days?
		if (!empty($modSettings['badbehavior_enabled']))
			$context['settings_message'][] = bb2_insert_stats(true) . '<a href="' . $boardurl . '/index.php?action=admin;area=logs;sa=badbehaviorlog;desc" /> [' . $txt['badbehavior_details'] . ']</a>';

		// Current whitelist data
		$whitelist = array('badbehavior_ip_wl', 'badbehavior_useragent_wl', 'badbehavior_url_wl');
		foreach ($whitelist as $list)
		{
			$context[$list] = array();
			$context[$list . '_desc'] = array();

			if (!empty($modSettings[$list]))
				$context[$list] = unserialize($modSettings[$list]);

			if (!empty($modSettings[$list . '_desc']))
				$context[$list . '_desc'] = unserialize($modSettings[$list . '_desc']);
		}

		$config_vars = $this->_bbSettings->settings();

		// Saving?
		if (isset($_GET['save']))
		{
			checkSession();

			// Make sure Bad Behavior defaults are set if nothing was specified
			$_POST['badbehavior_httpbl_threat'] = empty($_POST['badbehavior_httpbl_threat']) ? 25 : $_POST['badbehavior_httpbl_threat'];
			$_POST['badbehavior_httpbl_maxage'] = empty($_POST['badbehavior_httpbl_maxage']) ? 30 : $_POST['badbehavior_httpbl_maxage'];
			$_POST['badbehavior_reverse_proxy_header'] = empty($_POST['badbehavior_reverse_proxy_header']) ? 'X-Forwarded-For' : $_POST['badbehavior_reverse_proxy_header'];

			// Build up the whitelist options
			foreach ($whitelist as $list)
			{
				$this_list = array();
				$this_desc = array();

				if (isset($_POST[$list]))
				{
					// Clear blanks from the data field, only grab the comments that don't have blank data value
					$this_list = array_map('trim', array_filter($_POST[$list]));
					$this_desc = array_intersect_key($_POST[$list . '_desc'], $this_list);
				}
				updateSettings(array($list => serialize($this_list), $list . '_desc' => serialize($this_desc)));
			}

			Settings_Form::save_db($config_vars);
			redirectexit('action=admin;area=securitysettings;sa=badbehavior');
		}

		$context['post_url'] = $scripturl . '?action=admin;area=securitysettings;save;sa=badbehavior';

		// Javascript vars for the "add more xyz" buttons in the callback forms
		addJavascriptVar(array(
			'sUrlParent' => '\'add_more_url_placeholder\'',
			'oUrlOptionsdt' => '{name: \'badbehavior_url_wl_desc[]\', class: \'input_text\'}',
			'oUrlOptionsdd' => '{name: \'badbehavior_url_wl[]\', class: \'input_text\'}',
			'sUseragentParent' => '\'add_more_useragent_placeholder\'',
			'oUseragentOptionsdt' => '{name: \'badbehavior_useragent_wl_desc[]\', class: \'input_text\'}',
			'oUseragentOptionsdd' => '{name: \'badbehavior_useragent_wl[]\', class: \'input_text\'}',
			'sIpParent' => '\'add_more_ip_placeholder\'',
			'oIpOptionsdt' => '{name: \'badbehavior_ip_wl_desc[]\', class: \'input_text\'}',
			'oIpOptionsdd' => '{name: \'badbehavior_ip_wl[]\', class: \'input_text\'}'
		));

		Settings_Form::prepare_db($config_vars);
	}

	/**
	 * Retrieves and returns the configuration settings for Bad Behavior.
	 * Initializes bbSettings form.
	 */
	private function _initBBSettingsForm()
	{
		// We're working with them settings.
		require_once(SUBSDIR . '/Settings.class.php');

		// Instantiate the form
		$this->_bbSettings = new Settings_Form();

		// Initialize it with our settings
		$config_vars = $this->_bbSettings();

		return $this->_bbSettings->settings($config_vars);
	}

	/**
	 * Moderation settings.
	 */
	private function _moderationSettings()
	{
		global $txt;

		$config_vars = array(
			// Warning system?
			array('int', 'warning_watch', 'subtext' => $txt['setting_warning_watch_note'], 'help' => 'warning_enable'),
			'moderate' => array('int', 'warning_moderate', 'subtext' => $txt['setting_warning_moderate_note']),
			array('int', 'warning_mute', 'subtext' => $txt['setting_warning_mute_note']),
			'rem1' => array('int', 'user_limit', 'subtext' => $txt['setting_user_limit_note']),
			'rem2' => array('int', 'warning_decrement', 'subtext' => $txt['setting_warning_decrement_note']),
			array('select', 'warning_show', 'subtext' => $txt['setting_warning_show_note'], array($txt['setting_warning_show_mods'], $txt['setting_warning_show_user'], $txt['setting_warning_show_all'])),
		);

		call_integration_hook('integrate_moderation_settings', array(&$config_vars));

		return $config_vars;
	}

	/**
	 * Public method to return moderation settings, used for admin search
	 */
	public function moderationSettings_search()
	{
		return $this->_moderationSettings();
	}

	/**
	 * Security settings.
	 */
	private function _securitySettings()
	{
		global $txt;

		// Set up the config array for use
		$config_vars = array(
				array('check', 'make_email_viewable'),
			'',
				array('int', 'failed_login_threshold'),
				array('int', 'loginHistoryDays'),
			'',
				array('check', 'enableErrorLogging'),
				array('check', 'enableErrorQueryLogging'),
			'',
				array('int', 'admin_session_lifetime'),
				array('check', 'auto_admin_session'),
				array('check', 'securityDisable'),
				array('check', 'securityDisable_moderate'),
			'',
				// Reactive on email, and approve on delete
				array('check', 'send_validation_onChange'),
				array('check', 'approveAccountDeletion'),
			'',
				// Password strength.
				array('select', 'password_strength', array($txt['setting_password_strength_low'], $txt['setting_password_strength_medium'], $txt['setting_password_strength_high'])),
				array('check', 'enable_password_conversion'),
			'',
				array('select', 'frame_security', array('SAMEORIGIN' => $txt['setting_frame_security_SAMEORIGIN'], 'DENY' => $txt['setting_frame_security_DENY'], 'DISABLE' => $txt['setting_frame_security_DISABLE'])),
		);

		call_integration_hook('integrate_general_security_settings', array(&$config_vars));

		return $config_vars;
	}

	/**
	 * Public method to return security form settings, used in admin search
	 */
	public function securitySettings_search()
	{
		return $this->_securitySettings();
	}

	/**
	 * Spam settings.
	 */
	private function _spamSettings()
	{
		global $txt;

		require_once(SUBSDIR . '/VerificationControls.class.php');

		// Build up our options array
		$config_vars = array(
			array('check', 'reg_verification'),
			array('check', 'search_enable_captcha'),
			// This, my friend, is a cheat :p
			'guest_verify' => array('check', 'guests_require_captcha', 'postinput' => $txt['setting_guests_require_captcha_desc']),
			array('int', 'posts_require_captcha', 'postinput' => $txt['posts_require_captcha_desc'], 'onchange' => 'if (this.value > 0){ document.getElementById(\'guests_require_captcha\').checked = true; document.getElementById(\'guests_require_captcha\').disabled = true;} else {document.getElementById(\'guests_require_captcha\').disabled = false;}'),
			array('check', 'guests_report_require_captcha'),
			// PM Settings
			array('title', 'antispam_PM'),
				'pm1' => array('int', 'max_pm_recipients', 'postinput' => $txt['max_pm_recipients_note']),
				'pm2' => array('int', 'pm_posts_verification', 'postinput' => $txt['pm_posts_verification_note']),
				'pm3' => array('int', 'pm_posts_per_hour', 'postinput' => $txt['pm_posts_per_hour_note']),
		);

		$known_verifications = loadVerificationControls();

		foreach ($known_verifications as $verification)
		{
			$class_name = 'Control_Verification_' . ucfirst($verification);
			$current_instance = new $class_name();

			$new_settings = $current_instance->settings();
			if (!empty($new_settings) && is_array($new_settings))
				foreach ($new_settings as $new_setting)
				$config_vars[] = $new_setting;
		}

		// @todo: it may be removed, it may stay, the two hooks may have different functions
		call_integration_hook('integrate_spam_settings', array(&$config_vars));

		return $config_vars;
	}

	/**
	 * Public method to return spam settings, used in admin search
	 */
	public function spamSettings_search()
	{
		return $this->_spamSettings();
	}

	/**
	 * Bad Behavior settings.
	 */
	private function _bbSettings()
	{
		global $txt, $context, $modSettings;

		// See if they supplied a valid looking http:BL API Key
		$context['invalid_badbehavior_httpbl_key'] = (!empty($modSettings['badbehavior_httpbl_key']) && (strlen($modSettings['badbehavior_httpbl_key']) !== 12 || !ctype_lower($modSettings['badbehavior_httpbl_key'])));

		// Build up our options array
		$config_vars = array(
			array('title', 'badbehavior_title'),
				array('check', 'badbehavior_enabled', 'postinput' => $txt['badbehavior_enabled_desc']),
				array('check', 'badbehavior_logging', 'postinput' => $txt['badbehavior_default_on']),
				array('check', 'badbehavior_verbose', 'postinput' => $txt['badbehavior_default_off']),
				array('check', 'badbehavior_strict', 'postinput' => $txt['badbehavior_default_off']),
				array('check', 'badbehavior_offsite_forms', 'postinput' => $txt['badbehavior_default_off']),
				array('check', 'badbehavior_eucookie', 'postinput' => $txt['badbehavior_default_off']),
				array('check', 'badbehavior_display_stats', 'postinput' => $txt['badbehavior_default_off']),
			'',
				array('check', 'badbehavior_reverse_proxy', 'postinput' => $txt['badbehavior_default_off']),
				array('text', 'badbehavior_reverse_proxy_header', 30, 'postinput' => $txt['badbehavior_reverse_proxy_header_desc']),
				array('text', 'badbehavior_reverse_proxy_addresses', 30),
			'',
				array('text', 'badbehavior_httpbl_key', 12, 'invalid' => $context['invalid_badbehavior_httpbl_key']),
				array('int', 'badbehavior_httpbl_threat', 'postinput' => $txt['badbehavior_httpbl_threat_desc']),
				array('int', 'badbehavior_httpbl_maxage', 'postinput' => $txt['badbehavior_httpbl_maxage_desc']),
			array('title', 'badbehavior_whitelist_title'),
				array('desc', 'badbehavior_wl_desc'),
				array('int', 'badbehavior_postcount_wl', 'postinput' => $txt['badbehavior_postcount_wl_desc']),
				array('callback', 'badbehavior_add_ip'),
				array('callback', 'badbehavior_add_url'),
				array('callback', 'badbehavior_add_useragent'),
		);

		return $config_vars;
	}

	/**
	 * Public method to return bb settings, used in admin search
	 */
	public function bbSettings_search()
	{
		return $this->_bbSettings();
	}
}