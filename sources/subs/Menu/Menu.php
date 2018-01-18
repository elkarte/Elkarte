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
 * @version   2.0 dev
 *
 */

declare(strict_types=1);

namespace ElkArte\Menu;

use Elk_Exception;
use HttpReq;

/**
 * This class implements a standard way of creating menus
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

	/**
	 * Initial processing for the menu
	 *
	 * @param HttpReq|null $req
	 */
	public function __construct(HttpReq $req = null)
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
	}

	/**
	 * Create a menu
	 *
	 * @return array
	 * @throws Elk_Exception
	 */
	public function prepareMenu(): array
	{
		// Build URLs first.
		$this->menuContext['base_url'] = $this->menuOptions->getBaseUrl();
		$this->menuContext['current_action'] = $this->menuOptions->getAction();
		$this->currentArea = $this->req->getQuery('area', 'trim|strval', $this->menuOptions->getArea());
		$this->menuContext['extra_parameters'] = $this->menuOptions->buildAdditionalParams();

		// Process the loopy menu data.
		$this->processMenuData();

		// Here is some activity.
		$this->setActiveButtons();

		// Make sure we created some awesome sauce.
		if (empty($this->includeData))
		{
			// No valid areas -- reject!
			throw new Elk_Exception('no_access');
		}

		// Finally - return information on the selected item.
		return $this->includeData + [
				'current_action' => $this->menuContext['current_action'],
				'current_area' => $this->currentArea,
				'current_section' => !empty($this->menuContext['current_section']) ? $this->menuContext['current_section'] : '',
				'current_subsection' => $this->currentSubaction,
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
		$tabContext = &$context['menu_data_' . $this->maxMenuId]['tab_data'];

		// Tabs are really just subactions.
		if (isset($tabContext['tabs'], $this->menuContext['sections'][$this->menuContext['current_section']]['areas'][$this->currentArea]['subsections']))
		{
			$tabContext['tabs'] = array_replace_recursive(
				$tabContext['tabs'],
				$this->menuContext['sections'][$this->menuContext['current_section']]['areas'][$this->currentArea]['subsections']
			);

			// Has it been deemed selected?
			$tabContext = array_merge($tabContext, $tabContext['tabs'][$this->currentSubaction]);
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
		foreach ($this->menuData as $sectionId => $section)
		{
			// Is this section enabled? and do they have permissions?
			if ($section->isEnabled() && $this->checkPermissions($section))
			{
				$this->setSectionContext($sectionId, $section);

				// Process this menu section
				$this->processSectionAreas($sectionId, $section);
			}
		}
	}

	/**
	 * Determines if the user has the permissions to access the section/area
	 *
	 * If said item did not provide any permission to check, fullly
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
			if (isset($obj->getPermission()['own'], $obj->getPermission()['any']))
			{
				return allowedTo($obj->getPermission()[$this->permissionSet]);
			}

			return allowedTo($obj->getPermission());
		}

		return true;
	}

	/**
	 * Checks if the area has a label or not
	 *
	 * @param string   $areaId
	 * @param MenuArea $area
	 *
	 * @return bool
	 */
	private function areaHasLabel(string $areaId, MenuArea $area): bool
	{
		global $txt;

		return !empty($area->getLabel()) || isset($txt[$areaId]);
	}

	/**
	 * Main processing for creating the menu items for all sections
	 *
	 * @param string      $sectionId
	 * @param MenuSection $section
	 */
	protected function processSectionAreas(string $sectionId, MenuSection $section): void
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
	}

	/**
	 * Checks the menu item to see if it is the currently selected one
	 *
	 * @param string   $sectionId
	 * @param string   $areaId
	 * @param MenuArea $area
	 */
	private function checkCurrentSection(string $sectionId, string $areaId, MenuArea $area): void
	{
		// Is this the current section?
		if ($this->currentArea == $areaId && !$this->foundSection)
		{
			$this->setAreaCurrent($sectionId, $areaId, $area);

			// Only do this once, m'kay?
			$this->foundSection = true;
		}
	}

	/**
	 * @param string   $sectionId
	 * @param string   $areaId
	 * @param MenuArea $area
	 */
	private function setFirstAreaCurrent(string $sectionId, string $areaId, MenuArea $area): void
	{
		// If we don't have an area then the first valid one is our choice.
		if (empty($this->currentArea))
		{
			$this->setAreaCurrent($sectionId, $areaId, $area);
		}
	}

	/**
	 * Simply sets the current area
	 *
	 * @param string   $sectionId
	 * @param string   $areaId
	 * @param MenuArea $area
	 */
	private function setAreaCurrent(string $sectionId, string $areaId, MenuArea $area): void
	{
		// Update the context if required - as we can have areas pretending to be others. ;)
		$this->menuContext['current_section'] = $sectionId;
		$this->currentArea = $area->getSelect() ?: $areaId;
		$this->includeData = $area->toArray();
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
	 * Sets the various section ID items
	 *
	 * What it does:
	 *   - If the ID is not set, sets it and sets the section title
	 *   - Sets the section title
	 *
	 * @param string      $sectionId
	 * @param MenuSection $section
	 */
	private function setSectionContext(string $sectionId, MenuSection $section): void
	{
		global $txt;

		$this->menuContext['sections'][$sectionId] = [
			'id' => $sectionId,
			'label' => ($section->getLabel() ?: $txt[$sectionId]) . $this->parseCounter($section, 0),
			'url' => $this->menuContext['base_url'] . $this->menuContext['extra_parameters'],
		];
	}

	/**
	 * Sets the various area items
	 *
	 * What it does:
	 *   - If the ID is not set, sets it and sets the area title
	 *   - Sets the area title
	 *
	 * @param string   $sectionId
	 * @param string   $areaId
	 * @param MenuArea $area
	 */
	private function setAreaContext(string $sectionId, string $areaId, MenuArea $area): void
	{
		global $txt;

		$this->menuContext['sections'][$sectionId]['areas'][$areaId] = [
			'label' => ($area->getLabel() ?: $txt[$areaId]) . $this->parseCounter($area, 1),
		];
	}

	/**
	 * Set the URL for the menu item
	 *
	 * @param string   $sectionId
	 * @param string   $areaId
	 * @param MenuArea $area
	 */
	private function setAreaUrl(string $sectionId, string $areaId, MenuArea $area): void
	{
		$area->setUrl(
			$this->menuContext['sections'][$sectionId]['areas'][$areaId]['url'] =
				$area->getUrl(
				) ?: $this->menuContext['base_url'] . ';area=' . $areaId . $this->menuContext['extra_parameters']
		);
	}

	/**
	 * Set the menu icon
	 *
	 * @param string   $sectionId
	 * @param string   $areaId
	 * @param MenuArea $area
	 */
	private function setAreaIcon(string $sectionId, string $areaId, MenuArea $area): void
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
				'<img ' . (!empty($area->getClass()) ? 'class="' . $area->getClass(
					) . '" ' : 'style="background: none"') . ' src="' . $imagePath . '/' . $area->getIcon(
				) . '" alt="" />&nbsp;&nbsp;';
		}
		else
		{
			$this->menuContext['sections'][$sectionId]['areas'][$areaId]['icon'] = '';
		}
	}

	/**
	 * Processes all of the subsections for a menu item
	 *
	 * @param string   $sectionId
	 * @param string   $areaId
	 * @param MenuArea $area
	 */
	protected function processAreaSubsections(string $sectionId, string $areaId, MenuArea $area): void
	{
		$this->menuContext['sections'][$sectionId]['areas'][$areaId]['subsections'] = [];

		// For each subsection process the options
		$subSections = array_filter(
			$area->getSubsections(),
			function ($sub) {
				return $this->checkPermissions($sub) && $sub->isEnabled();
			}
		);
		foreach ($subSections as $subId => $sub)
		{
			$this->menuContext['sections'][$sectionId]['areas'][$areaId]['subsections'][$subId] = [
				'label' => $sub->getLabel() . $this->parseCounter($sub, 2),
			];

			$this->setSubsSectionUrl($sectionId, $areaId, $subId, $sub);

			if ($this->currentArea == $areaId)
			{
				$this->setCurrentSubSection($subId, $sub);
			}
		}
		$this->setDefaultSubSection($areaId, $subSections);
	}

	/**
	 * @param string         $sectionId
	 * @param string         $areaId
	 * @param string         $subId
	 * @param MenuSubsection $sub
	 */
	private function setSubsSectionUrl(string $sectionId, string $areaId, string $subId, MenuSubsection $sub): void
	{
		$sub->setUrl(
			$this->menuContext['sections'][$sectionId]['areas'][$areaId]['subsections'][$subId]['url'] =
				$sub->getUrl(
				) ?: $this->menuContext['base_url'] . ';area=' . $areaId . ';sa=' . $subId . $this->menuContext['extra_parameters']
		);
	}

	/**
	 * Set the current subsection
	 *
	 * @param string         $subId
	 * @param MenuSubsection $sub
	 */
	private function setCurrentSubSection(string $subId, MenuSubsection $sub): void
	{
		// Is this the current subsection?
		$subIdCheck = $this->req->getQuery('sa', 'trim', null);
		if (
			$subIdCheck == $subId
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
	 * @param array  $subSections
	 */
	private function setDefaultSubSection(string $areaId, array $subSections): void
	{
		if ($this->currentArea == $areaId && empty($this->currentSubaction))
		{
			$this->currentSubaction = key($subSections) ?? '';
		}
	}

	/**
	 * Checks and updates base and section urls
	 */
	private function setActiveButtons(): void
	{
		// If there are sections quickly goes through all the sections to check if the base menu has an url
		if (!empty($this->menuContext['current_section']))
		{
			$this->menuContext['sections'][$this->menuContext['current_section']]['selected'] = true;
			$this->menuContext['sections'][$this->menuContext['current_section']]['areas'][$this->currentArea]['selected'] =
				true;

			if (!empty($this->menuContext['sections'][$this->menuContext['current_section']]['areas'][$this->currentArea]['subsections'][$this->currentSubaction]))
			{
				$this->menuContext['sections'][$this->menuContext['current_section']]['areas'][$this->currentArea]['subsections'][$this->currentSubaction]['selected'] =
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
		$this->menuOptions = MenuOptions::buildFromArray($menuOptions);
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
		if (isset($context[$menuName = 'menu_data_' . $this->maxMenuId]))
		{
			// Decrement the pointer if this is the final menu in the series.
			if ($this->maxMenuId == $context['max_menu_id'])
			{
				$context['max_menu_id'] = max($context['max_menu_id'] - 1, 0);
			}

			// Remove the template layer if this was the only menu left.
			if ($context['max_menu_id'] == 0)
			{
				theme()->getLayers()->remove($context[$menuName]['layer_name']);
			}

			unset($context[$menuName]);
		}
	}
}
