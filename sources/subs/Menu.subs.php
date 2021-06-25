<?php

/**
 * This file contains a standard way of displaying side/drop down menus.
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

use ElkArte\User;
use ElkArte\Action;
use ElkArte\Menu\Menu;

/**
 * Create a menu.
 *
 * @depreciated since 2.0 use the menu object
 *
 * developers don't remove this until 3.0-dev
 *
 * @param mixed[] $menuData the menu array
 *  - Possible indexes:
 *    - Menu name with named indexes as follows:
 *     - string $title       => Section title
 *     - bool $enabled       => Is the section enabled / shown
 *     - array $areas        => Array of areas within this menu section, see below
 *     - array $permission   => Permission required to access the whole section
 *
 *  - $areas sub array from above, named indexes as follows:
 *     - array $permission  => Array of permissions to determine who can access this area
 *     - string $label      => Optional text string for link (Otherwise $txt[$index] will be used)
 *     - string $controller => Name of controller required for this area
 *     - string $function   => Method in controller to call when area is selected
 *     - string $icon       => File name of an icon to use on the menu, if using a class set as transparent.png
 *     - string $class      => CSS class name to apply to the icon img, used to apply a sprite icon
 *     - string $custom_url => URL to call for this menu item
 *     - bool $enabled      => Should this area even be enabled / accessible?
 *     - bool $hidden       => If the area is visible in the menu
 *     - string $select     => If set, references another area
 *     - array $subsections => Array of subsections for this menu area, see below
 *
 *  - $subsections sub array from $areas above, unnamed indexes interpreted as follows, single named index 'enabled'
 *     - string 0           => Label for this subsection
 *     - array 1            => Array of permissions to check for this subsection.
 *     - bool 2             => Is this the default subaction - if not set for any will default to first...
 *     - bool enabled       => Enabled or not
 *     - array active       => Set the button active for other subsections.
 *     - string url         => Custom url for the subsection
 *
 * @param mixed[] $menuOptions an array of options that can be used to override some default behaviours.
 *  - Possible indexes:
 *     - action                    => overrides the default action
 *     - current_area              => overrides the current area
 *     - extra_url_parameters      => an array or pairs or parameters to be added to the url
 *     - disable_url_session_check => (boolean) if true the session var/id are omitted from the url
 *     - base_url                  => an alternative base url
 *     - menu_type                 => alternative menu types?
 *     - can_toggle_drop_down      => (boolean) if the menu can "toggle"
 *     - template_name             => an alternative template to load (instead of Generic)
 *     - layer_name                => alternative layer name for the menu
 *     - hook                      => hook name to call integrate_ . 'hook name' . '_areas'
 *     - default_include_dir       => directory to include for function support
 *
 * @return array
 * @throws \ElkArte\Exceptions\Exception
 */
function createMenu($menuData, $menuOptions = [])
{
	$menu = new Menu();

	// Call the hook in compatibility mode
	if (!empty($menuOptions['hook']))
	{
		call_integration_hook('integrate_' . $menuOptions['hook'] . '_areas', array(&$menuData, &$menuOptions));
		$menuOptions['hook'] = '';
	}

	// Process options and data
	$menu->addOptions($menuOptions);
	$menu->addMenuData($menuData);

	// Set it to context
	$include_data = $menu->prepareMenu();
	$menu->setContext();

	return $include_data;
}

/**
 * Delete a menu.
 *
 * @depreciated since 2.0 use the menu object
 *
 * developers don't remove this until 3.0-dev
 *
 * @param string $menu_id
 *
 * @return bool
 */
function destroyMenu($menu_id = 'last')
{
	global $context;

	$menu_name = $menu_id === 'last' && isset($context['max_menu_id'], $context['menu_data_' . $context['max_menu_id']])
		? 'menu_data_' . $context['max_menu_id']
		: 'menu_data_' . $menu_id;

	if (!isset($context['max_menu_id'], $context[$menu_name]))
	{
		return false;
	}

	// Decrement the pointer if this is the final menu in the series.
	if ($menu_id === 'last' || $menu_id === $context['max_menu_id'])
	{
		$context['max_menu_id'] = max($context['max_menu_id'] - 1, 0);
	}

	// Remove the template layer if this was the only menu left.
	if ($context['max_menu_id'] === 0)
	{
		theme()->getLayers()->remove($context[$menu_name]['layer_name']);
	}

	unset($context[$menu_name]);

	return true;
}

