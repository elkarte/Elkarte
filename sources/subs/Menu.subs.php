<?php

/**
 * This file contains a standard way of displaying side/drop down menus.
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 1.1
 *
 */

use ElkArte\Menu\Menu;
use ElkArte\Menu\MenuSection;
use ElkArte\Menu\MenuArea;
use ElkArte\Menu\MenuSubsection;

/**
 * Create a menu.
 *
 * @depreciated since 1.1 use MenuCreate.class
 *
 * @param array $menuData
 * @param array $menuOptions
 */
function createMenu($menuData, $menuOptions = array())
{
	$newMenuData = [];
	foreach ($menuData as $section_id => $section)
	{
		$newAreas = ['areas'=>[]];
		foreach ($section['areas'] as $area_id => $area)
		{
			$newSubsections = ['subsections'=>[]];
			if (!empty($area['subsections']))
			{
				foreach ($area['subsections'] as $sa => $sub)
				{
					$newSubsections['subsections'][$sa] = MenuSubsection::buildFromArray($sub);
					unset($area['subsections']);
				}
			}
			$newAreas['areas'][$area_id] = MenuArea::buildFromArray($area+$newSubsections);
		}
		unset($section['areas']);
		$newMenuData[$section_id] = MenuSection::buildFromArray($section+$newAreas);
	}
	$menu = new Menu();
	$menu->addOptions($menuOptions);
	$menu->addAreas($newMenuData);
	$include_data = $menu->prepareMenu();
	$menu->setContext();

	return $include_data;
}

/**
 * Delete a menu.
 *
 * @depreciated since 1.1 use MenuCreate.class
 *
 * @param string $menu_id
 */
function destroyMenu($menu_id = 'last')
{
	$menu = new Menu();
	$menu->destroyMenu($menu_id);
}

/**
 * Call the function or method for the selected menu item.
 *
 * @depreciated since 1.1 use MenuCreate.class
 *
 * @param array $selectedMenu
 */
function callMenu($selectedMenu)
{
	$action = new Action();
	$action->initialize(['action' => $selectedMenu]);
	$action->dispatch('action');
}

/**
 * Loads up all the default menu entries available.
 *
 * @return array
 */
