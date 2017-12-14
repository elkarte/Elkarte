<?php

/**
 * This class contains a standard way of displaying side/drop down menus.
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * This file contains code covered by:
 * copyright: 2011 Simple Machines (http://www.simplemachines.org)
 * license:   BSD, See included LICENSE.TXT for terms and conditions.
 *
 * @version 1.1
 *
 */

declare(strict_types=1);

namespace ElkArte\Menu;

use HttpReq;

/**
 * This class implements a standard way of creating menus
 */
class Menu
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
	protected $current_area = '';

	/**
	 * The current subaction of the system
	 * @var string
	 */
	protected $current_subaction = '';

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
	 * Initial processing for the menu
	 */
	public function __construct(HttpReq $req = null)
	{
		global $context;

		// Access to post/get data
		$this->req = $req ?: HttpReq::instance();

		// Every menu gets a unique ID, these are shown in first in, first out order.
		$this->max_menu_id = ($context['max_menu_id'] ?? 0) + 1;

		// This will be all the data for this menu
		$this->menu_context = [];

		// This is necessary only in profile (at least for the core), but we do it always because it's easier
		$this->permission_set = !empty($context['user']['is_owner']) ? 'own' : 'any';

		// We may have a current subaction
		$this->current_subaction = $context['current_subaction'] ?? null;
	}

	/**
	 * Create a menu
	 *
	 * @return array
	 * @throws \Elk_Exception
	 */
	public function prepareMenu(): array
	{
		// Process the menu Options
		$this->processMenuOptions();

		// Check the menus urls
		$this->setBaseUrl();

		// Process the menu Data
		$this->processMenuData();

		// Check the menus urls
		$this->setActiveButtons();

		// Make sure we created some awesome sauce
		if (!$this->validateData())
		{
			throw new \Elk_Exception('no_access', false);
		}

		// Finally - return information on the selected item.
		return $this->include_data + [
			'current_action' => $this->menu_context['current_action'],
			'current_area' => $this->current_area,
			'current_section' => !empty($this->menu_context['current_section']) ? $this->menu_context['current_section'] : '',
			'current_subsection' => $this->current_subaction,
		];

	}

	/**
	 * Prepares tabs for the template.
	 *
	 * This should be called after the area is dispatched, because areas
	 * are usually in their own file. Those files, once dispatched to, hold
	 * some data for the tabs which must be specially combined with subaction
	 * data for everything to work properly.
	 *
	 * Seems complicated, yes.
	 */
	public function prepareTabData(): void
	{
		global $context;

		// Handy shortcut.
		$tab_context = &$context['menu_data_' . $this->max_menu_id]['tab_data'];

		// Tabs are really just subactions.
		$tab_context['tabs'] = array_replace_recursive($tab_context['tabs'], $this->menu_context['sections'][$this->menu_context['current_section']]['areas'][$this->current_area]['subsections']);

		// Has it been deemed selected?
		$tab_context = array_merge($tab_context, $tab_context['tabs'][$this->current_subaction]);
	}

	/**
	 * Performs a sanity check that a menu was created successfully
	 *
	 *   - If it fails to find valid data, will reset max_menu_id and any menu context created
	 *
	 * @return bool
	 */
	private function validateData(): bool
	{
		if (empty($this->menu_context['sections']))
		{
			return false;
		}

		// Check we had something - for sanity sake.
		return !empty($this->include_data);
	}

	/**
	 * Process the array of MenuOptions passed to the class
	 */
	protected function processMenuOptions(): void
	{
		global $context;

		// What is the general action of this menu i.e. $scripturl?action=XYZ.
		$this->menu_context['current_action'] = $this->menuOptions['action'] ?? $context['current_action'];

		// What is the current area selected?
		$this->current_area = $this->req->getQuery('area', 'trim|strval', $this->menuOptions['area'] ?? '');

		$this->buildAdditionalParams();
		$this->buildTemplateVars();
	}

	/**
	 * Build the menuOption additional parameters for use in the url
	 */
	private function buildAdditionalParams(): void
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
	protected function processMenuData(): void
	{
		// Now setup the context correctly.
		foreach ($this->menuData as $section_id => $section)
		{
			// Is this section enabled? and do they have permissions?
			if ($section->isEnabled() && $this->checkPermissions($section))
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
	 * If said item did not provide any permission to check, full
	 * unfettered access is assumed.
	 *
	 * The profile areas are a bit different in that each permission is
	 * divided into two sets: "own" for owner and "any" for everyone else.
	 *
	 * @param MenuItem $obj area or section being checked
	 *
	 * @return bool
	 */
	private function checkPermissions(MenuItem $obj): bool
	{
		if (!empty($obj->getPermission()))
		{
			// The profile menu has slightly different permissions
			if (is_array($obj->getPermission()) && isset($obj->getPermission()['own'], $obj->getPermission()['any']))
			{
				return allowedTo($obj->getPermission()[$this->permission_set]);
			}

			return allowedTo($obj->getPermission());
		}

		return true;
	}

	/**
	 * Checks if the area has a label or not
	 *
	 * @return bool
	 */
	private function areaHasLabel(string $area_id, MenuArea $area): bool
	{
		global $txt;

		return !empty($area->getLabel()) || isset($txt[$area_id]);
	}

	/**
	 * Main processing for creating the menu items for all sections
	 *
	 * @param string      $section_id
	 * @param MenuSection $section
	 */
	protected function processSectionAreas(string $section_id, MenuSection $section): void
	{
		// Now we cycle through the sections to pick the right area.
		foreach ($section->getAreas() as $area_id => $area)
		{
			// Is the area enabled, Does the user have permission and it has some form of a name
			if ($area->isEnabled() && $this->checkPermissions($area) && $this->areaHasLabel($area_id, $area))
			{
				// Make sure we have a valid current area
				$this->setFirstAreaCurrent($section_id, $area_id, $area);

				// If this is hidden from view don't do the rest.
				if (!$area->isHidden())
				{
					// First time this section?
					$this->setAreaContext($section_id, $area_id, $area);

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
	 *
	 * @param string   $section_id
	 * @param string   $area_id
	 * @param MenuArea $area
	 */
	private function checkCurrentSection(string $section_id, string $area_id, MenuArea $area): void
	{
		// Is this the current section?
		if ($this->current_area == $area_id && !$this->found_section)
		{
			$this->setAreaCurrent($section_id, $area_id, $area);

			// Only do this once, m'kay?
			$this->found_section = true;
		}
	}

	/**
	 * @param string   $section_id
	 * @param string   $area_id
	 * @param MenuArea $area
	 */
	private function setFirstAreaCurrent(string $section_id, string $area_id, MenuArea $area): void
	{
		// If we don't have an area then the first valid one is our choice.
		if (empty($this->current_area))
		{
			$this->setAreaCurrent($section_id, $area_id, $area);
		}
	}

	/**
	 * Simply sets the current area
	 *
	 * @param string   $section_id
	 * @param string   $area_id
	 * @param MenuArea $area
	 */
	private function setAreaCurrent(string $section_id, string $area_id, MenuArea $area): void
	{
		// Update the context if required - as we can have areas pretending to be others. ;)
		$this->menu_context['current_section'] = $section_id;
		$this->current_area = $area->getSelect() ?: $area_id;
		$this->include_data = $area->toArray();
	}

	/**
	 * @param MenuItem $obj
	 * @param integer  $idx
	 *
	 * @return string
	 */
	private function parseCounter(MenuItem $obj, int $idx): string
	{
		global $settings;

		$counter = '';
		if (isset($this->menuOptions['counters']) && !empty($this->menuOptions['counters'][$obj->getCounter()]))
		{
			$counter =
				sprintf($settings['menu_numeric_notice'][$idx], $this->menuOptions['counters'][$obj->getCounter()]);
		}

		return $counter;
	}

	/**
	 * Sets the various section ID items
	 *
	 * What it does:
	 *   - If the ID is not set, sets it and sets the section title
	 *   - Sets the section title
	 *
	 * @param string      $section_id
	 * @param MenuSection $section
	 */
	private function setSectionContext(string $section_id, MenuSection $section): void
	{
		global $txt;

		$this->menu_context['sections'][$section_id] = [
			'id' => $section_id,
			'label' => ($section->getLabel() ?: $txt[$section_id]) . $this->parseCounter($section, 0),
			'url' => $this->menu_context['base_url'] . $this->menu_context['extra_parameters'],
		];
	}

	/**
	 * Sets the various area items
	 *
	 * What it does:
	 *   - If the ID is not set, sets it and sets the area title
	 *   - Sets the area title
	 *
	 * @param string   $section_id
	 * @param string   $area_id
	 * @param MenuArea $area
	 */
	private function setAreaContext(string $section_id, string $area_id, MenuArea $area): void
	{
		global $txt, $settings;

		$this->menu_context['sections'][$section_id]['areas'][$area_id] = [
			'label' => ($area->getLabel() ?: $txt[$area_id]) . $this->parseCounter($area, 1),
		];
	}

	/**
	 * Set the URL for the menu item
	 *
	 * @param string   $section_id
	 * @param string   $area_id
	 * @param MenuArea $area
	 */
	private function setAreaUrl(string $section_id, string $area_id, MenuArea $area): void
	{
		$area->setUrl(
			$this->menu_context['sections'][$section_id]['areas'][$area_id]['url'] =
				$area->getUrl(
				) ?: $this->menu_context['base_url'] . ';area=' . $area_id . $this->menu_context['extra_parameters']
		);
	}

	/**
	 * Set the menu icon
	 *
	 * @param string   $section_id
	 * @param string   $area_id
	 * @param MenuArea $area
	 */
	private function setAreaIcon(string $section_id, string $area_id, MenuArea $area): void
	{
		global $context, $settings;

		// Work out where we should get our menu images from.
		$image_path = file_exists($settings['theme_dir'] . '/images/admin/change_menu.png')
			? $settings['images_url'] . '/admin'
			: $settings['default_images_url'] . '/admin';

		// Does this area have its own icon?
		if (!empty($area->getIcon()))
		{
			$this->menu_context['sections'][$section_id]['areas'][$area_id]['icon'] =
				'<img ' . (!empty($area->getClass()) ? 'class="' . $area->getClass(
					) . '" ' : 'style="background: none"') . ' src="' . $image_path . '/' . $area->getIcon(
				) . '" alt="" />&nbsp;&nbsp;';
		}
		else
		{
			$this->menu_context['sections'][$section_id]['areas'][$area_id]['icon'] = '';
		}
	}

	/**
	 * Processes all of the subsections for a menu item
	 *
	 * @param string   $section_id
	 * @param string   $area_id
	 * @param MenuArea $area
	 */
	protected function processAreaSubsections(string $section_id, string $area_id, MenuArea $area): void
	{
		// If there are subsections for this menu item
		if (!empty($area->getSubsections()))
		{
			$this->menu_context['sections'][$section_id]['areas'][$area_id]['subsections'] = [];
			$first_sa = '';

			// For each subsection process the options
			foreach ($area->getSubsections() as $sa => $sub)
			{
				if ($this->checkPermissions($sub) && $sub->isEnabled())
				{
					$this->menu_context['sections'][$section_id]['areas'][$area_id]['subsections'][$sa] = [
						'label' => $sub->getLabel() . $this->parseCounter($sub, 2),
					];

					$this->setSubsSectionUrl($section_id, $area_id, $sa, $sub);

					if ($this->current_area == $area_id)
					{
						if (empty($first_sa))
						{
							$first_sa = $sa;
						}
						$this->setCurrentSubSection($sa, $sub);
					}
				}
				else
				{
					// Mark it as disabled...
					$this->menu_context['sections'][$section_id]['areas'][$area_id]['subsections'][$sa]['disabled'] =
						true;
				}
			}

			// Is this the current subsection?
			if (empty($this->current_subaction))
			{
				$this->current_subaction = $first_sa;
			}
		}
	}

	/**
	 * @param string         $section_id
	 * @param string         $area_id
	 * @param string         $sa
	 * @param MenuSubsection $sub
	 */
	private function setSubsSectionUrl(string $section_id, string $area_id, string $sa, MenuSubsection $sub): void
	{
		$sub->setUrl(
			$this->menu_context['sections'][$section_id]['areas'][$area_id]['subsections'][$sa]['url'] =
				$sub->getUrl(
				) ?: $this->menu_context['base_url'] . ';area=' . $area_id . ';sa=' . $sa . $this->menu_context['extra_parameters']
		);
	}

	/**
	 * Set the current subsection
	 *
	 * @param string         $sa
	 * @param MenuSubsection $sub
	 */
	private function setCurrentSubSection(string $sa, MenuSubsection $sub): void
	{
		// Is this the current subsection?
		$sa_check = $this->req->getQuery('sa', 'trim', null);
		if (
			$sa_check == $sa
			|| in_array($sa_check, $sub->getActive(), true)
			|| empty($this->current_subaction) && $sub->isDefault()
		)
		{
			$this->current_subaction = $sa;
		}
	}

	/**
	 * Checks and updates base and section urls
	 */
	private function setBaseUrl(): void
	{
		global $scripturl;

		// Should we use a custom base url, or use the default?
		$this->menu_context['base_url'] =
			$this->menuOptions['base_url'] ?? $scripturl . '?action=' . $this->menu_context['current_action'];
	}

	/**
	 * Checks and updates base and section urls
	 */
	private function setActiveButtons(): void
	{
		// If there are sections quickly goes through all the sections to check if the base menu has an url
		if (!empty($this->menu_context['current_section']))
		{
			$this->menu_context['sections'][$this->menu_context['current_section']]['selected'] = true;
			$this->menu_context['sections'][$this->menu_context['current_section']]['areas'][$this->current_area]['selected'] =
				true;

			if (!empty($this->menu_context['sections'][$this->menu_context['current_section']]['areas'][$this->current_area]['subsections'][$this->current_subaction]))
			{
				$this->menu_context['sections'][$this->menu_context['current_section']]['areas'][$this->current_area]['subsections'][$this->current_subaction]['selected'] =
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
	public function addOptions(array $menuOptions): void
	{
		$this->menuOptions = array_merge($this->menuOptions, $menuOptions);
	}

	/**
	 * @param string      $id
	 * @param MenuSection $section
	 *
	 * @return $this
	 */
	public function addSection(string $id, MenuSection $section): Menu
	{
		$this->menuData[$id] = $section;

		return $this;
	}

	private function buildTemplateVars(): void
	{
		global $user_info, $options;

		if (empty($this->menuOptions['menu_type']))
		{
			$this->menuOptions['menu_type'] = empty($options['use_sidebar_menu']) ? 'dropdown' : 'sidebar';
		}
		$this->menuOptions['can_toggle_drop_down'] =
			!$user_info['is_guest'] || !empty($this->menuOptions['can_toggle_drop_down']);

		$this->menuOptions['template_name'] = $this->menuOptions['template_name'] ?? 'GenericMenu';
		$this->menuOptions['layer_name'] = ($this->menuOptions['layer_name'] ?? 'generic_menu') . '_' . $this->menuOptions['menu_type'];
	}

	/**
	 * Finalizes items so the computed menu can be used
	 *
	 * What it does:
	 *   - Sets the menu layer in the template stack
	 *   - Loads context with the computed menu context
	 *   - Sets current subaction and current max menu id
	 */
	public function setContext(): void
	{
		global $context;

		// Almost there - load the template and add to the template layers.
		theme()->getTemplates()->load($this->menuOptions['template_name']);
		theme()->getLayers()->add($this->menuOptions['layer_name']);

		// Set it all to context for template consumption
		$this->menu_context['layer_name'] = $this->menuOptions['layer_name'];
		$this->menu_context['can_toggle_drop_down'] = $this->menuOptions['can_toggle_drop_down'];
		$context['max_menu_id'] = $this->max_menu_id;
		$context['current_subaction'] = $this->current_subaction;
		$this->menu_context['current_subsection'] = $this->current_subaction;
		$this->menu_context['current_area'] = $this->current_area;
		$context['menu_data_' . $this->max_menu_id] = $this->menu_context;
	}

	/**
	 * Delete a menu.
	 *
	 * Checks to see if this menu been loaded into context
	 * and, if so, resets $context['max_menu_id'] back to the
	 * last known menu (if any) and remove the template layer
	 * if there aren't any other known menus.
	 */
	public function destroy(): void
	{
		global $context;

		// Has this menu been loaded into context?
		if (isset($context[$menu_name = 'menu_data_' . $this->max_menu_id]))
		{
			// Decrement the pointer if this is the final menu in the series.
			if ($this->max_menu_id == $context['max_menu_id'])
			{
				$context['max_menu_id'] = max($context['max_menu_id'] - 1, 0);
			}

			// Remove the template layer if this was the only menu left.
			if ($context['max_menu_id'] == 0)
			{
				theme()->getLayers()->remove($context[$menu_name]['layer_name']);
			}

			unset($context[$menu_name]);
		}
	}
}
