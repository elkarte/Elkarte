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
 */

if (!defined('ELKARTE'))
	die('No access...');

/**
 * ManageSecurity controller handles the Security and Moderation
 * pages in admin panel.
 */
class ManageSecurity_Controller
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
	 */
	public function action_index()
	{
		global $context, $txt;

		$context['page_title'] = $txt['admin_security_moderation'];

		$subActions = array(
			'general' => array($this, 'action_securitySettings_display'),
			'spam' => array($this, 'action_spamSettings_display'),
			'badbehavior' => array($this, 'action_bbSettings_display'),
			'moderation' => array($this, 'action_moderationSettings_display'),
		);

		call_integration_hook('integrate_modify_security', array(&$subActions));

		// If Warning System is disabled don't show the setting page
		if (!in_array('w', $context['admin_features']))
			unset($subActions['moderation']);

		// @FIXME
		// loadGeneralSettingParameters($subActions, 'general');

		// You need to be an admin to edit settings!
		isAllowedTo('admin_forum');

		loadLanguage('Help');
		loadLanguage('ManageSettings');

		$context['sub_template'] = 'show_settings';

		// By default do the basic settings.
		$_REQUEST['sa'] = isset($_REQUEST['sa']) && isset($subActions[$_REQUEST['sa']]) ? $_REQUEST['sa'] : 'general';
		$context['sub_action'] = $_REQUEST['sa'];

		// trick you
		$subAction = $context['sub_action'];

		// Set up action stuff.
		$action = new Action();
		$action->initialize($subActions);

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
		$action->dispatch($subAction);
	}

	/**
	 * Handle settings regarding general security of the site.
	 * Uses a settings form for security options.
	 *
	 */
	public function action_securitySettings_display()
	{
		global $txt, $scripturl, $context;

		// initialize the form
		$this->_initSecuritySettingsForm();

		// retrieve the current config settings
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
		global $txt;

		// We're working with them settings.
		require_once(SUBSDIR . '/Settings.class.php');

		// instantiate the form
		$this->_securitySettings = new Settings_Form();

		// initialize it with our settings
		$config_vars = array(
				array('check', 'guest_hideContacts'),
				array('check', 'make_email_viewable'),
			'',
				array('int', 'failed_login_threshold'),
				array('int', 'loginHistoryDays'),
			'',
				array('check', 'enableErrorLogging'),
				array('check', 'enableErrorQueryLogging'),
			'',
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
				// Reporting of personal messages?
				array('check', 'enableReportPM'),
			'',
				array('select', 'frame_security', array('SAMEORIGIN' => $txt['setting_frame_security_SAMEORIGIN'], 'DENY' => $txt['setting_frame_security_DENY'], 'DISABLE' => $txt['setting_frame_security_DISABLE'])),
		);

		call_integration_hook('integrate_general_security_settings', array(&$config_vars));

		return $this->_securitySettings->settings($config_vars);
	}

	/**
	 * Allows to display and eventually change the moderation settings of the forum.
	 * Uses the moderation settings form.
	 *
	 */
	public function action_moderationSettings_display()
	{
		global $txt, $scripturl, $context, $modSettings;

		// initialize the form
		$this->_initModerationSettingsForm();

		// retrieve the current config settings
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
		global $txt;

		// we're working with them settings.
		require_once(SUBSDIR . '/Settings.class.php');

		// instantiate the form
		$this->_moderationSettings = new Settings_Form();

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

		return $this->_moderationSettings->settings($config_vars);
	}

	/**
	 * Handles admin security spam settings.
	 * Displays a page with settings and eventually allows the admin to change them.
	 */
	public function action_spamSettings_display()
	{
		global $txt, $scripturl, $context, $modSettings;

		// Let's try keep the spam to a minimum ah Thantos?
		// initialize the form
		$this->_initSpamSettingsForm();

		// retrieve the current config settings
		$config_vars = $this->_spamSettings->settings();

		// Saving?
		if (isset($_GET['save']))
		{
			checkSession();

			// Fix PM settings.
			$_POST['pm_spam_settings'] = (int) $_POST['max_pm_recipients'] . ',' . (int) $_POST['pm_posts_verification'] . ',' . (int) $_POST['pm_posts_per_hour'];

			// Hack in guest requiring verification!
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

		// Hack for PM spam settings.
		list ($modSettings['max_pm_recipients'], $modSettings['pm_posts_verification'], $modSettings['pm_posts_per_hour']) = explode(',', $modSettings['pm_spam_settings']);

		// Hack for guests requiring verification.
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
		global $txt;

		// we're working with them settings.
		require_once(SUBSDIR . '/Settings.class.php');
		require_once(SUBSDIR . '/Editor.subs.php');

		// instantiate the form
		$this->_spamSettings = new Settings_Form();

		// Build up our options array
		$config_vars = array(
			array('title', 'antispam_Settings'),
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

		// @todo: maybe move the list to $modSettings instead of hooking it?
		// Used in create_control_verification too
		$known_verifications = array(
			'captcha',
			'questions',
		);
		call_integration_hook('integrate_control_verification', array(&$known_verifications));

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

		return $this->_spamSettings->settings($config_vars);
	}

	/**
	 * Change the way bad behavior ... well behaves
	 *
	 */
	public function action_bbSettings_display()
	{
		global $txt, $scripturl, $context, $modSettings, $boardurl;

		// initialize the form
		$this->_initBBSettingsForm();

		// Our callback templates are here
		loadTemplate('BadBehavior');

		// Any errors to display?
		if ($context['invalid_badbehavior_httpbl_key'])
		{
			$context['settings_message'][] = $txt['setting_badbehavior_httpbl_key_invalid'];
			$context['error_type'] = 'notice';
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
					// clear blanks from the data field, only grab the comments that don't have blank data value
					$this_list = array_map('trim', array_filter($_POST[$list]));
					$this_desc = array_intersect_key($_POST[$list . '_desc'], $this_list);
				}
				updateSettings(array($list => serialize($this_list), $list . '_desc' => serialize($this_desc)));
			}

			Settings_Form::save_db($config_vars);
			redirectexit('action=admin;area=securitysettings;sa=badbehavior');
		}

		$context['post_url'] = $scripturl . '?action=admin;area=securitysettings;save;sa=badbehavior';

		// javascript vars for the "add more xyz" buttons in the callback forms
		addInlineJavascript('
		var sUrlParent = \'add_more_url_placeholder\';
		var oUrlOptionsdt = {name: \'badbehavior_url_wl_desc[]\', class: \'input_text\'};
		var oUrlOptionsdd = {name: \'badbehavior_url_wl[]\', class: \'input_text\'};
		var sUseragentParent = \'add_more_useragent_placeholder\';
		var oUseragentOptionsdt = {name: \'badbehavior_useragent_wl_desc[]\', class: \'input_text\'};
		var oUseragentOptionsdd = {name: \'badbehavior_useragent_wl[]\', class: \'input_text\'};
		var sIpParent = \'add_more_ip_placeholder\';
		var oIpOptionsdt = {name: \'badbehavior_ip_wl_desc[]\', class: \'input_text\'};
		var oIpOptionsdd = {name: \'badbehavior_ip_wl[]\', class: \'input_text\'};'
		);

		Settings_Form::prepare_db($config_vars);
	}

	/**
	 * Retrieves and returns the configuration settings for Bad Behavior.
	 * Initializes bbSettings form.
	 */
	private function _initBBSettingsForm()
	{
		global $txt, $context, $modSettings;

		// We're working with them settings.
		require_once(SUBSDIR . '/Settings.class.php');

		// instantiate the form
		$this->_bbSettings = new Settings_Form();
		
		// See if they supplied a valid looking http:BL API Key
		$context['invalid_badbehavior_httpbl_key'] = (!empty($modSettings['badbehavior_httpbl_key']) && (strlen($modSettings['badbehavior_httpbl_key']) !== 12 || !ctype_lower($modSettings['badbehavior_httpbl_key'])));

		// Build up our options array
		$config_vars = array(
			array('title', 'badbehavior_title'),
				array('desc', 'badbehavior_desc'),
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

		return $this->_bbSettings->settings($config_vars);
	}

	/**
	 * This function makes sure the requested subaction does exist,
	 *  if it doesn't, it sets a default action or.
	 * @deprecated
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

		// Will need the utility functions from here.
		require_once(SUBSDIR . '/Settings.class.php');

		$context['sub_template'] = 'show_settings';

		// By default do the basic settings.
		$_REQUEST['sa'] = isset($_REQUEST['sa']) && isset($subActions[$_REQUEST['sa']]) ? $_REQUEST['sa'] : (!empty($defaultAction) ? $defaultAction : array_pop($temp = array_keys($subActions)));
		$context['sub_action'] = $_REQUEST['sa'];
	}

	/**
	 * Moderation settings.
	 * Used in admin panel search.
	 */
	public function moderationSettings()
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
	 * Security settings.
	 * Used in admin panel search.
	 */
	public function securitySettings()
	{
		global $txt;

		// initialize it with our settings
		$config_vars = array(
				array('check', 'guest_hideContacts'),
				array('check', 'make_email_viewable'),
			'',
				array('int', 'failed_login_threshold'),
				array('int', 'loginHistoryDays'),
			'',
				array('check', 'enableErrorLogging'),
				array('check', 'enableErrorQueryLogging'),
			'',
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
				// Reporting of personal messages?
				array('check', 'enableReportPM'),
			'',
				array('select', 'frame_security', array('SAMEORIGIN' => $txt['setting_frame_security_SAMEORIGIN'], 'DENY' => $txt['setting_frame_security_DENY'], 'DISABLE' => $txt['setting_frame_security_DISABLE'])),
		);

		call_integration_hook('integrate_general_security_settings', array(&$config_vars));

		return $config_vars;
	}

	/**
	 * Spam settings.
	 * Used in admin panel search.
	 */
	public function spamSettings()
	{
		global $txt;

		require_once(SUBSDIR . '/Editor.subs.php');

		// Build up our options array
		$config_vars = array(
			array('title', 'antispam_Settings'),
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

		// @todo: maybe move the list to $modSettings instead of hooking it?
		// Used in create_control_verification too
		$known_verifications = array(
			'captcha',
			'questions',
		);
		call_integration_hook('integrate_control_verification', array(&$known_verifications));

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
	 * Bad Behavior settings.
	 * Used in admin panel search.
	 */
	public function bbSettings()
	{
		global $txt, $context, $modSettings;
		
		// See if they supplied a valid looking http:BL API Key
		$context['invalid_badbehavior_httpbl_key'] = (!empty($modSettings['badbehavior_httpbl_key']) && (strlen($modSettings['badbehavior_httpbl_key']) !== 12 || !ctype_lower($modSettings['badbehavior_httpbl_key'])));

		// Build up our options array
		$config_vars = array(
			array('title', 'badbehavior_title'),
				array('desc', 'badbehavior_desc'),
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
}
