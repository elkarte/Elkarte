<?php

/**
 * This file, unpredictable as this might be, handles basic administration.
 *
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * This file contains code covered by:
 * copyright: 2011 Simple Machines (http://www.simplemachines.org)
 *
 * @version 2.0 dev
 *
 */

namespace ElkArte\AdminController;

use ElkArte\AbstractController;
use ElkArte\Action;
use ElkArte\AdminSettingsSearch;
use ElkArte\EventManager;
use ElkArte\Exceptions\Exception;
use ElkArte\Hooks;
use ElkArte\User;
use ElkArte\XmlArray;

/**
 * Admin controller class.
 *
 * What it does:
 *
 * - This class handles the first general admin screens: home,
 * - Handles admin area search actions and end admin session.
 *
 * @package Admin
 */
class Admin extends AbstractController
{
	/**
	 * @var string[] areas to find current installed status and installed version
	 */
	private $_checkFor = array('gd', 'imagick', 'db_server', 'php', 'server',
							   'zend', 'apc', 'memcache', 'memcached', 'xcache', 'opcache');

	/**
	 * Pre Dispatch, called before other methods.
	 *
	 * - Loads integration hooks
	 */
	public function pre_dispatch()
	{
		Hooks::instance()->loadIntegrationsSettings();
	}

	/**
	 * The main admin handling function.
	 *
	 * What it does:
	 *
	 * - It initialises all the basic context required for the admin center.
	 * - It passes execution onto the relevant admin section.
	 * - If the passed section is not found it shows the admin home page.
	 * - Accessed by ?action=admin.
	 */
	public function action_index()
	{
		global $context, $modSettings;

		// Make sure the administrator has a valid session...
		validateSession();

		// Load the language and templates....
		theme()->getTemplates()->loadLanguageFile('Admin');
		theme()->getTemplates()->load('Admin');
		loadCSSFile('admin.css');
		loadJavascriptFile('admin.js', array(), 'admin_script');

		// The Admin functions require Jquery UI ....
		$modSettings['jquery_include_ui'] = true;

		// No indexing evil stuff.
		$context['robot_no_index'] = true;

		// Need these to do much
		require_once(SUBSDIR . '/Admin.subs.php');

		// Actually create the menu!
		$admin_include_data = $this->loadMenu();
		$this->buildLinktree($admin_include_data);

		// Now - finally - call the right place!
		if (isset($admin_include_data['file']))
		{
			require_once($admin_include_data['file']);
		}

		callMenu($admin_include_data);
	}

