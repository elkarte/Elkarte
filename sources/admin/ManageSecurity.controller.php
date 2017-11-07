<?php

/**
 * Handles the Security and Moderation pages in the admin panel.  This includes
 * bad behavior, anti spam, security and moderation settings
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * This file contains code covered by:
 * copyright:	2011 Simple Machines (http://www.simplemachines.org)
 * license:		BSD, See included LICENSE.TXT for terms and conditions.
 *
 * @version 1.1
 *
 */

/**
 * ManageSecurity controller handles the Security and Moderation
 * pages in admin panel.
 *
 * @package Security
 */
class ManageSecurity_Controller extends Action_Controller
{
	/**
	 * This function passes control through to the relevant security tab.
	 *
	 * @event integrate_sa_modify_security
	 * @see Action_Controller::action_index()
	 */
	public function action_index()
	{
		global $context, $txt;

		theme()->getTemplates()->loadLanguageFile('Help');
		theme()->getTemplates()->loadLanguageFile('ManageSettings');

		$subActions = array(
			'general' => array($this, 'action_securitySettings_display', 'permission' => 'admin_forum'),
			'spam' => array($this, 'action_spamSettings_display', 'permission' => 'admin_forum'),
			'badbehavior' => array($this, 'action_bbSettings_display', 'permission' => 'admin_forum'),
			'moderation' => array($this, 'action_moderationSettings_display', 'enabled' => in_array('w', $context['admin_features']), 'permission' => 'admin_forum'),
		);

		// Action control
		$action = new Action('modify_security');

		// Load up all the tabs...
		$context[$context['admin_menu_name']]['tab_data'] = array(
			'title' => $txt['admin_security_moderation'],
			'help' => 'securitysettings',
			'description' => $txt['security_settings_desc'],
			'tabs' => array(
				'general' => array(
				),
				'spam' => array(
					'description' => $txt['antispam_Settings_desc'],
				),
				'badbehavior' => array(
					'description' => $txt['badbehavior_desc'],
				),
				'moderation' => array(
				),
			),
		);

		// By default do the basic settings, call integrate_sa_modify_security
		$subAction = $action->initialize($subActions, 'general');

		// Last pieces of the puzzle
		$context['sub_action'] = $subAction;
		$context['page_title'] = $txt['admin_security_moderation'];
		$context['sub_template'] = 'show_settings';

		// Call the right function for this sub-action.
		$action->dispatch($subAction);
	}

	/**
	 * Handle settings regarding general security of the site.
	 *
	 * - Uses a settings form for security options.
	 *
	 * @event integrate_save_general_security_settings
	 */
	public function action_securitySettings_display()
	{
		global $txt, $scripturl, $context;

		// Initialize the form
		$settingsForm = new Settings_Form(Settings_Form::DB_ADAPTER);

		// Initialize it with our settings
		$settingsForm->setConfigVars($this->_securitySettings());

		// Saving?
		if (isset($this->_req->query->save))
		{
			checkSession();

			$settingsForm->setConfigValues((array) $this->_req->post);
			$settingsForm->save();

			call_integration_hook('integrate_save_general_security_settings');

			writeLog();
			redirectexit('action=admin;area=securitysettings;sa=general');
		}

		$context['post_url'] = $scripturl . '?action=admin;area=securitysettings;save;sa=general';
		$context['settings_title'] = $txt['mods_cat_security_general'];

		$settingsForm->prepare();
	}

