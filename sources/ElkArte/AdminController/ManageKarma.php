<?php

/**
 * Manage karma settings.
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
use ElkArte\SettingsForm\SettingsForm;
use ElkArte\Languages\Txt;

/**
 * Karma administration controller.
 * This class allows modifying karma settings for the forum.
 */
class ManageKarma extends AbstractController
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
			'karma' => [$this, 'action_karmaSettings_display', 'enabled' => featureEnabled('k'), 'permission' => 'admin_forum'],
		];

		// Set up the action control
		$action = new Action('modify_karma');

		// There is only one option
		$subAction = $action->initialize($subActions, 'karma');
		$action->dispatch($subAction);
	}

	/**
	 * Display configuration settings page for karma settings.
	 *
	 * - Accessed from ?action=admin;area=karma;
	 *
	 * @event integrate_save_karma_settings
	 */
	public function action_karmaSettings_display(): void
	{
		global $txt, $context;

		// Initialize the form
		$settingsForm = new SettingsForm(SettingsForm::DB_ADAPTER);

		// Initialize it with our settings
		$settingsForm->setConfigVars($this->_karmaSettings());

		// Saving?
		if ($this->_req->hasQuery('save'))
		{
			checkSession();

			call_integration_hook('integrate_save_karma_settings');

			$settingsForm->setConfigValues((array) $this->_req->post);
			$settingsForm->save();
			redirectexit('action=admin;area=karma');
		}

		$context['post_url'] = getUrl('admin', ['action' => 'admin', 'area' => 'karma', 'save']);
		$context['settings_title'] = $txt['karma'];
		$context['sub_template'] = 'show_settings';
		$context['page_title'] = $txt['modSettings_title'];

		$settingsForm->prepare();
	}

	/**
	 * Return karma settings.
	 *
	 * @event integrate_modify_karma_settings Adds to Configuration->Karma
	 */
	private function _karmaSettings()
	{
		global $txt;

		$config_vars = [
			// Karma - On or off?
			['select', 'karmaMode', explode('|', $txt['karma_options'])],
			'',
			// Who can do it... and who is restricted by time limits?
			['int', 'karmaMinPosts', 6, 'postinput' => $txt['manageposts_posts']],
			['float', 'karmaWaitTime', 6, 'postinput' => $txt['hours']],
			['check', 'karmaTimeRestrictAdmins'],
			['check', 'karmaDisableSmite'],
			'',
			// What does it look like? [smite]?
			['text', 'karmaLabel'],
			['text', 'karmaApplaudLabel', 'mask' => 'nohtml'],
			['text', 'karmaSmiteLabel', 'mask' => 'nohtml'],
		];

		call_integration_hook('integrate_modify_karma_settings', [&$config_vars]);

		return $config_vars;
	}

	/**
	 * Public method to return the karma settings, used for admin search
	 */
	public function karmaSettings_search()
	{
		global $modSettings;

		// Karma - On or off?
		if (empty($modSettings['karmaMode']))
		{
			return ['check', 'dummy_karma'];
		}

		return $this->_karmaSettings();
	}
}
