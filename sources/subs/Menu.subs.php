<?php

/**
 * This file contains a standard way of displaying side/drop down menus.
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * This file contains code covered by:
 * copyright:	2011 Simple Machines (http://www.simplemachines.org)
 * license:  	BSD, See included LICENSE.TXT for terms and conditions.
 *
 * @version 1.1 beta 3
 *
 */

/**
 * Create a menu.
 *
 * @param mixed[] $menuData the menu array
 * @param mixed[] $menuOptions an array of options that can be used to override some default behaviours.
 *   It can accept the following indexes:
 *    - action                    => overrides the default action
 *    - current_area              => overrides the current area
 *    - extra_url_parameters      => an array or pairs or parameters to be added to the url
 *    - disable_url_session_check => (boolean) if true the session var/id are omitted from the url
 *    - base_url                  => an alternative base url
 *    - menu_type                 => alternative menu types?
 *    - can_toggle_drop_down      => (boolean) if the menu can "toggle"
 *    - template_name             => an alternative template to load (instead of Generic)
 *    - layer_name                => alternative layer name for the menu
 * @return mixed[]|false
 */
function createMenu($menuData, $menuOptions = array())
{
	global $context, $settings, $options, $txt, $scripturl, $user_info;

	$_req = HttpReq::instance();

	// Work out where we should get our images from.
	$context['menu_image_path'] = file_exists($settings['theme_dir'] . '/images/admin/change_menu.png') ? $settings['images_url'] . '/admin' : $settings['default_images_url'] . '/admin';

	/**
	 * Note menuData is array of form:
	 *
	 * Possible fields:
	 *  For Section:
	 *    string $title:     Section title.
	 *    bool $enabled:     Should section be shown?
	 *    array $areas:      Array of areas within this section.
	 *    array $permission: Permission required to access the whole section.
	 *  For Areas:
	 *    array $permission:  Array of permissions to determine who can access this area.
	 *    string $label:      Optional text string for link (Otherwise $txt[$index] will be used)
	 *    string $file:       Name of source file required for this area.
	 *    string $function:   Function to call when area is selected.
	 *    string $custom_url: URL to use for this menu item.
	 *    string $icon:       File name of an icon to use on the menu, if using the sprite class, set as transparent.png
	 *    string $class:      Class name to apply to the icon img, used to apply a sprite icon
	 *    bool $enabled:      Should this area even be accessible?
	 *    bool $hidden:       Should this area be visible?
	 *    string $select:     If set this item will not be displayed - instead the item indexed here shall be.
	 *    array $subsections: Array of subsections from this area.
	 *
	 *  For Subsections:
	 *    string 0:     Text label for this subsection.
	 *    array 1:      Array of permissions to check for this subsection.
	 *    bool 2:       Is this the default subaction - if not set for any will default to first...
	 *    bool enabled: Bool to say whether this should be enabled or not.
	 *    array active: Set the button active for other subsections.
	 */

	// Every menu gets a unique ID, these are shown in first in, first out order.
	$context['max_menu_id'] = isset($context['max_menu_id']) ? $context['max_menu_id'] + 1 : 1;

	// This will be all the data for this menu - and we'll make a shortcut to it to aid readability here.
	$context['menu_data_' . $context['max_menu_id']] = array();
	$menu_context = &$context['menu_data_' . $context['max_menu_id']];

	// Allow extend *any* menu with a single hook
	if (!empty($menuOptions['hook']))
		call_integration_hook('integrate_' . $menuOptions['hook'] . '_areas', array(&$menuData, &$menuOptions));

	// What is the general action of this menu (i.e. $scripturl?action=XXXX.
	$menu_context['current_action'] = isset($menuOptions['action']) ? $menuOptions['action'] : $context['current_action'];

	// What is the current area selected?
	if (isset($menuOptions['current_area']) || isset($_req->query->area))
		$menu_context['current_area'] = isset($menuOptions['current_area']) ? $menuOptions['current_area'] : $_req->query->area;

	// Build a list of additional parameters that should go in the URL.
	$menu_context['extra_parameters'] = '';
	if (!empty($menuOptions['extra_url_parameters']))
		foreach ($menuOptions['extra_url_parameters'] as $key => $value)
			$menu_context['extra_parameters'] .= ';' . $key . '=' . $value;

	// Only include the session ID in the URL if it's strictly necessary.
	if (empty($menuOptions['disable_url_session_check']))
		$menu_context['extra_parameters'] .= ';' . $context['session_var'] . '=' . $context['session_id'];

	$include_data = array();
	// This is necessary only in profile (at least for the core), but we do it always because it's easier
	$permission_set = !empty($context['user']['is_owner']) ? 'own' : 'any';

	// Now setup the context correctly.
	foreach ($menuData as $section_id => $section)
	{
		// Is this enabled?
		if ((isset($section['enabled']) && $section['enabled'] == false))
			continue;
		// Has permission check?
		if (isset($section['permission']))
		{
			// The profile menu has slightly different permissions
			if (is_array($section['permission']) && isset($section['permission']['own'], $section['permission']['any']))
			{
				if (empty($section['permission'][$permission_set]) || !allowedTo($section['permission'][$permission_set]))
					continue;
			}
			elseif (!allowedTo($section['permission']))
				continue;
		}

		// Now we cycle through the sections to pick the right area.
		foreach ($section['areas'] as $area_id => $area)
		{
			// Can we do this?
			if (!isset($area['enabled']) || $area['enabled'] !== false)
			{
				// Has permission check?
				if (!empty($area['permission']))
				{
					// The profile menu has slightly different permissions
					if (is_array($area['permission']) && isset($area['permission']['own'], $area['permission']['any']))
					{
						if (empty($area['permission'][$permission_set]) || !allowedTo($area['permission'][$permission_set]))
							continue;
					}
					elseif (!allowedTo($area['permission']))
						continue;
				}

				// Add it to the context... if it has some form of name!
				if (isset($area['label']) || (isset($txt[$area_id]) && !isset($area['select'])))
				{
					// We may want to include a file, let's find out the path
					if (!empty($area['file']))
							$area['file'] = (!empty($area['dir']) ? $area['dir'] : (!empty($menuOptions['default_include_dir']) ? $menuOptions['default_include_dir'] : CONTROLLERDIR)) . '/' . $area['file'];

					// If we haven't got an area then the first valid one is our choice.
					if (!isset($menu_context['current_area']))
					{
						$menu_context['current_area'] = $area_id;
						$include_data = $area;
					}

					// If this is hidden from view don't do the rest.
					if (empty($area['hidden']))
					{
						// First time this section?
						if (!isset($menu_context['sections'][$section_id]))
						{
							if (isset($menuOptions['counters'], $section['counter']) && !empty($menuOptions['counters'][$section['counter']]))
								$section['title'] .= sprintf($settings['menu_numeric_notice'][0], $menuOptions['counters'][$section['counter']]);

							$menu_context['sections'][$section_id]['title'] = $section['title'];
						}

						$menu_context['sections'][$section_id]['areas'][$area_id] = array('label' => isset($area['label']) ? $area['label'] : $txt[$area_id]);
						if (isset($menuOptions['counters'], $area['counter']) && !empty($menuOptions['counters'][$area['counter']]))
							$menu_context['sections'][$section_id]['areas'][$area_id]['label'] .= sprintf($settings['menu_numeric_notice'][1], $menuOptions['counters'][$area['counter']]);

						// We'll need the ID as well...
						$menu_context['sections'][$section_id]['id'] = $section_id;

						// Does it have a custom URL?
						if (isset($area['custom_url']))
							$menu_context['sections'][$section_id]['areas'][$area_id]['url'] = $area['custom_url'];

						// Does this area have its own icon?
						if (isset($area['icon']))
							$menu_context['sections'][$section_id]['areas'][$area_id]['icon'] = '<img ' . (isset($area['class']) ? 'class="' . $area['class'] . '" ' : 'style="background: none"') . ' src="' . $context['menu_image_path'] . '/' . $area['icon'] . '" alt="" />&nbsp;&nbsp;';
						else
							$menu_context['sections'][$section_id]['areas'][$area_id]['icon'] = '';

						// Did it have subsections?
						if (!empty($area['subsections']))
						{
							$menu_context['sections'][$section_id]['areas'][$area_id]['subsections'] = array();
							$first_sa = $last_sa = null;
							foreach ($area['subsections'] as $sa => $sub)
							{
								if ((empty($sub[1]) || allowedTo($sub[1])) && (!isset($sub['enabled']) || !empty($sub['enabled'])))
								{
									if ($first_sa === null)
										$first_sa = $sa;

									$menu_context['sections'][$section_id]['areas'][$area_id]['subsections'][$sa] = array('label' => $sub[0]);
									if (isset($menuOptions['counters'], $sub['counter']) && !empty($menuOptions['counters'][$sub['counter']]))
										$menu_context['sections'][$section_id]['areas'][$area_id]['subsections'][$sa]['label'] .= sprintf($settings['menu_numeric_notice'][2], $menuOptions['counters'][$sub['counter']]);

									// Custom URL?
									if (isset($sub['url']))
										$menu_context['sections'][$section_id]['areas'][$area_id]['subsections'][$sa]['url'] = $sub['url'];

									// A bit complicated - but is this set?
									if ($menu_context['current_area'] == $area_id)
									{
										// Save which is the first...
										if (empty($first_sa))
											$first_sa = $sa;

										// Is this the current subsection?
										if (isset($_req->query->sa) && $_req->query->sa == $sa)
											$menu_context['current_subsection'] = $sa;

										elseif (isset($sub['active']) && isset($_req->query->sa) && in_array($_req->query->sa, $sub['active']))
											$menu_context['current_subsection'] = $sa;

										// Otherwise is it the default?
										elseif (!isset($menu_context['current_subsection']) && !empty($sub[2]))
											$menu_context['current_subsection'] = $sa;
									}

									// Let's assume this is the last, for now.
									$last_sa = $sa;
								}
								// Mark it as disabled...
								else
									$menu_context['sections'][$section_id]['areas'][$area_id]['subsections'][$sa]['disabled'] = true;
							}

							// Set which one is first, last and selected in the group.
							if (!empty($menu_context['sections'][$section_id]['areas'][$area_id]['subsections']))
							{
								$menu_context['sections'][$section_id]['areas'][$area_id]['subsections'][$context['right_to_left'] ? $last_sa : $first_sa]['is_first'] = true;
								$menu_context['sections'][$section_id]['areas'][$area_id]['subsections'][$context['right_to_left'] ? $first_sa : $last_sa]['is_last'] = true;

								if ($menu_context['current_area'] == $area_id && !isset($menu_context['current_subsection']))
									$menu_context['current_subsection'] = $first_sa;
							}
						}
					}
				}

				// Is this the current section?
				// @todo why $found_section is not initialized outside one of the loops? (Not sure which one lol)
				if ($menu_context['current_area'] == $area_id && empty($found_section))
				{
					// Only do this once?
					$found_section = true;

					// Update the context if required - as we can have areas pretending to be others. ;)
					$menu_context['current_section'] = $section_id;
					// @todo 'select' seems useless
					$menu_context['current_area'] = isset($area['select']) ? $area['select'] : $area_id;

					// This will be the data we return.
					$include_data = $area;
				}
				// Make sure we have something in case it's an invalid area.
				elseif (empty($found_section) && empty($include_data))
				{
					$menu_context['current_section'] = $section_id;
					$backup_area = isset($area['select']) ? $area['select'] : $area_id;
					$include_data = $area;
				}
			}
		}
	}

	if (!isset($context['current_subaction']) && isset($menu_context['current_subsection']))
	{
		$context['current_subaction'] = $menu_context['current_subsection'];
	}

	// Should we use a custom base url, or use the default?
	$menu_context['base_url'] = isset($menuOptions['base_url']) ? $menuOptions['base_url'] : $scripturl . '?action=' . $menu_context['current_action'];

	// If there are sections quickly goes through all the sections to check if the base menu has an url
	if (!empty($menu_context['current_section']))
	{
		$menu_context['sections'][$menu_context['current_section']]['selected'] = true;
		$menu_context['sections'][$menu_context['current_section']]['areas'][$menu_context['current_area']]['selected'] = true;
		if (!empty($menu_context['sections'][$menu_context['current_section']]['areas'][$menu_context['current_area']]['subsections'][$context['current_subaction']]))
			$menu_context['sections'][$menu_context['current_section']]['areas'][$menu_context['current_area']]['subsections'][$context['current_subaction']]['selected'] = true;

		foreach ($menu_context['sections'] as $section_id => $section)
			foreach ($section['areas'] as $area_id => $area)
			{
				if (!isset($menu_context['sections'][$section_id]['url']))
				{
					$menu_context['sections'][$section_id]['url'] = isset($area['url']) ? $area['url'] : $menu_context['base_url'] . ';area=' . $area_id;
					break;
				}
			}
	}

	// If we didn't find the area we were looking for go to a default one.
	if (isset($backup_area) && empty($found_section))
		$menu_context['current_area'] = $backup_area;

	// If still no data then return - nothing to show!
	if (empty($menu_context['sections']))
	{
		// Never happened!
		$context['max_menu_id']--;
		if ($context['max_menu_id'] == 0)
			unset($context['max_menu_id']);

		return false;
	}

	// What type of menu is this?
	if (empty($menuOptions['menu_type']))
	{
		$menuOptions['menu_type'] = '_' . (empty($options['use_sidebar_menu']) ? 'dropdown' : 'sidebar');
		$menu_context['can_toggle_drop_down'] = !$user_info['is_guest'] && isset($settings['theme_version']) && $settings['theme_version'] >= 2.0;
	}
	else
		$menu_context['can_toggle_drop_down'] = !empty($menuOptions['can_toggle_drop_down']);

	// Almost there - load the template and add to the template layers.
	loadTemplate(isset($menuOptions['template_name']) ? $menuOptions['template_name'] : 'GenericMenu');
	$menu_context['layer_name'] = (isset($menuOptions['layer_name']) ? $menuOptions['layer_name'] : 'generic_menu') . $menuOptions['menu_type'];
	Template_Layers::getInstance()->add($menu_context['layer_name']);

	// Check we had something - for sanity sake.
	if (empty($include_data))
		return false;

	// Finally - return information on the selected item.
	$include_data += array(
		'current_action' => $menu_context['current_action'],
		'current_area' => $menu_context['current_area'],
		'current_section' => $menu_context['current_section'],
		'current_subsection' => !empty($menu_context['current_subsection']) ? $menu_context['current_subsection'] : '',
	);

	return $include_data;
}