	/**
	 * Allows to display and eventually change the moderation settings of the forum.
	 *
	 * - Uses the moderation settings form.
	 *
	 * @event integrate_save_moderation_settings
	 */
	public function action_moderationSettings_display()
	{
		global $txt, $scripturl, $context, $modSettings;

		// Initialize the form
		$settingsForm = new Settings_Form(Settings_Form::DB_ADAPTER);

		// Initialize it with our settings
		$config_vars = $this->_moderationSettings();
		$settingsForm->setConfigVars($config_vars);

		// Saving?
		if (isset($this->_req->query->save))
		{
			checkSession();

			// Make sure these don't have an effect.
			if ($modSettings['warning_settings'][0] != 1)
			{
				$this->_req->post->warning_watch = 0;
				$this->_req->post->warning_moderate = 0;
				$this->_req->post->warning_mute = 0;
			}
			else
			{
				$this->_req->post->warning_watch = min($this->_req->post->warning_watch, 100);
				$this->_req->post->warning_moderate = $modSettings['postmod_active'] ? min($this->_req->post->warning_moderate, 100) : 0;
				$this->_req->post->warning_mute = min($this->_req->post->warning_mute, 100);
			}

			// Fix the warning setting array!
			$this->_req->post->warning_settings = '1,' . min(100, (int) $this->_req->post->user_limit) . ',' . min(100, (int) $this->_req->post->warning_decrement);
			$config_vars[] = array('text', 'warning_settings');
			unset($config_vars['rem1'], $config_vars['rem2']);

			call_integration_hook('integrate_save_moderation_settings');

			$settingsForm->setConfigVars($config_vars);
			$settingsForm->setConfigValues((array) $this->_req->post);
			$settingsForm->save();
			redirectexit('action=admin;area=securitysettings;sa=moderation');
		}

		// We actually store lots of these together - for efficiency.
		list ($modSettings['warning_enable'], $modSettings['user_limit'], $modSettings['warning_decrement']) = explode(',', $modSettings['warning_settings']);

		$context['post_url'] = $scripturl . '?action=admin;area=securitysettings;save;sa=moderation';
		$context['settings_title'] = $txt['moderation_settings'];
		$context['settings_message'] = $txt['warning_enable'];

		$settingsForm->prepare();
	}

	/**
	 * Handles admin security spam settings.
	 *
	 * - Displays a page with settings and eventually allows the admin to change them.
	 *
	 * @event integrate_save_spam_settings
	 */
	public function action_spamSettings_display()
	{
		global $txt, $scripturl, $context, $modSettings;

		// Initialize the form
		$settingsForm = new Settings_Form(Settings_Form::DB_ADAPTER);

		// Initialize it with our settings
		$config_vars = $this->_spamSettings();
		$settingsForm->setConfigVars($config_vars);

		// Saving?
		if (isset($this->_req->query->save))
		{
			checkSession();

			// Fix PM settings.
			$this->_req->post->pm_spam_settings = (int) $this->_req->post->max_pm_recipients . ',' . (int) $this->_req->post->pm_posts_verification . ',' . (int) $this->_req->post->pm_posts_per_hour;

			// Guest requiring verification!
			if (empty($this->_req->post->posts_require_captcha) && !empty($this->_req->post->guests_require_captcha))
				$this->_req->post->posts_require_captcha = -1;

			unset($config_vars['pm1'], $config_vars['pm2'], $config_vars['pm3'], $config_vars['guest_verify']);

			$config_vars[] = array('text', 'pm_spam_settings');

			call_integration_hook('integrate_save_spam_settings');

			// Now save.
			$settingsForm->setConfigValues((array) $this->_req->post);
			$settingsForm->save();
			Cache::instance()->remove('verificationQuestionIds');
			redirectexit('action=admin;area=securitysettings;sa=spam');
		}

		// Add in PM spam settings on the fly
		list ($modSettings['max_pm_recipients'], $modSettings['pm_posts_verification'], $modSettings['pm_posts_per_hour']) = explode(',', $modSettings['pm_spam_settings']);

		// And the same for guests requiring verification.
		$modSettings['guests_require_captcha'] = !empty($modSettings['posts_require_captcha']);
		$modSettings['posts_require_captcha'] = !isset($modSettings['posts_require_captcha']) || $modSettings['posts_require_captcha'] == -1 ? 0 : $modSettings['posts_require_captcha'];

		// Some minor javascript for the guest post setting.
		if ($modSettings['posts_require_captcha'])
			theme()->addInlineJavascript('document.getElementById(\'guests_require_captcha\').disabled = true;', true);

		$context['post_url'] = $scripturl . '?action=admin;area=securitysettings;save;sa=spam';
		$context['settings_title'] = $txt['antispam_Settings'];
		$settingsForm->prepare();
	}

