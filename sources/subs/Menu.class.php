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
 * @version 1.1 beta 3
 *
 */

/**
 * This class implements a standard way of creating menus
 */
Class Menu_Create
{
	/**
	 * Instance of HttpReq
	 * @var HttpReq
	 */
	protected $req;

	/**
	 * Will hold the created $context
	 * @var array
	 */
	protected $menu_context = array();

	/**
	 * Used for profile menu for own / any
	 * @var string
	 */
	protected $permission_set;

	/**
	 * If we found the menu item selected
	 * @var bool
	 */
	protected $found_section;

	/**
	 * If we can't find the selection, we pick for them
	 * @var string
	 */
	protected $backup_area = '';

	/**
	 * The current subaction of the system
	 * @var string
	 */
	protected $current_subaction;

	/**
	 * Will hold the selected menu data that is returned to the caller
	 * @var array
	 */
	private $include_data = array();

	/**
	 * Loop id for menu
	 * @var string
	 */
	private $section_id;

	/**
	 * Loop data for menu
	 * @var array
	 */
	private $section;

	/**
	 * Loop id for a specific menu level
	 * @var
	 */
	private $area_id;

	/**
	 * Loop data for the menu level
	 * @var array
	 */
	private $area;

	/**
	 * Loop id for the subaction of a menu
	 * @var string
	 */
	private $sa;

	/**
	 * Loop data for the subaction
	 * @var array
	 */
	private $sub;

	/**
	 * Unique menu number
	 * @var int
	 */
	private $max_menu_id;

	/**
	 * Hey its me, the menu object
	 * @var Menu
	 */
	private static $instance;

	/**
	 * Holds menu options set by AddOptions
	 * @var array
	 */
	public $menuOptions = array();

	/**
	 * Holds menu definition structure set by AddAreas
	 * @var array
	 */
	public $menuData = array();

	/**
	 * Menu_Create constructor.
	 */
	public function _construct()
	{
	}

	/**
	 * Initial processing for the menu
	 *
	 * @return array|bool
	 */
	public function prepareMenu()
	{
		global $context, $settings;

		// Access to post/get data
		$this->req = HttpReq::instance();

		// Work out where we should get our menu images from.
		$context['menu_image_path'] = file_exists($settings['theme_dir'] . '/images/admin/change_menu.png')
			? $settings['images_url'] . '/admin'
			: $settings['default_images_url'] . '/admin';

		// Every menu gets a unique ID, these are shown in first in, first out order.
		$this->max_menu_id = isset($context['max_menu_id']) ? $context['max_menu_id']++ : 1;

		// This will be all the data for this menu
		$this->menu_context = array();

		// This is necessary only in profile (at least for the core), but we do it always because it's easier
		$this->permission_set = !empty($context['user']['is_owner']) ? 'own' : 'any';

		// We may have a current subaction
		$this->current_subaction = isset($context['current_subaction']) ? $context['current_subaction'] : null;

		// Create menu will return the include data
		return $this->createMenu();
	}

	/**
	 * Create a menu
	 *
	 * @return array|bool
	 */
	public function createMenu()
	{
		// Call this menus integration hook
		$this->integrationHook();

		// Process the menu Options
		$this->processMenuOptions();

		// Process the menu Data
		$this->processMenuData();

		// Set the current
		$this->determineCurrentAction();

		// Check the menus urls
		$this->checkBaseUrl();

		// Make sure we created some awesome sauce
		if (!$this->validateData())
			return false;

		// Finally - return information on the selected item.
		$this->include_data += array(
			'current_action' => $menu_context['current_action'],
			'current_area' => $menu_context['current_area'],
			'current_section' => !empty($this->menu_context['current_section']) ? $this->menu_context['current_section'] : '',
			'current_subsection' => !empty($this->menu_context['current_subsection']) ? $this->menu_context['current_subsection'] : '',
		);

		return $this->include_data;
	}

	/**
	 * Performs a sanity check that a menu was created successfully
	 *
	 *   - If it fails to find valid data, will reset max_menu_id and any menu context created
	 *
	 * @return bool
	 */
	private function validateData()
	{
		global $context;

		// If we didn't find the area we were looking for go to a default one.
		if (isset($this->backup_area) && empty($this->found_section))
		{
			$this->menu_context['current_area'] = $this->backup_area;
		}

		// If still no data then reset - nothing to show!
		if (empty($this->menu_context['sections']))
		{
			// Never happened!
			$this->menu_context = array();
			$this->max_menu_id--;
			$context['max_menu_id'] = $this->max_menu_id;

			if ($this->max_menu_id === 0)
			{
				unset($context['max_menu_id']);
			}

			return false;
		}

		// Check we had something - for sanity sake.
		return !empty($this->include_data);
	}

	/**
	 * Call the integration hook for this menu
	 *
	 * What it does:
	 *   - If supplied a hook name in the menuOptions, calls the integration function
	 *   - Called before other menu processing to allow hook full control
	 */
	private function integrationHook()
	{
		// Allow extend *any* menu with a single hook
		if (!empty($this->menuOptions['hook']))
		{
			call_integration_hook('integrate_' . $this->menuOptions['hook'] . '_areas', array(&$this->menuData, &$this->menuOptions));
		}
	}

	/**
	 * Process the array of MenuOptions passed to the class
	 */
	protected function processMenuOptions()
	{
		global $context;

		// What is the general action of this menu i.e. $scripturl?action=XYZ.
		$this->menu_context['current_action'] = isset($this->menuOptions['action'])
			? $this->menuOptions['action']
			: $context['current_action'];

		// What is the current area selected?
		if (isset($this->menuOptions['current_area']) || isset($this->req->query->area))
		{
			$this->menu_context['current_area'] = isset($this->menuOptions['current_area'])
				? $this->menuOptions['current_area']
				: $this->req->query->area;
		}

		// Build a list of additional parameters that should go in the URL.
		$this->buildAdditionalParams();
	}

	/**
	 * Build the menuOption additional parameters for use in the url
	 */
	private function buildAdditionalParams()
	{
		global $context;

		$this->menu_context['extra_parameters'] = '';

		if (!empty($this->menuOptions['extra_url_parameters']))
		{
			foreach ($this->menuOptions['extra_url_parameters'] as $key => $value)
			{
				$this->menu_context['extra_parameters'] .= ';' . $key . '=' . $value;
			}
		}

		// Only include the session ID in the URL if it's strictly necessary.
		if (empty($this->menuOptions['disable_url_session_check']))
		{
			$this->menu_context['extra_parameters'] .= ';' . $context['session_var'] . '=' . $context['session_id'];
		}
	}

	/**
	 * Process the menuData array passed to the class
	 *
	 *   - Only processes areas that are enabled and that the user has permissions
	 */
	protected function processMenuData()
	{
		// Now setup the context correctly.
		foreach ($this->menuData as $section_id => $section)
		{
			// Is this section enabled? and do they have permissions?
			if (!$this->sectionEnabled() || !$this->menuPermissions($this->section))
			{
				continue;
			}

			// Process this menu section
			$this->processSectionAreas();
		}
	}

	/**
	 * Determines if the section is enabled or not
	 *
	 * @return bool
	 */
	private function sectionEnabled()
	{
		return !isset($this->section['enabled']) || (bool) $this->section['enabled'] !== false;
	}

	/**
	 * Determines if the user has the permissions to access the section/area
	 *
	 * @param array $area area or section being checked
	 *
	 * @return bool
	 */
	private function menuPermissions($area)
	{
		if (!empty($area['permission']))
		{
			// The profile menu has slightly different permissions
			if (is_array($area['permission']) && isset($area['permission']['own'], $area['permission']['any']))
			{
				if (empty($area['permission'][$this->permission_set]) || !allowedTo($area['permission'][$this->permission_set]))
				{
					return false;
				}
			}
			elseif (!allowedTo($area['permission']))
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
		foreach ($this->section['areas'] as $area_id => $area)
		{
			// Is the area enabled, Does the user have permission and it has some form of a name
			if (!$this->areaEnabled() || !$this->menuPermissions($this->area) || !$this->areaLabel())
			{
				continue;
			}

			// We may want to include a file, let's find out the path
			$this->setAreaFile();

			// Make sure we have a valid current area
			$this->setAreaCurrent();

			// If this is hidden from view don't do the rest.
			if (empty($this->area['hidden']))
			{
				// First time this section?
				$this->setSectionId();

				// Maybe a custom url
				$this->setAreaCustomUrl();

				// Even a little icon
				$this->setAreaIcon();

				// Did it have subsections?
				$this->processAreaSubsections();
			}

			// Is this the current section?
			$this->checkCurrentSection();
		}
	}

	/**
	 * Checks the menu item to see if it is the currently selected one
	 */
	private function checkCurrentSection()
	{
		// Is this the current section?
		// @todo why $this->found_section is not initialized outside one of the loops? (Not sure which one lol)
		if ($this->menu_context['current_area'] == $this->area_id && empty($this->found_section))
		{
			// Only do this once?
			$this->found_section = true;

			// Update the context if required - as we can have areas pretending to be others. ;)
			$this->menu_context['current_section'] = $this->section_id;

			$this->menu_context['current_area'] = isset($this->area['select']) ? $this->area['select'] : $this->area_id;

			// This will be the data we return.
			$this->include_data = $this->area;
		}
		// Make sure we have something in case it's an invalid area.
		elseif (empty($this->found_section) && empty($this->include_data))
		{
			$this->menu_context['current_section'] = $this->section_id;
			$this->backup_area = isset($this->area['select']) ? $this->area['select'] : $this->area_id;
			$this->include_data = $this->area;
		}
	}

	/**
	 * Determines of the menu area is enabled, area being each menu dropdown area
	 *
	 * @return bool
	 */
	private function areaEnabled()
	{
		return !isset($this->area['enabled']) || (bool) $this->area['enabled'] !== false;
	}

	/**
	 * Checks if the area has a label or not
	 *
	 * @return bool
	 */
	private function areaLabel()
	{
		global $txt;

		return isset($this->area['label']) || (isset($txt[$this->area_id]) && !isset($this->area['select']));
	}

	/**
	 * Sets the directory location for th e area file
	 *
	 * What it does
	 *   - Sets it to 'dir' if that is set in the areas menuData
	 *   - Sets it to 'default_include_dir' if that is set in the menuOptions
	 *   - Defaults to CONTROLLERDIR
	 */
	private function setAreaFile()
	{
		if (!empty($this->area['file']))
		{
			$this->area['file'] = (!empty($this->area['dir']) ? $this->area['dir'] : (!empty($this->menuOptions['default_include_dir']) ? $this->menuOptions['default_include_dir'] : CONTROLLERDIR)) . '/' . $this->area['file'];
		}
	}

	/**
	 * Simply sets the current area
	 */
	private function setAreaCurrent()
	{
		// If we don't have an area then the first valid one is our choice.
		if (!isset($this->menu_context['current_area']))
		{
			$this->menu_context['current_area'] = $this->area_id;
			$this->include_data = $this->area;
		}
	}

	/**
	 * Sets the various section ID items
	 *
	 * What it does:
	 *   - If the ID is not set, sets it and sets the section title
	 *   - Sets the section title
	 */
	private function setSectionId()
	{
		global $txt, $settings;

		if (!isset($this->menu_context['sections'][$this->section_id]))
		{
			if (isset($this->menuOptions['counters'], $this->section['counter']) && !empty($this->menuOptions['counters'][$this->section['counter']]))
			{
				$this->section['title'] .= sprintf($settings['menu_numeric_notice'][0], $this->menuOptions['counters'][$this->section['counter']]);
			}

			$this->menu_context['sections'][$this->section_id]['title'] = $this->section['title'];
		}

		$this->menu_context['sections'][$this->section_id]['areas'][$this->area_id] = array(
			'label' => isset($this->area['label']) ? $this->area['label'] : $txt[$this->area_id]
		);

		if (isset($this->menuOptions['counters'], $this->area['counter']) && !empty($this->menuOptions['counters'][$this->area['counter']]))
		{
			$this->menu_context['sections'][$this->section_id]['areas'][$this->area_id]['label'] .= sprintf($settings['menu_numeric_notice'][1], $this->menuOptions['counters'][$this->area['counter']]);
		}

		// We'll need the ID as well...
		$this->menu_context['sections'][$this->section_id]['id'] = $this->section_id;
	}

	/**
	 * Set the custom URL for the menu item
	 */
	private function setAreaCustomUrl()
	{
		// Does it have a custom URL?
		if (isset($this->area['custom_url']))
		{
			$this->menu_context['sections'][$this->section_id]['areas'][$this->area_id]['url'] = $this->area['custom_url'];
		}
	}

	/**
	 * Set the menu icon
	 */
	private function setAreaIcon()
	{
		global $context;

		// Does this area have its own icon?
		if (isset($this->area['icon']))
		{
			$this->menu_context['sections'][$this->section_id]['areas'][$this->area_id]['icon'] = '<img ' . (isset($this->area['class']) ? 'class="' . $this->area['class'] . '" ' : 'style="background: none"') . ' src="' . $context['menu_image_path'] . '/' . $this->area['icon'] . '" alt="" />&nbsp;&nbsp;';
		}
		else
		{
			$this->menu_context['sections'][$this->section_id]['areas'][$this->area_id]['icon'] = '';
		}
	}

	/**
	 * Processes all of the subsections for a menu item
	 */
	protected function processAreaSubsections()
	{
		global $settings, $context;

		// If there are subsections for this menu item
		if (!empty($this->area['subsections']))
		{
			$this->menu_context['sections'][$this->section_id]['areas'][$this->area_id]['subsections'] = array();
			$first_sa = null;
			$last_sa = null;

			// For each subsection process the options
			foreach ($this->area['subsections'] as $sa => $sub)
			{
				// Sub[1] is an array of permissions to check for this subsection
				if ((empty($this->sub[1]) || allowedTo($this->sub[1])) && (!isset($this->sub['enabled']) || !empty($this->sub['enabled'])))
				{
					if ($first_sa === null)
					{
						$first_sa = $this->sa;
					}

					// sub[0] is a string containing the label for this subsection
					$this->menu_context['sections'][$this->section_id]['areas'][$this->area_id]['subsections'][$this->sa] = array('label' => $sub[0]);
					if (isset($this->menuOptions['counters'], $this->sub['counter']) && !empty($this->menuOptions['counters'][$this->sub['counter']]))
					{
						$this->menu_context['sections'][$this->section_id]['areas'][$this->area_id]['subsections'][$this->sa]['label'] .= sprintf($settings['menu_numeric_notice'][2], $this->menuOptions['counters'][$this->sub['counter']]);
					}

					$this->setSubsSectionUrl();

					// A bit complicated - but is this set?
					$first_sa = $this->setCurrentSubSection($first_sa);

					// Let's assume this is the last, for now.
					$last_sa = $this->sa;
				}
				// Mark it as disabled...
				else
				{
					$this->menu_context['sections'][$this->section_id]['areas'][$this->area_id]['subsections'][$this->sa]['disabled'] = true;
				}
			}

			// Set which one is first, last and selected in the group.
			if (!empty($this->menu_context['sections'][$this->section_id]['areas'][$this->area_id]['subsections']))
			{
				$this->menu_context['sections'][$this->section_id]['areas'][$this->area_id]['subsections'][$context['right_to_left'] ? $last_sa : $first_sa]['is_first'] = true;
				$this->menu_context['sections'][$this->section_id]['areas'][$this->area_id]['subsections'][$context['right_to_left'] ? $first_sa : $last_sa]['is_last'] = true;

				if ($this->menu_context['current_area'] == $this->area_id && !isset($this->menu_context['current_subsection']))
				{
					$this->menu_context['current_subsection'] = $first_sa;
				}
			}
		}
	}

	/**
	 * Does the subsection have a custom url ?
	 */
	private function setSubsSectionUrl()
	{
		// Custom URL?
		if (isset($this->sub['url']))
		{
			$this->menu_context['sections'][$this->section_id]['areas'][$this->area_id]['subsections'][$this->sa]['url'] = $this->sub['url'];
		}
	}

	/**
	 * Set the current subsection
	 *
	 * @param $first_sa
	 *
	 * @return mixed
	 */
	private function setCurrentSubSection($first_sa)
	{
		if ($this->menu_context['current_area'] == $this->area_id)
		{
			// Save which is the first...
			if (empty($first_sa))
			{
				$first_sa = $this->sa;
			}

			// Is this the current subsection?
			$sa_check = $this->req->getQuery('sa', 'trim', null);
			if ($sa_check == $this->sa)
			{
				$this->menu_context['current_subsection'] = $this->sa;
			}
			elseif (isset($this->sub['active']) && isset($sa_check) && in_array($sa_check, $this->sub['active']))
			{
				$this->menu_context['current_subsection'] = $this->sa;
			}
			// Otherwise is it the default?
			elseif (!isset($this->menu_context['current_subsection']) && !empty($this->sub[2]))
			{
				$this->menu_context['current_subsection'] = $this->sa;
			}
		}

		return $first_sa;
	}

	/**
	 * Checks that a current subaction for the menu is set
	 */
	private function determineCurrentAction()
	{
		// Ensure we have a current subaction defined
		if (!isset($this->current_subaction) && isset($this->menu_context['current_subsection']))
		{
			$this->current_subaction = $this->menu_context['current_subsection'];
		}
	}

	/**
	 * Checks and updates base and section urls
	 */
	private function checkBaseUrl()
	{
		global $scripturl;

		// Should we use a custom base url, or use the default?
		$this->menu_context['base_url'] = isset($this->menuOptions['base_url'])
			? $this->menuOptions['base_url']
			: $scripturl . '?action=' . $this->menu_context['current_action'];

		// If there are sections quickly goes through all the sections to check if the base menu has an url
		if (!empty($this->menu_context['current_section']))
		{
			$this->menu_context['sections'][$this->menu_context['current_section']]['selected'] = true;
			$this->menu_context['sections'][$this->menu_context['current_section']]['areas'][$this->menu_context['current_area']]['selected'] = true;

			if (!empty($this->menu_context['sections'][$this->menu_context['current_section']]['areas'][$this->menu_context['current_area']]['subsections'][$this->current_subaction]))
			{
				$this->menu_context['sections'][$this->menu_context['current_section']]['areas'][$this->menu_context['current_area']]['subsections'][$this->current_subaction]['selected'] = true;
			}

			foreach ($this->menu_context['sections'] as $section_id => $section)
			{
				foreach ($this->section['areas'] as $area_id => $area)
				{
					if (!isset($this->menu_context['sections'][$this->section_id]['url']))
					{
						$this->menu_context['sections'][$this->section_id]['url'] = isset($this->area['url'])
							? $this->area['url']
							: $this->menu_context['base_url'] . ';area=' . $this->area_id;
						break;
					}
				}
			}
		}
	}

	/**
	 * Add the base menu options for this menu
	 *
	 * @param array $menuOptions an array of options that can be used to override some default behaviours.
	 * It can accept the following indexes:
	 *      - action                    => overrides the default action
	 *      - current_area              => overrides the current area
	 *      - extra_url_parameters      => an array or pairs or parameters to be added to the url
	 *      - disable_url_session_check => (boolean) if true the session var/id are omitted from the url
	 *      - base_url                  => an alternative base url
	 *      - menu_type                 => alternative menu types?
	 *      - can_toggle_drop_down      => (boolean) if the menu can "toggle"
	 *      - template_name             => an alternative template to load (instead of Generic)
	 *      - layer_name                => alternative layer name for the menu
	 *      - hook                      => hook name to call integrate_ . 'hook name' . '_areas'
	 *      - default_include_dir       => directory to include for function support
	 */
	public function addOptions($menuOptions)
	{
		$this->menuOptions = array_merge($this->menuOptions, $menuOptions);
	}

	/**
	 * Add the data the is used to build the menu
	 *
	 * @param array $menuData the menu array
	 *   Possible indexes:
	 *   Menu name with named indexes as follows:
	 *        - string $title       => Section title
	 *        - bool $enabled       => Is the section enabled / shown
	 *        - array $areas        => Array of areas within this menu section, see below
	 *        - array $permission   => Permission required to access the whole section
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
	 */
	public function addAreas($menuData = array())
	{
		$this->menuData = $this->mergeAreas($this->menuData, $menuData);
	}

	/**
	 * Recursive array merge for adding and replacing areas
	 *
	 * What it does:
	 *   - Adds new keys in array2 that don't exist in array1
	 *   - Replaces keys in array2 that exist in array1
	 *   - array_merge_recursive does not do this as needed, so you get comp101
	 *
	 * @param array $array1 beginning array
	 * @param array $array2 array to replace / add to array1
	 *
	 * @return array
	 */
	public function mergeAreas(&$array1, &$array2)
	{
		$merged = $array1;

		foreach ($array2 as $key => &$value)
		{
			if (is_array($value) && isset($merged[$key]) && is_array($merged[$key]))
			{
				$merged[$key] = $this->mergeAreas($merged[$key], $value);
			}
			else
			{
				$merged[$key] = $value;
			}
		}

		return $merged;
	}

	/**
	 * Finalizes items so the computed menu can be used
	 *
	 * What it does:
	 *   - Sets the menu layer in the template stack
	 *   - Loads context with the computed menu context
	 *   - Sets current subaction and current max menu id
	 */
	public function setContext()
	{
		global $user_info, $settings, $options, $context;

		// What type of menu is this, dropdown or sidebar
		if (empty($this->menuOptions['menu_type']))
		{
			$this->menuOptions['menu_type'] = '_' . (empty($options['use_sidebar_menu']) ? 'dropdown' : 'sidebar');
			$this->menu_context['can_toggle_drop_down'] = !$user_info['is_guest'] && isset($settings['theme_version']) && $settings['theme_version'] >= 2.0;
		}
		else
		{
			$this->menu_context['can_toggle_drop_down'] = !empty($this->menuOptions['can_toggle_drop_down']);
		}

		// Almost there - load the template and add to the template layers.
		loadTemplate(isset($this->menuOptions['template_name']) ? $this->menuOptions['template_name'] : 'GenericMenu');
		$this->menu_context['layer_name'] = (isset($this->menuOptions['layer_name']) ? $this->menuOptions['layer_name'] : 'generic_menu') . $this->menuOptions['menu_type'];
		Template_Layers::getInstance()->add($this->menu_context['layer_name']);

		// Set it all to context for template consumption
		$context['max_menu_id'] = $this->max_menu_id;
		$context['current_subaction'] = $this->current_subaction;
		$context['menu_data_' . $this->max_menu_id] = $this->menu_context;
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

		$menu_name = $menu_id === 'last' && isset($context['max_menu_id'], $context['menu_data_' . $context['max_menu_id']])
			? 'menu_data_' . $context['max_menu_id']
			: 'menu_data_' . $menu_id;

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
	 * If $selectedMenu ['controller'] is set, then it is a class, and $selectedMenu['function'] will be a method of it.
	 * If it is not set, then $selectedMenu ['function'] is simply a function to call.
	 *
	 * @param array|bool $selectedMenu
	 */
	public function callMenu($selectedMenu)
	{
		// Always be safe
		if (empty($selectedMenu) || empty($selectedMenu['function']))
		{
			Errors::instance()->fatal_lang_error('no_access', false);
		}

		// We use only selectedMenu ['function'] and selectedMenu ['controller'] if the latter is set.
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

	/**
	 * Retrieve easily the sole instance of this class.
	 *
	 * @return Menu
	 */
	public static function instance()
	{
		if (self::$instance === null)
			self::$instance = new Menu();

		return self::$instance;
	}
}