/**
 * Delete a menu.
 *
 * @param string $menu_id = 'last'
 * @return false|null
 */
function destroyMenu($menu_id = 'last')
{
	global $context;

	$menu_name = $menu_id == 'last' && isset($context['max_menu_id']) && isset($context['menu_data_' . $context['max_menu_id']]) ? 'menu_data_' . $context['max_menu_id'] : 'menu_data_' . $menu_id;
	if (!isset($context[$menu_name]))
		return false;

	Template_Layers::getInstance()->remove($context[$menu_name]['layer_name']);

	unset($context[$menu_name]);
}

/**
 * Call the function or method for the selected menu item.
 * $selectedMenu is the array of menu information, with the format as retrieved from createMenu()
 *
 * If $selectedMenu['controller'] is set, then it is a class, and $selectedMenu['function'] will be a method of it.
 * If it is not set, then $selectedMenu['function'] is simply a function to call.
 *
 * @param array|string $selectedMenu
 */
function callMenu($selectedMenu)
{
	// We use only $selectedMenu['function'] and
	//  $selectedMenu['controller'] if the latter is set.

	if (!empty($selectedMenu['controller']))
	{
		// 'controller' => 'ManageAttachments_Controller'
		// 'function' => 'action_avatars'
		$controller = new $selectedMenu['controller'](new Event_Manager());

		// always set up the environment
		$controller->pre_dispatch();
		// and go!
		$controller->{$selectedMenu['function']}();
	}
	else
	{
		// a single function name... call it over!
		$selectedMenu['function']();
	}
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