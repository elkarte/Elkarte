<?php

/**
 * This class contains a standard way of displaying side/drop down menus.
 *
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * @version 2.0 dev
 *
 */

namespace ElkArte\Menu;

/**
 * Class MenuSection
 *
 * This class implements a standard way of creating the top section menu
 *
 * The array is a unnamed index array interpreted as follows,
 *   - string $title      => Section title
 *   - bool $enabled      => Is the section enabled / shown
 *   - array $areas       => Array of areas within this menu section, each area options processed by MenuArea
 *   - array $permission  => Permission required to access the whole section
 *
 * @package ElkArte\Menu
 */
class MenuSection extends MenuItem
{
	/** @var array $areas Array of areas within this section. */
	private $areas = [];

	/**
	 * @param array $arr
	 *
	 * @return MenuSection
	 */
	protected function buildMoreFromArray($arr)
	{
		if (isset($arr['title']))
		{
			$this->setLabel($arr['title']);
		}

		if (isset($arr['areas']))
		{
			foreach ($arr['areas'] as $var => $area)
			{
				$this->addArea($var, $area);
			}
		}

		return $this;
	}

	/**
	 * @param string $id
	 * @param MenuArea $area
	 *
	 * @return $this
	 */
	public function addArea($id, $area)
	{
		$this->areas[$id] = $area;

		return $this;
	}

	/**
	 * @return array
	 */
	public function getAreas()
	{
		return $this->areas;
	}
}
