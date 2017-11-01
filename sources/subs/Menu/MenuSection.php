<?php

/**
 * This class contains a standard way of displaying side/drop down menus.
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 1.1
 *
 */

namespace ElkArte\Menu;

class MenuSection extends MenuItem
{
	/** @var array $areas Array of areas within this section. */
	private $areas = [];

	/**
	 * @param array $arr
	 *
	 * @return MenuSection
	 */
	protected function buildMoreFromArray(array $arr): MenuSection
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
	 * @param string   $id
	 * @param MenuArea $area
	 *
	 * @return $this
	 */
	public function addArea(string $id, MenuArea $area): MenuSection
	{
		$this->areas[$id] = $area;

		return $this;
	}

	/**
	 * @return array
	 */
	public function getAreas(): array
	{
		return $this->areas;
	}
}
