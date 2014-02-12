<?php

/**
 * This file contains a standard way of displaying side/drop down menus.
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * This software is a derived product, based on:
 *
 * Simple Machines Forum (SMF)
 * copyright:	2011 Simple Machines (http://www.simplemachines.org)
 * license:  	BSD, See included LICENSE.TXT for terms and conditions.
 *
 * @version 1.0 Beta 2
 *
 */

if (!defined('ELK'))
	die('No access...');

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

	// Work out where we should get our images from.
	$context['menu_image_path'] = file_exists($settings['theme_dir'] . '/images/admin/change_menu.png') ? $settings['images_url'] . '/admin' : $settings['default_images_url'] . '/admin';

	/* Note menuData is array of form:

		Possible fields:
			For Section:
				string $title:		Section title.
				bool $enabled:		Should section be shown?
				array $areas:		Array of areas within this section.
				array $permission:	Permission required to access the whole section.

			For Areas:
				array $permission:	Array of permissions to determine who can access this area.
				string $label:		Optional text string for link (Otherwise $txt[$index] will be used)
				string $file:		Name of source file required for this area.
				string $function:	Function to call when area is selected.
				string $custom_url:	URL to use for this menu item.
				string $icon:		File name of an icon to use on the menu, if using the sprite class, set as transparent.png
	 			string $class:		Class name to apply to the icon img, used to apply a sprite icon
				bool $enabled:		Should this area even be accessible?
				bool $hidden:		Should this area be visible?
				string $select:		If set this item will not be displayed - instead the item indexed here shall be.
				array $subsections:	Array of subsections from this area.

			For Subsections:
				string 0:		Text label for this subsection.
				array 1:		Array of permissions to check for this subsection.
				bool 2:			Is this the default subaction - if not set for any will default to first...
				bool enabled:	Bool to say whether this should be enabled or not.
				array active:	Set the button active for other subsections.
	*/

	// Every menu gets a unique ID, these are shown in first in, first out order.
	$context['max_menu_id'] = isset($context['max_menu_id']) ? $context['max_menu_id'] + 1 : 1;

	// This will be all the data for this menu - and we'll make a shortcut to it to aid readability here.
	$context['menu_data_' . $context['max_menu_id']] = array();
	$menu_context = &$context['menu_data_' . $context['max_menu_id']];

	// What is the general action of this menu (i.e. $scripturl?action=XXXX.
	$menu_context['current_action'] = isset($menuOptions['action']) ? $menuOptions['action'] : $context['current_action'];

	// What is the current area selected?
	if (isset($menuOptions['current_area']) || isset($_REQUEST['area']))
		$menu_context['current_area'] = isset($menuOptions['current_area']) ? $menuOptions['current_area'] : $_REQUEST['area'];

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
				if (empty($area['permission'][$permission_set]) || !allowedTo($section['permission'][$permission_set]))
					continue;
			}
			elseif (!allowedTo($section['permission']))
				continue;
		}

		// Now we cycle through the sections to pick the right area.
		foreach ($section['areas'] as $area_id => $area)
		{
			// Can we do this?
			if (!isset($area['enabled']) || $area['enabled'] != false)
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
							$menu_context['sections'][$section_id]['areas'][$area_id]['icon'] = '<img ' . (isset($area['class']) ? 'class="' . $area['class'] . '" ' : 'style="background: none"') . 'src="' . $context['menu_image_path'] . '/' . $area['icon'] . '" alt="" />&nbsp;&nbsp;';
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
									if ($first_sa == null)
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
										if (isset($_REQUEST['sa']) && $_REQUEST['sa'] == $sa)
											$menu_context['current_subsection'] = $sa;

										elseif (isset($sub['active']) && isset($_REQUEST['sa']) && in_array($_REQUEST['sa'], $sub['active']))
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
 * $selectedMenu is the array of menu information,
 *  with the format as retrieved from createMenu()
 *
 * If $selectedMenu['controller'] is set, then it is a class,
 * and $selectedMenu['function'] will be a method of it.
 * If it is not set, then $selectedMenu['function'] is
 * simply a function to call.
 *
 * @param mixed[] $selectedMenu
 */
function callMenu($selectedMenu)
{
	// We use only $selectedMenu['function'] and
	//  $selectedMenu['controller'] if the latter is set.

	if (!empty($selectedMenu['controller']))
	{
		// 'controller' => 'ManageAttachments_Controller'
		// 'function' => 'action_avatars'
		$controller = new $selectedMenu['controller']();

		// always set up the environment
		if (method_exists($controller, 'pre_dispatch'))
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