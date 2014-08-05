<?php

/**
 * This file, unpredictable as this might be, handles basic administration.
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * This software is a derived product, based on:
 *
 * Simple Machines Forum (SMF)
 * copyright:	2011 Simple Machines (http://www.simplemachines.org)
 * license:		BSD, See included LICENSE.TXT for terms and conditions.
 *
 * @version 1.0 Release Candidate 2
 *
 */

if (!defined('ELK'))
	die('No access...');

/**
 * Admin controller class.
 *
 * What it does:
 * - This class handles the first general admin screens: home,
 * - Handles admin area search actions and end admin session.
 *
 * @package Admin
 */
class Admin_Controller extends Action_Controller
{
	/**
	 * The main admin handling function.
	 *
	 * What it does:
	 * - It initialises all the basic context required for the admin center.
	 * - It passes execution onto the relevant admin section.
	 * - If the passed section is not found it shows the admin home page.
	 * - Accessed by ?action=admin.
	 */
	public function action_index()
	{
		global $txt, $context, $scripturl, $modSettings, $settings;

		// Make sure the administrator has a valid session...
		validateSession();

		// Load the language and templates....
		loadLanguage('Admin');
		loadTemplate('Admin', 'admin');
		loadJavascriptFile('admin.js', array(), 'admin_script');

		// The Admin functions require Jquery UI ....
		$modSettings['jquery_include_ui'] = true;

		// No indexing evil stuff.
		$context['robot_no_index'] = true;

		// Need these to do much
		require_once(SUBSDIR . '/Menu.subs.php');
		require_once(SUBSDIR . '/Action.class.php');

		// Define the menu structure - see subs/Menu.subs.php for details!
		$admin_areas = array(
			'forum' => array(
				'title' => $txt['admin_main'],
				'permission' => array('admin_forum', 'manage_permissions', 'moderate_forum', 'manage_membergroups', 'manage_bans', 'send_mail', 'edit_news', 'manage_boards', 'manage_smileys', 'manage_attachments'),
				'areas' => array(
					'index' => array(
						'label' => $txt['admin_center'],
						'controller' => 'Admin_Controller',
						'function' => 'action_home',
						'icon' => 'transparent.png',
						'class' => 'admin_img_administration',
					),
					'credits' => array(
						'label' => $txt['support_credits_title'],
						'controller' => 'Admin_Controller',
						'function' => 'action_credits',
						'icon' => 'transparent.png',
						'class' => 'admin_img_support',
					),
					'maillist' => array(
						'label' => $txt['mail_center'],
						'file' => 'ManageMaillist.controller.php',
						'controller' => 'ManageMaillist_Controller',
						'function' => 'action_index',
						'icon' => 'mail.png',
						'class' => 'admin_img_mail',
						'permission' => array('approve_emails', 'admin_forum'),
						'enabled' => in_array('pe', $context['admin_features']),
						'subsections' => array(
							'emaillist' => array($txt['mm_emailerror'], 'approve_emails'),
							'emailfilters' => array($txt['mm_emailfilters'], 'admin_forum'),
							'emailparser' => array($txt['mm_emailparsers'], 'admin_forum'),
							'emailtemplates' => array($txt['mm_emailtemplates'], 'approve_emails'),
							'emailsettings' => array($txt['mm_emailsettings'], 'admin_forum'),
						),
					),
					'news' => array(
						'label' => $txt['news_title'],
						'file' => 'ManageNews.controller.php',
						'controller' => 'ManageNews_Controller',
						'function' => 'action_index',
						'icon' => 'transparent.png',
						'class' => 'admin_img_news',
						'permission' => array('edit_news', 'send_mail', 'admin_forum'),
						'subsections' => array(
							'editnews' => array($txt['admin_edit_news'], 'edit_news'),
							'mailingmembers' => array($txt['admin_newsletters'], 'send_mail'),
							'settings' => array($txt['settings'], 'admin_forum'),
						),
					),
					'packages' => array(
						'label' => $txt['package'],
						'file' => 'Packages.controller.php',
						'controller' => 'Packages_Controller',
						'function' => 'action_index',
						'permission' => array('admin_forum'),
						'icon' => 'transparent.png',
						'class' => 'admin_img_packages',
						'subsections' => array(
							'browse' => array($txt['browse_packages']),
							'installed' => array($txt['installed_packages']),
							'perms' => array($txt['package_file_perms']),
							'options' => array($txt['package_settings']),
							'servers' => array($txt['download_packages']),
							'upload' => array($txt['upload_packages']),
						),
					),
					'packageservers' => array(
						'label' => $txt['package_servers'],
						'file' => 'PackageServers.controller.php',
						'controller' => 'PackageServers_Controller',
						'function' => 'action_index',
						'permission' => array('admin_forum'),
						'icon' => 'transparent.png',
						'class' => 'admin_img_packages',
						'hidden' => true,
					),
					'search' => array(
						'controller' => 'Admin_Controller',
						'function' => 'action_search',
						'permission' => array('admin_forum'),
						'select' => 'index'
					),
					'adminlogoff' => array(
						'controller' => 'Admin_Controller',
						'function' => 'action_endsession',
						'label' => $txt['admin_logoff'],
						'enabled' => empty($modSettings['securityDisable']),
						'icon' => 'transparent.png',
						'class' => 'admin_img_exit',
					),
				),
			),
			'config' => array(
				'title' => $txt['admin_config'],
				'permission' => array('admin_forum'),
				'areas' => array(
					'corefeatures' => array(
						'label' => $txt['core_settings_title'],
						'file' => 'CoreFeatures.controller.php',
						'controller' => 'CoreFeatures_Controller',
						'function' => 'action_index',
						'icon' => 'transparent.png',
						'class' => 'admin_img_corefeatures',
					),
					'featuresettings' => array(
						'label' => $txt['modSettings_title'],
						'file' => 'ManageFeatures.controller.php',
						'controller' => 'ManageFeatures_Controller',
						'function' => 'action_index',
						'icon' => 'transparent.png',
						'class' => 'admin_img_features',
						'subsections' => array(
							'basic' => array($txt['mods_cat_features']),
							'layout' => array($txt['mods_cat_layout']),
							'pmsettings' => array($txt['personal_messages']),
							'karma' => array($txt['karma'], 'enabled' => in_array('k', $context['admin_features'])),
							'likes' => array($txt['likes'], 'enabled' => in_array('l', $context['admin_features'])),
							'mention' => array($txt['mention']),
							'sig' => array($txt['signature_settings_short']),
							'profile' => array($txt['custom_profile_shorttitle'], 'enabled' => in_array('cp', $context['admin_features'])),
						),
					),
					'serversettings' => array(
						'label' => $txt['admin_server_settings'],
						'file' => 'ManageServer.controller.php',
						'controller' => 'ManageServer_Controller',
						'function' => 'action_index',
						'icon' => 'transparent.png',
						'class' => 'admin_img_server',
						'subsections' => array(
							'general' => array($txt['general_settings']),
							'database' => array($txt['database_paths_settings']),
							'cookie' => array($txt['cookies_sessions_settings']),
							'cache' => array($txt['caching_settings']),
							'loads' => array($txt['load_balancing_settings']),
							'phpinfo' => array($txt['phpinfo_settings']),
						),
					),
					'securitysettings' => array(
						'label' => $txt['admin_security_moderation'],
						'file' => 'ManageSecurity.controller.php',
						'controller' => 'ManageSecurity_Controller',
						'function' => 'action_index',
						'icon' => 'transparent.png',
						'class' => 'admin_img_security',
						'subsections' => array(
							'general' => array($txt['mods_cat_security_general']),
							'spam' => array($txt['antispam_title']),
							'badbehavior' => array($txt['badbehavior_title']),
							'moderation' => array($txt['moderation_settings_short'], 'enabled' => !empty($modSettings['warning_enable'])),
						),
					),
					'theme' => array(
						'label' => $txt['theme_admin'],
						'file' => 'ManageThemes.controller.php',
						'controller' => 'ManageThemes_Controller',
						'function' => 'action_index',
						'custom_url' => $scripturl . '?action=admin;area=theme',
						'icon' => 'transparent.png',
						'class' => 'admin_img_themes',
						'subsections' => array(
							'admin' => array($txt['themeadmin_admin_title']),
							'list' => array($txt['themeadmin_list_title']),
							'reset' => array($txt['themeadmin_reset_title']),
							'themelist' => array($txt['themeadmin_edit_title'], 'active' => array('edit', 'browse')),
							'edit' => array($txt['themeadmin_edit_title'], 'enabled' => false),
							'browse' => array($txt['themeadmin_edit_title'], 'enabled' => false),
						),
					),
					'current_theme' => array(
						'label' => $txt['theme_current_settings'],
						'file' => 'ManageThemes.controller.php',
						'controller' => 'ManageThemes_Controller',
						'function' => 'action_index',
						'custom_url' => $scripturl . '?action=admin;area=theme;sa=list;th=' . $settings['theme_id'],
						'icon' => 'transparent.png',
						'class' => 'admin_img_current_theme',
					),
					'languages' => array(
						'label' => $txt['language_configuration'],
						'file' => 'ManageLanguages.controller.php',
						'controller' => 'ManageLanguages_Controller',
						'function' => 'action_index',
						'icon' => 'transparent.png',
						'class' => 'admin_img_languages',
						'subsections' => array(
							'edit' => array($txt['language_edit']),
// 							'add' => array($txt['language_add']),
							'settings' => array($txt['language_settings']),
						),
					),
					'addonsettings' => array(
						'label' => $txt['admin_modifications'],
						'file' => 'AddonSettings.controller.php',
						'controller' => 'AddonSettings_Controller',
						'function' => 'action_index',
						'icon' => 'transparent.png',
						'class' => 'admin_img_modifications',
						'subsections' => array(
							'general' => array($txt['mods_cat_modifications_misc']),
						),
					),
				),
			),
			'layout' => array(
				'title' => $txt['layout_controls'],
				'permission' => array('manage_boards', 'admin_forum', 'manage_smileys', 'manage_attachments', 'moderate_forum'),
				'areas' => array(
					'manageboards' => array(
						'label' => $txt['admin_boards'],
						'file' => 'ManageBoards.controller.php',
						'controller' => 'ManageBoards_Controller',
						'function' => 'action_index',
						'icon' => 'transparent.png',
						'class' => 'admin_img_boards',
						'permission' => array('manage_boards'),
						'subsections' => array(
							'main' => array($txt['boardsEdit']),
							'newcat' => array($txt['mboards_new_cat']),
							'settings' => array($txt['settings'], 'admin_forum'),
						),
					),
					'postsettings' => array(
						'label' => $txt['manageposts'],
						'file' => 'ManagePosts.controller.php',
						'controller' => 'ManagePosts_Controller',
						'function' => 'action_index',
						'permission' => array('admin_forum'),
						'icon' => 'transparent.png',
						'class' => 'admin_img_posts',
						'subsections' => array(
							'posts' => array($txt['manageposts_settings']),
							'bbc' => array($txt['manageposts_bbc_settings']),
							'censor' => array($txt['admin_censored_words']),
							'topics' => array($txt['manageposts_topic_settings']),
						),
					),
					'smileys' => array(
						'label' => $txt['smileys_manage'],
						'file' => 'ManageSmileys.controller.php',
						'controller' => 'ManageSmileys_Controller',
						'function' => 'action_index',
						'icon' => 'transparent.png',
						'class' => 'admin_img_smiley',
						'permission' => array('manage_smileys'),
						'subsections' => array(
							'editsets' => array($txt['smiley_sets']),
							'addsmiley' => array($txt['smileys_add'], 'enabled' => !empty($modSettings['smiley_enable'])),
							'editsmileys' => array($txt['smileys_edit'], 'enabled' => !empty($modSettings['smiley_enable'])),
							'setorder' => array($txt['smileys_set_order'], 'enabled' => !empty($modSettings['smiley_enable'])),
							'editicons' => array($txt['icons_edit_message_icons'], 'enabled' => !empty($modSettings['messageIcons_enable'])),
							'settings' => array($txt['settings']),
						),
					),
					'manageattachments' => array(
						'label' => $txt['attachments_avatars'],
						'file' => 'ManageAttachments.controller.php',
						'controller' => 'ManageAttachments_Controller',
						'function' => 'action_index',
						'icon' => 'transparent.png',
						'class' => 'admin_img_attachment',
						'permission' => array('manage_attachments'),
						'subsections' => array(
							'browse' => array($txt['attachment_manager_browse']),
							'attachments' => array($txt['attachment_manager_settings']),
							'avatars' => array($txt['attachment_manager_avatar_settings']),
							'attachpaths' => array($txt['attach_directories']),
							'maintenance' => array($txt['attachment_manager_maintenance']),
						),
					),
					'managesearch' => array(
						'label' => $txt['manage_search'],
						'file' => 'ManageSearch.controller.php',
						'controller' => 'ManageSearch_Controller',
						'function' => 'action_index',
						'icon' => 'transparent.png',
						'class' => 'admin_img_search',
						'permission' => array('admin_forum'),
						'subsections' => array(
							'weights' => array($txt['search_weights']),
							'method' => array($txt['search_method']),
							'managesphinx' => array($txt['search_sphinx']),
							'settings' => array($txt['settings']),
						),
					),
					'managecalendar' => array(
						'label' => $txt['manage_calendar'],
						'file' => 'ManageCalendar.controller.php',
						'controller' => 'ManageCalendar_Controller',
						'function' => 'action_index',
						'icon' => 'transparent.png',
						'class' => 'admin_img_calendar',
						'permission' => array('admin_forum'),
						'enabled' => in_array('cd', $context['admin_features']),
						'subsections' => array(
							'holidays' => array($txt['manage_holidays'], 'admin_forum', 'enabled' => !empty($modSettings['cal_enabled'])),
							'settings' => array($txt['calendar_settings'], 'admin_forum'),
						),
					),
					'managedrafts' => array(
						'label' => $txt['manage_drafts'],
						'file' => 'ManageDrafts.controller.php',
						'controller' => 'ManageDrafts_Controller',
						'function' => 'action_index',
						'icon' => 'transparent.png',
						'class' => 'admin_img_logs',
						'permission' => array('admin_forum'),
						'enabled' => in_array('dr', $context['admin_features']),
					),
				),
			),
			'members' => array(
				'title' => $txt['admin_manage_members'],
				'permission' => array('moderate_forum', 'manage_membergroups', 'manage_bans', 'manage_permissions', 'admin_forum'),
				'areas' => array(
					'viewmembers' => array(
						'label' => $txt['admin_users'],
						'file' => 'ManageMembers.controller.php',
						'controller' => 'ManageMembers_Controller',
						'function' => 'action_index',
						'icon' => 'transparent.png',
						'class' => 'admin_img_members',
						'permission' => array('moderate_forum'),
						'subsections' => array(
							'all' => array($txt['view_all_members']),
							'search' => array($txt['mlist_search']),
						),
					),
					'membergroups' => array(
						'label' => $txt['admin_groups'],
						'file' => 'ManageMembergroups.controller.php',
						'controller' => 'ManageMembergroups_Controller',
						'function' => 'action_index',
						'icon' => 'transparent.png',
						'class' => 'admin_img_membergroups',
						'permission' => array('manage_membergroups'),
						'subsections' => array(
							'index' => array($txt['membergroups_edit_groups'], 'manage_membergroups'),
							'add' => array($txt['membergroups_new_group'], 'manage_membergroups'),
							'settings' => array($txt['settings'], 'admin_forum'),
						),
					),
					'permissions' => array(
						'label' => $txt['edit_permissions'],
						'file' => 'ManagePermissions.controller.php',
						'controller' => 'ManagePermissions_Controller',
						'function' => 'action_index',
						'icon' => 'transparent.png',
						'class' => 'admin_img_permissions',
						'permission' => array('manage_permissions'),
						'subsections' => array(
							'index' => array($txt['permissions_groups'], 'manage_permissions'),
							'board' => array($txt['permissions_boards'], 'manage_permissions'),
							'profiles' => array($txt['permissions_profiles'], 'manage_permissions'),
							'postmod' => array($txt['permissions_post_moderation'], 'manage_permissions', 'enabled' => $modSettings['postmod_active']),
							'settings' => array($txt['settings'], 'admin_forum'),
						),
					),
					'ban' => array(
						'label' => $txt['ban_title'],
						'file' => 'ManageBans.controller.php',
						'controller' => 'ManageBans_Controller',
						'function' => 'action_index',
						'icon' => 'transparent.png',
						'class' => 'admin_img_ban',
						'permission' => 'manage_bans',
						'subsections' => array(
							'list' => array($txt['ban_edit_list']),
							'add' => array($txt['ban_add_new']),
							'browse' => array($txt['ban_trigger_browse']),
							'log' => array($txt['ban_log']),
						),
					),
					'regcenter' => array(
						'label' => $txt['registration_center'],
						'file' => 'ManageRegistration.controller.php',
						'controller' => 'ManageRegistration_Controller',
						'function' => 'action_index',
						'icon' => 'transparent.png',
						'class' => 'admin_img_regcenter',
						'permission' => array('admin_forum', 'moderate_forum'),
						'subsections' => array(
							'register' => array($txt['admin_browse_register_new'], 'moderate_forum'),
							'agreement' => array($txt['registration_agreement'], 'admin_forum'),
							'reservednames' => array($txt['admin_reserved_set'], 'admin_forum'),
							'settings' => array($txt['settings'], 'admin_forum'),
						),
					),
					'sengines' => array(
						'label' => $txt['search_engines'],
						'enabled' => in_array('sp', $context['admin_features']),
						'file' => 'ManageSearchEngines.controller.php',
						'controller' => 'ManageSearchEngines_Controller',
						'function' => 'action_index',
						'icon' => 'transparent.png',
						'class' => 'admin_img_engines',
						'permission' => 'admin_forum',
						'subsections' => array(
							'stats' => array($txt['spider_stats']),
							'logs' => array($txt['spider_logs']),
							'spiders' => array($txt['spiders']),
							'settings' => array($txt['settings']),
						),
					),
					'paidsubscribe' => array(
						'label' => $txt['paid_subscriptions'],
						'enabled' => in_array('ps', $context['admin_features']),
						'file' => 'ManagePaid.controller.php',
						'controller' => 'ManagePaid_Controller',
						'icon' => 'transparent.png',
						'class' => 'admin_img_paid',
						'function' => 'action_index',
						'permission' => 'admin_forum',
						'subsections' => array(
							'view' => array($txt['paid_subs_view']),
							'settings' => array($txt['settings']),
						),
					),
				),
			),
			'maintenance' => array(
				'title' => $txt['admin_maintenance'],
				'permission' => array('admin_forum'),
				'areas' => array(
					'maintain' => array(
						'label' => $txt['maintain_title'],
						'file' => 'Maintenance.controller.php',
						'controller' => 'Maintenance_Controller',
						'function' => 'action_index',
						'icon' => 'transparent.png',
						'class' => 'admin_img_maintain',
						'subsections' => array(
							'routine' => array($txt['maintain_sub_routine'], 'admin_forum'),
							'database' => array($txt['maintain_sub_database'], 'admin_forum'),
							'members' => array($txt['maintain_sub_members'], 'admin_forum'),
							'topics' => array($txt['maintain_sub_topics'], 'admin_forum'),
							'hooks' => array($txt['maintain_sub_hooks_list'], 'admin_forum'),
							'attachments' => array($txt['maintain_sub_attachments'], 'admin_forum'),
						),
					),
					'logs' => array(
						'label' => $txt['logs'],
						'file' => 'AdminLog.controller.php',
						'controller' => 'AdminLog_Controller',
						'function' => 'action_index',
						'icon' => 'transparent.png',
						'class' => 'admin_img_logs',
						'subsections' => array(
							'errorlog' => array($txt['errlog'], 'admin_forum', 'enabled' => !empty($modSettings['enableErrorLogging']), 'url' => $scripturl . '?action=admin;area=logs;sa=errorlog;desc'),
							'adminlog' => array($txt['admin_log'], 'admin_forum', 'enabled' => in_array('ml', $context['admin_features'])),
							'modlog' => array($txt['moderation_log'], 'admin_forum', 'enabled' => in_array('ml', $context['admin_features'])),
							'banlog' => array($txt['ban_log'], 'manage_bans'),
							'spiderlog' => array($txt['spider_logs'], 'admin_forum', 'enabled' => in_array('sp', $context['admin_features'])),
							'tasklog' => array($txt['scheduled_log'], 'admin_forum'),
							'badbehaviorlog' => array($txt['badbehavior_log'], 'admin_forum', 'enabled' => !empty($modSettings['badbehavior_enabled']), 'url' => $scripturl . '?action=admin;area=logs;sa=badbehaviorlog;desc'),
							'pruning' => array($txt['pruning_title'], 'admin_forum'),
						),
					),
					'scheduledtasks' => array(
						'label' => $txt['maintain_tasks'],
						'file' => 'ManageScheduledTasks.controller.php',
						'controller' => 'ManageScheduledTasks_Controller',
						'function' => 'action_index',
						'icon' => 'transparent.png',
						'class' => 'admin_img_scheduled',
						'subsections' => array(
							'tasks' => array($txt['maintain_tasks'], 'admin_forum'),
							'tasklog' => array($txt['scheduled_log'], 'admin_forum'),
						),
					),
					'mailqueue' => array(
						'label' => $txt['mailqueue_title'],
						'file' => 'ManageMail.controller.php',
						'controller' => 'ManageMail_Controller',
						'function' => 'action_index',
						'icon' => 'transparent.png',
						'class' => 'admin_img_mail',
						'subsections' => array(
							'browse' => array($txt['mailqueue_browse'], 'admin_forum'),
							'settings' => array($txt['mailqueue_settings'], 'admin_forum'),
						),
					),
					'reports' => array(
						'enabled' => in_array('rg', $context['admin_features']),
						'label' => $txt['generate_reports'],
						'file' => 'Reports.controller.php',
						'controller' => 'Reports_Controller',
						'function' => 'action_index',
						'icon' => 'transparent.png',
						'class' => 'admin_img_reports',
					),
					'repairboards' => array(
						'label' => $txt['admin_repair'],
						'file' => 'RepairBoards.controller.php',
						'controller' => 'RepairBoards_Controller',
						'function' => 'action_repairboards',
						'select' => 'maintain',
						'hidden' => true,
					),
				),
			),
		);

		// Any files to include for administration?
		call_integration_include_hook('integrate_admin_include');

		$menuOptions = array('hook' => 'admin', 'default_include_dir' => ADMINDIR);

		// Actually create the menu!
		$admin_include_data = createMenu($admin_areas, $menuOptions);
		unset($admin_areas);

		// Nothing valid?
		if ($admin_include_data == false)
			fatal_lang_error('no_access', false);

		// Build the link tree.
		$context['linktree'][] = array(
			'url' => $scripturl . '?action=admin',
			'name' => $txt['admin_center'],
		);

		if (isset($admin_include_data['current_area']) && $admin_include_data['current_area'] != 'index')
			$context['linktree'][] = array(
				'url' => $scripturl . '?action=admin;area=' . $admin_include_data['current_area'] . ';' . $context['session_var'] . '=' . $context['session_id'],
				'name' => $admin_include_data['label'],
			);

		if (!empty($admin_include_data['current_subsection']) && $admin_include_data['subsections'][$admin_include_data['current_subsection']][0] != $admin_include_data['label'])
			$context['linktree'][] = array(
				'url' => $scripturl . '?action=admin;area=' . $admin_include_data['current_area'] . ';sa=' . $admin_include_data['current_subsection'] . ';' . $context['session_var'] . '=' . $context['session_id'],
				'name' => $admin_include_data['subsections'][$admin_include_data['current_subsection']][0],
			);

		// Make a note of the Unique ID for this menu.
		$context['admin_menu_id'] = $context['max_menu_id'];
		$context['admin_menu_name'] = 'menu_data_' . $context['admin_menu_id'];

		// Where in the admin are we?
		$context['admin_area'] = $admin_include_data['current_area'];

		// Now - finally - call the right place!
		if (isset($admin_include_data['file']))
			require_once($admin_include_data['file']);

		callMenu($admin_include_data);
	}

