<?php

/**
 * This class contains a standard way of displaying side/drop down menus.
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * This file contains code covered by:
 * copyright:    2011 Simple Machines (http://www.simplemachines.org)
 * license:    BSD, See included LICENSE.TXT for terms and conditions.
 *
 * @version 1.1 beta 2
 *
 */

/**
 * This class implements a standard way of creating menus
 */
Class Menu_Create
{
	protected $_req;
	protected $_menuOptions;
	protected $_menuData;
	protected $_menu_context = array();
	protected $_permission_set;
	protected $_found_section;
	protected $_backup_area = '';
	protected $_include_data = array();
	protected $_section_id;
	protected $_section;
	protected $_area_id;
	protected $_area;
	protected $_sa;
	protected $_sub;

	/**
	 * Class constructor
	 *
	 * @param array $menuData the menu array
	 *   Possible indexes:
	 *   Menu name with named indexes as follows:
	 *        - string $title     => Section title
	 *        - bool $enabled     => Is the section enabled / shown
	 *        - array $this->_areas      => Array of areas within this menu section, see below
	 *        - array $permission => Permission required to access the whole section
	 *
	 *   areas sub array from above, named indexes as follows:
	 *        - array $permission  => Array of permissions to determine who can access this area
	 *        - string $label      => Optional text string for link (Otherwise $txt[$index] will be used)
	 *        - string $controller => Name of controller required for this area
	 *        - string $function   => Method in controller to call when area is selected
	 *        - string $icon       => File name of an icon to use on the menu, if using a class set as transparent.png
	 *        - string $class      => CSS class name to apply to the icon img, used to apply a sprite icon
	 *        - string $custom_url => URL to call for this menu item
	 *        - bool $enabled      => Should this area even be enabled / accessible?
	 *        - bool $hidden       => If the area is visible in the menu
	 *        - string $select     => If set, references another area
	 *        - array $subsections => Array of subsections for this menu area, see below
	 *
	 *   subsections sub array from above, unnamed indexes interpreted as follows, single named index 'enabled'
	 *        - string 0           => Label for this subsection
	 *        - array 1            => Array of permissions to check for this subsection.
	 *        - bool 2             => Is this the default subaction - if not set for any will default to first...
	 *        - bool enabled       => Enabled or not
	 *        - array active       => Set the button active for other subsections.
	 *        - string url         => Custom url for the subsection
	 *
	 * @param array $menuOptions an array of options that can be used to override some default behaviours.
	 *   It can accept the following indexes:
	 *        - action                    => overrides the default action
	 *        - current_area              => overrides the current area
	 *        - extra_url_parameters      => an array or pairs or parameters to be added to the url
	 *        - disable_url_session_check => (boolean) if true the session var/id are omitted from the url
	 *        - base_url                  => an alternative base url
	 *        - menu_type                 => alternative menu types?
	 *        - can_toggle_drop_down      => (boolean) if the menu can "toggle"
	 *        - template_name             => an alternative template to load (instead of Generic)
	 *        - layer_name                => alternative layer name for the menu
	 *        - hook                      => hook name to call integrate_ . 'hook name' . '_areas'
	 *        - default_include_dir       => directory to include for function support
	 */
	public function __construct($menuData, $menuOptions = array())
	{
		global $context, $settings;

		// Set the passed menu definition to class vars
		$this->_menuData = $menuData;
		$this->_menuOptions = $menuOptions;

		// Access to post/get data
		$this->_req = HttpReq::instance();

		// Work out where we should get our menu images from.
		$context['menu_image_path'] = file_exists($settings['theme_dir'] . '/images/admin/change_menu.png')
			? $settings['images_url'] . '/admin'
			: $settings['default_images_url'] . '/admin';

		// Every menu gets a unique ID, these are shown in first in, first out order.
		$context['max_menu_id'] = isset($context['max_menu_id']) ? $context['max_menu_id']++ : 1;

		// This will be all the data for this menu
		$this->_menu_context = array();

		// This is necessary only in profile (at least for the core), but we do it always because it's easier
		$this->_permission_set = !empty($context['user']['is_owner']) ? 'own' : 'any';
	}

	/**
	 * Create a menu.
	 *
	 * @return array|bool
	 */
	public function createMenu()
	{
		global $context, $settings, $options, $scripturl, $user_info;

		// Call this menus integration hook
		$this->_integrationHook();

		// Process the menu Options
		$this->processMenuOptions();

		// Process the menu Data
		$this->processMenuData();

		// Ensure we have a current subaction defined
		if (!isset($context['current_subaction']) && isset($this->_menu_context['current_subsection']))
		{
			$context['current_subaction'] = $this->_menu_context['current_subsection'];
		}

		// Should we use a custom base url, or use the default?
		$this->_menu_context['base_url'] = isset($this->_menuOptions['base_url'])
			? $this->_menuOptions['base_url']
			: $scripturl . '?action=' . $this->_menu_context['current_action'];

		$this->_checkBaseUrl();

		// If we didn't find the area we were looking for go to a default one.
		if (isset($this->_backup_area) && empty($this->_found_section))
		{
			$this->_menu_context['current_area'] = $this->_backup_area;
		}

		// If still no data then return - nothing to show!
		if (empty($this->_menu_context['sections']))
		{
			// Never happened!
			$context['max_menu_id']--;
			if ($context['max_menu_id'] == 0)
			{
				unset($context['max_menu_id']);
			}

			return false;
		}

		// What type of menu is this?
		if (empty($this->_menuOptions['menu_type']))
		{
			$this->_menuOptions['menu_type'] = '_' . (empty($options['use_sidebar_menu']) ? 'dropdown' : 'sidebar');
			$this->_menu_context['can_toggle_drop_down'] = !$user_info['is_guest'] && isset($settings['theme_version']) && $settings['theme_version'] >= 2.0;
		}
		else
		{
			$this->_menu_context['can_toggle_drop_down'] = !empty($this->_menuOptions['can_toggle_drop_down']);
		}

		// Almost there - load the template and add to the template layers.
		loadTemplate(isset($this->_menuOptions['template_name']) ? $this->_menuOptions['template_name'] : 'GenericMenu');
		$this->_menu_context['layer_name'] = (isset($this->_menuOptions['layer_name']) ? $this->_menuOptions['layer_name'] : 'generic_menu') . $this->_menuOptions['menu_type'];
		Template_Layers::getInstance()->add($this->_menu_context['layer_name']);

		// Check we had something - for sanity sake.
		if (empty($this->_include_data))
		{
			return false;
		}

		// Set it all to context for template consumption
		$context['menu_data_' . $context['max_menu_id']] = $this->_menu_context;

		// Finally - return information on the selected item.
		$this->_include_data += array(
			'current_action' => $this->_menu_context['current_action'],
			'current_area' => $this->_menu_context['current_area'],
			'current_section' => $this->_menu_context['current_section'],
			'current_subsection' => !empty($this->_menu_context['current_subsection']) ? $this->_menu_context['current_subsection'] : '',
		);

		return $this->_include_data;
	}

	/**
	 * Call the integration hook for this menu
	 *
	 * What it does:
	 *   - If supplied a hook name in the menuOptions, calls the integration function
	 *   - Called before other menu processing to allow hook full control
	 */
	private function _integrationHook()
	{
		// Allow extend *any* menu with a single hook
		if (!empty($this->_menuOptions['hook']))
		{
			call_integration_hook('integrate_' . $this->_menuOptions['hook'] . '_areas', array(&$this->_menuData, &$this->_menuOptions));
		}
	}

	/**
	 * Process the array of MenuOptions passed to the class
	 */
	protected function processMenuOptions()
	{
		global $context;

		// What is the general action of this menu i.e. $scripturl?action=XYZ.
		$this->_menu_context['current_action'] = isset($this->_menuOptions['action'])
			? $this->_menuOptions['action']
			: $context['current_action'];

		// What is the current area selected?
		if (isset($this->_menuOptions['current_area']) || isset($this->_req->query->area))
		{
			$this->_menu_context['current_area'] = isset($this->_menuOptions['current_area'])
				? $this->_menuOptions['current_area']
				: $this->_req->query->area;
		}

		// Build a list of additional parameters that should go in the URL.
		$this->_menu_context['extra_parameters'] = '';
		if (!empty($this->_menuOptions['extra_url_parameters']))
		{
			foreach ($this->_menuOptions['extra_url_parameters'] as $key => $value)
			{
				$this->_menu_context['extra_parameters'] .= ';' . $key . '=' . $value;
			}
		}

		// Only include the session ID in the URL if it's strictly necessary.
		if (empty($this->_menuOptions['disable_url_session_check']))
		{
			$this->_menu_context['extra_parameters'] .= ';' . $context['session_var'] . '=' . $context['session_id'];
		}
	}

	/**
	 * Process the menuData array passed to the class
	 */
	protected function processMenuData()
	{

		// Now setup the context correctly.
		foreach ($this->_menuData as $this->_section_id => $this->_section)
		{
			// Is this section enabled?
			if (!$this->_sectionEnabled())
			{
				continue;
			}

			// Do they have permissions?
			if (!$this->_sectionPermissions())
			{
				continue;
			}

			// Process the sections to pick the right area.
			$this->processSectionAreas();
		}
	}

	/**
	 * Determines if the section is enabled or not
	 *
	 * @return bool
	 */
	private function _sectionEnabled()
	{
		return !isset($this->_section['enabled']) || (bool) $this->_section['enabled'] !== false;
	}

	/**
	 * Determines if the user has the permissions to access the section
	 *
	 * @return bool
	 */
	private function _sectionPermissions()
	{
		if (isset($this->_section['permission']))
		{
			// The profile menu has slightly different permissions
			if (is_array($this->_section['permission']) && isset($this->_section['permission']['own'], $this->_section['permission']['any']))
			{
				if (empty($this->_area['permission'][$this->_permission_set]) || !allowedTo($this->_section['permission'][$this->_permission_set]))
				{
					return false;
				}
			}
			elseif (!allowedTo($this->_section['permission']))
			{
				return false;
			}
		}

		return true;
	}

	/**
	 * Main processing for creating the menu items for all sections
	 */
	protected function processSectionAreas()
	{
		// Now we cycle through the sections to pick the right area.
		foreach ($this->_section['areas'] as $this->_area_id => $this->_area)
		{
			// Is the area enabled, Does the user have permission and it has some form of a name
			if (!$this->_areaEnabled() || !$this->_areaPermissions() || !$this->_areaLabel())
			{
				continue;
			}

			// We may want to include a file, let's find out the path
			$this->_setAreaFile();

			// Make sure we have a valid current area
			$this->_setAreaCurrent();

			// If this is hidden from view don't do the rest.
			if (empty($this->_area['hidden']))
			{
				// First time this section?
				$this->_setSectionId();

				// Maybe a custom url
				$this->_setAreaCustomUrl();

				// Even a little icon
				$this->_setAreaIcon();

				// Did it have subsections?
				$this->processAreaSubsections();
			}

			// Is this the current section?
			// @todo why $this->_found_section is not initialized outside one of the loops? (Not sure which one lol)
			if ($this->_menu_context['current_area'] == $this->_area_id && empty($this->_found_section))
			{
				// Only do this once?
				$this->_found_section = true;

				// Update the context if required - as we can have areas pretending to be others. ;)
				$this->_menu_context['current_section'] = $this->_section_id;

				$this->_menu_context['current_area'] = isset($this->_area['select']) ? $this->_area['select'] : $this->_area_id;

				// This will be the data we return.
				$this->_include_data = $this->_area;
			}
			// Make sure we have something in case it's an invalid area.
			elseif (empty($this->_found_section) && empty($this->_include_data))
			{
				$this->_menu_context['current_section'] = $this->_section_id;
				$this->_backup_area = isset($this->_area['select']) ? $this->_area['select'] : $this->_area_id;
				$this->_include_data = $this->_area;
			}
		}
	}

	/**
	 * Determines of the menu area is enabled, area being each menu dropdown area
	 *
	 * @return bool
	 */
	private function _areaEnabled()
	{
		return !isset($this->_area['enabled']) || (bool) $this->_area['enabled'] !== false;
	}

	/**
	 * Determines if a user has permission to access an area
	 *
	 * @return bool
	 */
	private function _areaPermissions()
	{
		if (!empty($this->_area['permission']))
		{
			// The profile menu has slightly different permissions
			if (is_array($this->_area['permission']) && isset($this->_area['permission']['own'], $this->_area['permission']['any']))
			{
				if (empty($this->_area['permission'][$this->_permission_set]) || !allowedTo($this->_area['permission'][$this->_permission_set]))
				{
					return false;
				}
			}
			elseif (!allowedTo($this->_area['permission']))
			{
				return false;
			}
		}

		return true;
	}

	/**
	 * Checks if the area has a label or not
	 *
	 * @return bool
	 */
	private function _areaLabel()
	{
		return isset($this->_area['label']) || (isset($txt[$this->_area_id]) && !isset($this->_area['select']));
	}

	/**
	 * Sets the directory location for th e area file
	 *
	 * What it does
	 *   - Sets it to 'dir' if that is set in the areas menuData
	 *   - Sets it to 'default_include_dir' if that is set in the menuOptions
	 *   - Defaults to CONTROLLERDIR
	 */
	private function _setAreaFile()
	{
		if (!empty($this->_area['file']))
		{
			$this->_area['file'] = (!empty($this->_area['dir']) ? $this->_area['dir'] : (!empty($this->_menuOptions['default_include_dir']) ? $this->_menuOptions['default_include_dir'] : CONTROLLERDIR)) . '/' . $this->_area['file'];
		}
	}

	/**
	 * Simply sets the current area
	 */
	private function _setAreaCurrent()
	{
		// If we don't have an area then the first valid one is our choice.
		if (!isset($this->_menu_context['current_area']))
		{
			$this->_menu_context['current_area'] = $this->_area_id;
			$this->_include_data = $this->_area;
		}
	}

	/**
	 * Sets the various section ID items
	 *
	 * What it does:
	 *   - If the ID is not set, sets it and sets the section title
	 *   - Sets the section title
	 */
	private function _setSectionId()
	{
		global $txt, $settings;

		if (!isset($this->_menu_context['sections'][$this->_section_id]))
		{
			if (isset($this->_menuOptions['counters'], $this->_section['counter']) && !empty($this->_menuOptions['counters'][$this->_section['counter']]))
			{
				$this->_section['title'] .= sprintf($settings['menu_numeric_notice'][0], $this->_menuOptions['counters'][$this->_section['counter']]);
			}

			$this->_menu_context['sections'][$this->_section_id]['title'] = $this->_section['title'];
		}

		$this->_menu_context['sections'][$this->_section_id]['areas'][$this->_area_id] = array(
			'label' => isset($this->_area['label']) ? $this->_area['label'] : $txt[$this->_area_id]
		);

		if (isset($this->_menuOptions['counters'], $this->_area['counter']) && !empty($this->_menuOptions['counters'][$this->_area['counter']]))
		{
			$this->_menu_context['sections'][$this->_section_id]['areas'][$this->_area_id]['label'] .= sprintf($settings['menu_numeric_notice'][1], $this->_menuOptions['counters'][$this->_area['counter']]);
		}

		// We'll need the ID as well...
		$this->_menu_context['sections'][$this->_section_id]['id'] = $this->_section_id;
	}

	/**
	 * Set the custom URL for the menu item
	 */
	private function _setAreaCustomUrl()
	{
		// Does it have a custom URL?
		if (isset($this->_area['custom_url']))
		{
			$this->_menu_context['sections'][$this->_section_id]['areas'][$this->_area_id]['url'] = $this->_area['custom_url'];
		}
	}

	/**
	 * Set the menu icon
	 */
	private function _setAreaIcon()
	{
		global $context;

		// Does this area have its own icon?
		if (isset($this->_area['icon']))
		{
			$this->_menu_context['sections'][$this->_section_id]['areas'][$this->_area_id]['icon'] = '<img ' . (isset($this->_area['class']) ? 'class="' . $this->_area['class'] . '" ' : 'style="background: none"') . ' src="' . $context['menu_image_path'] . '/' . $this->_area['icon'] . '" alt="" />&nbsp;&nbsp;';
		}
		else
		{
			$this->_menu_context['sections'][$this->_section_id]['areas'][$this->_area_id]['icon'] = '';
		}
	}

	/**
	 * Processes all of the subsections for a menu item
	 */
	protected function processAreaSubsections()
	{
		global $settings, $context;

		// If there are subsections for this menu item
		if (!empty($this->_area['subsections']))
		{
			$this->_menu_context['sections'][$this->_section_id]['areas'][$this->_area_id]['subsections'] = array();
			$first_sa = null;
			$last_sa = null;

			// For each subsection process the options
			foreach ($this->_area['subsections'] as $this->_sa => $this->_sub)
			{
				// Sub[1] is an array of permissions to check for this subsection
				if ((empty($this->_sub[1]) || allowedTo($this->_sub[1])) && (!isset($this->_sub['enabled']) || !empty($this->_sub['enabled'])))
				{
					if ($first_sa === null)
					{
						$first_sa = $this->_sa;
					}

					// sub[0] is a string containing the label for this subsection
					$this->_menu_context['sections'][$this->_section_id]['areas'][$this->_area_id]['subsections'][$this->_sa] = array('label' => $this->_sub[0]);
					if (isset($this->_menuOptions['counters'], $this->_sub['counter']) && !empty($this->_menuOptions['counters'][$this->_sub['counter']]))
					{
						$this->_menu_context['sections'][$this->_section_id]['areas'][$this->_area_id]['subsections'][$this->_sa]['label'] .= sprintf($settings['menu_numeric_notice'][2], $this->_menuOptions['counters'][$this->_sub['counter']]);
					}

					$this->_setSubsSectionUrl();

					// A bit complicated - but is this set?
					$first_sa = $this->_setCurrentSubSection($first_sa);

					// Let's assume this is the last, for now.
					$last_sa = $this->_sa;
				}
				// Mark it as disabled...
				else
				{
					$this->_menu_context['sections'][$this->_section_id]['areas'][$this->_area_id]['subsections'][$this->_sa]['disabled'] = true;
				}
			}

			// Set which one is first, last and selected in the group.
			if (!empty($this->_menu_context['sections'][$this->_section_id]['areas'][$this->_area_id]['subsections']))
			{
				$this->_menu_context['sections'][$this->_section_id]['areas'][$this->_area_id]['subsections'][$context['right_to_left'] ? $last_sa : $first_sa]['is_first'] = true;
				$this->_menu_context['sections'][$this->_section_id]['areas'][$this->_area_id]['subsections'][$context['right_to_left'] ? $first_sa : $last_sa]['is_last'] = true;

				if ($this->_menu_context['current_area'] == $this->_area_id && !isset($this->_menu_context['current_subsection']))
				{
					$this->_menu_context['current_subsection'] = $first_sa;
				}
			}
		}
	}

	/**
	 * Does teh subsection have a custom url ?
	 */
	private function _setSubsSectionUrl()
	{
		// Custom URL?
		if (isset($this->_sub['url']))
		{
			$this->_menu_context['sections'][$this->_section_id]['areas'][$this->_area_id]['subsections'][$this->_sa]['url'] = $this->_sub['url'];
		}
	}

	/**
	 * Set the current subsection
	 *
	 * @param $first_sa
	 *
	 * @return mixed
	 */
	private function _setCurrentSubSection($first_sa)
	{
		if ($this->_menu_context['current_area'] == $this->_area_id)
		{
			// Save which is the first...
			if (empty($first_sa))
			{
				$first_sa = $this->_sa;
			}

			// Is this the current subsection?
			if (isset($this->_req->query->sa) && $this->_req->query->sa == $this->_sa)
			{
				$this->_menu_context['current_subsection'] = $this->_sa;
			}
			elseif (isset($this->_sub['active']) && isset($this->_req->query->sa) && in_array($this->_req->query->sa, $this->_sub['active']))
			{
				$this->_menu_context['current_subsection'] = $this->_sa;
			}

			// Otherwise is it the default?
			elseif (!isset($this->_menu_context['current_subsection']) && !empty($this->_sub[2]))
			{
				$this->_menu_context['current_subsection'] = $this->_sa;
			}
		}

		return $first_sa;
	}

	/**
	 * 	If there is a current section, checks if the base menu has an url
	 */
	private function _checkBaseUrl()
	{
		global $context;

		// If there are sections quickly goes through all the sections to check if the base menu has an url
		if (!empty($this->_menu_context['current_section']))
		{
			$this->_menu_context['sections'][$this->_menu_context['current_section']]['selected'] = true;
			$this->_menu_context['sections'][$this->_menu_context['current_section']]['areas'][$this->_menu_context['current_area']]['selected'] = true;

			if (!empty($this->_menu_context['sections'][$this->_menu_context['current_section']]['areas'][$this->_menu_context['current_area']]['subsections'][$context['current_subaction']]))
			{
				$this->_menu_context['sections'][$this->_menu_context['current_section']]['areas'][$this->_menu_context['current_area']]['subsections'][$context['current_subaction']]['selected'] = true;
			}

			foreach ($this->_menu_context['sections'] as $this->_section_id => $this->_section)
			{
				foreach ($this->_section['areas'] as $this->_area_id => $this->_area)
				{
					if (!isset($this->_menu_context['sections'][$this->_section_id]['url']))
					{
						$this->_menu_context['sections'][$this->_section_id]['url'] = isset($this->_area['url'])
							? $this->_area['url']
							: $this->_menu_context['base_url'] . ';area=' . $this->_area_id;
						break;
					}
				}
			}
		}
	}

	/**
	 * Delete a menu.
	 *
	 * @param string $menu_id = 'last'
	 *
	 * @return false|null
	 */
	public function destroyMenu($menu_id = 'last')
	{
		global $context;

		$menu_name = $menu_id == 'last' && isset($context['max_menu_id']) && isset($context['menu_data_' . $context['max_menu_id']]) ? 'menu_data_' . $context['max_menu_id'] : 'menu_data_' . $menu_id;
		if (!isset($context[$menu_name]))
		{
			return false;
		}

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
	public function callMenu($selectedMenu)
	{
		// We use only $selectedMenu['function'] and
		//  $selectedMenu['controller'] if the latter is set.
		if (!empty($selectedMenu['controller']))
		{
			// 'controller' => 'ManageAttachments_Controller'
			// 'function' => 'action_avatars'
			$controller = new $selectedMenu['controller'](new Event_Manager());

			// Always set up the environment
			$controller->pre_dispatch();

			// and go!
			$controller->{$selectedMenu['function']}();
		}
		else
		{
			// A single function name... call it over!
			$selectedMenu['function']();
		}
	}
}