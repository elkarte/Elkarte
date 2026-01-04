<?php

/**
 * Handles the warning moderation settings in the admin panel.
 *
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * @version 2.0 Beta 1
 *
 */

namespace ElkArte\AdminController;

use ElkArte\AbstractController;
use ElkArte\Action;
use ElkArte\Languages\Txt;
use ElkArte\SettingsForm\SettingsForm;

/**
 * ManageWarnings controller handles the moderation warning settings
 * pages in admin panel.
 *
 * @package Warnings
 */
class ManageWarnings extends AbstractController
{
	/**
	 * This function passes control through to the relevant moderation warning tab.
	 *
	 * @event integrate_sa_manage_warnings
	 * @see AbstractController::action_index()
	 */
	public function action_index()
	{
		global $context, $txt;

		Txt::load('Help+ManageSettings');

		$subActions = [
			'moderation' => [$this, 'action_moderationSettings_display', 'enabled' => featureEnabled('w'), 'permission' => 'admin_forum'],
		];

		// Action control
		$action = new Action('manage_warnings');

		// By default, do the basic settings, call integrate_sa_manage_warnings
		$subAction = $action->initialize($subActions, 'moderation');

		// Last pieces of the puzzle
		$context['sub_action'] = $subAction;
		$context['page_title'] = $txt['admin_security_moderation'];
		$context['sub_template'] = 'show_settings';

		// Load up all the tabs...
		$context[$context['admin_menu_name']]['object']->prepareTabData([
			'title' => 'moderation_warning_short',
			'help' => 'securitysettings',
			'description' => 'warning_enable',
		]);

		// Call the right function for this sub-action.
		$action->dispatch($subAction);
	}

	/**
	 * Allows displaying and eventually change the moderation settings of the forum.
	 *
	 * - Uses the moderation settings form.
	 *
	 * @event integrate_save_moderation_settings
	 */
	public function action_moderationSettings_display(): void
	{
		global $txt, $context, $modSettings;

		// Initialize the form
		$settingsForm = new SettingsForm(SettingsForm::DB_ADAPTER);

		// Initialize it with our settings
		$config_vars = $this->_warningSettings();
		$settingsForm->setConfigVars($config_vars);

		// Saving?
		if ($this->_req->hasQuery('save'))
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
			$config_vars[] = ['text', 'warning_settings'];
			unset($config_vars['rem1'], $config_vars['rem2']);

			call_integration_hook('integrate_save_moderation_settings');

			$settingsForm->setConfigVars($config_vars);
			$settingsForm->setConfigValues((array) $this->_req->post);
			$settingsForm->save();
			redirectexit('action=admin;area=warnings;sa=moderation');
		}

		// We actually store lots of these together - for efficiency.
		[$modSettings['warning_enable'], $modSettings['user_limit'], $modSettings['warning_decrement']] = explode(',', $modSettings['warning_settings']);

		$context['post_url'] = getUrl('admin', ['action' => 'admin', 'area' => 'warnings', 'save']);

		$settingsForm->prepare();
	}

	/**
	 * Moderation settings.
	 *
	 * @event integrate_modify_moderation_settings add new moderation settings
	 */
	private function _warningSettings()
	{
		global $txt;

		$config_vars = [
			// Warning system
			['int', 'warning_watch', 'subtext' => $txt['setting_warning_watch_note'], 'help' => 'watch_enable'],
			'moderate' => ['int', 'warning_moderate', 'subtext' => $txt['setting_warning_moderate_note'], 'help' => 'moderate_enable'],
			['int', 'warning_mute', 'subtext' => $txt['setting_warning_mute_note'], 'help' => 'mute_enable'],
			'rem1' => ['int', 'user_limit', 'subtext' => $txt['setting_user_limit_note'], 'help' => 'perday_limit'],
			'rem2' => ['int', 'warning_decrement', 'subtext' => $txt['setting_warning_decrement_note']],
			['select', 'warning_show', 'subtext' => $txt['setting_warning_show_note'], [$txt['setting_warning_show_mods'], $txt['setting_warning_show_user'], $txt['setting_warning_show_all']]],
		];

		call_integration_hook('integrate_modify_moderation_settings', [&$config_vars]);

		return $config_vars;
	}

	/**
	 * Public method to return moderation settings, used for admin search
	 */
	public function warningSettings_search()
	{
		global $modSettings;

		if (!featureEnabled('w'))
		{
			return ['check', 'dummy_enable'];
		}

		return $this->_warningSettings();
	}
}
