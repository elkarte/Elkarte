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
 * Class MenuArea
 *
 * This class will set and access the menu area options. The supported options are:
 *
 * areas is an named index as follows:
 *   - array $permission  => Array of permissions to determine who can access this area
 *   - string $label      => Optional text string for link (Otherwise $txt[$index] will be used)
 *   - string $controller => Name of controller required for this area
 *   - string $function   => Method in controller to call when area is selected
 *   - string $icon       => File name of an icon to use on the menu, if using a class set as transparent.png
 *   - string $class      => CSS class name to apply to the icon img, used to apply a sprite icon
 *   - string $custom_url => URL to call for this menu item
 *   - bool $enabled      => Should this area even be enabled / accessible?
 *   - bool $hidden       => If the area is visible in the menu
 *   - string $select     => If set, references another area
 *   - array $subsections => Array of subsections for this menu area see MenuSubsections
 *
 * @package ElkArte\Menu
 */
class MenuArea extends MenuItem
{
	/** @var string $select References another area to be highlighted while this one is active */
	public $select = '';

	/** @var string $controller URL to use for this menu item. */
	protected $controller = '';

	/** @var callable $function function to call when area is selected. */
	protected $function;

	/** @var string $icon File name of an icon to use on the menu, if using the sprite class, set as transparent.png */
	protected $icon = '';

	/** @var string $class Class name to apply to the icon img, used to apply a sprite icon */
	protected $class = '';

	/** @var bool $hidden Should this area be visible? */
	protected $hidden = false;

	/** @var array $subsections Array of subsections from this area. */
	private $subsections = [];

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
	public function toArray()
	{
		return get_object_vars($this);
	}

	/**
	 * @return array
	 */
	public function getSubsections()
	{
		return $this->subsections;
	}

	/**
	 * @param array $arr
	 *
	 * @return MenuArea
	 */
	protected function buildMoreFromArray($arr)
	{
		if (isset($arr['custom_url']))
		{
			$this->setUrl($arr['custom_url']);
		}

		if (isset($arr['subsections']))
		{
			foreach ($arr['subsections'] as $var => $subsection)
			{
				$this->addSubsection($var, $subsection);
			}
		}

		return $this;
	}

	/**
	 * @param string $id
	 * @param MenuSubsection $subsection
	 *
	 * @return $this
	 */
	public function addSubsection($id, $subsection)
	{
		$this->subsections[$id] = $subsection;

		return $this;
	}
}
