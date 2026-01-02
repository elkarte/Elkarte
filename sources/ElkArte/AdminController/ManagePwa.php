<?php

/**
 * Allows for the modifying of the Progressive Web Application (PWA) settings.
 *
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * @version 2.0 Beta 1
 */

namespace ElkArte\AdminController;

use ElkArte\AbstractController;
use ElkArte\Action;
use ElkArte\Helper\DataValidator;
use ElkArte\Languages\Txt;
use ElkArte\SettingsForm\SettingsForm;

/**
 * PWA administration controller.
 * This class allows modifying admin PWA settings for the forum.
 */
class ManagePwa extends AbstractController
{
	/**
	 * Pre-dispatch, called before other methods.
	 */
	public function pre_dispatch()
	{
		// We need this in a few places, so it's easier to have it loaded here
		require_once(SUBSDIR . '/ManageFeatures.subs.php');

		Txt::load('Help+ManageSettings');
	}

	/**
	 * Default action for this controller.
	 */
	public function action_index()
	{
		$subActions = [
			'pwa' => [$this, 'action_pwaSettings_display', 'permission' => 'admin_forum'],
		];

		// Set up the action control
		$action = new Action('modify_pwa');

		// There is only one option
		$subAction = $action->initialize($subActions, 'pwa');
		$action->dispatch($subAction);
	}

	/**
	 * Display configuration settings page for progressive web application settings.
	 *
	 * - Accessed from ?action=admin;area=featuresettings;sa=pwa;
	 *
	 * @event integrate_save_pwa_settings
	 */
	public function action_pwaSettings_display(): void
	{
		global $txt, $context;

		// Initialize the form
		$settingsForm = new SettingsForm(SettingsForm::DB_ADAPTER);

		// Initialize it with our settings
		$settingsForm->setConfigVars($this->_pwaSettings());

		// Saving, lots of checks then
		if ($this->_req->hasQuery('save'))
		{
			checkSession();

			call_integration_hook('integrate_save_pwa_settings');

			// Don't allow it to be enabled if we don't have SSL
			$canUse = detectServer()->supportsSSL();
			if (!$canUse)
			{
				$this->_req->post->pwa_enabled = 0;
			}

			// And you must enable this if PWA is enabled
			if ($this->_req->getPost('pwa_enabled', 'intval') === 1)
			{
				$this->_req->post->pwa_manifest_enabled = 1;
			}

			$validator = new DataValidator();
			$validation_rules = [
				'pwa_theme_color' => 'valid_color',
				'pwa_background_color' => 'valid_color',
				'pwa_short_name' => 'max_length[12]'
			];

			// Only check the rest if they entered something.
			$valid_urls = ['pwa_small_icon', 'pwa_large_icon', 'favicon_icon', 'apple_touch_icon'];
			foreach ($valid_urls as $url)
			{
				if ($this->_req->getPost($url, 'trim') !== '')
				{
					$validation_rules[$url] = 'valid_url';
				}
			}
			$validator->validation_rules($validation_rules);

			if (!$validator->validate($this->_req->post))
			{
				// Some input error, let's tell them what is wrong
				$context['error_type'] = 'minor';
				$context['settings_message'] = [];
				foreach ($validator->validation_errors() as $error)
				{
					$context['settings_message'][] = $error;
				}
			}
			else
			{
				$settingsForm->setConfigValues((array) $this->_req->post);
				$settingsForm->save();
				redirectexit('action=admin;area=featuresettings;sa=pwa');
			}
		}

		$context['post_url'] = getUrl('admin', ['action' => 'admin', 'area' => 'featuresettings', 'sa'=>'pwa', 'save']);
		$context['settings_title'] = $txt['pwa_settings'];
		theme()->addInlineJavascript('
			pwaPreview("pwa_small_icon");
			pwaPreview("pwa_large_icon");
			pwaPreview("favicon_icon");
			pwaPreview("apple_touch_icon");', true);

		$settingsForm->prepare();
	}

	/**
	 * Return PWA settings.
	 *
	 * @event integrate_modify_pwa_settings Adds to Configuration->Pwa
	 */
	private function _pwaSettings()
	{
		global $txt;

		// PWA requires SSL
		$canUse = detectServer()->supportsSSL();

		$config_vars = [
			// PWA - On or off?
			['check', 'pwa_enabled', 'disabled' => !$canUse, 'invalid' => !$canUse, 'postinput' => !$canUse ? $txt['pwa_disabled'] : ''],
			'',
			['check', 'pwa_manifest_enabled', 'helptext' => $txt['pwa_manifest_enabled_desc']],
			['text', 'pwa_short_name', 12, 'mask' => 'nohtml', 'helptext' => $txt['pwa_short_name_desc'], 'maxlength' => 12],
			['color', 'pwa_theme_color', 'helptext' => $txt['pwa_theme_color_desc']],
			['color', 'pwa_background_color', 'helptext' => $txt['pwa_background_color_desc']],
			'',
			['url', 'pwa_small_icon', 'size' => 40, 'helptext' => $txt['pwa_small_icon_desc'], 'onchange' => "pwaPreview('pwa_small_icon');"],
			['url', 'pwa_large_icon', 'size' => 40, 'helptext' => $txt['pwa_large_icon_desc'], 'onchange' => "pwaPreview('pwa_large_icon');"],
			['title', 'other_icons_title'],
			['url', 'favicon_icon', 'size' => 40, 'helptext' => $txt['favicon_icon_desc'], 'onchange' => "pwaPreview('favicon_icon');"],
			['url', 'apple_touch_icon', 'size' => 40, 'helptext' => $txt['apple_touch_icon_desc'], 'onchange' => "pwaPreview('apple_touch_icon');"],
		];

		call_integration_hook('integrate_modify_pwa_settings', [&$config_vars]);

		return $config_vars;
	}

	/**
	 * Public method to return the PWA settings, used for admin search
	 */
	public function pwaSettings_search()
	{
		return $this->_pwaSettings();
	}
}
