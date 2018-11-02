<?php

/**
 * Allows for the modifying of the forum drafts settings.
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 2.0 dev
 */

namespace ElkArte\admin;

/**
 * Drafts administration controller.
 * This class allows to modify admin drafts settings for the forum.
 *
 * @package Drafts
 */
class ManageDraftsModule extends \ElkArte\AbstractController
{
	/**
	 * Used to add the Drafts entry to the Core Features list.
	 *
	 * @param mixed[] $core_features The core features array
	 */
	public static function addCoreFeature(&$core_features)
	{
		$core_features['dr'] = array(
			'url' => 'action=admin;area=managedrafts',
			'settings' => array(
				'drafts_enabled' => 1,
				'drafts_post_enabled' => 2,
				'drafts_pm_enabled' => 2,
				'drafts_autosave_enabled' => 2,
				'drafts_show_saved_enabled' => 2,
			),
			'setting_callback' => function ($value) {
				require_once(SUBSDIR . '/ScheduledTasks.subs.php');
				toggleTaskStatusByName('remove_old_drafts', $value);

				$modules = array('admin', 'post', 'display', 'profile', 'personalmessage');

				// Enabling, let's register the modules and prepare the scheduled task
				if ($value)
				{
					enableModules('drafts', $modules);
					calculateNextTrigger('remove_old_drafts');
					Hooks::instance()->enableIntegration('Drafts_Integrate');
				}
				// Disabling, just forget about the modules
				else
				{
					disableModules('drafts', $modules);
					Hooks::instance()->disableIntegration('Drafts_Integrate');
				}
			},
		);
	}

	/**
	 * Integrate drafts in to the delete member chain
	 *
	 * @param int[] $users
	 */
	public static function integrate_delete_members($users)
	{
		$db = database();

		// Delete any drafts...
		$db->query('', '
			DELETE FROM {db_prefix}user_drafts
			WHERE id_member IN ({array_int:users})',
			array(
				'users' => $users,
			)
		);
	}

	/**
	 * Integrate draft permission in to the members and board permissions
	 *
	 * @param array $permissionGroups
	 * @param array $permissionList
	 */
	public static function integrate_load_permissions(&$permissionGroups, &$permissionList)
	{
		$permissionList['board'] += array(
			'post_draft' => array(false, 'topic'),
			'post_autosave_draft' => array(false, 'topic'),
		);

		$permissionList['membergroup'] += array(
			'pm_draft' => array(false, 'pm'),
			'pm_autosave_draft' => array(false, 'pm'),
		);
	}

	/**
	 * Integrate draft permission in to illegal guest permissions
	 */
	public static function integrate_load_illegal_guest_permissions()
	{
		global $context;

		$context['non_guest_permissions'] += array(
			'post_draft',
			'post_autosave_draft',
		);
	}

	/**
	 * Integrate draft options in to the topics maintenance procedures
	 *
	 * @param array $topics_actions
	 */
	public static function integrate_topics_maintenance(&$topics_actions)
	{
		global $txt;

		$topics_actions['olddrafts'] = array(
			'url' => getUrl('admin', ['action' => 'admin', 'area' => 'maintain', 'sa' => 'topics', 'activity' => 'olddrafts']),
			'title' => $txt['maintain_old_drafts'],
			'submit' => $txt['maintain_old_remove'],
			'confirm' => $txt['maintain_old_drafts_confirm'],
			'hidden' => array(
				'session_var' => 'session_id',
				'admin-maint_token_var' => 'admin-maint_token',
			)
		);
	}

	/**
	 * Drafts maintenance integration hooks
	 *
	 * @param array $subActions
	 */
	public static function integrate_sa_manage_maintenance(&$subActions)
	{
		$subActions['topics']['activities']['olddrafts'] = function () {
			$controller = new \ElkArte\admin\ManageDraftsModule(new Event_manager());
			$controller->pre_dispatch();
			$controller->action_olddrafts_display();
		};
	}

	/**
	 * This method removes old drafts.
	 */
	public function action_olddrafts_display()
	{
		global $context, $txt;

		validateToken('admin-maint');

		require_once(SUBSDIR . '/Drafts.subs.php');
		$drafts = getOldDrafts((int) $this->_req->post->draftdays);

		// If we have old drafts, remove them
		if (count($drafts) > 0)
			deleteDrafts($drafts, -1, false);

		// Errors?  no errors, only success !
		$context['maintenance_finished'] = array(
			'errors' => array(sprintf($txt['maintain_done'], $txt['maintain_old_drafts'])),
		);
	}

	/**
	 * Default method.
	 * Requires admin_forum permissions
	 *
	 * @uses Drafts language file
	 */
	public function action_index()
	{
		isAllowedTo('admin_forum');
		theme()->getTemplates()->loadLanguageFile('Drafts');

		$this->action_draftSettings_display();
	}

	/**
	 * Modify any setting related to drafts.
	 *
	 * - Requires the admin_forum permission.
	 * - Accessed from ?action=admin;area=managedrafts
	 *
	 * @event integrate_save_drafts_settings
	 * @uses Admin template, edit_topic_settings sub-template.
	 */
	public function action_draftSettings_display()
	{
		global $context, $txt;

		isAllowedTo('admin_forum');
		theme()->getTemplates()->loadLanguageFile('Drafts');

		// Initialize the form
		$settingsForm = new Settings_Form(Settings_Form::DB_ADAPTER);

		// Initialize it with our settings
		$settingsForm->setConfigVars($this->_settings());

		// Setup the template.
		$context['page_title'] = $txt['managedrafts_settings'];
		$context['sub_template'] = 'show_settings';
		$context[$context['admin_menu_name']]['tab_data'] = array(
			'title' => $txt['drafts'],
			'help' => '',
			'description' => $txt['managedrafts_settings_description'],
		);

		// Saving them ?
		if (isset($this->_req->query->save))
		{
			checkSession();

			call_integration_hook('integrate_save_drafts_settings');

			// Protect them from themselves.
			$this->_req->post->drafts_autosave_frequency = $this->_req->post->drafts_autosave_frequency < 30 ? 30 : $this->_req->post->drafts_autosave_frequency;

			$settingsForm->setConfigValues((array) $this->_req->post);
			$settingsForm->save();
			redirectexit('action=admin;area=managedrafts');
		}

		// Some javascript to enable / disable the frequency input box
		theme()->addInlineJavascript('
			var autosave = document.getElementById(\'drafts_autosave_enabled\');

			createEventListener(autosave);
			autosave.addEventListener(\'change\', toggle);
			toggle();

			function toggle()
			{
				var select_elem = document.getElementById(\'drafts_autosave_frequency\');

				select_elem.disabled = !autosave.checked;
			}', true);

		// Final settings...
		$context['post_url'] = getUrl('admin', ['action' => 'admin', 'area' => 'managedrafts', 'save']);
		$context['settings_title'] = $txt['managedrafts_settings'];

		// Prepare the settings...
		$settingsForm->prepare();
	}

	/**
	 * Returns all admin drafts settings in config_vars format.
	 *
	 * @event integrate_modify_drafts_settings
	 */
	private function _settings()
	{
		global $txt;

		theme()->getTemplates()->loadLanguageFile('Drafts');

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

		call_integration_hook('integrate_modify_drafts_settings', array(&$config_vars));

		return $config_vars;
	}

	/**
	 * Return the form settings for use in admin search
	 */
	public function settings_search()
	{
		return $this->_settings();
	}
}
