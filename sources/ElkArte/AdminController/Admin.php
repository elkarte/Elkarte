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
 * @version 2.0 Beta 1
 *
 */

namespace ElkArte\AdminController;

use ElkArte\AbstractController;
use ElkArte\Action;
use ElkArte\AdminSettingsSearch;
use ElkArte\EventManager;
use ElkArte\Exceptions\Exception;
use ElkArte\Hooks;
use ElkArte\Languages\Txt;
use ElkArte\Menu\Menu;
use ElkArte\Packages\Packages;
use ElkArte\Packages\PackageServers;
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
	/** @var string[] areas to find current installed status and installed version */
	private array $_checkFor = ['gd', 'imagick', 'db_server', 'php', 'server',
		'zend', 'apc', 'memcache', 'memcached', 'opcache'];

	/**
	 * Pre-dispatch, called before other methods.
	 *
	 * - Loads integration hooks
	 */
	public function pre_dispatch()
	{
		global $context, $modSettings;

		Hooks::instance()->loadIntegrationsSettings();

		// The Admin functions require Jquery UI ....
		$modSettings['jquery_include_ui'] = true;

		// No indexing evil stuff.
		$context['robot_no_index'] = true;

		// Need this to do much
		require_once(SUBSDIR . '/Admin.subs.php');
	}

	/**
	 * The main admin handling function.
	 *
	 * What it does:
	 *
	 * - It initializes all the basic context required for the admin center.
	 * - It passes execution onto the relevant admin section.
	 * - If the passed section is not found, it shows the admin home page.
	 * - Accessed by ?action=admin.
	 */
	public function action_index()
	{
		// Make sure the administrator has a valid session...
		validateSession();

		// Load the language and templates....
		Txt::load('Admin+Help+ManageSettings');
		theme()->getTemplates()->load('Admin');
		loadCSSFile('admin.css');
		loadJavascriptFile('admin.js', [], 'admin_script');

		// Actually create the menu!
		$admin_include_data = $this->loadMenu();
		$this->buildBreadCrumbs($admin_include_data);

		// And off we go, only one action, the chosen menu area
		$action = new Action();
		$action->initialize(['action' => $admin_include_data]);
		$action->dispatch('action');
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
	 * @event addMenu passed admin area, allows active modules registered to this event to add items to the admin menu,
	 * @event integrate_admin_areas passed admin area, used to add items to the admin menu
	 *
	 * @return array
	 * @throws Exception no_access
	 */
	private function loadMenu(): array
	{
		global $txt, $context, $modSettings, $settings;

		// Need these to do much
		require_once(SUBSDIR . '/Menu.subs.php');

		// Define the menu structure - see subs/Menu.subs.php for details!
		$admin_areas = [
			'admin_tools' => [
				'title' => $txt['admin_main'],
				'permission' => ['admin_forum', 'manage_permissions', 'moderate_forum', 'manage_membergroups', 'manage_bans', 'send_mail', 'edit_news', 'manage_boards', 'manage_smileys', 'manage_attachments'],
				'areas' => [
					'index' => [
						'label' => $txt['admin_center'],
						'controller' => __CLASS__,
						'function' => 'action_home',
						'class' => 'i-home i-admin',
					],
					'credits' => [
						'label' => $txt['support_credits_title'],
						'controller' => __CLASS__,
						'function' => 'action_credits',
						'class' => 'i-support i-admin',
					],
					'adminlogoff' => [
						'label' => $txt['admin_logoff'],
						'controller' => __CLASS__,
						'function' => 'action_endsession',
						'enabled' => empty($modSettings['securityDisable']),
						'class' => 'i-sign-out i-admin',
					],
					'search' => [
						'controller' => __CLASS__,
						'function' => 'action_search',
						'permission' => ['admin_forum'],
						'class' => 'i-search i-admin',
						'select' => 'index',
						'hidden' => true,
					],
				],
			],
			'communication' => [
				'title' => $txt['communication_title'],
				'permission' => ['approve_emails', 'send_mail', 'edit_news', 'admin_forum'],
				'areas' => [
					'maillist' => [
						'label' => $txt['mail_center'],
						'controller' => ManageMaillist::class,
						'function' => 'action_index',
						'class' => 'i-envelope-blank i-admin',
						'permission' => ['approve_emails', 'admin_forum'],
						'enabled' => featureEnabled('pe'),
						'subsections' => [
							'emaillist' => [$txt['mm_emailerror'], 'approve_emails'],
							'emailfilters' => [$txt['mm_emailfilters'], 'admin_forum'],
							'emailparser' => [$txt['mm_emailparsers'], 'admin_forum'],
							'emailtemplates' => [$txt['mm_emailtemplates'], 'approve_emails'],
							'emailsettings' => [$txt['mm_emailsettings'], 'admin_forum'],
						],
					],
					'news' => [
						'label' => $txt['news_title'],
						'controller' => ManageNews::class,
						'function' => 'action_index',
						'class' => 'i-post-text i-admin',
						'permission' => ['edit_news', 'send_mail', 'admin_forum'],
						'subsections' => [
							'editnews' => [$txt['admin_edit_news'], 'edit_news'],
							'mailingmembers' => [$txt['admin_newsletters'], 'send_mail'],
							'settings' => [$txt['settings'], 'admin_forum'],
						],
					],
					'mailqueue' => [
						'label' => $txt['mailqueue_title'],
						'controller' => ManageMail::class,
						'function' => 'action_index',
						'class' => 'i-envelope-blank i-admin',
						'subsections' => [
							'browse' => [$txt['mailqueue_browse'], 'admin_forum'],
							'test' => [$txt['mailqueue_test'], 'admin_forum'],
							'settings' => [$txt['mailqueue_settings'], 'admin_forum'],
						],
					],
				],
			],
			'forum' => [
				'title' => $txt['layout_controls'],
				'permission' => ['manage_boards', 'admin_forum', 'manage_smileys', 'manage_attachments', 'moderate_forum'],
				'areas' => [
					'manageboards' => [
						'label' => $txt['admin_boards'],
						'controller' => ManageBoards::class,
						'function' => 'action_index',
						'class' => 'i-directory i-admin',
						'permission' => ['manage_boards'],
						'subsections' => [
							'main' => [$txt['boardsEdit']],
							'newcat' => [$txt['mboards_new_cat']],
							'settings' => [$txt['settings'], 'admin_forum'],
						],
					],
					'postsettings' => [
						'label' => $txt['manageposts'],
						'controller' => ManagePosts::class,
						'function' => 'action_index',
						'permission' => ['admin_forum'],
						'class' => 'i-post-text i-admin',
						'subsections' => [
							'censor' => [$txt['admin_censored_words']],
							'posts' => [$txt['manageposts_settings']],
							'topics' => [$txt['manageposts_topic_settings']],
							'sig' => [$txt['signature_settings']],
						],
					],
					'editor' => [
						'label' => $txt['editor_manage'],
						'controller' => ManageEditor::class,
						'function' => 'action_index',
						'class' => 'i-modify i-admin',
						'permission' => ['manage_bbc'],
					],
					'smileys' => [
						'label' => $txt['smileys_manage'],
						'controller' => ManageSmileys::class,
						'function' => 'action_index',
						'class' => 'i-smiley-blank i-admin',
						'permission' => ['manage_smileys'],
						'subsections' => [
							'editsets' => [$txt['smiley_sets']],
							'addsmiley' => [$txt['smileys_add']],
							'editsmileys' => [$txt['smileys_edit']],
							'setorder' => [$txt['smileys_set_order']],
							'editicons' => [$txt['icons_edit_message_icons'], 'enabled' => !empty($modSettings['messageIcons_enable'])],
							'settings' => [$txt['settings']],
						],
					],
					'manageattachments' => [
						'label' => $txt['attachments_avatars'],
						'controller' => ManageAttachments::class,
						'function' => 'action_index',
						'class' => 'i-paperclip i-admin',
						'permission' => ['manage_attachments'],
						'subsections' => [
							'browse' => [$txt['attachment_manager_browse']],
							'attachments' => [$txt['attachment_manager_settings']],
							'avatars' => [$txt['attachment_manager_avatar_settings']],
							'attachpaths' => [$txt['attach_directories']],
							'maintenance' => [$txt['attachment_manager_maintenance']],
						],
					],
					'managesearch' => [
						'label' => $txt['manage_search'],
						'controller' => ManageSearch::class,
						'function' => 'action_index',
						'class' => 'i-search i-admin',
						'permission' => ['admin_forum'],
						'subsections' => [
							'method' => [$txt['search_method']],
							'weights' => [$txt['search_weights']],
							'managesphinxql' => [$txt['search_sphinx']],
							'managemanticore' => [$txt['search_manticore']],
							'settings' => [$txt['settings']],
						],
					],
					'likes' => [
						'label' => $txt['likes'],
						'enabled' => featureEnabled('l'),
						'controller' => ManageLikes::class,
						'class' => 'i-thumbsup i-admin',
						'function' => 'action_index',
						'permission' => 'admin_forum',
					],
				],
			],
			'members' => [
				'title' => $txt['admin_manage_members'],
				'permission' => ['moderate_forum', 'manage_membergroups', 'manage_bans', 'manage_permissions', 'admin_forum'],
				'areas' => [
					'viewmembers' => [
						'label' => $txt['admin_users'],
						'controller' => ManageMembers::class,
						'function' => 'action_index',
						'class' => 'i-user i-admin',
						'permission' => ['moderate_forum'],
					],
					'membergroups' => [
						'label' => $txt['admin_groups'],
						'controller' => ManageMembergroups::class,
						'function' => 'action_index',
						'class' => 'i-users',
						'permission' => ['manage_membergroups'],
						'subsections' => [
							'index' => [$txt['membergroups_edit_groups'], 'manage_membergroups'],
							'add' => [$txt['membergroups_new_group'], 'manage_membergroups'],
							'settings' => [$txt['settings'], 'admin_forum'],
						],
					],
					'permissions' => [
						'label' => $txt['edit_permissions'],
						'controller' => ManagePermissions::class,
						'function' => 'action_index',
						'class' => 'i-key i-admin',
						'permission' => ['manage_permissions'],
						'subsections' => [
							'index' => [$txt['permissions_groups'], 'manage_permissions'],
							'board' => [$txt['permissions_boards'], 'manage_permissions'],
							'profiles' => [$txt['permissions_profiles'], 'manage_permissions'],
							'postmod' => [$txt['permissions_post_moderation'], 'manage_permissions', 'enabled' => $modSettings['postmod_active']],
							'settings' => [$txt['settings'], 'admin_forum'],
						],
					],
					'ban' => [
						'label' => $txt['ban_title'],
						'controller' => ManageBans::class,
						'function' => 'action_index',
						'class' => 'i-thumbdown i-admin',
						'permission' => 'manage_bans',
						'subsections' => [
							'list' => [$txt['ban_edit_list']],
							'add' => [$txt['ban_add_new']],
							'browse' => [$txt['ban_trigger_browse']],
							'log' => [$txt['ban_log']],
						],
					],
					'moderation' => [
						'label' => $txt['moderation_warning_short'],
						'class' => 'i-key i-admin',
						'enabled' => !empty($modSettings['warning_enable']),
						'custom_url' => getUrl('admin', ['action' => 'admin', 'area' => 'securitysettings', 'sa' => 'moderation']),
					],
					'regcenter' => [
						'label' => $txt['registration_center'],
						'controller' => ManageRegistration::class,
						'function' => 'action_index',
						'class' => 'i-user-plus i-admin',
						'permission' => ['admin_forum', 'moderate_forum'],
						'subsections' => [
							'register' => [$txt['admin_browse_register_new'], 'moderate_forum'],
							'agreement' => [$txt['registration_agreement'], 'admin_forum'],
							'privacypol' => [$txt['privacy_policy'], 'admin_forum'],
							'reservednames' => [$txt['admin_reserved_set'], 'admin_forum'],
							'settings' => [$txt['settings'], 'admin_forum'],
						],
					],
					'paidsubscribe' => [
						'label' => $txt['paid_subscriptions'],
						'enabled' => featureEnabled('ps'),
						'controller' => ManagePaid::class,
						'class' => 'i-credit i-admin',
						'function' => 'action_index',
						'permission' => 'admin_forum',
						'subsections' => [
							'view' => [$txt['paid_subs_view']],
							'settings' => [$txt['settings']],
						],
					],
					'karma' => [
						'label' => $txt['karma'],
						'enabled' => featureEnabled('k'),
						'controller' => ManageKarma::class,
						'class' => 'i-karma i-admin',
						'function' => 'action_index',
						'permission' => 'admin_forum',
					],
				],
			],
			'settings' => [
				'title' => $txt['admin_config'],
				'permission' => ['admin_forum'],
				'areas' => [
					'application' => [
						'label' => $txt['core_settings_title'],
						'controller' => CoreFeatures::class,
						'function' => 'action_index',
						'class' => 'i-switch-on i-admin',
					],
					'featuresettings' => [
						'label' => $txt['core_settings'],
						'controller' => ManageFeatures::class,
						'function' => 'action_index',
						'class' => 'i-tools i-admin',
						'subsections' => [
							'basic' => [$txt['general_settings']],
							'profile' => [$txt['custom_profile_shorttitle'], 'enabled' => featureEnabled('cp')],
							'pmsettings' => [$txt['personal_messages'], 'controller' => ManagePmSettings::class],
							'pwa' => [$txt['pwa_settings'], 'controller' => ManagePwa::class],
							'mentions' => [$txt['notifications'], 'controller' => ManageMentions::class],
						],
					],
					'layout' => [
						'label' => $txt['mods_cat_layout'],
						'controller' => ManageLayout::class,
						'function' => 'action_index',
						'class' => 'i-paint i-admin',
					],
					'themes' => [
						'label' => $txt['theme_admin'],
						'controller' => ManageThemes::class,
						'function' => 'action_index',
						'class' => 'i-modify i-admin',
						'subsections' => [
							'admin' => [$txt['themeadmin_admin_title']],
							'list' => [$txt['themeadmin_list_title']],
							'reset' => [$txt['themeadmin_reset_title']],
						],
					],
					'languages' => [
						'label' => $txt['language_configuration'],
						'controller' => ManageLanguages::class,
						'function' => 'action_index',
						'class' => 'i-language i-admin',
						'subsections' => [
							'edit' => [$txt['language_edit']],
							'settings' => [$txt['language_settings']],
						],
					],
				],
			],
			'system_moderation' => [
				'title' => $txt['admin_system_moderation'],
				'permission' => ['admin_forum'],
				'areas' => [
					'serversettings' => [
						'label' => $txt['admin_server_settings'],
						'controller' => ManageServer::class,
						'function' => 'action_index',
						'class' => 'i-menu i-admin',
						'subsections' => [
							'general' => [$txt['general_settings']],
							'database' => [$txt['database_paths_settings']],
							'cookie' => [$txt['cookies_sessions_settings']],
							'cache' => [$txt['caching_settings']],
							'loads' => [$txt['loadavg_settings']],
							'phpinfo' => [$txt['phpinfo_settings']],
						],
					],
					'securitysettings' => [
						'label' => $txt['admin_security_moderation'],
						'controller' => ManageSecurity::class,
						'function' => 'action_index',
						'class' => 'i-key i-admin',
						'subsections' => [
							'general' => [$txt['mods_cat_security_general']],
							'spam' => [$txt['antispam_title']],
						],
					],
					'maintain' => [
						'label' => $txt['maintain_title'],
						'controller' => Maintenance::class,
						'function' => 'action_index',
						'class' => 'i-cog i-admin',
						'subsections' => [
							'routine' => [$txt['maintain_sub_routine'], 'admin_forum'],
							'database' => [$txt['maintain_sub_database'], 'admin_forum'],
							'members' => [$txt['maintain_sub_members'], 'admin_forum'],
							'topics' => [$txt['maintain_sub_topics'], 'admin_forum'],
							'hooks' => [$txt['maintain_sub_hooks_list'], 'admin_forum'],
							'attachments' => [$txt['maintain_sub_attachments'], 'admin_forum'],
						],
					],
					'logs' => [
						'label' => $txt['logs'],
						'controller' => AdminLog::class,
						'function' => 'action_index',
						'class' => 'i-comments i-admin',
						'subsections' => [
							'errorlog' => [$txt['errlog'], 'admin_forum', 'enabled' => !empty($modSettings['enableErrorLogging'])],
							'adminlog' => [$txt['admin_log'], 'admin_forum', 'enabled' => featureEnabled('ml')],
							'modlog' => [$txt['moderation_log'], 'admin_forum', 'enabled' => featureEnabled('ml')],
							'banlog' => [$txt['ban_log'], 'manage_bans'],
							'spiderlog' => [$txt['spider_logs'], 'admin_forum', 'enabled' => featureEnabled('sp')],
							'tasklog' => [$txt['scheduled_log'], 'admin_forum'],
							'pruning' => [$txt['settings'], 'admin_forum'],
						],
					],
					'scheduledtasks' => [
						'label' => $txt['maintain_tasks'],
						'controller' => ManageScheduledTasks::class,
						'function' => 'action_index',
						'class' => 'i-calendar i-admin',
						'subsections' => [
							'tasks' => [$txt['maintain_tasks'], 'admin_forum'],
							'tasklog' => [$txt['scheduled_log'], 'admin_forum'],
						],
					],
					'sengines' => [
						'label' => $txt['search_engines'],
						'enabled' => featureEnabled('sp'),
						'controller' => ManageSearchEngines::class,
						'function' => 'action_index',
						'class' => 'i-website i-admin',
						'permission' => 'admin_forum',
						'subsections' => [
							'stats' => [$txt['spider_stats']],
							'logs' => [$txt['spider_logs']],
							'spiders' => [$txt['spiders']],
							'settings' => [$txt['settings']],
						],
					],
					'reports' => [
						'label' => $txt['generate_reports'],
						'controller' => Reports::class,
						'function' => 'action_index',
						'class' => 'i-pie-chart i-admin',
						'enabled' => featureEnabled('rg'),
					],
					'repairboards' => [
						'label' => $txt['admin_repair'],
						'controller' => RepairBoards::class,
						'function' => 'action_repairboards',
						'select' => 'maintain',
						'hidden' => true,
					],
				],
			],
			'addons' => [
				'title' => $txt['admin_modifications'],
				'permission' => ['admin_forum'],
				'areas' => [
					'packages' => [
						'label' => $txt['package'],
						'controller' => Packages::class,
						'function' => 'action_index',
						'permission' => ['admin_forum'],
						'class' => 'i-package i-admin',
						'subsections' => [
							'browse' => [$txt['browse_packages']],
							'servers' => [$txt['add_packages']],
							'options' => [$txt['package_settings']],
						],
					],
					'packageservers' => [
						'label' => $txt['package_servers'],
						'controller' => PackageServers::class,
						'function' => 'action_index',
						'permission' => ['admin_forum'],
						'class' => 'i-package i-admin',
						'hidden' => true,
					],
					'addonsettings' => [
						'label' => $txt['admin_modifications_settings'],
						'controller' => AddonSettings::class,
						'function' => 'action_index',
						'class' => 'i-puzzle i-admin',
						'subsections' => [
							'general' => [$txt['mods_cat_modifications_misc']],
						],
					],
				],
			],
		];

		$this->_events->trigger('addMenu', ['admin_areas' => &$admin_areas]);

		// Any files to include for administration?
		call_integration_include_hook('integrate_admin_include');

		$menuOptions = [
			'hook' => 'admin',
		];

		// Actually create the admin menu!
		$admin_include_data = (new Menu())
			->addMenuData($admin_areas)
			->addOptions($menuOptions)
			->prepareMenu()
			->setContext()
			->getIncludeData();

		unset($admin_areas);

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
	private function buildBreadCrumbs(array $admin_include_data): void
	{
		global $txt, $context;

		// Build the link tree.
		$context['breadcrumbs'][] = [
			'url' => getUrl('admin', ['action' => 'admin']),
			'name' => $txt['admin_center'],
		];

		if (isset($admin_include_data['current_area']) && $admin_include_data['current_area'] !== 'index')
		{
			$context['breadcrumbs'][] = [
				'url' => getUrl('admin', ['action' => 'admin', 'area' => $admin_include_data['current_area'], '{session_data}']),
				'name' => $admin_include_data['label'],
			];
		}

		if (!isset($admin_include_data['current_subsection'], $admin_include_data['subsections'][$admin_include_data['current_subsection']]))
		{
			return;
		}

		if ($admin_include_data['subsections'][$admin_include_data['current_subsection']]['label'] === $admin_include_data['label'])
		{
			return;
		}

		$context['breadcrumbs'][] = [
			'url' => getUrl('admin', ['action' => 'admin', 'area' => $admin_include_data['current_area'], 'sa' => $admin_include_data['current_subsection'], '{session_data}']),
			'name' => $admin_include_data['subsections'][$admin_include_data['current_subsection']]['label'],
		];
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
	public function action_home(): void
	{
		global $txt, $context;

		// We need a little help
		require_once(SUBSDIR . '/Membergroups.subs.php');

		// You have to be able to do at least one of the below to see this page.
		isAllowedTo(['admin_forum', 'manage_permissions', 'moderate_forum', 'manage_membergroups', 'manage_bans', 'send_mail', 'edit_news', 'manage_boards', 'manage_smileys', 'manage_attachments']);

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
		$context[$context['admin_menu_name']]['object']->prepareTabData([
			'title' => 'admin_center',
			'description' => '
				<span class="bbc_strong">' . $txt['hello_guest'] . ' ' . $context['user']['name'] . '!</span>
				' . sprintf($txt['admin_main_welcome'], $txt['admin_control_panel']),
		]);

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
	public function action_credits(): void
	{
		global $txt, $context;

		// You have to be able to do at least one of the below to see this page.
		isAllowedTo(['admin_forum', 'manage_permissions', 'moderate_forum', 'manage_membergroups', 'manage_bans', 'send_mail', 'edit_news', 'manage_boards', 'manage_smileys', 'manage_attachments']);

		// We need a little help from our friends
		require_once(SUBSDIR . '/Membergroups.subs.php');
		require_once(SUBSDIR . '/Who.subs.php');
		require_once(SUBSDIR . '/Admin.subs.php');
		require_once(SUBSDIR . '/About.subs.php');

		// Find all of this forum's administrators...
		if (listMembergroupMembers_Href($context['administrators'], 1, 32) && allowedTo('manage_membergroups'))
		{
			// Add a 'more'-link if there are more than 32.
			$context['more_admins_link'] = '<a href="' . getUrl('moderate', ['action' => 'moderate', 'area' => 'viewgroups', 'sa' => 'members', 'group' => 1]) . '">' . $txt['more'] . '</a>';
		}

		// Load credits.
		$context[$context['admin_menu_name']]['object']->prepareTabData([
			'title' => 'support_credits_title',
			'description' => 'support_credits_desc',
		]);

		Txt::load('About');
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

		$index = 'new_in_' . str_replace(['ElkArte ', '.'], ['', '_'], FORUM_VERSION);
		if (isset($txt[$index]))
		{
			$context['latest_updates'] = replaceBasicActionUrl($txt[$index]);
			require_once(SUBSDIR . '/Themes.subs.php');

			updateThemeOptions([1, $this->user->id, 'dismissed_' . $index, 1]);
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
	public function action_search(): void
	{
		global $txt, $context;

		// What can we search for?
		$subActions = [
			'internal' => [$this, 'action_search_internal', 'permission' => 'admin_forum'],
			'online' => [$this, 'action_search_doc', 'permission' => 'admin_forum'],
			'member' => [$this, 'action_search_member', 'permission' => 'admin_forum'],
		];

		// Set the subaction
		$action = new Action('admin_search');
		$subAction = $action->initialize($subActions, 'internal');

		// Keep track of what the admin wants in terms of advanced or not
		if (empty($context['admin_preferences']['sb']) || $context['admin_preferences']['sb'] !== $subAction)
		{
			$context['admin_preferences']['sb'] = $subAction;

			// Update the preferences.
			require_once(SUBSDIR . '/Admin.subs.php');
			updateAdminPreferences();
		}

		// Setup for the template
		$context['search_type'] = $subAction;
		$context['search_term'] = $this->_req->getPost('search_term', 'trim|Util::htmlspecialchars[ENT_QUOTES]');
		$context['sub_template'] = 'admin_search_results';
		$context['page_title'] = $txt['admin_search_results'];

		// You did remember to enter something to search for, otherwise it's easy
		if ($context['search_term'] === '')
		{
			$context['search_results'] = [];
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
	 * - Can be accessed with /index.php?action=admin;sa=search;search_term=x) or from the admin search form ("Task/Setting" option).
	 * - Polls the controllers for their configuration settings.
	 * - Calls integrate_admin_search to allow addons to add search configs.
	 * - Loads up the "Help" language file and all "Manage" language files.
	 * - Loads up information about each item it found for the template.
	 *
	 * @event integrate_admin_search Allows integration to add areas to the internal admin search
	 * @event search Allows active modules registered to search to add settings for internal search
	 */
	public function action_search_internal(): void
	{
		global $context, $txt;

		// Try to get some more memory.
		detectServer()->setMemoryLimit('128M');

		// Load a lot of language files.
		$language_files = [
			'Help', 'ManageMail', 'ManageSettings', 'ManageBoards', 'ManagePaid', 'ManagePermissions', 'Search',
			'Login', 'ManageSmileys', 'Maillist', 'Mentions', 'Addons'
		];

		// All the files we need to include to search for settings
		$include_files = [];

		// This is a special array of functions that contain setting data
		// - we query all these to simply pull all setting bits!
		$settings_search = [
			['settings_search', 'area=addonsettings;sa=general', AddonSettings::class],
			['settings_search', 'area=logs;sa=pruning', AdminLog::class],
			['config_vars', 'area=corefeatures', CoreFeatures::class],
			['settings_search', 'area=manageattachments;sa=attachments', ManageAttachments::class],
			['settings_search', 'area=manageattachments;sa=avatars', ManageAvatars::class],
			['settings_search', 'area=manageboards;sa=settings', ManageBoards::class],
			['settings_search', 'area=postsettings;sa=bbc', ManageEditor::class],
			['basicSettings_search', 'area=featuresettings;sa=basic', ManageFeatures::class],
			['layoutSettings_search', 'area=featuresettings;sa=layout', ManageLayout::class],
			['pwaSettings_search', 'area=featuresettings;sa=pwa', ManagePwa::class],
			['karmaSettings_search', 'area=featuresettings;sa=karma', ManageKarma::class],
			['pmSettings_search', 'area=featuresettings;sa=pmsettings', ManagePmSettings::class],
			['likesSettings_search', 'area=featuresettings;sa=likes', ManageLikes::class],
			['mentionSettings_search', 'area=featuresettings;sa=mentions', ManageMentions::class],
			['signatureSettings_search', 'area=featuresettings;sa=sig', ManageSignature::class],
			['settings_search', 'area=languages;sa=settings', ManageLanguages::class],
			['settings_search', 'area=mailqueue;sa=settings', ManageMail::class],
			['settings_search', 'area=maillist;sa=emailsettings', ManageMaillist::class],
			['filter_search', 'area=maillist;sa=emailsettings', ManageMaillist::class],
			['parser_search', 'area=maillist;sa=emailsettings', ManageMaillist::class],
			['settings_search', 'area=membergroups;sa=settings', ManageMembergroups::class],
			['settings_search', 'area=news;sa=settings', ManageNews::class],
			['settings_search', 'area=paidsubscribe;sa=settings', ManagePaid::class],
			['settings_search', 'area=permissions;sa=settings', ManagePermissions::class],
			['settings_search', 'area=postsettings;sa=posts', ManagePosts::class],
			['settings_search', 'area=regcenter;sa=settings', ManageRegistration::class],
			['settings_search', 'area=managesearch;sa=settings', ManageSearch::class],
			['settings_search', 'area=sengines;sa=settings', ManageSearchEngines::class],
			['securitySettings_search', 'area=securitysettings;sa=general', ManageSecurity::class],
			['spamSettings_search', 'area=securitysettings;sa=spam', ManageSecurity::class],
			['moderationSettings_search', 'area=securitysettings;sa=moderation', ManageSecurity::class],
			['generalSettings_search', 'area=serversettings;sa=general', ManageServer::class],
			['databaseSettings_search', 'area=serversettings;sa=database', ManageServer::class],
			['cookieSettings_search', 'area=serversettings;sa=cookie', ManageServer::class],
			['cacheSettings_search', 'area=serversettings;sa=cache', ManageServer::class],
			['balancingSettings_search', 'area=serversettings;sa=loads', ManageServer::class],
			['settings_search', 'area=smileys;sa=settings', ManageSmileys::class],
			['settings_search', 'area=postsettings;sa=topics', ManageTopics::class],
		];

		// Allow integration to add settings to search
		call_integration_hook('integrate_admin_search', [&$language_files, &$include_files, &$settings_search]);

		// Allow active modules to add settings for internal search
		$this->_events->trigger('addSearch', ['language_files' => &$language_files, 'include_files' => &$include_files, 'settings_search' => &$settings_search]);

		// Go through all the search data trying to find this text!
		$context['search_results'] = [];
		if (isset($context['search_term']))
		{
			$search_term = strtolower(un_htmlspecialchars($context['search_term']));
			$search = new AdminSettingsSearch($language_files, $include_files, $settings_search);
			$search->initSearch($context['admin_menu_name'], [
				['COPPA', 'area=regcenter;sa=settings'],
				['CAPTCHA', 'area=securitysettings;sa=spam'],
			]);
			$context['search_results'] = $search->doSearch($search_term);
		}

		$context['page_title'] = $txt['admin_search_results'];
	}

	/**
	 * All this does is pass-through to manage members.
	 */
	public function action_search_member(): void
	{
		global $context;

		// @todo once Action.class is changed
		$_REQUEST['sa'] = 'query';

		// Set the query values
		$this->_req->post->sa = 'query';
		$this->_req->post->membername = un_htmlspecialchars($context['search_term']);
		$this->_req->post->types = '';

		$manageMembers = new ManageMembers(new EventManager());
		$manageMembers->setUser(User::$info);
		$manageMembers->pre_dispatch();
		$manageMembers->action_index();
	}

	/**
	 * This file allows the user to search the wiki documentation for a little help.
	 *
	 * What it does:
	 *   - Creates an exception since GitHub does not yet support API wiki searches, so the connection
	 * will fail.
	 */
	public function action_search_doc(): void
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

		// If we didn't get any XML back, we are in trouble - perhaps the doc site is overloaded?
		if (!$search_results || preg_match('~<\?xml\sversion="\d+\.\d+"\?>\s*(<api>.+?</api>)~is', $search_results, $matches) !== 1)
		{
			throw new Exception('cannot_connect_doc_site');
		}

		$search_results = empty($matches[1]) ? '' : $matches[1];

		// Otherwise we simply walk through the XML and stick it in context for display.
		$context['search_results'] = [];

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
				$title = $result->fetch('@title');
				$context['search_results'][$title] = [
					'title' => $title,
					'relevance' => $relevance++,
					'snippet' => str_replace("class='searchmatch'", 'class="highlight"', un_htmlspecialchars($result->fetch('@snippet'))),
				];
			}
		}
	}

	/**
	 * This ends an admin session, requiring authentication to access the ACP again.
	 */
	public function action_endsession(): void
	{
		// This is so easy!
		unset($_SESSION['admin_time']);

		// Clean any admin tokens as well.
		cleanTokens(false, '-admin');

		if (isset($this->_req->query->redir, $_SERVER['HTTP_REFERER']))
		{
			redirectexit($_SERVER['HTTP_REFERER']);
		}

		redirectexit();
	}
}