	/**
	 * The main administration section.
	 *
	 * What it does:
	 * - It prepares all the data necessary for the administration front page.
	 * - It uses the Admin template along with the admin sub template.
	 * - It requires the moderate_forum, manage_membergroups, manage_bans,
	 * admin_forum, manage_permissions, manage_attachments, manage_smileys,
	 * manage_boards, edit_news, or send_mail permission.
	 * - It uses the index administrative area.
	 * - Accessed by ?action=admin.
	 */
	public function action_home()
	{
		global $forum_version, $txt, $scripturl, $context, $user_info, $settings;

		// We need a little help
		require_once(SUBSDIR . '/Membergroups.subs.php');
		require_once(SUBSDIR . '/Admin.subs.php');

		// You have to be able to do at least one of the below to see this page.
		isAllowedTo(array('admin_forum', 'manage_permissions', 'moderate_forum', 'manage_membergroups', 'manage_bans', 'send_mail', 'edit_news', 'manage_boards', 'manage_smileys', 'manage_attachments'));

		// Find all of this forum's administrators...
		if (listMembergroupMembers_Href($context['administrators'], 1, 32) && allowedTo('manage_membergroups'))
		{
			// Add a 'more'-link if there are more than 32.
			$context['more_admins_link'] = '<a href="' . $scripturl . '?action=moderate;area=viewgroups;sa=members;group=1">' . $txt['more'] . '</a>';
		}

		// This makes it easier to get the latest news with your time format.
		$context['time_format'] = urlencode($user_info['time_format']);
		$context['forum_version'] = $forum_version;

		// Get a list of current server versions.
		$checkFor = array(
			'gd',
			'imagick',
			'db_server',
			'mmcache',
			'eaccelerator',
			'zend',
			'apc',
			'memcache',
			'xcache',
			'php',
			'server',
		);
		$context['current_versions'] = getServerVersions($checkFor);

		$context['can_admin'] = allowedTo('admin_forum');
		$context['sub_template'] = 'admin';
		$context['page_title'] = $txt['admin_center'];
		$context[$context['admin_menu_name']]['tab_data'] = array(
			'title' => $txt['admin_center'],
			'help' => '',
			'description' => '
				<strong>' . $txt['hello_guest'] . ' ' . $context['user']['name'] . '!</strong>
				' . sprintf($txt['admin_main_welcome'], $txt['admin_center'], $txt['help'], $settings['images_url']),
		);

		// Load in the admin quick tasks
		$context['quick_admin_tasks'] = getQuickAdminTasks();
	}