	/**
	 * Change the way bad behavior ... well behaves
	 */
	public function action_bbSettings_display()
	{
		global $txt, $scripturl, $context, $modSettings, $boardurl;

		// Initialize the form
		$settingsForm = new Settings_Form(Settings_Form::DB_ADAPTER);

		// Initialize it with our settings
		$settingsForm->setConfigVars($this->_bbSettings());

		// Our callback templates are here
		theme()->getTemplates()->load('BadBehavior');

		// Any errors to display?
		if ($context['invalid_badbehavior_httpbl_key'])
		{
			$context['settings_message'][] = $txt['badbehavior_httpbl_key_invalid'];
			$context['error_type'] = 'warning';
		}

		// Have we blocked anything in the last 7 days?
		if (!empty($modSettings['badbehavior_enabled']))
			$context['settings_message'][] = bb2_insert_stats(true) . ' <a class="linkbutton" href="' . $boardurl . '/index.php?action=admin;area=logs;sa=badbehaviorlog;desc">' . $txt['badbehavior_details'] . '</a>';

		// Current whitelist data
		$whitelist = array('badbehavior_ip_wl', 'badbehavior_useragent_wl', 'badbehavior_url_wl');
		foreach ($whitelist as $list)
		{
			$context[$list] = array();
			$context[$list . '_desc'] = array();

			if (!empty($modSettings[$list]))
				$context[$list] = Util::unserialize($modSettings[$list]);

			if (!empty($modSettings[$list . '_desc']))
				$context[$list . '_desc'] = Util::unserialize($modSettings[$list . '_desc']);
		}

		// Saving?
		if (isset($this->_req->query->save))
		{
			checkSession();

			// Make sure Bad Behavior defaults are set if nothing was specified
			$this->_req->post->badbehavior_httpbl_threat = empty($this->_req->post->badbehavior_httpbl_threat) ? 25 : $this->_req->post->badbehavior_httpbl_threat;
			$this->_req->post->badbehavior_httpbl_maxage = empty($this->_req->post->badbehavior_httpbl_maxage) ? 30 : $this->_req->post->badbehavior_httpbl_maxage;
			$this->_req->post->badbehavior_reverse_proxy_header = empty($this->_req->post->badbehavior_reverse_proxy_header) ? 'X-Forwarded-For' : $this->_req->post->badbehavior_reverse_proxy_header;

			// Build up the whitelist options
			foreach ($whitelist as $list)
			{
				$this_list = array();
				$this_desc = array();

				if (isset($this->_req->post->{$list}))
				{
					// Clear blanks from the data field, only grab the comments that don't have blank data value
					$this_list = array_map('trim', array_filter($this->_req->post->{$list}));
					$this_desc = array_intersect_key($this->_req->post->{$list . '_desc'}, $this_list);
				}

				updateSettings(array($list => serialize($this_list), $list . '_desc' => serialize($this_desc)));
			}

			$settingsForm->setConfigValues((array) $this->_req->post);
			$settingsForm->save();
			redirectexit('action=admin;area=securitysettings;sa=badbehavior');
		}

		$context['post_url'] = $scripturl . '?action=admin;area=securitysettings;save;sa=badbehavior';

		// Javascript vars for the "add more xyz" buttons in the callback forms
		theme()->addJavascriptVar(array(
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

		$settingsForm->prepare();
	}

	/**
	 * Moderation settings.
	 *
	 * @event integrate_modify_moderation_settings add new moderation settings
	 */
	private function _moderationSettings()
	{
		global $txt;

		$config_vars = array(
			// Warning system?
			array('int', 'warning_watch', 'subtext' => $txt['setting_warning_watch_note'], 'help' => 'watch_enable'),
			'moderate' => array('int', 'warning_moderate', 'subtext' => $txt['setting_warning_moderate_note'], 'help' => 'moderate_enable'),
			array('int', 'warning_mute', 'subtext' => $txt['setting_warning_mute_note'], 'help' => 'mute_enable'),
			'rem1' => array('int', 'user_limit', 'subtext' => $txt['setting_user_limit_note'], 'help' => 'perday_limit'),
			'rem2' => array('int', 'warning_decrement', 'subtext' => $txt['setting_warning_decrement_note']),
			array('select', 'warning_show', 'subtext' => $txt['setting_warning_show_note'], array($txt['setting_warning_show_mods'], $txt['setting_warning_show_user'], $txt['setting_warning_show_all'])),
		);

		call_integration_hook('integrate_modify_moderation_settings', array(&$config_vars));

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
	 *
	 * @event integrate_general_security_settings add more security settings
	 */
	private function _securitySettings()
	{
		global $txt;

		// Set up the config array for use
		$config_vars = array(
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
				array('check', 'enableOTP'),
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
	 *
	 * @event integrate_spam_settings mmmm Spam
	 */
	private function _spamSettings()
	{
		global $txt, $modSettings;

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

		// Cannot use moderation if post moderation is not enabled.
		if (!$modSettings['postmod_active'])
			unset($config_vars['moderate']);

		require_once(SUBSDIR . '/VerificationControls.class.php');
		$known_verifications = loadVerificationControls();

		foreach ($known_verifications as $verification)
		{
			$class_name = 'Verification_Controls_' . ucfirst($verification);
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
