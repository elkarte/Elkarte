<?php

/**
 * This class contains a standard way of displaying side/drop down menus.
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

namespace ElkArte\Menu;

use ElkArte\Exceptions\Exception;
use ElkArte\HttpReq;
use ElkArte\User;

/**
 * Class Menu
 *
 * This class implements a standard way of creating menus
 *
 * @package ElkArte\Menu
 */
class Menu
{
	/** @var HttpReq */
	protected $req;

	/** @var array Will hold the created $context */
	public $menuContext = [];

	/** @var string Used for profile menu for own / any */
	public $permissionSet;

	/** @var bool If we found the menu item selected */
	public $foundSection = false;

	/** @var string Current area */
	public $currentArea = '';

	/** @var null|string The current subaction of the system */
	public $currentSubaction = '';

	/** @var array Will hold the selected menu data that is returned to the caller */
	private $includeData = [];

	/** @var int Unique menu number */
	private $maxMenuId = 0;

	/** @var MenuOptions  Holds menu options */
	private $menuOptions;

	/** @var array  Holds menu definition structure set by addSection */
	private $menuData = [];

	/** @var array Holds the first accessible menu section/area if any */
	private $firstAreaCurrent = [];

	/**
	 * Initial processing for the menu
	 *
	 * @param HttpReq|null $req
	 */
	public function __construct($req = null)
	{
		global $context;

		// Access to post/get data
		$this->req = $req ?: HttpReq::instance();

		// Every menu gets a unique ID, these are shown in first in, first out order.
		$this->maxMenuId = ($context['max_menu_id'] ?? 0) + 1;

		// This will be all the data for this menu
		$this->menuContext = [];

		// This is necessary only in profile (at least for the core), but we do it always because it's easier
		$this->permissionSet = !empty($context['user']['is_owner']) ? 'own' : 'any';

		// We may have a current subaction
		$this->currentSubaction = $context['current_subaction'] ?? null;

		// Would you like fries with that?
		$this->menuOptions = new MenuOptions();
	}

	/**
	 * Add the base menu options for this menu
	 *
	 * @param array $menuOptions an array of options that can be used to override some default
	 *                           behaviours. See MenuOptions for details.
	 */
	public function addOptions(array $menuOptions)
	{
		$this->menuOptions = MenuOptions::buildFromArray($menuOptions);

		return $this;
	}

	/**
	 * Parses the supplied menu data in to the relevant menu array structure
	 *
	 * @param array $menuData the menu array
	 */
	public function addMenuData($menuData)
	{
		// Process each menu area's section/subsections
		foreach ($menuData as $section_id => $section)
		{
			// $section['areas'] are the items under a menu button
			$newAreas = ['areas' => []];
			foreach ($section['areas'] as $area_id => $area)
			{
				// subsections are deeper menus inside of a area (3rd level menu)
				$newSubsections = ['subsections' => []];
				if (!empty($area['subsections']))
				{
					foreach ($area['subsections'] as $sa => $sub)
					{
						$newSubsections['subsections'][$sa] = MenuSubsection::buildFromArray($sub, $sa);
						unset($area['subsections']);
					}
				}

				$newAreas['areas'][$area_id] = MenuArea::buildFromArray($area + $newSubsections);
			}

			// Finally, the menu button
			unset($section['areas']);
			$this->addSection($section_id, MenuSection::buildFromArray($section + $newAreas));
		}

		return $this;
	}

	/**
	 * Adds the built out menu sections/subsections to the menu
	 *
	 * @param string $id
	 * @param MenuSection $section
	 *
	 * @return $this
	 */
	public function addSection($id, $section)
	{
		$this->menuData[$id] = $section;

		return $this;
	}

