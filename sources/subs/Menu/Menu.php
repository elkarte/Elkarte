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
 * license:        BSD, See included LICENSE.TXT for terms and conditions.
 *
 * @version   1.1
 *
 */

namespace ElkArte\Menu;

/**
 * This class implements a standard way of creating menus
 */
Class Menu
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
	protected $menu_context = [];

	/**
	 * Used for profile menu for own / any
	 * @var string
	 */
	protected $permission_set;

	/**
	 * If we found the menu item selected
	 * @var bool
	 */
	protected $found_section = false;

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
	private $include_data = [];

	/**
	 * Unique menu number
	 * @var int
	 */
	private $max_menu_id;

	/**
	 * Holds menu options set by AddOptions
	 * @var array
	 */
	public $menuOptions = [];

	/**
	 * Holds menu definition structure set by AddAreas
	 * @var array
	 */
	public $menuData = [];

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
		$this->req = \HttpReq::instance();

		// Work out where we should get our menu images from.
		$context['menu_image_path'] = file_exists($settings['theme_dir'] . '/images/admin/change_menu.png')
			? $settings['images_url'] . '/admin'
			: $settings['default_images_url'] . '/admin';

		// Every menu gets a unique ID, these are shown in first in, first out order.
		$this->max_menu_id = isset($context['max_menu_id']) ? $context['max_menu_id']++ : 1;

		// This will be all the data for this menu
		$this->menu_context = [];

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

		// Check the menus urls
		$this->setBaseUrl();

		// Process the menu Data
		$this->processMenuData();

		// Set the current
		$this->determineCurrentAction();

		// Check the menus urls
		$this->checkBaseUrl();

		// Make sure we created some awesome sauce
		if (!$this->validateData())
		{
			return false;
		}

		// Finally - return information on the selected item.
		$this->include_data += [
			'current_action' => $this->menu_context['current_action'],
			'current_area' => $this->menu_context['current_area'],
			'current_section' => !empty($this->menu_context['current_section']) ? $this->menu_context['current_section'] : '',
			'current_subsection' => !empty($this->menu_context['current_subsection']) ? $this->menu_context['current_subsection'] : '',
		];

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
			$this->menu_context = [];
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
			call_integration_hook(
				'integrate_' . $this->menuOptions['hook'] . '_areas',
				[&$this->menuData, &$this->menuOptions]
			);
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
		global $settings;

		// Now setup the context correctly.
		foreach ($this->menuData as $section_id => $section)
		{
			// Is this section enabled? and do they have permissions?
			if ($section->enabled && $this->checkPermissions($section))
			{
				$this->setSectionContext($section_id, $section);

				// Process this menu section
				$this->processSectionAreas($section_id, $section);
			}
		}
	}

	/**
	 * Determines if the user has the permissions to access the section/area
	 *
	 * @param array $area area or section being checked
	 *
	 * @return bool
	 */
	private function checkPermissions($area)
	{
		if (!empty($area->permission))
		{
			// The profile menu has slightly different permissions
			if (is_array($area->permission) && isset($area->permission['own'], $area->permission['any']))
			{
				return allowedTo($area->permission[$this->permission_set]);
			}

			return allowedTo($area->permission);
		}

		return true;
	}

	/**
	 * Checks if the area has a label or not
	 *
	 * @return bool
	 */
	private function areaHasLabel($area_id, $area)
	{
		global $txt;

		return !empty($area->label) || isset($txt[$area_id]);
	}

	/**
	 * Main processing for creating the menu items for all sections
	 */
	protected function processSectionAreas($section_id, $section)
	{
		// Now we cycle through the sections to pick the right area.
		foreach ($section->areas as $area_id => $area)
		{
			// Is the area enabled, Does the user have permission and it has some form of a name
			if ($area->enabled && $this->checkPermissions($area) && $this->areaHasLabel($area_id, $area))
			{
				// Make sure we have a valid current area
				$this->setAreaCurrent($area_id, $area);

				// If this is hidden from view don't do the rest.
				if (empty($area->hidden))
				{
					// First time this section?
					$this->setAreaContext($section_id, $section, $area_id, $area);

					// Maybe a custom url
					$this->setAreaUrl($section_id, $area_id, $area);

					// Even a little icon
					$this->setAreaIcon($section_id, $area_id, $area);

					// Did it have subsections?
					$this->processAreaSubsections($section_id, $area_id, $area);
				}

				// Is this the current section?
				$this->checkCurrentSection($section_id, $area_id, $area);
			}
		}
	}

	/**
	 * Checks the menu item to see if it is the currently selected one
	 */
	private function checkCurrentSection($section_id, $area_id, $area)
	{
		// Is this the current section?
		if ($this->menu_context['current_area'] == $area_id && !$this->found_section)
		{
			// Only do this once, m'kay?
			$this->found_section = true;

			// Update the context if required - as we can have areas pretending to be others. ;)
			$this->menu_context['current_section'] = $section_id;

			$this->menu_context['current_area'] = $area->select ?: $area_id;

			// This will be the data we return.
			$this->include_data = get_object_vars($area);
		} // Make sure we have something in case it's an invalid area.
		elseif (!$this->found_section && empty($this->include_data))
		{
			$this->menu_context['current_section'] = $section_id;
			$this->backup_area = $area->select ?: $area_id;
			$this->include_data = get_object_vars($area);
		}
	}

	/**
	 * Simply sets the current area
	 */
	private function setAreaCurrent($area_id, $area)
	{
		// If we don't have an area then the first valid one is our choice.
		if (!isset($this->menu_context['current_area']))
		{
			$this->menu_context['current_area'] = $area_id;
			$this->include_data = get_object_vars($area);
		}
	}

	/**
	 * Sets the various section ID items
	 *
	 * What it does:
	 *   - If the ID is not set, sets it and sets the section title
	 *   - Sets the section title
	 */
	private function setSectionContext($section_id, $section)
	{
		global $txt, $settings;

		$counter = '';
		if (isset($this->menuOptions['counters'], $section->counter) && !empty($this->menuOptions['counters'][$section->counter]))
		{
			$counter = sprintf($settings['menu_numeric_notice'][0], $this->menuOptions['counters'][$section->counter]);
		}

		$this->menu_context['sections'][$section_id] = [
			'id' => $section_id,
			'title' => ($section->title ?: $txt[$section_id]) . $counter,
			'url' => $this->menu_context['base_url'] . $this->menu_context['extra_parameters'],
		];
	}

	/**
	 * Sets the various area items
	 *
	 * What it does:
	 *   - If the ID is not set, sets it and sets the area title
	 *   - Sets the area title
	 */
	private function setAreaContext($section_id, $section, $area_id, $area)
	{
		global $txt, $settings;

		$this->menu_context['sections'][$section_id]['areas'][$area_id] = [
			'label' => $area->label ?: $txt[$area_id],
		];

		if (isset($this->menuOptions['counters'], $area->counter) && !empty($this->menuOptions['counters'][$area->counter]))
		{
			$this->menu_context['sections'][$section_id]['areas'][$area_id]['label'] .=
				sprintf($settings['menu_numeric_notice'][1], $this->menuOptions['counters'][$area->counter]);
		}
	}

	/**
	 * Set the URL for the menu item
	 */
	private function setAreaUrl($section_id, $area_id, $area)
	{
		$area->url = $this->menu_context['sections'][$section_id]['areas'][$area_id]['url'] =
			$area->custom_url ?: $this->menu_context['base_url'] . ';area=' . $area_id . $this->menu_context['extra_parameters'];
	}

	/**
	 * Set the menu icon
	 */
	private function setAreaIcon($section_id, $area_id, $area)
	{
		global $context;

		// Does this area have its own icon?
		if (isset($area->icon))
		{
			$this->menu_context['sections'][$section_id]['areas'][$area_id]['icon'] =
				'<img ' . (isset($area->class) ? 'class="' . $area->class . '" ' : 'style="background: none"') . ' src="' . $context['menu_image_path'] . '/' . $area->icon . '" alt="" />&nbsp;&nbsp;';
		}
		else
		{
			$this->menu_context['sections'][$section_id]['areas'][$area_id]['icon'] = '';
		}
	}

	/**
	 * Processes all of the subsections for a menu item
	 */
	protected function processAreaSubsections($section_id, $area_id, $area)
	{
		global $settings, $context;

		// If there are subsections for this menu item
		if (!empty($area->subsections))
		{
			$this->menu_context['sections'][$section_id]['areas'][$area_id]['subsections'] = [];
			$first_sa = null;
			$last_sa = null;

			// For each subsection process the options
			foreach ($area->subsections as $sa => $sub)
			{
				if ($this->checkPermissions($sub) && $sub->enabled)
				{
					if ($first_sa === null)
					{
						$first_sa = $sa;
					}

					$this->menu_context['sections'][$section_id]['areas'][$area_id]['subsections'][$sa] =
						['label' => $sub->label];
					if (isset($this->menuOptions['counters'], $sub->counter) && !empty($this->menuOptions['counters'][$sub->counter]))
					{
						$this->menu_context['sections'][$section_id]['areas'][$area_id]['subsections'][$sa]['label'] .=
							sprintf($settings['menu_numeric_notice'][2], $this->menuOptions['counters'][$sub->counter]);
					}

					$this->setSubsSectionUrl($section_id, $area_id, $sa, $sub);

					// A bit complicated - but is this set?
					$first_sa = $this->setCurrentSubSection($sa, $first_sa, $area_id, $sub);

					// Let's assume this is the last, for now.
					$last_sa = $sa;
				} // Mark it as disabled...
				else
				{
					$this->menu_context['sections'][$section_id]['areas'][$area_id]['subsections'][$sa]['disabled'] =
						true;
				}
			}
		}
	}

	/**
	 * Does the subsection have a custom url ?
	 */
	private function setSubsSectionUrl($section_id, $area_id, $sa, $sub)
	{
		$sub->url = $this->menu_context['sections'][$section_id]['areas'][$area_id]['subsections'][$sa]['url'] =
			$sub->url ?: $this->menu_context['base_url'] . ';area=' . $area_id . ';sa=' . $sa . $this->menu_context['extra_parameters'];
	}

	/**
	 * Set the current subsection
	 *
	 * @param $first_sa
	 *
	 * @return mixed
	 */
	private function setCurrentSubSection($sa, $first_sa, $area_id, $sub)
	{
		if ($this->menu_context['current_area'] == $area_id)
		{
			// Save which is the first...
			if (empty($first_sa))
			{
				$first_sa = $sa;
			}

			// Is this the current subsection?
			$sa_check = $this->req->getQuery('sa', 'trim', null);
			if (
				$sa_check == $sa
				|| (isset($sa_check) && in_array($sa_check, $sub->active, true))
				|| (!isset($this->menu_context['current_subsection']) && ($sub->default || $first_sa == $sa))
			)
			{
				$this->menu_context['current_subsection'] = $sa;
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
		if (isset($this->menu_context['current_subsection']))
		{
			$this->current_subaction = $this->menu_context['current_subsection'];
		}
	}

	/**
	 * Checks and updates base and section urls
	 */
	private function setBaseUrl()
	{
		global $scripturl;

		// Should we use a custom base url, or use the default?
		$this->menu_context['base_url'] = isset($this->menuOptions['base_url'])
			? $this->menuOptions['base_url']
			: $scripturl . '?action=' . $this->menu_context['current_action'];
	}

	/**
	 * Checks and updates base and section urls
	 */
	private function checkBaseUrl()
	{
		// If there are sections quickly goes through all the sections to check if the base menu has an url
		if (!empty($this->menu_context['current_section']))
		{
			$this->menu_context['sections'][$this->menu_context['current_section']]['selected'] = true;
			$this->menu_context['sections'][$this->menu_context['current_section']]['areas'][$this->menu_context['current_area']]['selected'] =
				true;

			if (!empty($this->menu_context['sections'][$this->menu_context['current_section']]['areas'][$this->menu_context['current_area']]['subsections'][$this->current_subaction]))
			{
				$this->menu_context['sections'][$this->menu_context['current_section']]['areas'][$this->menu_context['current_area']]['subsections'][$this->current_subaction]['selected'] =
					true;
			}
		}
	}

	/**
	 * Add the base menu options for this menu
	 *
	 * @param array $menuOptions an array of options that can be used to override some default behaviours.
	 *                           It can accept the following indexes:
	 *                           - action                    => overrides the default action
	 *                           - current_area              => overrides the current area
	 *                           - extra_url_parameters      => an array or pairs or parameters to be added to the url
	 *                           - disable_url_session_check => (boolean) if true the session var/id are omitted from
	 *                           the url
	 *                           - base_url                  => an alternative base url
	 *                           - menu_type                 => alternative menu types?
	 *                           - can_toggle_drop_down      => (boolean) if the menu can "toggle"
	 *                           - template_name             => an alternative template to load (instead of Generic)
	 *                           - layer_name                => alternative layer name for the menu
	 *                           - hook                      => hook name to call integrate_ . 'hook name' . '_areas'
	 *                           - default_include_dir       => directory to include for function support
	 */
	public function addOptions($menuOptions)
	{
		$this->menuOptions = array_merge($this->menuOptions, $menuOptions);
	}

	/**
	 * Add the data the is used to build the menu
	 *
	 * @param array $menuData the menu array
	 *                        Possible indexes:
	 *                        Menu name with named indexes as follows:
	 *                        - string $title       => Section title
	 *                        - bool $enabled       => Is the section enabled / shown
	 *                        - array $areas        => Array of areas within this menu section, see below
	 *                        - array $permission   => Permission required to access the whole section
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
	 */
	public function addAreas($menuData = [])
	{
		$this->menuData = array_replace_recursive($this->menuData, $menuData);
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
			$this->menu_context['can_toggle_drop_down'] =
				!$user_info['is_guest'] && isset($settings['theme_version']) && $settings['theme_version'] >= 2.0;
		}
		else
		{
			$this->menu_context['can_toggle_drop_down'] = !empty($this->menuOptions['can_toggle_drop_down']);
		}

		// Almost there - load the template and add to the template layers.
		loadTemplate(isset($this->menuOptions['template_name']) ? $this->menuOptions['template_name'] : 'GenericMenu');
		$this->menu_context['layer_name'] =
			(isset($this->menuOptions['layer_name']) ? $this->menuOptions['layer_name'] : 'generic_menu') . $this->menuOptions['menu_type'];
		theme()->getLayers()->add($this->menu_context['layer_name']);

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

		$menu_name =
			$menu_id === 'last' && isset($context['max_menu_id'], $context['menu_data_' . $context['max_menu_id']])
				? 'menu_data_' . $context['max_menu_id']
				: 'menu_data_' . $menu_id;

		if (!isset($context[$menu_name]))
		{
			return false;
		}

		theme()->getLayers()->remove($context[$menu_name]['layer_name']);

		unset($context[$menu_name]);
	}
}