	/**
	 * The credits section in admin panel.
	 *
	 * What it does:
	 * - Determines the current level of support functions from the server, such as
	 * current level of caching engine or graphics librayrs installed.
	 * - Accessed by ?action=admin;area=credits
	 */
	public function action_credits()
	{
		global $forum_version, $txt, $scripturl, $context, $user_info;

		// We need a little help from our friends
		require_once(SUBSDIR . '/Membergroups.subs.php');
		require_once(SUBSDIR . '/Who.subs.php');
		require_once(SUBSDIR . '/Admin.subs.php');

		// You have to be able to do at least one of the below to see this page.
		isAllowedTo(array('admin_forum', 'manage_permissions', 'moderate_forum', 'manage_membergroups', 'manage_bans', 'send_mail', 'edit_news', 'manage_boards', 'manage_smileys', 'manage_attachments'));

		// Find all of this forum's administrators...
		if (listMembergroupMembers_Href($context['administrators'], 1, 32) && allowedTo('manage_membergroups'))
		{
			// Add a 'more'-link if there are more than 32.
			$context['more_admins_link'] = '<a href="' . $scripturl . '?action=moderate;area=viewgroups;sa=members;group=1">' . $txt['more'] . '</a>';
		}

		// Load credits.
		$context[$context['admin_menu_name']]['tab_data'] = array(
			'title' => $txt['support_credits_title'],
			'help' => '',
			'description' => '',
		);
		loadLanguage('Who');
		$context += prepareCreditsData();

		// This makes it easier to get the latest news with your time format.
		$context['time_format'] = urlencode($user_info['time_format']);
		$context['forum_version'] = $forum_version;

		// Get a list of current server versions.
		$checkFor = array(
			'gd',
			'imagick',
			'db_server',
			'mmcache',
			'eaccelerator',
			'zend',
			'apc',
			'memcache',
			'xcache',
			'php',
			'server',
		);
		$context['current_versions'] = getServerVersions($checkFor);

		$context['can_admin'] = allowedTo('admin_forum');
		$context['sub_template'] = 'credits';
		$context['page_title'] = $txt['support_credits_title'];

		// Load in the admin quick tasks
		$context['quick_admin_tasks'] = getQuickAdminTasks();
	}