	/**
	 * Adds sections/subsections to the existing menu.  Generally used by addons via hook
	 *
	 * @param array $section_data
	 * @param string $location optional menu item after which you want to add the section
	 *
	 * @return $this
	 */
	public function insertSection($section_data, $location = '')
	{
		foreach ($section_data as $section_id => $section)
		{
			foreach ($section as $area_id => $area)
			{
				$newSubsections = ['subsections' => []];
				if (!empty($area['subsections']))
				{
					foreach ($area['subsections'] as $sa => $sub)
					{
						$newSubsections['subsections'][$sa] = MenuSubsection::buildFromArray($sub, $sa);
					}
				}

				/** @var \ElkArte\Menu\MenuSection $section */
				$section = $this->menuData[$section_id];
				$section->insertArea($area_id, $location, MenuArea::buildFromArray($area + $newSubsections));
			}
		}

		return $this;
	}

	/**
	 * Create a menu.  Expects that addOptions and addMenuData (or equivalent) have been called
	 *
	 * @throws \ElkArte\Exceptions\Exception
	 */
	public function prepareMenu()
	{
		// If options set a hook, give it call
		$this->callHook();

		// Build URLs first.
		$this->menuContext['base_url'] = $this->menuOptions->getBaseUrl();
		$this->menuContext['current_action'] = $this->menuOptions->getAction();
		$this->currentArea = !empty($this->menuOptions->getCurrentArea()) ? $this->menuOptions->getCurrentArea() : $this->req->getQuery('area', 'trim|strval', '');
		$this->menuContext['extra_parameters'] = $this->menuOptions->buildAdditionalParams();

		// Process the loopy menu data.
		$this->processMenuData();

		// Here is some activity.
		$this->setActiveButtons();

		// Make sure we created some awesome sauce.
		if (empty($this->includeData))
		{
			// Give a guest a boot to the login screen
			if (User::$info->is_guest)
			{
				is_not_guest();
			}

			// Users get a slap in the face, No valid areas -- reject!
			throw new Exception('no_access', false);
		}

		// For consistency with the past, clean up the returned array
		$this->includeData = array_filter($this->includeData, static function ($value) {
			return !is_null($value) && $value !== '';
		});

		// Set information on the selected item.
		$this->includeData += [
			'current_action' => $this->menuContext['current_action'],
			'current_area' => $this->currentArea,
			'current_section' => !empty($this->menuContext['current_section']) ? $this->menuContext['current_section'] : '',
			'current_subsection' => $this->currentSubaction,
		];

		return $this;
	}

	/**
	 * Return the computed include data array
	 *
	 * @return array
	 */
	public function getIncludeData()
	{
		return $this->includeData;
	}

