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
 * Class MenuItem
 *
 * This class implements a standard way of creating menus
 *
 * @package ElkArte\Menu
 */
abstract class MenuItem
{
	/** @var string $label Text label for this subsection. */
	protected $label = '';

	/** @var string $counter Index of counter specified in the menu options. */
	protected $counter = '';

	/** @var string $url URL to use for this menu item. */
	protected $url = '';

	/** @var string[] $permission Array of permissions to check for this subsection. */
	protected $permission = [];

	/** @var bool $enabled Bool to say whether this should be enabled. */
	protected $enabled = true;

	/**
	 * @param array $arr
	 *
	 * @return MenuItem
	 */
	public static function buildFromArray($arr)
	{
		$obj = new static;
		$arr['permission'] = isset($arr['permission']) ? (array) $arr['permission'] : [];
		$vars = get_object_vars($obj);

		foreach (array_replace($vars, array_intersect_key($arr, $vars)) as $var => $val)
		{
			$obj->{'set' . ucfirst($var)}($val);
		}

		$obj->buildMoreFromArray($arr);

		return $obj;
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
	 * @return MenuItem
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
	 * @return MenuItem
	 */
	public function setCounter($counter)
	{
		$this->counter = $counter;

		return $this;
	}

	/**
	 * @return string
	 */
	public function getUrl()
	{
		return $this->url;
	}

	/**
	 * @param string $url
	 *
	 * @return MenuItem
	 */
	public function setUrl($url)
	{
		$this->url = $url;

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
	 * @return MenuItem
	 */
	public function setPermission($permission)
	{
		$this->permission = $permission;

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
	 * @return MenuItem
	 */
	public function setEnabled($enabled)
	{
		$this->enabled = $enabled;

		return $this;
	}
}