function loadDefaultMenuButtons()
{
	global $scripturl, $txt, $context, $user_info, $modSettings;

	$buttons = array(
		'home' => array(
			'title' => $txt['community'],
			'href' => $scripturl,
			'data-icon' => 'i-home',
			'show' => true,
			'sub_buttons' => array(
				'help' => array(
					'title' => $txt['help'],
					'href' => $scripturl . '?action=help',
					'show' => true,
				),
				'search' => array(
					'title' => $txt['search'],
					'href' => $scripturl . '?action=search',
					'show' => $context['allow_search'],
				),
				'calendar' => array(
					'title' => $txt['calendar'],
					'href' => $scripturl . '?action=calendar',
					'show' => $context['allow_calendar'],
				),
				'memberlist' => array(
					'title' => $txt['members_title'],
					'href' => $scripturl . '?action=memberlist',
					'show' => $context['allow_memberlist'],
				),
				'recent' => array(
					'title' => $txt['recent_posts'],
					'href' => $scripturl . '?action=recent',
					'show' => true,
				),
				'like_stats' => array(
					'title' => $txt['like_post_stats'],
					'href' => $scripturl . '?action=likes;sa=likestats',
					'show' => allowedTo('like_posts_stats'),
				),
				'contact' => array(
					'title' => $txt['contact'],
					'href' => $scripturl . '?action=register;sa=contact',
					'show' => $user_info['is_guest'] && !empty($modSettings['enable_contactform']) && $modSettings['enable_contactform'] == 'menu',
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
							'show' => !empty($user_info['mod_cache']) && $user_info['mod_cache']['bq'] != '0=1',
						),
						'modlog' => array(
							'title' => $txt['modlog_view'],
							'href' => $scripturl . '?action=moderate;area=modlog',
							'show' => !empty($modSettings['modlog_enabled']) && !empty($user_info['mod_cache']) && $user_info['mod_cache']['bq'] != '0=1',
						),
						'attachments' => array(
							'title' => $txt['mc_unapproved_attachments'],
							'counter' => 'attachments',
							'href' => $scripturl . '?action=moderate;area=attachmod;sa=attachments',
							'show' => $modSettings['postmod_active'] && !empty($user_info['mod_cache']['ap']),
						),
						'poststopics' => array(
							'title' => $txt['mc_unapproved_poststopics'],
							'counter' => 'postmod',
							'href' => $scripturl . '?action=moderate;area=postmod;sa=posts',
							'show' => $modSettings['postmod_active'] && !empty($user_info['mod_cache']['ap']),
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
					'show' => !empty($user_info['mod_cache']) && $user_info['mod_cache']['bq'] != '0=1',
				),
				'modlog' => array(
					'title' => $txt['modlog_view'],
					'href' => $scripturl . '?action=moderate;area=modlog',
					'show' => !empty($modSettings['modlog_enabled']) && !empty($user_info['mod_cache']) && $user_info['mod_cache']['bq'] != '0=1',
				),
				'attachments' => array(
					'title' => $txt['mc_unapproved_attachments'],
					'counter' => 'attachments',
					'href' => $scripturl . '?action=moderate;area=attachmod;sa=attachments',
					'show' => $modSettings['postmod_active'] && !empty($user_info['mod_cache']['ap']),
				),
				'poststopics' => array(
					'title' => $txt['mc_unapproved_poststopics'],
					'counter' => 'postmod',
					'href' => $scripturl . '?action=moderate;area=postmod;sa=posts',
					'show' => $modSettings['postmod_active'] && !empty($user_info['mod_cache']['ap']),
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
			'title' => !empty($modSettings['displayMemberNames']) ? $user_info['name'] : $txt['account_short'],
			'href' => $scripturl . '?action=profile',
			'data-icon' => 'i-account',
			'show' => $context['allow_edit_profile'],
			'sub_buttons' => array(
				'account' => array(
					'title' => $txt['account'],
					'href' => $scripturl . '?action=profile;area=account',
					'show' => allowedTo(array('profile_identity_any', 'profile_identity_own', 'manage_membergroups')),
				),
				'drafts' => array(
					'title' => $txt['mydrafts'],
					'href' => $scripturl . '?action=profile;area=showdrafts',
					'show' => !empty($modSettings['drafts_enabled']) && !empty($modSettings['drafts_post_enabled']) && allowedTo('post_draft'),
				),
				'forumprofile' => array(
					'title' => $txt['forumprofile'],
					'href' => $scripturl . '?action=profile;area=forumprofile',
					'show' => allowedTo(array('profile_extra_any', 'profile_extra_own')),
				),
				'theme' => array(
					'title' => $txt['theme'],
					'href' => $scripturl . '?action=profile;area=theme',
					'show' => allowedTo(array('profile_extra_any', 'profile_extra_own', 'profile_extra_any')),
				),
				'logout' => array(
					'title' => $txt['logout'],
					'href' => $scripturl . '?action=logout',
					'show' => !$user_info['is_guest'],
				),
			),
		),
		// @todo Look at doing something here, to provide instant access to inbox when using click menus.
		// @todo A small pop-up anchor seems like the obvious way to handle it. ;)
		'pm' => array(
			'title' => $txt['pm_short'],
			'counter' => 'unread_messages',
			'href' => $scripturl . '?action=pm',
			'data-icon' => ($context['user']['unread_messages'] ? 'i-envelope' : 'i-envelope-blank'),
			'show' => $context['allow_pm'],
			'sub_buttons' => array(
				'pm_read' => array(
					'title' => $txt['pm_menu_read'],
					'href' => $scripturl . '?action=pm',
					'show' => allowedTo('pm_read'),
				),
				'pm_send' => array(
					'title' => $txt['pm_menu_send'],
					'href' => $scripturl . '?action=pm;sa=send',
					'show' => allowedTo('pm_send'),
				),
			),
		),
		'mentions' => array(
			'title' => $txt['mention'],
			'counter' => 'mentions',
			'href' => $scripturl . '?action=mentions',
			'data-icon' => ($context['user']['mentions'] ? 'i-bell' : 'i-bell-blank'),
			'show' => !$user_info['is_guest'] && !empty($modSettings['mentions_enabled']),
		),
		// The old language string made no sense, and was too long.
		// "New posts" is better, because there are probably a pile
		// of old unread posts, and they wont be reached from this button.
		'unread' => array(
			'title' => $txt['view_unread_category'],
			'href' => $scripturl . '?action=unread',
			'data-icon' => 'i-comments',
			'show' => !$user_info['is_guest'],
		),
		// The old language string made no sense, and was too long.
		// "New replies" is better, because there are "updated topics"
		// that the user has never posted in and doesn't care about.
		'unreadreplies' => array(
			'title' => $txt['view_replies_category'],
			'href' => $scripturl . '?action=unreadreplies',
			'data-icon' => 'i-comments-blank',
			'show' => !$user_info['is_guest'],
		),
		'login' => array(
			'title' => $txt['login'],
			'href' => $scripturl . '?action=login',
			'data-icon' => 'i-sign-in',
			'show' => $user_info['is_guest'],
		),

		'register' => array(
			'title' => $txt['register'],
			'href' => $scripturl . '?action=register',
			'data-icon' => 'i-register',
			'show' => $user_info['is_guest'] && $context['can_register'],
		),
	);

	return $buttons;
}