	/**
	 * Allow extending *any* menu with a single hook
	 *
	 * - Call hook name defined in options as integrate_supplied name_areas
	 * - example, integrate_profile_areas, integrate_admin_areas
	 * - Hooks are passed $this
	 */
	public function callHook()
	{
		// Allow to extend *any* menu with a single hook
		if ($this->menuOptions->getHook())
		{
			call_integration_hook($this->menuOptions->getHook(), array($this));
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
		foreach ($this->menuData as $sectionId => $section)
		{
			// Is this section enabled? and do they have permissions?
			if ($section->isEnabled() && $this->checkPermissions($section))
			{
				$this->setSectionContext($sectionId, $section);

				// Process this menu section
				$this->processSectionAreas($sectionId, $section);

				// Validate is created *something*
				$this->validateSection($sectionId);
			}
		}

		// If we did not find a current area the use the first valid one found, if any
		if (!$this->foundSection && !empty($this->firstAreaCurrent))
		{
			$this->setAreaCurrent($this->firstAreaCurrent[0], $this->firstAreaCurrent[1], $this->firstAreaCurrent[2]);
		}
	}

	/**
	 * Removes a generated section that has no areas and no URL, aka empty.  This can happen
	 * due to conflicting permissions.
	 *
	 * @param string $sectionId
	 */
	private function validateSection($sectionId)
	{
		if (empty($this->menuContext['sections'][$sectionId]['areas'])
			&& empty($this->menuContext['sections'][$sectionId]['url']))
		{
			unset($this->menuContext['sections'][$sectionId]);
		}
	}

	/**
	 * Determines if the user has the permissions to access the section/area
	 *
	 * If said item did not provide any permission to check, fully
	 * unfettered access is assumed.
	 *
	 * The profile areas are a bit different in that each permission is
	 * divided into two sets: "own" for owner and "any" for everyone else.
	 *
	 * @param MenuItem $obj area or section being checked
	 *
	 * @return bool
	 */
	private function checkPermissions($obj)
	{
		if (!empty($obj->getPermission()))
		{
			// The profile menu has slightly different permissions
			if (isset($obj->getPermission()['own'], $obj->getPermission()['any']))
			{
				return !empty($obj->getPermission()[$this->permissionSet]) && allowedTo($obj->getPermission()[$this->permissionSet]);
			}

			return allowedTo($obj->getPermission());
		}

		return true;
	}

	/**
	 * Sets the various section ID items
	 *
	 * What it does:
	 *   - If the ID is not set, sets it and sets the section title
	 *   - Sets the section title
	 *
	 * @param string $sectionId
	 * @param MenuSection $section
	 */
	private function setSectionContext($sectionId, $section)
	{
		global $txt;

		$this->menuContext['sections'][$sectionId] = [
			'id' => $sectionId,
			'label' => ($section->getLabel() ?: $txt[$sectionId]) . $this->parseCounter($section, 0),
			'url' => '',
		];
	}

	/**
	 * If the menu has that little notification count, that's right this sets its value
	 *
	 * @param MenuItem $obj
	 * @param int $idx
	 *
	 * @return string
	 */
	private function parseCounter($obj, $idx)
	{
		global $settings;

		$counter = '';
		if (!empty($this->menuOptions->getCounters()[$obj->getCounter()]))
		{
			$counter = sprintf(
				$settings['menu_numeric_notice'][$idx],
				$this->menuOptions->getCounters()[$obj->getCounter()]
			);
		}

		return $counter;
	}

	/**
	 * Main processing for creating the menu items for all sections
	 *
	 * @param string $sectionId
	 * @param MenuSection $section
	 */
	protected function processSectionAreas($sectionId, $section)
	{
		// Now we cycle through the sections to pick the right area.
		foreach ($section->getAreas() as $areaId => $area)
		{
			// Is the area enabled, Does the user have permission and it has some form of a name
			if ($area->isEnabled() && $this->checkPermissions($area) && $this->areaHasLabel($areaId, $area))
			{
				// Make sure we have a valid current area
				$this->setFirstAreaCurrent($sectionId, $areaId, $area);

				// If this is hidden from view don't do the rest.
				if (!$area->isHidden())
				{
					// First time this section?
					$this->setAreaContext($sectionId, $areaId, $area);

					// Maybe a custom url
					$this->setAreaUrl($sectionId, $areaId, $area);

					// Even a little icon
					$this->setAreaIcon($sectionId, $areaId, $area);

					// Did it have subsections?
					$this->processAreaSubsections($sectionId, $areaId, $area);
				}

				// Is this the current section?
				$this->checkCurrentSection($sectionId, $areaId, $area);
			}
		}

		// Now that we have valid section areas, set the section url
		$this->setSectionUrl($sectionId);
	}

	/**
	 * Checks if the area has a label or not
	 *
	 * @param string $areaId
	 * @param MenuArea $area
	 *
	 * @return bool
	 */
	private function areaHasLabel($areaId, $area)
	{
		global $txt;

		return !empty($area->getLabel()) || isset($txt[$areaId]);
	}

	/**
	 * Set the current area, or pick it for them
	 *
	 * @param string $sectionId
	 * @param string $areaId
	 * @param MenuArea $area
	 */
	private function setFirstAreaCurrent($sectionId, $areaId, $area)
	{
		// If an area was not directly specified, or wrongly specified, this first valid one is our choice.
		if (empty($this->firstAreaCurrent))
		{
			$this->firstAreaCurrent = [$sectionId, $areaId, $area];
		}
	}

	/**
	 * Simply sets the current area
	 *
	 * @param string $sectionId
	 * @param string $areaId
	 * @param MenuArea $area
	 */
	private function setAreaCurrent($sectionId, $areaId, $area)
	{
		// Update the context if required - as we can have areas pretending to be others. ;)
		$this->menuContext['current_section'] = $sectionId;
		$this->currentArea = $area->getSelect() ?: $areaId;
		$this->includeData = $area->toArray($area);
	}

	/**
	 * Sets the various area items
	 *
	 * What it does:
	 *   - If the ID is not set, sets it and sets the area title
	 *   - Sets the area title
	 *
	 * @param string $sectionId
	 * @param string $areaId
	 * @param MenuArea $area
	 */
	private function setAreaContext($sectionId, $areaId, $area)
	{
		global $txt;

		$this->menuContext['sections'][$sectionId]['areas'][$areaId] = [
			'label' => ($area->getLabel() ?: $txt[$areaId]) . $this->parseCounter($area, 1),
		];
	}

	/**
	 * Set the URL for the menu item
	 *
	 * @param string $sectionId
	 * @param string $areaId
	 * @param MenuArea $area
	 */
	private function setAreaUrl($sectionId, $areaId, $area)
	{
		$area->setUrl(
			$this->menuContext['sections'][$sectionId]['areas'][$areaId]['url'] =
				($area->getUrl() ?: $this->menuContext['base_url'] . ';area=' . $areaId) . $this->menuContext['extra_parameters']
		);
	}

	/**
	 * Set the menu icon from a class name if using pseudo elements
	 * of class and icon if using that method
	 *
	 * @param string $sectionId
	 * @param string $areaId
	 * @param MenuArea $area
	 */
	private function setAreaIcon($sectionId, $areaId, $area)
	{
		global $settings;

		// Work out where we should get our menu images from.
		$imagePath = file_exists($settings['theme_dir'] . '/images/admin/transparent.png')
			? $settings['images_url'] . '/admin'
			: $settings['default_images_url'] . '/admin';

		// Does this area even have an icon?
		if (empty($area->getIcon()) && empty($area->getClass()))
		{
			$this->menuContext['sections'][$sectionId]['areas'][$areaId]['icon'] = '';
			return;
		}

		// Perhaps a png
		if (!empty($area->getIcon()))
		{
			$this->menuContext['sections'][$sectionId]['areas'][$areaId]['icon'] =
				'<img ' . (!empty($area->getClass()) ? 'class="' . $area->getClass() . '"' : 'style="background: none"') . ' src="' . $imagePath . '/' . $area->getIcon() . '" alt="" />';
			return;
		}

		$this->menuContext['sections'][$sectionId]['areas'][$areaId]['icon'] =
			'<i class="' . (!empty($area->getClass()) ? 'icon ' . $area->getClass() . '"' : '') . '></i>';
	}

	/**
	 * Processes all subsections for a menu item
	 *
	 * @param string $sectionId
	 * @param string $areaId
	 * @param MenuArea $area
	 */
	protected function processAreaSubsections($sectionId, $areaId, $area)
	{
		$this->menuContext['sections'][$sectionId]['areas'][$areaId]['subsections'] = [];

		// Clear out ones not enabled or accessible
		$subSections = array_filter(
			$area->getSubsections(),
			function ($sub) {
				return $sub->isEnabled() && $this->checkPermissions($sub);
			}
		);

		// For each subsection process the options
		foreach ($subSections as $subId => $sub)
		{
			$this->menuContext['sections'][$sectionId]['areas'][$areaId]['subsections'][$subId] = [
				'label' => $sub->getLabel() . $this->parseCounter($sub, 2),
			];

			$this->setSubsSectionUrl($sectionId, $areaId, $subId, $sub);

			if ($this->currentArea === $areaId)
			{
				$this->setCurrentSubSection($subId, $sub);
			}
		}

		$this->setDefaultSubSection($areaId, $subSections);
	}

	/**
	 * Set subsection url/click location
	 *
	 * @param string $sectionId
	 * @param string $areaId
	 * @param string $subId
	 * @param MenuSubsection $sub
	 */
	private function setSubsSectionUrl($sectionId, $areaId, $subId, $sub)
	{
		$sub->setUrl(
			$this->menuContext['sections'][$sectionId]['areas'][$areaId]['subsections'][$subId]['url'] =
				$sub->getUrl() ?: $this->menuContext['base_url'] . ';area=' . $areaId . ';sa=' . $subId . $this->menuContext['extra_parameters']
		);
	}

	/**
	 * Set the current subsection
	 *
	 * @param string $subId
	 * @param MenuSubsection $sub
	 */
	private function setCurrentSubSection($subId, $sub)
	{
		// Is this the current subsection?
		$subIdCheck = $this->req->getQuery('sa', 'trim', null);
		if ($subIdCheck === $subId
			|| (empty($this->currentSubaction) && $sub->isDefault())
			|| in_array($subIdCheck, $sub->getActive(), true)
		)
		{
			$this->currentSubaction = $subId;
		}
	}

	/**
	 * Ensures that the current subsection is set.
	 *
	 * @param string $areaId
	 * @param array $subSections
	 */
	private function setDefaultSubSection($areaId, $subSections)
	{
		if ($this->currentArea === $areaId && empty($this->currentSubaction))
		{
			$this->currentSubaction = key($subSections) ?? '';
		}
	}

	/**
	 * Checks the menu item to see if it is the one specified
	 *
	 * @param string $sectionId
	 * @param string $areaId
	 * @param MenuArea $area
	 */
	private function checkCurrentSection($sectionId, $areaId, $area)
	{
		// Is this the current selection?
		if ($this->currentArea === $areaId && !$this->foundSection)
		{
			$this->setAreaCurrent($sectionId, $areaId, $area);

			// Only do this once, m'kay?
			$this->foundSection = true;
		}
	}

	/**
	 * The top level section gets its url from the first valid area under it.  Its
	 * done here to avoid setting it to an invalid area.
	 *
	 * @param string $sectionId
	 */
	private function setSectionUrl($sectionId)
	{
		if (!empty($this->menuContext['sections'][$sectionId]['areas']))
		{
			$firstAreaId = key($this->menuContext['sections'][$sectionId]['areas']);

			$this->menuContext['sections'][$sectionId]['url'] =
				$this->menuContext['sections'][$sectionId]['areas'][$firstAreaId]['url'];
		}
	}

	/**
	 * Checks and updates base and section urls
	 */
	private function setActiveButtons()
	{
		// If there are sections quickly goes through all the sections to check if the base menu has an url
		if (!empty($this->menuContext['current_section']))
		{
			$this->menuContext['sections'][$this->menuContext['current_section']]['selected'] = true;
			$this->menuContext['sections'][$this->menuContext['current_section']]['areas'][$this->currentArea]['selected'] = true;

			if (!empty($this->menuContext['sections'][$this->menuContext['current_section']]['areas'][$this->currentArea]['subsections'][$this->currentSubaction]))
			{
				$this->menuContext['sections'][$this->menuContext['current_section']]['areas'][$this->currentArea]['subsections'][$this->currentSubaction]['selected'] = true;
			}
		}
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
		global $context;

		// Almost there - load the template and add to the template layers.
		theme()->getTemplates()->load($this->menuOptions->getTemplateName());
		theme()->getLayers()->add($this->menuOptions->getLayerName());

		// Set it all to context for template consumption
		$this->menuContext['layer_name'] = $this->menuOptions->getLayerName();
		$this->menuContext['can_toggle_drop_down'] = $this->menuOptions->isDropDownToggleable();

		// Keep track of where we are
		$this->menuContext['current_area'] = $this->currentArea;
		$context['current_subaction'] = $this->currentSubaction;
		$this->menuContext['current_subsection'] = $this->currentSubaction;

		// Make a note of the Unique ID for this menu.
		$context['max_menu_id'] = $this->maxMenuId;
		$context['menu_data_' . $this->maxMenuId] = $this->menuContext;
		$context['menu_data_' . $this->maxMenuId]['object'] = $this;

		return $this;
	}

	/**
	 * Prepares tabs for the template, specifically for template_generic_menu_tabs
	 *
	 * This should be called after the menu area is dispatched, because areas are usually in their
	 * own controller file. Those files, once dispatched to, hold data for the tabs (descriptions,
	 * disabled, extra tabs, etc), which must be combined with subaction data for everything to work properly.
	 *
	 * Seems complicated, yes.
	 *
	 * @param array $tabArray named key array holding details on how to build a tab area
	 */
	public function prepareTabData($tabArray = [])
	{
		global $context;

		$currentArea = $this->menuContext['sections'][$this->menuContext['current_section']]['areas'][$this->currentArea];

		// Build out the tab title/description area
		$tabBuilder = new MenuTabs($this->menuContext['current_area'], $this->currentSubaction);
		$tabBuilder
			->setDescription($tabArray['description'] ?? '')
			->setTitle($tabArray['title'] ?? '')
			->setPrefix($tabArray['prefix'] ?? '')
			->setClass($tabArray['class'] ?? null)
			->setHelp($tabArray['help'] ?? null);
		$tabContext = $tabBuilder->setHeader();

		// Nothing supplied, then subsections of the current area are used as tabs.
		if (!isset($tabArray['tabs']) && isset($currentArea['subsections']))
		{
			$tabContext['tabs'] = $tabBuilder->getTabs($currentArea);
		}
		// Tabs specified with area subsections, combine them
		elseif (isset($tabArray['tabs'], $currentArea['subsections']))
		{
			// Tabs are really just subactions.
			$tabContext['tabs'] = array_replace_recursive(
				$tabBuilder->getTabs($currentArea),
				$tabArray['tabs']
			);
		}
		// Custom loading tabs
		else
		{
			$tabContext['tabs'] = $tabArray['tabs'] ?? [];
		}

		// Drop any non-enabled ones
		$tabContext['tabs'] = array_filter($tabContext['tabs'], static function ($tab) {
			return !isset($tab['disabled']) || $tab['disabled'] === false;
		});

		// Has it been deemed selected?
		if (isset($tabContext['tabs'][$this->currentSubaction]))
		{
			$tabContext = array_merge($tabContext, $tabContext['tabs'][$this->currentSubaction]);
		}

		$context['menu_data_' . $this->maxMenuId]['tab_data'] = $tabContext;
	}

	/**
	 * Delete a menu.
	 *
	 * Checks to see if this menu been loaded into context
	 * and, if so, resets $context['max_menu_id'] back to the
	 * last known menu (if any) and remove the template layer
	 * if there aren't any other known menus.
	 */
	public function destroy()
	{
		global $context;

		// Has this menu been loaded into context?
		if (isset($context[$menuName = 'menu_data_' . $this->maxMenuId]))
		{
			// Decrement the pointer if this is the final menu in the series.
			if ($this->maxMenuId === $context['max_menu_id'])
			{
				$context['max_menu_id'] = max($context['max_menu_id'] - 1, 0);
			}

			// Remove the template layer if this was the only menu left.
			if ($context['max_menu_id'] === 0)
			{
				theme()->getLayers()->remove($context[$menuName]['layer_name']);
			}

			unset($context[$menuName]);
		}
	}
}