	/**
	 * Load the admin_areas array
	 *
	 * What it does:
	 *
	 * - Creates the admin menu
	 * - Allows integrations to add/edit menu with addMenu event and integrate_admin_include
	 *
	 * @event integrate_admin_include used add files to include for administration
	 * @event addMenu passed admin area area, allows active modules registered to this event to add items to the admin menu,
	 * @event integrate_admin_areas passed admin area area, used to add items to the admin menu
	 *
	 * @return false|mixed[]
	 * @throws \ElkArte\Exceptions\Exception no_access
	 */
	private function loadMenu()
	{
		global $txt, $context, $modSettings, $settings;

		// Need these to do much
		require_once(SUBSDIR . '/Menu.subs.php');

		// Define the menu structure - see subs/Menu.subs.php for details!
		$admin_areas = array(
			'forum' => array(
				'title' => $txt['admin_main'],
				'permission' => array('admin_forum', 'manage_permissions', 'moderate_forum', 'manage_membergroups', 'manage_bans', 'send_mail', 'edit_news', 'manage_boards', 'manage_smileys', 'manage_attachments'),
				'areas' => array(
					'index' => array(
						'label' => $txt['admin_center'],
						'controller' => '\\ElkArte\\AdminController\\Admin',
						'function' => 'action_home',
						'icon' => 'transparent.png',
						'class' => 'admin_img_administration',
					),
					'credits' => array(
						'label' => $txt['support_credits_title'],
						'controller' => '\\ElkArte\\AdminController\\Admin',
						'function' => 'action_credits',
						'icon' => 'transparent.png',
						'class' => 'admin_img_support',
					),
					'maillist' => array(
						'label' => $txt['mail_center'],
						'controller' => '\\ElkArte\\AdminController\\ManageMaillist',
						'function' => 'action_index',
						'icon' => 'transparent.png',
						'class' => 'admin_img_mail',
						'permission' => array('approve_emails', 'admin_forum'),
						'enabled' => featureEnabled('pe'),
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
						'controller' => '\\ElkArte\\AdminController\\ManageNews',
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
						'controller' => '\\ElkArte\\AdminController\\Packages',
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
						'controller' => '\\ElkArte\\AdminController\\PackageServers',
						'function' => 'action_index',
						'permission' => array('admin_forum'),
						'icon' => 'transparent.png',
						'class' => 'admin_img_packages',
						'hidden' => true,
					),
					'search' => array(
						'controller' => '\\ElkArte\\AdminController\\Admin',
						'function' => 'action_search',
						'permission' => array('admin_forum'),
						'select' => 'index'
					),
					'adminlogoff' => array(
						'controller' => '\\ElkArte\\AdminController\\Admin',
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
						'controller' => '\\ElkArte\\AdminController\\CoreFeatures',
						'function' => 'action_index',
						'icon' => 'transparent.png',
						'class' => 'admin_img_corefeatures',
					),
					'featuresettings' => array(
						'label' => $txt['modSettings_title'],
						'controller' => '\\ElkArte\\AdminController\\ManageFeatures',
						'function' => 'action_index',
						'icon' => 'transparent.png',
						'class' => 'admin_img_features',
						'subsections' => array(
							'basic' => array($txt['mods_cat_features']),
							'layout' => array($txt['mods_cat_layout']),
							'pmsettings' => array($txt['personal_messages']),
							'karma' => array($txt['karma'], 'enabled' => featureEnabled('k')),
							'likes' => array($txt['likes'], 'enabled' => featureEnabled('l')),
							'mention' => array($txt['mention']),
							'sig' => array($txt['signature_settings_short']),
							'profile' => array($txt['custom_profile_shorttitle'], 'enabled' => featureEnabled('cp')),
						),
					),
					'serversettings' => array(
						'label' => $txt['admin_server_settings'],
						'controller' => '\\ElkArte\\AdminController\\ManageServer',
						'function' => 'action_index',
						'icon' => 'transparent.png',
						'class' => 'admin_img_server',
						'subsections' => array(
							'general' => array($txt['general_settings']),
							'database' => array($txt['database_paths_settings']),
							'cookie' => array($txt['cookies_sessions_settings']),
							'cache' => array($txt['caching_settings']),
							'loads' => array($txt['loadavg_settings']),
							'phpinfo' => array($txt['phpinfo_settings']),
						),
					),
					'securitysettings' => array(
						'label' => $txt['admin_security_moderation'],
						'controller' => '\\ElkArte\\AdminController\\ManageSecurity',
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
						'controller' => '\\ElkArte\\AdminController\\ManageThemes',
						'function' => 'action_index',
						'custom_url' => getUrl('admin', ['action' => 'admin', 'area' => 'theme']),
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
						'controller' => '\\ElkArte\\AdminController\\ManageThemes',
						'function' => 'action_index',
						'custom_url' => getUrl('admin', ['action' => 'admin', 'area' => 'theme', 'sa' => 'list', 'th' => $settings['theme_id']]),
						'icon' => 'transparent.png',
						'class' => 'admin_img_current_theme',
					),
					'languages' => array(
						'label' => $txt['language_configuration'],
						'controller' => '\\ElkArte\\AdminController\\ManageLanguages',
						'function' => 'action_index',
						'icon' => 'transparent.png',
						'class' => 'admin_img_languages',
						'subsections' => array(
							'edit' => array($txt['language_edit']),
							// 'add' => array($txt['language_add']),
							'settings' => array($txt['language_settings']),
						),
					),
					'addonsettings' => array(
						'label' => $txt['admin_modifications'],
						'controller' => '\\ElkArte\\AdminController\\AddonSettings',
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
						'controller' => '\\ElkArte\\AdminController\\ManageBoards',
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
						'controller' => '\\ElkArte\\AdminController\\ManagePosts',
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
					'bbc' => array(
						'label' => $txt['bbc_manage'],
						'controller' => '\\ElkArte\\AdminController\\ManageBBC',
						'function' => 'action_index',
						'icon' => 'transparent.png',
						'class' => 'admin_img_smiley',
						'permission' => array('manage_bbc'),
					),
					'smileys' => array(
						'label' => $txt['smileys_manage'],
						'controller' => '\\ElkArte\\AdminController\\ManageSmileys',
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
						'controller' => '\\ElkArte\\AdminController\\ManageAttachments',
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
						'controller' => '\\ElkArte\\AdminController\\ManageSearch',
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
				),
			),
			'members' => array(
				'title' => $txt['admin_manage_members'],
				'permission' => array('moderate_forum', 'manage_membergroups', 'manage_bans', 'manage_permissions', 'admin_forum'),
				'areas' => array(
					'viewmembers' => array(
						'label' => $txt['admin_users'],
						'controller' => '\\ElkArte\\AdminController\\ManageMembers',
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
						'controller' => '\\ElkArte\\AdminController\\ManageMembergroups',
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
						'controller' => '\\ElkArte\\AdminController\\ManagePermissions',
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
						'controller' => '\\ElkArte\\AdminController\\ManageBans',
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
						'controller' => '\\ElkArte\\AdminController\\ManageRegistration',
						'function' => 'action_index',
						'icon' => 'transparent.png',
						'class' => 'admin_img_regcenter',
						'permission' => array('admin_forum', 'moderate_forum'),
						'subsections' => array(
							'register' => array($txt['admin_browse_register_new'], 'moderate_forum'),
							'agreement' => array($txt['registration_agreement'], 'admin_forum'),
							'privacypol' => array($txt['privacy_policy'], 'admin_forum'),
							'reservednames' => array($txt['admin_reserved_set'], 'admin_forum'),
							'settings' => array($txt['settings'], 'admin_forum'),
						),
					),
					'sengines' => array(
						'label' => $txt['search_engines'],
						'enabled' => featureEnabled('sp'),
						'controller' => '\\ElkArte\\AdminController\\ManageSearchEngines',
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
						'enabled' => featureEnabled('ps'),
						'controller' => '\\ElkArte\\AdminController\\ManagePaid',
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
						'controller' => '\\ElkArte\\AdminController\\Maintenance',
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
						'controller' => '\\ElkArte\\AdminController\\AdminLog',
						'function' => 'action_index',
						'icon' => 'transparent.png',
						'class' => 'admin_img_logs',
						'subsections' => array(
							'errorlog' => array($txt['errlog'], 'admin_forum', 'enabled' => !empty($modSettings['enableErrorLogging']), 'url' => getUrl('admin', ['action' => 'admin', 'area' => 'logs', 'sa' => 'errorlog', 'desc'])),
							'adminlog' => array($txt['admin_log'], 'admin_forum', 'enabled' => featureEnabled('ml')),
							'modlog' => array($txt['moderation_log'], 'admin_forum', 'enabled' => featureEnabled('ml')),
							'banlog' => array($txt['ban_log'], 'manage_bans'),
							'spiderlog' => array($txt['spider_logs'], 'admin_forum', 'enabled' => featureEnabled('sp')),
							'tasklog' => array($txt['scheduled_log'], 'admin_forum'),
							'badbehaviorlog' => array($txt['badbehavior_log'], 'admin_forum', 'enabled' => !empty($modSettings['badbehavior_enabled']), 'url' => getUrl('admin', ['action' => 'admin', 'area' => 'logs', 'sa' => 'badbehaviorlog', 'desc'])),
							'pruning' => array($txt['pruning_title'], 'admin_forum'),
						),
					),
					'scheduledtasks' => array(
						'label' => $txt['maintain_tasks'],
						'controller' => '\\ElkArte\\AdminController\\ManageScheduledTasks',
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
						'controller' => '\\ElkArte\\AdminController\\ManageMail',
						'function' => 'action_index',
						'icon' => 'transparent.png',
						'class' => 'admin_img_mail',
						'subsections' => array(
							'browse' => array($txt['mailqueue_browse'], 'admin_forum'),
							'settings' => array($txt['mailqueue_settings'], 'admin_forum'),
						),
					),
					'reports' => array(
						'enabled' => featureEnabled('rg'),
						'label' => $txt['generate_reports'],
						'controller' => '\\ElkArte\\AdminController\\Reports',
						'function' => 'action_index',
						'icon' => 'transparent.png',
						'class' => 'admin_img_reports',
					),
					'repairboards' => array(
						'label' => $txt['admin_repair'],
						'controller' => '\\ElkArte\\AdminController\\RepairBoards',
						'function' => 'action_repairboards',
						'select' => 'maintain',
						'hidden' => true,
					),
				),
			),
		);

		// Any menu items that Modules want to add
		$this->_getModulesMenu($admin_areas);

		// Any files to include for administration?
		call_integration_include_hook('integrate_admin_include');

		// Set our menu options
		$menuOptions = array('hook' => 'admin', 'default_include_dir' => ADMINDIR);

		// Setup the menu
		$menu = Menu::instance();
		$menu->addOptions($menuOptions);
		$menu->addAreas($admin_areas);

		// Create the menu, calling integrate_admin_areas at the start
		$admin_include_data = $menu->prepareMenu();
		$menu->setContext();
		unset($admin_areas);

		// Nothing valid?
		if ($admin_include_data === false)
		{
			throw new Exception('no_access', false);
		}

		// Make a note of the Unique ID for this menu.
		$context['admin_menu_id'] = $context['max_menu_id'];
		$context['admin_menu_name'] = 'menu_data_' . $context['admin_menu_id'];

		// Where in the admin are we?
		$context['admin_area'] = $admin_include_data['current_area'];

		return $admin_include_data;
	}

	/**
	 * Builds out the navigation link tree for the admin area
	 *
	 * @param array $admin_include_data
	 */
	private function buildLinktree($admin_include_data)
	{
		global $txt, $context;

		// Build the link tree.
		$context['linktree'][] = array(
			'url' => getUrl('admin', ['action' => 'admin']),
			'name' => $txt['admin_center'],
		);

		if (isset($admin_include_data['current_area']) && $admin_include_data['current_area'] !== 'index')
		{
			$context['linktree'][] = array(
				'url' => getUrl('admin', ['action' => 'admin', 'area' => $admin_include_data['current_area'], '{session_data}']),
				'name' => $admin_include_data['label'],
			);
		}

		if (!empty($admin_include_data['current_subsection']) && $admin_include_data['subsections'][$admin_include_data['current_subsection']][0] != $admin_include_data['label'])
		{
			$context['linktree'][] = array(
				'url' => getUrl('admin', ['action' => 'admin', 'area' => $admin_include_data['current_area'], 'sa' => $admin_include_data['current_subsection'], '{session_data}']),
				'name' => $admin_include_data['subsections'][$admin_include_data['current_subsection']][0],
			);
		}
	}

	/**
	 * The main administration section.
	 *
	 * What it does:
	 *
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
		global $txt, $context, $settings;

		// We need a little help
		require_once(SUBSDIR . '/Membergroups.subs.php');

		// You have to be able to do at least one of the below to see this page.
		isAllowedTo(array('admin_forum', 'manage_permissions', 'moderate_forum', 'manage_membergroups', 'manage_bans', 'send_mail', 'edit_news', 'manage_boards', 'manage_smileys', 'manage_attachments'));

		// Find all of this forum's administrators...
		if (listMembergroupMembers_Href($context['administrators'], 1, 32) && allowedTo('manage_membergroups'))
		{
			// Add a 'more'-link if there are more than 32.
			$context['more_admins_link'] = '<a href="' . getUrl('moderate', ['action' => 'moderate', 'area' => 'viewgroups', 'sa' => 'members', 'group' => 1]) . '">' . $txt['more'] . '</a>';
		}

		// This makes it easier to get the latest news with your time format.
		$context['time_format'] = urlencode($this->user->time_format);
		$context['forum_version'] = FORUM_VERSION;

		// Get a list of current server versions.
		$context['current_versions'] = getServerVersions($this->_checkFor);
		$context['can_admin'] = allowedTo('admin_forum');
		$context['sub_template'] = 'admin';
		$context['page_title'] = $txt['admin_center'];
		$context[$context['admin_menu_name']]['tab_data'] = array(
			'title' => $txt['admin_center'],
			'help' => '',
			'description' => '
				<span class="bbc_strong">' . $txt['hello_guest'] . ' ' . $context['user']['name'] . '!</span>
				' . sprintf($txt['admin_main_welcome'], $txt['admin_control_panel'], $txt['help'], $settings['images_url']),
		);

		// Load in the admin quick tasks
		$context['quick_admin_tasks'] = getQuickAdminTasks();
	}

	/**
	 * The credits section in admin panel.
	 *
	 * What it does:
	 *
	 * - Determines the current level of support functions from the server, such as
	 * current level of caching engine or graphics library's installed.
	 * - Accessed by ?action=admin;area=credits
	 */
	public function action_credits()
	{
		global $txt, $context;

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
			$context['more_admins_link'] = '<a href="' . getUrl('moderate', ['action' => 'moderate', 'area' => 'viewgroups', 'sa' => 'members', 'group' => 1]) . '">' . $txt['more'] . '</a>';
		}

		// Load credits.
		$context[$context['admin_menu_name']]['tab_data'] = array(
			'title' => $txt['support_credits_title'],
			'help' => '',
			'description' => '',
		);
		theme()->getTemplates()->loadLanguageFile('Who');
		$context += prepareCreditsData();

		// This makes it easier to get the latest news with your time format.
		$context['time_format'] = urlencode($this->user->time_format);
		$context['forum_version'] = FORUM_VERSION;

		// Get a list of current server versions.
		$context['current_versions'] = getServerVersions($this->_checkFor);
		$context['can_admin'] = allowedTo('admin_forum');
		$context['sub_template'] = 'credits';
		$context['page_title'] = $txt['support_credits_title'];

		// Load in the admin quick tasks
		$context['quick_admin_tasks'] = getQuickAdminTasks();

		$index = 'new_in_' . str_replace(array('ElkArte ', '.'), array('', '_'), FORUM_VERSION);
		if (isset($txt[$index]))
		{
			$context['latest_updates'] = replaceBasicActionUrl($txt[$index]);
			require_once(SUBSDIR . '/Themes.subs.php');

			updateThemeOptions(array(1, $this->user->id, 'dismissed_' . $index, 1));
		}
	}

	/**
	 * This function allocates out all the search stuff.
	 *
	 * What it does:
	 *
	 * - Accessed with /index.php?action=admin;area=search[;search_type=x]
	 * - Sets up an array of applicable sub-actions (search types) and the function that goes with each
	 * - Search type specified by "search_type" request variable (either from a
	 * form or from the query string) Defaults to 'internal'
	 * - Calls the appropriate sub action based on the search_type
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

		// Set the subaction
		$action = new Action('admin_search');
		$subAction = $action->initialize($subActions, 'internal');

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
		$context['search_term'] = $this->_req->getPost('search_term', 'trim|\\ElkArte\\Util::htmlspecialchars[ENT_QUOTES]');
		$context['sub_template'] = 'admin_search_results';
		$context['page_title'] = $txt['admin_search_results'];

		// You did remember to enter something to search for, otherwise its easy
		if ($context['search_term'] === '')
		{
			$context['search_results'] = array();
		}
		else
		{
			$action->dispatch($subAction);
		}
	}

	/**
	 * A complicated but relatively quick internal search.
	 *
	 * What it does:
	 *
	 * - Can be accessed with /index.php?action=admin;sa=search;search_term=x) or
	 * from the admin search form ("Task/Setting" option)
	 * - Polls the controllers for their configuration settings
	 * - Calls integrate_admin_search to allow addons to add search configs
	 * - Loads up the "Help" language file and all of the "Manage" language files
	 * - Loads up information about each item it found for the template
	 *
	 * @event integrate_admin_search Allows integration to add areas to the internal admin search
	 * @event search Allows active modules registered to search to add settings for internal search
	 */
	public function action_search_internal()
	{
		global $context, $txt;

		// Try to get some more memory.
		detectServer()->setMemoryLimit('128M');

		// Load a lot of language files.
		$language_files = array(
			'Help', 'ManageMail', 'ManageSettings', 'ManageBoards', 'ManagePaid', 'ManagePermissions', 'Search',
			'Login', 'ManageSmileys', 'Maillist', 'Mentions'
		);

		// All the files we need to include to search for settings
		$include_files = array();

		// This is a special array of functions that contain setting data
		// - we query all these to simply pull all setting bits!
		$settings_search = array(
			array('settings_search', 'area=logs;sa=pruning', '\\ElkArte\\AdminController\\AdminLog'),
			array('config_vars', 'area=corefeatures', '\\ElkArte\\AdminController\\CoreFeatures'),
			array('basicSettings_search', 'area=featuresettings;sa=basic', '\\ElkArte\\AdminController\\ManageFeatures'),
			array('layoutSettings_search', 'area=featuresettings;sa=layout', '\\ElkArte\\AdminController\\ManageFeatures'),
			array('karmaSettings_search', 'area=featuresettings;sa=karma', '\\ElkArte\\AdminController\\ManageFeatures'),
			array('likesSettings_search', 'area=featuresettings;sa=likes', '\\ElkArte\\AdminController\\ManageFeatures'),
			array('mentionSettings_search', 'area=featuresettings;sa=mention', '\\ElkArte\\AdminController\\ManageFeatures'),
			array('signatureSettings_search', 'area=featuresettings;sa=sig', '\\ElkArte\\AdminController\\ManageFeatures'),
			array('settings_search', 'area=addonsettings;sa=general', '\\ElkArte\\AdminController\\AddonSettings'),
			array('settings_search', 'area=manageattachments;sa=attachments', '\\ElkArte\\AdminController\\ManageAttachments'),
			array('settings_search', 'area=manageattachments;sa=avatars', '\\ElkArte\\AdminController\\ManageAvatars'),
			array('settings_search', 'area=postsettings;sa=bbc', '\\ElkArte\\AdminController\\ManageBBC'),
			array('settings_search', 'area=manageboards;sa=settings', '\\ElkArte\\AdminController\\ManageBoards'),
			array('settings_search', 'area=languages;sa=settings', '\\ElkArte\\AdminController\\ManageLanguages'),
			array('settings_search', 'area=mailqueue;sa=settings', '\\ElkArte\\AdminController\\ManageMail'),
			array('settings_search', 'area=maillist;sa=emailsettings', '\\ElkArte\\AdminController\\ManageMaillist'),
			array('settings_search', 'area=membergroups;sa=settings', '\\ElkArte\\AdminController\\ManageMembergroups'),
			array('settings_search', 'area=news;sa=settings', '\\ElkArte\\AdminController\\ManageNews'),
			array('settings_search', 'area=paidsubscribe;sa=settings', '\\ElkArte\\AdminController\\ManagePaid'),
			array('settings_search', 'area=permissions;sa=settings', '\\ElkArte\\AdminController\\ManagePermissions'),
			array('settings_search', 'area=postsettings;sa=posts', '\\ElkArte\\AdminController\\ManagePosts'),
			array('settings_search', 'area=regcenter;sa=settings', '\\ElkArte\\AdminController\\ManageRegistration'),
			array('settings_search', 'area=managesearch;sa=settings', '\\ElkArte\\AdminController\\ManageSearch'),
			array('settings_search', 'area=sengines;sa=settings', '\\ElkArte\\AdminController\\ManageSearchEngines'),
			array('securitySettings_search', 'area=securitysettings;sa=general', '\\ElkArte\\AdminController\\ManageSecurity'),
			array('spamSettings_search', 'area=securitysettings;sa=spam', '\\ElkArte\\AdminController\\ManageSecurity'),
			array('moderationSettings_search', 'area=securitysettings;sa=moderation', '\\ElkArte\\AdminController\\ManageSecurity'),
			array('bbSettings_search', 'area=securitysettings;sa=badbehavior', '\\ElkArte\\AdminController\\ManageSecurity'),
			array('generalSettings_search', 'area=serversettings;sa=general', '\\ElkArte\\AdminController\\ManageServer'),
			array('databaseSettings_search', 'area=serversettings;sa=database', '\\ElkArte\\AdminController\\ManageServer'),
			array('cookieSettings_search', 'area=serversettings;sa=cookie', '\\ElkArte\\AdminController\\ManageServer'),
			array('cacheSettings_search', 'area=serversettings;sa=cache', '\\ElkArte\\AdminController\\ManageServer'),
			array('balancingSettings_search', 'area=serversettings;sa=loads', '\\ElkArte\\AdminController\\ManageServer'),
			array('settings_search', 'area=smileys;sa=settings', '\\ElkArte\\AdminController\\ManageSmileys'),
			array('settings_search', 'area=postsettings;sa=topics', '\\ElkArte\\AdminController\\ManageTopics'),
		);

		// Allow integration to add settings to search
		call_integration_hook('integrate_admin_search', array(&$language_files, &$include_files, &$settings_search));

		// Allow active modules to add settings for internal search
		$this->_events->trigger('search', array('language_files' => &$language_files, 'include_files' => &$include_files, 'settings_search' => &$settings_search));

		// Go through all the search data trying to find this text!
		$search_term = strtolower(un_htmlspecialchars($context['search_term']));
		$search = new AdminSettingsSearch($language_files, $include_files, $settings_search);
		$search->initSearch($context['admin_menu_name'], array(
			array('COPPA', 'area=regcenter;sa=settings'),
			array('CAPTCHA', 'area=securitysettings;sa=spam'),
		));

		$context['page_title'] = $txt['admin_search_results'];
		$context['search_results'] = $search->doSearch($search_term);
	}

	/**
	 * All this does is pass through to manage members.
	 */
	public function action_search_member()
	{
		global $context;

		// @todo once Action.class is changed
		$_REQUEST['sa'] = 'query';

		// Set the query values
		$this->_req->post->sa = 'query';
		$this->_req->post->membername = un_htmlspecialchars($context['search_term']);
		$this->_req->post->types = '';

		$managemembers = new ManageMembers(new EventManager());
		$managemembers->setUser(User::$info);
		$managemembers->pre_dispatch();
		$managemembers->action_index();
	}

	/**
	 * This file allows the user to search the wiki documentation
	 * for a little help.
	 *
	 * What it does:
	 *
	 * - Creates an exception since GitHub does not yet support API wiki searches so the connection
	 * will fail.
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
		{
			$postVars[$k] = urlencode($v);
		}

		// This is what we will send.
		$postVars = implode('+', $postVars);

		// Get the results from the doc site.
		require_once(SUBSDIR . '/Package.subs.php');
		// Demo URL:
		// https://github.com/elkarte/Elkarte/wiki/api.php?action=query&list=search&srprop=timestamp|snippet&format=xml&srwhat=text&srsearch=template+eval
		$search_results = fetch_web_data($context['doc_apiurl'] . '?action=query&list=search&srprop=timestamp|snippet&format=xml&srwhat=text&srsearch=' . $postVars);

		// If we didn't get any xml back we are in trouble - perhaps the doc site is overloaded?
		if (!$search_results || preg_match('~<' . '\?xml\sversion="\d+\.\d+"\?' . '>\s*(<api>.+?</api>)~is', $search_results, $matches) !== 1)
		{
			throw new Exception('cannot_connect_doc_site');
		}

		$search_results = !empty($matches[1]) ? $matches[1] : '';

		// Otherwise we simply walk through the XML and stick it in context for display.
		$context['search_results'] = array();

		// Get the results loaded into an array for processing!
		$results = new XmlArray($search_results, false);

		// Move through the api layer.
		if (!$results->exists('api'))
		{
			throw new Exception('cannot_connect_doc_site');
		}

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

		if (isset($this->_req->query->redir, $this->_req->server->HTTP_REFERER))
		{
			redirectexit($_SERVER['HTTP_REFERER']);
		}

		redirectexit();
	}
}
