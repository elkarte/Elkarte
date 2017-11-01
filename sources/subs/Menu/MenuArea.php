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
}
