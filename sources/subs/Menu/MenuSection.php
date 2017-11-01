<?php

/**
 * This class contains a standard way of displaying side/drop down menus.
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version   1.1
 *
 */

namespace ElkArte\Menu;

class MenuSection
{
	/** @var string $title Section title. */
	public $title = '';

	/** @var string $counter Index of counter specified in the menu options. */
	public $counter = '';

	/** @var bool $enabled Should section be shown? */
	public $enabled = true;

	/** @var string[] $permission Permissions required to access the whole section */
	public $permission = [];

	/** @var array $areas Array of areas within this section. */
	public $areas = [];

	/**
	 * @param array $arr
	 *
	 * @return MenuSection
	 */
	public static function buildFromArray(array $arr)
	{
		$section = new self;
		$vars = get_object_vars($section);
		foreach (array_replace(
					$vars,
					array_intersect_key($arr, $vars)
				) as $var => $val)
		{
			$section->{$var} = $val;
		}
		if (isset($arr['areas']))
		{
			foreach ($arr['areas'] as $var => $area)
			{
				$section->addArea($var, $area);
			}
		}

		return $section;
	}

	/**
	 * @param string   $id
	 * @param MenuArea $area
	 *
	 * @return $this
	 */
	public function addArea($id, MenuArea $area)
	{
		$this->areas[$id] = $area;

		return $this;
	}

	/**
	 * @return string
	 */
	public function getTitle()
	{
		return $this->title;
	}

	/**
	 * @param string $title
	 *
	 * @return MenuSection
	 */
	public function setTitle($title)
	{
		$this->title = $title;

		return $this;
	}

	/**
	 * @return string
	 */
	public function getCounter()
	{
		return $this->counter;
	}

	/**
	 * @param string $counter
	 *
	 * @return MenuSection
	 */
	public function setCounter($counter)
	{
		$this->counter = $counter;

		return $this;
	}

	/**
	 * @return boolean
	 */
	public function isEnabled()
	{
		return $this->enabled;
	}

	/**
	 * @param boolean $enabled
	 *
	 * @return MenuSection
	 */
	public function setEnabled($enabled)
	{
		$this->enabled = $enabled;

		return $this;
	}

	/**
	 * @return string[]
	 */
	public function getPermission()
	{
		return $this->permission;
	}

	/**
	 * @param string[] $permission
	 *
	 * @return MenuSection
	 */
	public function setPermission($permission)
	{
		$this->permission = $permission;

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
