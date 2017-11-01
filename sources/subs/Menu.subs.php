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
use ElkArte\Menu\MenuArea;
use ElkArte\Menu\MenuSection;
use ElkArte\Menu\MenuSubsection;

/**
 * Create a menu.
 *
 * @depreciated since 1.1 use MenuCreate.class
 *
 * @param array $menuData
 * @param array $menuOptions
 *
 * @return array
 */
function createMenu($menuData, $menuOptions = [])
{
	$menu = new Menu();
	$menu->addOptions($menuOptions);
	foreach ($menuData as $section_id => $section)
	{
		$newAreas = ['areas' => []];
		foreach ($section['areas'] as $area_id => $area)
		{
			$newSubsections = ['subsections' => []];
			if (!empty($area['subsections']))
			{
				foreach ($area['subsections'] as $sa => $sub)
				{
					$newSubsections['subsections'][$sa] = MenuSubsection::buildFromArray($sub);
					unset($area['subsections']);
				}
			}
			$newAreas['areas'][$area_id] = MenuArea::buildFromArray($area + $newSubsections);
		}
		unset($section['areas']);
		$menu->addSection($section_id, MenuSection::buildFromArray($section + $newAreas));
	}
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
 *
 * @return boolean
 */
function destroyMenu($menu_id = 'last')
{
	global $context;

	$menu_name =
		$menu_id === 'last'
		&& isset($context['max_menu_id'], $context['menu_data_' . $context['max_menu_id']])
			? 'menu_data_' . $context['max_menu_id']
			: 'menu_data_' . $menu_id;

	if (!isset($context['max_menu_id'], $context[$menu_name]))
	{
		return false;
	}
	else
	{
		// Decrement the pointer if this is the final menu in the series.
		if ($menu_id == 'last' || $menu_id == $context['max_menu_id'])
		{
			$context['max_menu_id'] = max($context['max_menu_id'] - 1, 0);
		}

		// Remove the template layer if this was the only menu left.
		if ($context['max_menu_id'] == 0)
		{
			theme()->getLayers()->remove($context[$menu_name]['layer_name']);
		}

		unset($context[$menu_name]);

		return true;
	}
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

	$buttons = [
		'home' => [
			'title' => $txt['community'],
			'href' => $scripturl,
			'data-icon' => 'i-home',
			'show' => true,
			'sub_buttons' => [
				'help' => [
					'title' => $txt['help'],
					'href' => $scripturl . '?action=help',
					'show' => true,
				],
				'search' => [
					'title' => $txt['search'],
					'href' => $scripturl . '?action=search',
					'show' => $context['allow_search'],
				],
				'calendar' => [
					'title' => $txt['calendar'],
					'href' => $scripturl . '?action=calendar',
					'show' => $context['allow_calendar'],
				],
				'memberlist' => [
					'title' => $txt['members_title'],
					'href' => $scripturl . '?action=memberlist',
					'show' => $context['allow_memberlist'],
				],
				'recent' => [
					'title' => $txt['recent_posts'],
					'href' => $scripturl . '?action=recent',
					'show' => true,
				],
				'like_stats' => [
					'title' => $txt['like_post_stats'],
					'href' => $scripturl . '?action=likes;sa=likestats',
					'show' => allowedTo('like_posts_stats'),
				],
				'contact' => [
					'title' => $txt['contact'],
					'href' => $scripturl . '?action=register;sa=contact',
					'show' => $user_info['is_guest'] && !empty($modSettings['enable_contactform']) && $modSettings['enable_contactform'] == 'menu',
				],
			],
		],
	];

	// Will change title correctly if user is either a mod or an admin.
	// Button highlighting works properly too (see current action stuffz).
	if ($context['allow_admin'])
	{
		$buttons['admin'] = [
			'title' => $context['current_action'] !== 'moderate' ? $txt['admin'] : $txt['moderate'],
			'counter' => 'grand_total',
			'href' => $scripturl . '?action=admin',
			'data-icon' => 'i-cog',
			'show' => true,
			'sub_buttons' => [
				'admin_center' => [
					'title' => $txt['admin_center'],
					'href' => $scripturl . '?action=admin',
					'show' => $context['allow_admin'],
				],
				'featuresettings' => [
					'title' => $txt['modSettings_title'],
					'href' => $scripturl . '?action=admin;area=featuresettings',
					'show' => allowedTo('admin_forum'),
				],
				'packages' => [
					'title' => $txt['package'],
					'href' => $scripturl . '?action=admin;area=packages',
					'show' => allowedTo('admin_forum'),
				],
				'permissions' => [
					'title' => $txt['edit_permissions'],
					'href' => $scripturl . '?action=admin;area=permissions',
					'show' => allowedTo('manage_permissions'),
				],
				'errorlog' => [
					'title' => $txt['errlog'],
					'href' => $scripturl . '?action=admin;area=logs;sa=errorlog;desc',
					'show' => allowedTo('admin_forum') && !empty($modSettings['enableErrorLogging']),
				],
				'moderate_sub' => [
					'title' => $txt['moderate'],
					'counter' => 'grand_total',
					'href' => $scripturl . '?action=moderate',
					'show' => $context['allow_moderation_center'],
					'sub_buttons' => [
						'reports' => [
							'title' => $txt['mc_reported_posts'],
							'counter' => 'reports',
							'href' => $scripturl . '?action=moderate;area=reports',
							'show' => !empty($user_info['mod_cache']) && $user_info['mod_cache']['bq'] != '0=1',
						],
						'modlog' => [
							'title' => $txt['modlog_view'],
							'href' => $scripturl . '?action=moderate;area=modlog',
							'show' => !empty($modSettings['modlog_enabled']) && !empty($user_info['mod_cache']) && $user_info['mod_cache']['bq'] != '0=1',
						],
						'attachments' => [
							'title' => $txt['mc_unapproved_attachments'],
							'counter' => 'attachments',
							'href' => $scripturl . '?action=moderate;area=attachmod;sa=attachments',
							'show' => $modSettings['postmod_active'] && !empty($user_info['mod_cache']['ap']),
						],
						'poststopics' => [
							'title' => $txt['mc_unapproved_poststopics'],
							'counter' => 'postmod',
							'href' => $scripturl . '?action=moderate;area=postmod;sa=posts',
							'show' => $modSettings['postmod_active'] && !empty($user_info['mod_cache']['ap']),
						],
						'postbyemail' => [
							'title' => $txt['mc_emailerror'],
							'counter' => 'emailmod',
							'href' => $scripturl . '?action=admin;area=maillist;sa=emaillist',
							'show' => !empty($modSettings['maillist_enabled']) && allowedTo('approve_emails'),
						],
					],
				],
			],
		];
	}
	else
	{
		$buttons['admin'] = [
			'title' => $txt['moderate'],
			'counter' => 'grand_total',
			'href' => $scripturl . '?action=moderate',
			'data-icon' => 'i-cog',
			'show' => $context['allow_moderation_center'],
			'sub_buttons' => [
				'reports' => [
					'title' => $txt['mc_reported_posts'],
					'counter' => 'reports',
					'href' => $scripturl . '?action=moderate;area=reports',
					'show' => !empty($user_info['mod_cache']) && $user_info['mod_cache']['bq'] != '0=1',
				],
				'modlog' => [
					'title' => $txt['modlog_view'],
					'href' => $scripturl . '?action=moderate;area=modlog',
					'show' => !empty($modSettings['modlog_enabled']) && !empty($user_info['mod_cache']) && $user_info['mod_cache']['bq'] != '0=1',
				],
				'attachments' => [
					'title' => $txt['mc_unapproved_attachments'],
					'counter' => 'attachments',
					'href' => $scripturl . '?action=moderate;area=attachmod;sa=attachments',
					'show' => $modSettings['postmod_active'] && !empty($user_info['mod_cache']['ap']),
				],
				'poststopics' => [
					'title' => $txt['mc_unapproved_poststopics'],
					'counter' => 'postmod',
					'href' => $scripturl . '?action=moderate;area=postmod;sa=posts',
					'show' => $modSettings['postmod_active'] && !empty($user_info['mod_cache']['ap']),
				],
				'postbyemail' => [
					'title' => $txt['mc_emailerror'],
					'counter' => 'emailmod',
					'href' => $scripturl . '?action=admin;area=maillist;sa=emaillist',
					'show' => !empty($modSettings['maillist_enabled']) && allowedTo('approve_emails'),
				],
			],
		];
	}

	$buttons += [
		'profile' => [
			'title' => !empty($modSettings['displayMemberNames']) ? $user_info['name'] : $txt['account_short'],
			'href' => $scripturl . '?action=profile',
			'data-icon' => 'i-account',
			'show' => $context['allow_edit_profile'],
			'sub_buttons' => [
				'account' => [
					'title' => $txt['account'],
					'href' => $scripturl . '?action=profile;area=account',
					'show' => allowedTo(['profile_identity_any', 'profile_identity_own', 'manage_membergroups']),
				],
				'drafts' => [
					'title' => $txt['mydrafts'],
					'href' => $scripturl . '?action=profile;area=showdrafts',
					'show' => !empty($modSettings['drafts_enabled']) && !empty($modSettings['drafts_post_enabled']) && allowedTo(
							'post_draft'
						),
				],
				'forumprofile' => [
					'title' => $txt['forumprofile'],
					'href' => $scripturl . '?action=profile;area=forumprofile',
					'show' => allowedTo(['profile_extra_any', 'profile_extra_own']),
				],
				'theme' => [
					'title' => $txt['theme'],
					'href' => $scripturl . '?action=profile;area=theme',
					'show' => allowedTo(['profile_extra_any', 'profile_extra_own', 'profile_extra_any']),
				],
				'logout' => [
					'title' => $txt['logout'],
					'href' => $scripturl . '?action=logout',
					'show' => !$user_info['is_guest'],
				],
			],
		],
		// @todo Look at doing something here, to provide instant access to inbox when using click menus.
		// @todo A small pop-up anchor seems like the obvious way to handle it. ;)
		'pm' => [
			'title' => $txt['pm_short'],
			'counter' => 'unread_messages',
			'href' => $scripturl . '?action=pm',
			'data-icon' => ($context['user']['unread_messages'] ? 'i-envelope' : 'i-envelope-blank'),
			'show' => $context['allow_pm'],
			'sub_buttons' => [
				'pm_read' => [
					'title' => $txt['pm_menu_read'],
					'href' => $scripturl . '?action=pm',
					'show' => allowedTo('pm_read'),
				],
				'pm_send' => [
					'title' => $txt['pm_menu_send'],
					'href' => $scripturl . '?action=pm;sa=send',
					'show' => allowedTo('pm_send'),
				],
			],
		],
		'mentions' => [
			'title' => $txt['mention'],
			'counter' => 'mentions',
			'href' => $scripturl . '?action=mentions',
			'data-icon' => ($context['user']['mentions'] ? 'i-bell' : 'i-bell-blank'),
			'show' => !$user_info['is_guest'] && !empty($modSettings['mentions_enabled']),
		],
		// The old language string made no sense, and was too long.
		// "New posts" is better, because there are probably a pile
		// of old unread posts, and they wont be reached from this button.
		'unread' => [
			'title' => $txt['view_unread_category'],
			'href' => $scripturl . '?action=unread',
			'data-icon' => 'i-comments',
			'show' => !$user_info['is_guest'],
		],
		// The old language string made no sense, and was too long.
		// "New replies" is better, because there are "updated topics"
		// that the user has never posted in and doesn't care about.
		'unreadreplies' => [
			'title' => $txt['view_replies_category'],
			'href' => $scripturl . '?action=unreadreplies',
			'data-icon' => 'i-comments-blank',
			'show' => !$user_info['is_guest'],
		],
		'login' => [
			'title' => $txt['login'],
			'href' => $scripturl . '?action=login',
			'data-icon' => 'i-sign-in',
			'show' => $user_info['is_guest'],
		],
		'register' => [
			'title' => $txt['register'],
			'href' => $scripturl . '?action=register',
			'data-icon' => 'i-register',
			'show' => $user_info['is_guest'] && $context['can_register'],
		],
	];

	return $buttons;
}