	/**
	 * This function allocates out all the search stuff.
	 */
	public function action_search()
	{
		global $txt, $context;

		// What can we search for?
		$subActions = array(
			'internal' => array($this, 'action_search_internal', 'permission' => 'admin_forum'),
			'online' => array($this, 'action_search_doc', 'permission' => 'admin_forum'),
			'member' => array($this, 'action_search_member', 'permission' => 'admin_forum'),
		);

		$subAction = !isset($_REQUEST['search_type']) || !isset($subActions[$_REQUEST['search_type']]) ? 'internal' : $_REQUEST['search_type'];

		// Keep track of what the admin wants in terms of advanced or not
		if (empty($context['admin_preferences']['sb']) || $context['admin_preferences']['sb'] != $subAction)
		{
			$context['admin_preferences']['sb'] = $subAction;

			// Update the preferences.
			require_once(SUBSDIR . '/Admin.subs.php');
			updateAdminPreferences();
		}

		// Setup for the template
		$context['search_type'] = $subAction;
		$context['search_term'] = isset($_REQUEST['search_term']) ? Util::htmlspecialchars($_REQUEST['search_term'], ENT_QUOTES) : '';
		$context['sub_template'] = 'admin_search_results';
		$context['page_title'] = $txt['admin_search_results'];

		// You did remember to enter something to search for, otherwise its easy
		if (trim($context['search_term']) == '')
			$context['search_results'] = array();
		else
		{
			$action = new Action();
			$subAction = $action->initialize($subActions, 'internal');
			$action->dispatch($subAction);
		}
	}

