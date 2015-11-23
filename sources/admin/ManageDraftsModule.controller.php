<?php

/**
 * Allows for the modifying of the forum drafts settings.
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 1.1 dev
 */

if (!defined('ELK'))
	die('No access...');

/**
 * Drafts administration controller.
 * This class allows to modify admin drafts settings for the forum.
 *
 * @package Drafts
 */
class ManageDraftsModule_Controller extends Action_Controller
{
	/**
	 * Drafts settings form
	 * @var Settings_Form
	 */
	protected $_draftSettings;

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

				$modules = array('post', 'display', 'profile', 'personalmessage');

				// Enabling, let's register the modules and prepare the scheduled task
				if ($value)
				{
					enableModules('drafts', $modules);
					calculateNextTrigger('remove_old_drafts');
					Hooks::get()->enableIntegration('Drafts_Integrate');
				}
				// Disabling, just forget about the modules
				else
				{
					disableModules('drafts', $modules);
					Hooks::get()->disableIntegration('Drafts_Integrate');
				}
			},
		);
	}

	/**
	 * Used to add the Drafts entry to the admin menu.
	 *
	 * @param mixed[] $admin_areas The admin menu array
	 */
	public static function addAdminMenu(&$admin_areas)
	{
		global $txt, $context;

		$admin_areas['layout']['areas']['managedrafts'] = array(
			'label' => $txt['manage_drafts'],
			'controller' => 'ManageDraftsModule_Controller',
			'function' => 'action_index',
			'icon' => 'transparent.png',
			'class' => 'admin_img_logs',
			'permission' => array('admin_forum'),
			'enabled' => in_array('dr', $context['admin_features']),
		);
	}

	/**
	 * Integrate drafts in to the delete member chain
	 * call_integration_hook('integrate_delete_members' ...)
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
	 * call_integration_hook('integrate_load_permissions' ...
	 *
	 * @param array $permissionGroups
	 * @param array $permissionList
	 * @param array $leftPermissionGroups
	 * @param array $hiddenPermissions
	 * @param array $relabelPermissions
	 */
	public static function integrate_load_permissions(&$permissionGroups, &$permissionList, &$leftPermissionGroups, &$hiddenPermissions, &$relabelPermissions)
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
		global $scripturl, $txt;

		$topics_actions['olddrafts'] = array(
			'url' => $scripturl . '?action=admin;area=maintain;sa=topics;activity=olddrafts',
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
		$subActions['topics']['activities']['olddrafts'] = function() {
			$controller = new ManageDraftsModule_Controller(new Event_manager());
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
	 * Used to add the Drafts entry to the admin search.
	 *
	 * @param string[] $language_files
	 * @param string[] $include_files
	 * @param mixed[] $settings_search
	 */
	public static function addAdminSearch(&$language_files, &$include_files, &$settings_search)
	{
		$settings_search[] = array('settings_search', 'area=managedrafts', 'ManageDraftsModule_Controller');
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
		loadLanguage('Drafts');

		$this->action_draftSettings_display();
	}

	/**
	 * Modify any setting related to drafts.
	 *
	 * - Requires the admin_forum permission.
	 * - Accessed from ?action=admin;area=managedrafts
	 *
	 * @uses Admin template, edit_topic_settings sub-template.
	 */
	public function action_draftSettings_display()
	{
		global $context, $txt, $scripturl;

		isAllowedTo('admin_forum');
		loadLanguage('Drafts');

		// Initialize the form
		$this->_initDraftSettingsForm();

		$config_vars = $this->_draftSettings->settings();

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

			Settings_Form::save_db($config_vars, $this->_req->post);
			redirectexit('action=admin;area=managedrafts');
		}

		// Some javascript to enable / disable the frequency input box
		addInlineJavascript('
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
		$context['post_url'] = $scripturl . '?action=admin;area=managedrafts;save';
		$context['settings_title'] = $txt['managedrafts_settings'];

		// Prepare the settings...
		Settings_Form::prepare_db($config_vars);
	}

	/**
	 * Initialize drafts settings with the current forum settings
	 */
	private function _initDraftSettingsForm()
	{
		// Instantiate the form
		$this->_draftSettings = new Settings_Form();

		// Initialize it with our settings
		$config_vars = $this->_settings();

		return $this->_draftSettings->settings($config_vars);
	}

	/**
	 * Returns all admin drafts settings in config_vars format.
	 */
	private function _settings()
	{
		global $txt;

		loadLanguage('Drafts');

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