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

class MenuArea extends MenuItem
{
	/** @var callable $function Function to call when area is selected. */
	protected $function;

	/** @var string $icon File name of an icon to use on the menu, if using the sprite class, set as transparent.png */
	protected $icon = '';

	/** @var string $controller URL to use for this menu item. */
	protected $controller = '';

	/** @var string $select References another area to be highlighted while this one is active */
	public $select = '';

	/** @var string $class Class name to apply to the icon img, used to apply a sprite icon */
	protected $class = '';

	/** @var bool $enabled Should this area even be accessible? */
	protected $enabled = true;

	/** @var bool $hidden Should this area be visible? */
	protected $hidden = false;

	/** @var MenuSubsection[] $subsections Array of subsections from this area. */
	private $subsections = [];

	/**
	 * @param array $arr
	 *
	 * @return MenuArea
	 */
	protected function buildMoreFromArray(array $arr)
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
	 * @return MenuSubsection[]
	 */
	public function getSubsections()
	{
		return $this->subsections;
	}
}
