<?php

/**
 * Manage personal messages settings.
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
use ElkArte\Languages\Txt;
use ElkArte\SettingsForm\SettingsForm;

/**
 * PM administration controller.
 * This class allows modifying personal messages settings for the forum.
 */
class ManagePmSettings extends AbstractController
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
			'pmsettings' => [$this, 'action_pmsettings', 'permission' => 'admin_forum'],
		];

		// Set up the action control
		$action = new Action('modify_pmsettings');

		// There is only one option
		$subAction = $action->initialize($subActions, 'pmsettings');
		$action->dispatch($subAction);
	}

	/**
	 * Editing personal messages settings
	 *
	 * - Accessed with ?action=admin;area=pmsettings
	 *
	 * @event integrate_save_pmsettings_settings
	 */
	public function action_pmsettings(): void
	{
		global $txt, $context;

		// Initialize the form
		$settingsForm = new SettingsForm(SettingsForm::DB_ADAPTER);

		// Initialize it with our settings
		$settingsForm->setConfigVars($this->_pmSettings());

		require_once(SUBSDIR . '/PersonalMessage.subs.php');
		Txt::load('ManageMembers');

		$context['pm_limits'] = loadPMLimits();

		// Saving?
		if ($this->_req->hasQuery('save'))
		{
			checkSession();

			require_once(SUBSDIR . '/Membergroups.subs.php');
			foreach ($context['pm_limits'] as $group_id => $group)
			{
				if (!isset($this->_req->post->group[$group_id]))
				{
					continue;
				}

				if ($this->_req->post->group[$group_id] == $group['max_messages'])
				{
					continue;
				}

				updateMembergroupProperties(['current_group' => $group_id, 'max_messages' => $this->_req->post->group[$group_id]]);
			}

			call_integration_hook('integrate_save_pmsettings_settings');

			$settingsForm->setConfigValues((array) $this->_req->post);
			$settingsForm->save();
			redirectexit('action=admin;area=featuresettings;sa=pmsettings');
		}

		$context['post_url'] = getUrl('admin', ['action' => 'admin', 'area' => 'featuresettings', 'sa' => 'pmsettings', 'save']);
		$context['settings_title'] = $txt['personal_messages'];
		$context['sub_template'] = 'show_settings';
		$context['page_title'] = $txt['modSettings_title'];

		$settingsForm->prepare();
	}

	/**
	 * Return pm settings.
	 *
	 * - Used in admin center search and settings form
	 *
	 * @event integrate_modify_pmsettings_settings Adds / Modifies PM Settings
	 */
	private function _pmSettings()
	{
		global $txt;

		$config_vars = [
			// Reporting of personal messages?
			['check', 'enableReportPM'],
			// Inline permissions.
			['permissions', 'pm_send'],
			// PM Settings
			['title', 'antispam_PM'],
			'pm1' => ['int', 'max_pm_recipients', 'postinput' => $txt['max_pm_recipients_note']],
			'pm2' => ['int', 'pm_posts_verification', 'postinput' => $txt['pm_posts_verification_note']],
			'pm3' => ['int', 'pm_posts_per_hour', 'postinput' => $txt['pm_posts_per_hour_note']],
			['title', 'membergroups_max_messages'],
			['desc', 'membergroups_max_messages_desc'],
			['callback', 'pm_limits'],
		];

		call_integration_hook('integrate_modify_pmsettings_settings', [&$config_vars]);

		return $config_vars;
	}

	/**
	 * Public method to return the PM settings, used for admin search
	 */
	public function pmSettings_search()
	{
		return $this->_pmSettings();
	}
}