	/**
	 * A complicated but relatively quick internal search.
	 */
	public function action_search_internal()
	{
		global $context, $txt, $helptxt, $scripturl;

		// Try to get some more memory.
		setMemoryLimit('128M');

		// Load a lot of language files.
		$language_files = array(
			'Help', 'ManageMail', 'ManageSettings', 'ManageCalendar', 'ManageBoards', 'ManagePaid', 'ManagePermissions', 'Search',
			'Login', 'ManageSmileys', 'Maillist',
		);

		// All the files we need to include.
		$include_files = array(
			'AddonSettings.controller', 'AdminLog.controller', 'CoreFeatures.controller',
			'ManageAttachments.controller', 'ManageAvatars.controller', 'ManageBBC.controller',
			'ManageBoards.controller', 'ManageCalendar.controller', 'ManageDrafts.controller',
			'ManageFeatures.controller', 'ManageLanguages.controller', 'ManageMail.controller',
			'ManageNews.controller', 'ManagePaid.controller', 'ManagePermissions.controller',
			'ManagePosts.controller', 'ManageRegistration.controller', 'ManageSearch.controller',
			'ManageSearchEngines.controller', 'ManageSecurity.controller', 'ManageServer.controller',
			'ManageSmileys.controller', 'ManageTopics.controller', 'ManageMaillist.controller',
			'ManageMembergroups.controller',
		);

		// This is a special array of functions that contain setting data
		// - we query all these to simply pull all setting bits!
		$settings_search = array(
			array('settings_search', 'area=logs;sa=pruning', 'AdminLog_Controller'),
			array('config_vars', 'area=corefeatures', 'CoreFeatures_Controller'),
			array('basicSettings_search', 'area=featuresettings;sa=basic', 'ManageFeatures_Controller'),
			array('layoutSettings_search', 'area=featuresettings;sa=layout', 'ManageFeatures_Controller'),
			array('karmaSettings_search', 'area=featuresettings;sa=karma', 'ManageFeatures_Controller'),
			array('likesSettings_search', 'area=featuresettings;sa=likes', 'ManageFeatures_Controller'),
			array('mentionSettings_search', 'area=featuresettings;sa=mention', 'ManageFeatures_Controller'),
			array('signatureSettings_search', 'area=featuresettings;sa=sig', 'ManageFeatures_Controller'),
			array('settings_search', 'area=addonsettings;sa=general', 'AddonSettings_Controller'),
			array('settings_search', 'area=manageattachments;sa=attachments', 'ManageAttachments_Controller'),
			array('settings_search', 'area=manageattachments;sa=avatars', 'ManageAvatars_Controller'),
			array('settings_search', 'area=postsettings;sa=bbc', 'ManageBBC_Controller'),
			array('settings_search', 'area=manageboards;sa=settings', 'ManageBoards_Controller'),
			array('settings_search', 'area=managecalendar;sa=settings', 'ManageCalendar_Controller'),
			array('settings_search', 'area=managedrafts', 'ManageDrafts_Controller'),
			array('settings_search', 'area=languages;sa=settings', 'ManageLanguages_Controller'),
			array('settings_search', 'area=mailqueue;sa=settings', 'ManageMail_Controller'),
			array('settings_search', 'area=maillist;sa=emailsettings', 'ManageMaillist_Controller'),
			array('settings_search', 'area=membergroups;sa=settings', 'ManageMembergroups_Controller'),
			array('settings_search', 'area=news;sa=settings', 'ManageNews_Controller'),
			array('settings_search', 'area=paidsubscribe;sa=settings', 'ManagePaid_Controller'),
			array('settings_search', 'area=permissions;sa=settings', 'ManagePermissions_Controller'),
			array('settings_search', 'area=postsettings;sa=posts', 'ManagePosts_Controller'),
			array('settings_search', 'area=regcenter;sa=settings', 'ManageRegistration_Controller'),
			array('settings_search', 'area=managesearch;sa=settings', 'ManageSearch_Controller'),
			array('settings_search', 'area=sengines;sa=settings', 'ManageSearchEngines_Controller'),
			array('securitySettings_search', 'area=securitysettings;sa=general', 'ManageSecurity_Controller'),
			array('spamSettings_search', 'area=securitysettings;sa=spam', 'ManageSecurity_Controller'),
			array('moderationSettings_search', 'area=securitysettings;sa=moderation', 'ManageSecurity_Controller'),
			array('bbSettings_search', 'area=securitysettings;sa=badbehavior', 'ManageSecurity_Controller'),
			array('generalSettings_search', 'area=serversettings;sa=general', 'ManageServer_Controller'),
			array('databaseSettings_search', 'area=serversettings;sa=database', 'ManageServer_Controller'),
			array('cookieSettings_search', 'area=serversettings;sa=cookie', 'ManageServer_Controller'),
			array('cacheSettings_search', 'area=serversettings;sa=cache', 'ManageServer_Controller'),
			array('balancingSettings_search', 'area=serversettings;sa=loads', 'ManageServer_Controller'),
			array('settings_search', 'area=smileys;sa=settings', 'ManageSmileys_Controller'),
			array('settings_search', 'area=postsettings;sa=topics', 'ManageTopics_Controller'),
		);

		call_integration_hook('integrate_admin_search', array(&$language_files, &$include_files, &$settings_search));

		loadLanguage(implode('+', $language_files));

		foreach ($include_files as $file)
			require_once(ADMINDIR . '/' . $file . '.php');

		/* This is the huge array that defines everything ... it's items are formatted as follows:
			0 = Language index (Can be array of indexes) to search through for this setting.
			1 = URL for this indexes page.
			2 = Help index for help associated with this item (If different from 0)
		*/

		$search_data = array(
			// All the major sections of the forum.
			'sections' => array(
			),
			'settings' => array(
				array('COPPA', 'area=regcenter;sa=settings'),
				array('CAPTCHA', 'area=securitysettings;sa=spam'),
			),
		);

		// Go through the admin menu structure trying to find suitably named areas!
		foreach ($context[$context['admin_menu_name']]['sections'] as $section)
		{
			foreach ($section['areas'] as $menu_key => $menu_item)
			{
				$search_data['sections'][] = array($menu_item['label'], 'area=' . $menu_key);
				if (!empty($menu_item['subsections']))
					foreach ($menu_item['subsections'] as $key => $sublabel)
					{
						if (isset($sublabel['label']))
							$search_data['sections'][] = array($sublabel['label'], 'area=' . $menu_key . ';sa=' . $key);
					}
			}
		}

		foreach ($settings_search as $setting_area)
		{
			// Get a list of their variables.
			if (isset($setting_area[2]))
			{
				// an OOP controller: get the settings from the settings method.
				$controller = new $setting_area[2]();
				$config_vars = $controller->{$setting_area[0]}();
			}
			else
			{
				// a good ole' procedural controller: get the settings from the function.
				$config_vars = $setting_area[0](true);
			}

			foreach ($config_vars as $var)
				if (!empty($var[1]) && !in_array($var[0], array('permissions', 'switch', 'warning')))
					$search_data['settings'][] = array($var[(isset($var[2]) && in_array($var[2], array('file', 'db'))) ? 0 : 1], $setting_area[1]);
		}

		$context['page_title'] = $txt['admin_search_results'];
		$context['search_results'] = array();

		// Go through all the search data trying to find this text!
		$search_term = strtolower(un_htmlspecialchars($context['search_term']));
		foreach ($search_data as $section => $data)
		{
			foreach ($data as $item)
			{
				$found = false;
				if (!is_array($item[0]))
					$item[0] = array($item[0]);
				foreach ($item[0] as $term)
				{
					if (stripos($term, $search_term) !== false || (isset($txt[$term]) && stripos($txt[$term], $search_term) !== false) || (isset($txt['setting_' . $term]) && stripos($txt['setting_' . $term], $search_term) !== false))
					{
						$found = $term;
						break;
					}
				}

				if ($found)
				{
					// Format the name - and remove any descriptions the entry may have.
					$name = isset($txt[$found]) ? $txt[$found] : (isset($txt['setting_' . $found]) ? $txt['setting_' . $found] : $found);
					$name = preg_replace('~<(?:div|span)\sclass="smalltext">.+?</(?:div|span)>~', '', $name);

					if (!empty($name))
						$context['search_results'][] = array(
							'url' => (substr($item[1], 0, 4) == 'area' ? $scripturl . '?action=admin;' . $item[1] : $item[1]) . ';' . $context['session_var'] . '=' . $context['session_id'] . ((substr($item[1], 0, 4) == 'area' && $section == 'settings' ? '#' . $item[0][0] : '')),
							'name' => $name,
							'type' => $section,
							'help' => Util::shorten_text(isset($item[2]) ? strip_tags($helptxt[$item[2]]) : (isset($helptxt[$found]) ? strip_tags($helptxt[$found]) : ''), 255),
						);
				}
			}
		}
	}

