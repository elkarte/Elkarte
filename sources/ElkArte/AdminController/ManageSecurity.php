<?php

/**
 * Handles the Security and Moderation pages in the admin panel.  This includes
 * anti-spam, security, and moderation settings
 *
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * This file contains code covered by:
 * copyright: 2011 Simple Machines (http://www.simplemachines.org)
 *
 * @version 2.0 Beta 1
 *
 */

namespace ElkArte\AdminController;

use ElkArte\AbstractController;
use ElkArte\Action;
use ElkArte\Cache\Cache;
use ElkArte\Languages\Txt;
use ElkArte\SettingsForm\SettingsForm;

/**
 * ManageSecurity controller handles the Security and Moderation
 * pages in admin panel.
 *
 * @package Security
 */
class ManageSecurity extends AbstractController
{
	/**
	 * This function passes control through to the relevant security tab.
	 *
	 * @event integrate_sa_modify_security
	 * @see AbstractController::action_index()
	 */
	public function action_index()
	{
		global $context, $txt;

		Txt::load('Help+ManageSettings');

		$subActions = [
			'general' => [$this, 'action_securitySettings_display', 'permission' => 'admin_forum'],
			'spam' => [$this, 'action_spamSettings_display', 'permission' => 'admin_forum'],
		];

		// Action control
		$action = new Action('modify_security');

		// By default, do the basic settings, call integrate_sa_modify_security
		$subAction = $action->initialize($subActions, 'general');

		// Last pieces of the puzzle
		$context['sub_action'] = $subAction;
		$context['page_title'] = $txt['admin_security_moderation'];
		$context['sub_template'] = 'show_settings';

		// Load up all the tabs...
		$context[$context['admin_menu_name']]['object']->prepareTabData([
			'title' => 'admin_security_moderation',
			'help' => 'securitysettings',
			'description' => 'security_settings_desc',
		]);

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
	public function action_securitySettings_display(): void
	{
		global $txt, $context;

		// Initialize the form
		$settingsForm = new SettingsForm(SettingsForm::DB_ADAPTER);

		// Initialize it with our settings
		$settingsForm->setConfigVars($this->_securitySettings());

		// Saving?
		if ($this->_req->hasQuery('save'))
		{
			checkSession();

			$settingsForm->setConfigValues((array) $this->_req->post);
			$settingsForm->save();

			call_integration_hook('integrate_save_general_security_settings');

			writeLog();
			redirectexit('action=admin;area=securitysettings;sa=general');
		}

		$context['post_url'] = getUrl('admin', ['action' => 'admin', 'area' => 'securitysettings', 'save', 'sa' => 'general']);
		$context['settings_title'] = $txt['mods_cat_security_general'];

		$settingsForm->prepare();
	}

	/**
	 * Security settings.
	 *
	 * @event integrate_general_security_settings add more security settings
	 */
	private function _securitySettings()
	{
		global $txt, $context, $modSettings;

		// See if they supplied a valid-looking http:BL API Key
		$context['invalid_badbehavior_httpbl_key'] = (!empty($modSettings['badbehavior_httpbl_key']) && (strlen($modSettings['badbehavior_httpbl_key']) !== 12));

		// Set up the config array for use
		$config_vars = [
			['int', 'failed_login_threshold'],
			['int', 'loginHistoryDays'],
			'',
			['int', 'admin_session_lifetime'],
			['check', 'auto_admin_session'],
			['check', 'securityDisable'],
			['check', 'securityDisable_moderate'],
			'',
			['check', 'enableOTP'],
			'',
			// Reactive on email and approve on delete
			['check', 'send_validation_onChange'],
			['check', 'approveAccountDeletion'],
			'',
			// Password strength.
			['select', 'password_strength', [$txt['setting_password_strength_low'], $txt['setting_password_strength_medium'], $txt['setting_password_strength_high']]],
			['check', 'enable_password_conversion'],
			'',
			['select', 'frame_security', ['SAMEORIGIN' => $txt['setting_frame_security_SAMEORIGIN'], 'DENY' => $txt['setting_frame_security_DENY'], 'DISABLE' => $txt['setting_frame_security_DISABLE']]],
			// Bad Behavior
			['title', 'badbehavior_title'],
			['check', 'badbehavior_accept_header'],
			['text', 'badbehavior_httpbl_key', 12, 'invalid' => $context['invalid_badbehavior_httpbl_key']],
			['int', 'badbehavior_httpbl_threat', 'postinput' => $txt['badbehavior_httpbl_threat_desc']],
			['int', 'badbehavior_httpbl_maxage', 'postinput' => $txt['badbehavior_httpbl_maxage_desc']],
		];

		call_integration_hook('integrate_general_security_settings', [&$config_vars]);

		return $config_vars;
	}

	/**
	 * Handles admin security spam settings.
	 *
	 * - Displays a page with settings and eventually allows the admin to change them.
	 *
	 * @event integrate_save_spam_settings
	 */
	public function action_spamSettings_display(): void
	{
		global $txt, $context, $modSettings;

		// Initialize the form
		$settingsForm = new SettingsForm(SettingsForm::DB_ADAPTER);

		// Initialize it with our settings
		$config_vars = $this->_spamSettings();
		$settingsForm->setConfigVars($config_vars);

		// Saving?
		if ($this->_req->hasQuery('save'))
		{
			checkSession();

			// Guest requiring verification!
			if (empty($this->_req->post->posts_require_captcha) && !empty($this->_req->post->guests_require_captcha))
			{
				$this->_req->post->posts_require_captcha = -1;
			}

			unset($config_vars['guest_verify']);

			call_integration_hook('integrate_save_spam_settings');

			// Now save.
			$settingsForm->setConfigValues((array) $this->_req->post);
			$settingsForm->save();
			Cache::instance()->remove('verificationQuestionIds');
			redirectexit('action=admin;area=securitysettings;sa=spam');
		}

		// And the same for guests requiring verification.
		$modSettings['guests_require_captcha'] = !empty($modSettings['posts_require_captcha']);
		$modSettings['posts_require_captcha'] = !isset($modSettings['posts_require_captcha']) || $modSettings['posts_require_captcha'] == -1 ? 0 : $modSettings['posts_require_captcha'];

		// Some minor JavaScript for the guest post setting.
		if ($modSettings['posts_require_captcha'])
		{
			theme()->addInlineJavascript("document.getElementById('guests_require_captcha').disabled = true;", true);
		}

		$context['post_url'] = getUrl('admin', ['action' => 'admin', 'area' => 'securitysettings', 'save', 'sa' => 'spam']);
		$context['settings_title'] = $txt['antispam_Settings'];
		$settingsForm->prepare();
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
		$config_vars = [
			['check', 'reg_verification'],
			['check', 'search_enable_captcha'],
			// This, my friend, is a cheat :p
			'guest_verify' => ['check', 'guests_require_captcha', 'postinput' => $txt['setting_guests_require_captcha_desc']],
			['int', 'posts_require_captcha', 'postinput' => $txt['posts_require_captcha_desc'], 'onchange' => "if (this.value > 0){ document.getElementById('guests_require_captcha').checked = true; document.getElementById('guests_require_captcha').disabled = true;} else {document.getElementById('guests_require_captcha').disabled = false;}"],
			['check', 'guests_report_require_captcha'],
		];

		// Cannot use moderation if post-moderation is not enabled.
		if (!$modSettings['postmod_active'])
		{
			unset($config_vars['moderate']);
		}

		// @todo: it may be removed, it may stay, the two hooks may have different functions
		call_integration_hook('integrate_spam_settings', [&$config_vars]);

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
	 * Public method to return spam settings, used in admin search
	 */
	public function spamSettings_search()
	{
		return $this->_spamSettings();
	}
}
