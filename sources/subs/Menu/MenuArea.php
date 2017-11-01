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

class MenuArea
{
	/** @var string[] $permission Array of permissions to determine who can access this area. */
	public $permission = [];

	/** @var string $label Optional text string for link (Otherwise $txt[$index] will be used) */
	public $label = '';

	/** @var string $counter Index of counter specified in the menu options. */
	public $counter = '';

	/** @var callable $function Function to call when area is selected. */
	public $function;

	/** @var string $custom_url URL to use for this menu item. */
	public $custom_url = '';

	/** @var string $icon File name of an icon to use on the menu, if using the sprite class, set as transparent.png */
	public $icon = '';

	/** @var string $controller URL to use for this menu item. */
	public $controller = '';

	/** @var string $select References another area to be highlighted while this one is active */
	public $select = '';

	/** @var string $class Class name to apply to the icon img, used to apply a sprite icon */
	public $class = '';

	/** @var bool $enabled Should this area even be accessible? */
	public $enabled = true;

	/** @var bool $hidden Should this area be visible? */
	public $hidden = false;

	/** @var array $subsections Array of subsections from this area. */
	public $subsections = [];

	/**
	 * @param array $arr
	 *
	 * @return MenuArea
	 */
	public static function buildFromArray(array $arr)
	{
		$area = new self;
		$vars = get_object_vars($area);
		foreach (array_replace(
					$vars,
					array_intersect_key($arr, $vars)
				) as $var => $val)
		{
			$area->{$var} = $val;
		}

		if (isset($arr['subsections']))
		{
			foreach ($arr['subsections'] as $var => $subsection)
			{
				$area->addSubsection($var, $subsection);
			}
		}

		return $area;
	}

	/**
	 * @param string         $id
	 * @param MenuSubsection $subsection
	 *
	 * @return $this
	 */
	public function addSubsection($id, MenuSubsection $subsection)
	{
		$this->subsections[$id] = $subsection;

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
	 * @return MenuArea
	 */
	public function setPermission($permission)
	{
		$this->permission = $permission;

		return $this;
	}

	/**
	 * @return string
	 */
	public function getLabel()
	{
		return $this->label;
	}

	/**
	 * @param string $label
	 *
	 * @return MenuArea
	 */
	public function setLabel($label)
	{
		$this->label = $label;

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
	 * @return MenuArea
	 */
	public function setCounter($counter)
	{
		$this->counter = $counter;

		return $this;
	}

	/**
	 * @return callable
	 */
	public function getFunction()
	{
		return $this->function;
	}

	/**
	 * @param callable $function
	 *
	 * @return MenuArea
	 */
	public function setFunction($function)
	{
		$this->function = $function;

		return $this;
	}

	/**
	 * @return string
	 */
	public function getCustomUrl()
	{
		return $this->custom_url;
	}

	/**
	 * @param string $custom_url
	 *
	 * @return MenuArea
	 */
	public function setCustomUrl($custom_url)
	{
		$this->custom_url = $custom_url;

		return $this;
	}

	/**
	 * @return string
	 */
	public function getIcon()
	{
		return $this->icon;
	}

	/**
	 * @param string $icon
	 *
	 * @return MenuArea
	 */
	public function setIcon($icon)
	{
		$this->icon = $icon;

		return $this;
	}

	/**
	 * @return string
	 */
	public function getController()
	{
		return $this->controller;
	}

	/**
	 * @param string $controller
	 *
	 * @return MenuArea
	 */
	public function setController($controller)
	{
		$this->controller = $controller;

		return $this;
	}

	/**
	 * @return string
	 */
	public function getSelect()
	{
		return $this->select;
	}

	/**
	 * @param string $select
	 *
	 * @return MenuArea
	 */
	public function setSelect($select)
	{
		$this->select = $select;

		return $this;
	}

	/**
	 * @return string
	 */
	public function getClass()
	{
		return $this->class;
	}

	/**
	 * @param string $class
	 *
	 * @return MenuArea
	 */
	public function setClass($class)
	{
		$this->class = $class;

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
	 * @return MenuArea
	 */
	public function setEnabled($enabled)
	{
		$this->enabled = $enabled;

		return $this;
	}

	/**
	 * @return boolean
	 */
	public function isHidden()
	{
		return $this->hidden;
	}

	/**
	 * @param boolean $hidden
	 *
	 * @return MenuArea
	 */
	public function setHidden($hidden)
	{
		$this->hidden = $hidden;

		return $this;
	}

	/**
	 * @return array
	 */
	public function getSubsections()
	{
		return $this->subsections;
	}
}