/**
 * Call the function or method for the selected menu item.
 * $selectedMenu is the array of menu information, with the format as retrieved from createMenu()
 *
 * If $selectedMenu['controller'] is set, then it is a class, and $selectedMenu['function'] will be a method of it.
 * If it is not set, then $selectedMenu['function'] is simply a function to call.
 *
 * @depreciated since 2.0 use the menu object
 *
 * developers don't remove this until 3.0-dev
 *
 * @param array $selectedMenu
 * @throws \ElkArte\Exceptions\Exception
 */
function callMenu($selectedMenu)
{
	global $context;

	$action = new Action();
	$action->initialize(['action' => $selectedMenu]);
	$action->dispatch('action');
	$context['menu_data_' . $context['max_menu_id']]['object']->prepareTabData();
}

/**
 * Loads up all the default menu entries available.
 *
 * @return array
 * @throws \ElkArte\Exceptions\Exception
 */
function loadDefaultMenuButtons()
{
	global $scripturl, $txt, $context, $modSettings;

	$buttons = array(
		'home' => array(
			'title' => $txt['community'],
			'href' => getUrl('home', []),
			'data-icon' => 'i-home',
			'show' => true,
			'sub_buttons' => array(
				'help' => array(
					'title' => $txt['help'],
					'href' => getUrl('action', ['action' => 'help']),
					'show' => true,
				),
				'search' => array(
					'title' => $txt['search'],
					'href' => getUrl('action', ['action' => 'search']),
					'show' => $context['allow_search'],
				),
				'calendar' => array(
					'title' => $txt['calendar'],
					'href' => getUrl('action', ['action' => 'calendar']),
					'show' => $context['allow_calendar'],
				),
				'memberlist' => array(
					'title' => $txt['members_title'],
					'href' => getUrl('action', ['action' => 'memberlist']),
					'show' => $context['allow_memberlist'],
				),
				'recent' => array(
					'title' => $txt['recent_posts'],
					'href' => getUrl('action', ['action' => 'recent']),
					'show' => true,
				),
				'like_stats' => array(
					'title' => $txt['like_post_stats'],
					'href' => getUrl('action', ['action' => 'likes', 'sa' => 'likestats']),
					'show' => !empty($modSettings['likes_enabled']) && allowedTo('like_posts_stats'),
				),
				'contact' => array(
					'title' => $txt['contact'],
					'href' => getUrl('action', ['action' => 'register', 'sa' => 'contact']),
					'show' => User::$info->is_guest && !empty($modSettings['enable_contactform']) && $modSettings['enable_contactform'] == 'menu',
				),
			),
		)
	);

	// Will change title correctly if user is either a mod or an admin.
	// Button highlighting works properly too (see current action stuffz).
	if ($context['allow_admin'])
	{
		$buttons['admin'] = array(
			'title' => $context['current_action'] !== 'moderate' ? $txt['admin'] : $txt['moderate'],
			'counter' => 'grand_total',
			'href' => $scripturl . '?action=admin',
			'data-icon' => 'i-cog',
			'show' => true,
			'sub_buttons' => array(
				'admin_center' => array(
					'title' => $txt['admin_center'],
					'href' => $scripturl . '?action=admin',
					'show' => $context['allow_admin'],
				),
				'featuresettings' => array(
					'title' => $txt['modSettings_title'],
					'href' => $scripturl . '?action=admin;area=featuresettings',
					'show' => allowedTo('admin_forum'),
				),
				'packages' => array(
					'title' => $txt['package'],
					'href' => $scripturl . '?action=admin;area=packages',
					'show' => allowedTo('admin_forum'),
				),
				'permissions' => array(
					'title' => $txt['edit_permissions'],
					'href' => $scripturl . '?action=admin;area=permissions',
					'show' => allowedTo('manage_permissions'),
				),
				'errorlog' => array(
					'title' => $txt['errlog'],
					'href' => $scripturl . '?action=admin;area=logs;sa=errorlog;desc',
					'show' => allowedTo('admin_forum') && !empty($modSettings['enableErrorLogging']),
				),
				'moderate_sub' => array(
					'title' => $txt['moderate'],
					'counter' => 'grand_total',
					'href' => $scripturl . '?action=moderate',
					'show' => $context['allow_moderation_center'],
					'sub_buttons' => array(
						'reports' => array(
							'title' => $txt['mc_reported_posts'],
							'counter' => 'reports',
							'href' => $scripturl . '?action=moderate;area=reports',
							'show' => !empty(User::$info->mod_cache) && User::$info->mod_cache['bq'] != '0=1',
						),
						'modlog' => array(
							'title' => $txt['modlog_view'],
							'href' => $scripturl . '?action=moderate;area=modlog',
							'show' => !empty($modSettings['modlog_enabled']) && !empty(User::$info->mod_cache) && User::$info->mod_cache['bq'] != '0=1',
						),
						'attachments' => array(
							'title' => $txt['mc_unapproved_attachments'],
							'counter' => 'attachments',
							'href' => $scripturl . '?action=moderate;area=attachmod;sa=attachments',
							'show' => $modSettings['postmod_active'] && !empty(User::$info->mod_cache['ap']),
						),
						'poststopics' => array(
							'title' => $txt['mc_unapproved_poststopics'],
							'counter' => 'postmod',
							'href' => $scripturl . '?action=moderate;area=postmod;sa=posts',
							'show' => $modSettings['postmod_active'] && !empty(User::$info->mod_cache['ap']),
						),
						'postbyemail' => array(
							'title' => $txt['mc_emailerror'],
							'counter' => 'emailmod',
							'href' => $scripturl . '?action=admin;area=maillist;sa=emaillist',
							'show' => !empty($modSettings['maillist_enabled']) && allowedTo('approve_emails'),
						),
					),
				),
			),
		);
	}
	else
	{
		$buttons['admin'] = array(
			'title' => $txt['moderate'],
			'counter' => 'grand_total',
			'href' => $scripturl . '?action=moderate',
			'data-icon' => 'i-cog',
			'show' => $context['allow_moderation_center'],
			'sub_buttons' => array(
				'reports' => array(
					'title' => $txt['mc_reported_posts'],
					'counter' => 'reports',
					'href' => $scripturl . '?action=moderate;area=reports',
					'show' => !empty(User::$info->mod_cache) && User::$info->mod_cache['bq'] != '0=1',
				),
				'modlog' => array(
					'title' => $txt['modlog_view'],
					'href' => $scripturl . '?action=moderate;area=modlog',
					'show' => !empty($modSettings['modlog_enabled']) && !empty(User::$info->mod_cache) && User::$info->mod_cache['bq'] != '0=1',
				),
				'attachments' => array(
					'title' => $txt['mc_unapproved_attachments'],
					'counter' => 'attachments',
					'href' => $scripturl . '?action=moderate;area=attachmod;sa=attachments',
					'show' => $modSettings['postmod_active'] && !empty(User::$info->mod_cache['ap']),
				),
				'poststopics' => array(
					'title' => $txt['mc_unapproved_poststopics'],
					'counter' => 'postmod',
					'href' => $scripturl . '?action=moderate;area=postmod;sa=posts',
					'show' => $modSettings['postmod_active'] && !empty(User::$info->mod_cache['ap']),
				),
				'postbyemail' => array(
					'title' => $txt['mc_emailerror'],
					'counter' => 'emailmod',
					'href' => $scripturl . '?action=admin;area=maillist;sa=emaillist',
					'show' => !empty($modSettings['maillist_enabled']) && allowedTo('approve_emails'),
				),
			),
		);
	}

	$buttons += array(
		'profile' => array(
			'title' => !empty($modSettings['displayMemberNames']) ? User::$info->name : $txt['account_short'],
			'href' => getUrl('profile', ['action' => 'profile', 'u' => User::$info->id, 'name' => User::$info->name]),
			'data-icon' => 'i-account',
			'show' => $context['allow_edit_profile'],
			'sub_buttons' => array(
				'account' => array(
					'title' => $txt['account'],
					'href' => getUrl('profile', ['action' => 'profile', 'area' => 'account', 'u' => User::$info->id, 'name' => User::$info->name]),
					'show' => allowedTo(array('profile_identity_any', 'profile_identity_own', 'manage_membergroups')),
				),
				'drafts' => array(
					'title' => $txt['mydrafts'],
					'href' => getUrl('profile', ['action' => 'profile', 'area' => 'showdrafts', 'u' => User::$info->id, 'name' => User::$info->name]),
					'show' => !empty($modSettings['drafts_enabled']) && !empty($modSettings['drafts_post_enabled']) && allowedTo('post_draft'),
				),
				'forumprofile' => array(
					'title' => $txt['forumprofile'],
					'href' => getUrl('profile', ['action' => 'profile', 'area' => 'forumprofile', 'u' => User::$info->id, 'name' => User::$info->name]),
					'show' => allowedTo(array('profile_extra_any', 'profile_extra_own')),
				),
				'theme' => array(
					'title' => $txt['theme'],
					'href' => getUrl('profile', ['action' => 'profile', 'area' => 'theme', 'u' => User::$info->id, 'name' => User::$info->name]),
					'show' => allowedTo(array('profile_extra_any', 'profile_extra_own', 'profile_extra_any')),
				),
				'logout' => array(
					'title' => $txt['logout'],
					'href' => getUrl('action', ['action' => 'logout']),
					'show' => User::$info->is_guest === false,
				),
			),
		),
		'pm' => array(
			'title' => $txt['pm_short'],
			'counter' => 'unread_messages',
			'href' => getUrl('action', ['action' => 'pm']),
			'data-icon' => ($context['user']['unread_messages'] ? 'i-envelope' : 'i-envelope-blank'),
			'show' => $context['allow_pm'],
			'sub_buttons' => array(
				'pm_read' => array(
					'title' => $txt['pm_menu_read'],
					'href' => getUrl('action', ['action' => 'pm']),
					'show' => allowedTo('pm_read'),
				),
				'pm_send' => array(
					'title' => $txt['pm_menu_send'],
					'href' => getUrl('action', ['action' => 'pm', 'sa' => 'send']),
					'show' => allowedTo('pm_send'),
				),
			),
		),
		'mentions' => array(
			'title' => $txt['mention'],
			'counter' => 'mentions',
			'href' => getUrl('action', ['action' => 'mentions']),
			'data-icon' => ($context['user']['mentions'] ? 'i-bell' : 'i-bell-blank'),
			'show' => User::$info->is_guest === false && !empty($modSettings['mentions_enabled']),
		),
		// The old language string made no sense, and was too long.
		// "New posts" is better, because there are probably a pile
		// of old unread posts, and they wont be reached from this button.
		'unread' => array(
			'title' => $txt['view_unread_category'],
			'href' => getUrl('action', ['action' => 'unread']),
			'data-icon' => 'i-comments',
			'show' => User::$info->is_guest === false,
		),
		// The old language string made no sense, and was too long.
		// "New replies" is better, because there are "updated topics"
		// that the user has never posted in and doesn't care about.
		'unreadreplies' => array(
			'title' => $txt['view_replies_category'],
			'href' => getUrl('action', ['action' => 'unreadreplies']),
			'data-icon' => 'i-comments-blank',
			'show' => User::$info->is_guest === false,
		),
		'login' => array(
			'title' => $txt['login'],
			'href' => getUrl('action', ['action' => 'login']),
			'data-icon' => 'i-sign-in',
			'show' => User::$info->is_guest,
		),

		'register' => array(
			'title' => $txt['register'],
			'href' => getUrl('action', ['action' => 'register']),
			'data-icon' => 'i-register',
			'show' => User::$info->is_guest && $context['can_register'],
		),
	);

	return $buttons;
}
