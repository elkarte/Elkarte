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
	protected $menuContext = [];

	/** @var string Used for profile menu for own / any */
	protected $permissionSet;

	/** @var bool If we found the menu item selected */
	protected $foundSection = false;

	/** @var string Current area */
	protected $currentArea = '';

	/** @var null|string The current subaction of the system */
	protected $currentSubaction = '';

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
	}

	/**
	 * Parses the supplied menu data in to the relevant menu array structure
	 *
	 * @param mixed[] $menuData the menu array
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
	 * Create a menu.  Expects that addOptions and addMenuData (or equivalent) have been called
	 *
	 * @return array
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
		$this->includeData = array_filter($this->includeData, function ($value) {
			return !is_null($value) && $value !== '';
		});

		// Finally - return information on the selected item.
		return $this->includeData + [
				'current_action' => $this->menuContext['current_action'],
				'current_area' => $this->currentArea,
				'current_section' => !empty($this->menuContext['current_section']) ? $this->menuContext['current_section'] : '',
				'current_subsection' => $this->currentSubaction,
			];
	}

	/**
	 * Allow extending *any* menu with a single hook
	 */
	public function callHook()
	{
		// Allow extend *any* menu with a single hook
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
		if (empty($this->menuContext['sections'][$sectionId]['areas']))
		{
			if (empty($this->menuContext['sections'][$sectionId]['url']))
			{
				unset($this->menuContext['sections'][$sectionId]);
			}
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
	 * @throws \ElkArte\Exceptions\Exception
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
	 * Set the menu icon
	 *
	 * @param string $sectionId
	 * @param string $areaId
	 * @param MenuArea $area
	 */
	private function setAreaIcon($sectionId, $areaId, $area)
	{
		global $settings;

		// Work out where we should get our menu images from.
		$imagePath = file_exists($settings['theme_dir'] . '/images/admin/change_menu.png')
			? $settings['images_url'] . '/admin'
			: $settings['default_images_url'] . '/admin';

		// Does this area have its own icon?
		if (!empty($area->getIcon()))
		{
			$this->menuContext['sections'][$sectionId]['areas'][$areaId]['icon'] =
				'<img ' . (!empty($area->getClass()) ? 'class="' . $area->getClass() . '"' : 'style="background: none"') . ' src="' . $imagePath . '/' . $area->getIcon() . '" alt="" />&nbsp;&nbsp;';
		}
		else
		{
			$this->menuContext['sections'][$sectionId]['areas'][$areaId]['icon'] = '';
		}
	}

	/**
	 * Processes all of the subsections for a menu item
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
			|| in_array($subIdCheck, $sub->getActive(), true)
			|| empty($this->currentSubaction) && $sub->isDefault()
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
		$context['max_menu_id'] = $this->maxMenuId;
		$context['current_subaction'] = $this->currentSubaction;
		$this->menuContext['current_subsection'] = $this->currentSubaction;
		$this->menuContext['current_area'] = $this->currentArea;
		$context['menu_data_' . $this->maxMenuId] = $this->menuContext;
		$context['menu_data_' . $this->maxMenuId]['object'] = $this;
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
	public function prepareTabData()
	{
		global $context;

		// Handy shortcuts.
		$tabContext = &$context['menu_data_' . $this->maxMenuId]['tab_data'];
		$currentArea = $this->menuContext['sections'][$this->menuContext['current_section']]['areas'][$this->currentArea];

		// Subsections of the current area are tabs unless we are told otherwise.
		if (!isset($tabContext['tabs']))
		{
			$tabContext['tabs'] = $currentArea['subsections'] ?? array();
		}

		// Tabs are really just subactions.
		if (isset($tabContext['tabs'], $currentArea['subsections']))
		{
			$tabContext['tabs'] = array_replace_recursive(
				$tabContext['tabs'],
				$currentArea['subsections']
			);

			// Has it been deemed selected?
			if (isset($tabContext['tabs'][$this->currentSubaction]))
			{
				$tabContext = array_merge($tabContext, $tabContext['tabs'][$this->currentSubaction]);
			}
		}
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