	/**
	 * All this does is pass through to manage members.
	 */
	public function action_search_member()
	{
		global $context;

		require_once(ADMINDIR . '/ManageMembers.controller.php');

		$_REQUEST['sa'] = 'query';
		$_POST['membername'] = un_htmlspecialchars($context['search_term']);
		$_POST['types'] = '';

		$managemembers = new ManageMembers_Controller();
		$managemembers->action_index();
	}

	/**
	 * This file allows the user to search the wiki documentation
	 * for a little help.
	 */
	public function action_search_doc()
	{
		global $context;

		$context['doc_apiurl'] = 'https://github.com/elkarte/Elkarte/wiki/api.php';
		$context['doc_scripturl'] = 'https://github.com/elkarte/Elkarte/wiki/';

		// Set all the parameters search might expect.
		$postVars = explode(' ', $context['search_term']);

		// Encode the search data.
		foreach ($postVars as $k => $v)
			$postVars[$k] = urlencode($v);

		// This is what we will send.
		$postVars = implode('+', $postVars);

		// Get the results from the doc site.
		require_once(SUBSDIR . '/Package.subs.php');
		// Demo URL:
		// https://github.com/elkarte/Elkarte/wiki/api.php?action=query&list=search&srprop=timestamp|snippet&format=xml&srwhat=text&srsearch=template+eval
		$search_results = fetch_web_data($context['doc_apiurl'] . '?action=query&list=search&srprop=timestamp|snippet&format=xml&srwhat=text&srsearch=' . $postVars);

		// If we didn't get any xml back we are in trouble - perhaps the doc site is overloaded?
		if (!$search_results || preg_match('~<' . '\?xml\sversion="\d+\.\d+"\?>\s*(<api>.+?</api>)~is', $search_results, $matches) != true)
			fatal_lang_error('cannot_connect_doc_site');

		$search_results = $matches[1];

		// Otherwise we simply walk through the XML and stick it in context for display.
		$context['search_results'] = array();
		require_once(SUBSDIR . '/XmlArray.class.php');

		// Get the results loaded into an array for processing!
		$results = new Xml_Array($search_results, false);

		// Move through the api layer.
		if (!$results->exists('api'))
			fatal_lang_error('cannot_connect_doc_site');

		// Are there actually some results?
		if ($results->exists('api/query/search/p'))
		{
			$relevance = 0;
			foreach ($results->set('api/query/search/p') as $result)
			{
				$context['search_results'][$result->fetch('@title')] = array(
					'title' => $result->fetch('@title'),
					'relevance' => $relevance++,
					'snippet' => str_replace('class=\'searchmatch\'', 'class="highlight"', un_htmlspecialchars($result->fetch('@snippet'))),
				);
			}
		}
	}

	/**
	 * This ends a admin session, requiring authentication to access the ACP again.
	 */
	public function action_endsession()
	{
		// This is so easy!
		unset($_SESSION['admin_time']);

		// Clean any admin tokens as well.
		cleanTokens(false, '-admin');

		if (isset($_GET['redir']) && isset($_SERVER['HTTP_REFERER']))
			redirectexit($_SERVER['HTTP_REFERER']);
		else
			redirectexit();
	}
}