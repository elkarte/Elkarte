<?php

/**
 * Manage likes settings.
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
 * Likes administration controller.
 * This class allows modifying likes settings for the forum.
 */
class ManageLikes extends AbstractController
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
			'likes' => [$this, 'action_likesSettings_display', 'enabled' => featureEnabled('l'), 'permission' => 'admin_forum'],
		];

		// Set up the action control
		$action = new Action('modify_likes');

		// There is only one option
		$subAction = $action->initialize($subActions, 'likes');
		$action->dispatch($subAction);
	}

	/**
	 * Display configuration settings page for likes settings.
	 *
	 * - Accessed from ?action=admin;area=featuresettings;sa=likes;
	 *
	 * @event integrate_save_likes_settings
	 */
	public function action_likesSettings_display(): void
	{
		global $txt, $context;

		// Initialize the form
		$settingsForm = new SettingsForm(SettingsForm::DB_ADAPTER);

		// Initialize it with our settings
		$settingsForm->setConfigVars($this->_likesSettings());

		// Saving?
		if ($this->_req->hasQuery('save'))
		{
			checkSession();

			call_integration_hook('integrate_save_likes_settings');

			$settingsForm->setConfigValues((array) $this->_req->post);
			$settingsForm->save();
			redirectexit('action=admin;area=featuresettings;sa=likes');
		}

		$context['post_url'] = getUrl('admin', ['action' => 'admin', 'area' => 'featuresettings', 'sa' => 'likes', 'save']);
		$context['settings_title'] = $txt['likes'];
		$context['sub_template'] = 'show_settings';
		$context['page_title'] = $txt['modSettings_title'];

		$settingsForm->prepare();
	}

	/**
	 * Return likes settings.
	 *
	 * @event integrate_modify_likes_settings Adds to Configuration->Likes
	 */
	private function _likesSettings()
	{
		global $txt;

		$config_vars = [
			// Likes - On or off?
			['check', 'likes_enabled'],
			'',
			// Who can do it... and who is restricted by count limits?
			['int', 'likeMinPosts', 6, 'postinput' => $txt['manageposts_posts']],
			['int', 'likeWaitTime', 6, 'postinput' => $txt['minutes']],
			['int', 'likeWaitCount', 6],
			['check', 'likeRestrictAdmins'],
			['check', 'likeAllowSelf'],
			['check', 'useLikesNotViews'],
			'',
			['int', 'likeDisplayLimit', 6]
		];

		call_integration_hook('integrate_modify_likes_settings', [&$config_vars]);

		return $config_vars;
	}

	/**
	 * Public method to return the likes settings, used for admin search
	 */
	public function likesSettings_search()
	{
		global $modSettings;

		// Likes - On or off?
		if (empty($modSettings['enable_likes']))
		{
			return ['check', 'dummy_likes'];
		}

		return $this->_likesSettings();
	}
}
