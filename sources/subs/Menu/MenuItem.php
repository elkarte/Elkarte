<?php

/**
 * This class contains a standard way of displaying side/drop down menus.
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version   2.0 dev
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
	public static function buildFromArray(array $arr): MenuItem
	{
		$obj = new static;
		$arr['permission'] = isset($arr['permission']) ? (array) $arr['permission'] : [];
		$vars = get_object_vars($obj);
		foreach (array_replace(
					$vars,
					array_intersect_key($arr, $vars)
				) as $var => $val)
		{
			$obj->{'set' . ucfirst($var)}($val);
		}
		$obj->buildMoreFromArray($arr);

		return $obj;
	}

	/**
	 * @return string
	 */
	public function getLabel(): string
	{
		return $this->label;
	}

	/**
	 * @param string $label
	 *
	 * @return MenuItem
	 */
	public function setLabel(string $label): MenuItem
	{
		$this->label = $label;

		return $this;
	}

	/**
	 * @return string
	 */
	public function getCounter(): string
	{
		return $this->counter;
	}

	/**
	 * @param string $counter
	 *
	 * @return MenuItem
	 */
	public function setCounter(string $counter): MenuItem
	{
		$this->counter = $counter;

		return $this;
	}

	/**
	 * @return string
	 */
	public function getUrl(): string
	{
		return $this->url;
	}

	/**
	 * @param string $url
	 *
	 * @return MenuItem
	 */
	public function setUrl(string $url): MenuItem
	{
		$this->url = $url;

		return $this;
	}

	/**
	 * @return string[]
	 */
	public function getPermission(): array
	{
		return $this->permission;
	}

	/**
	 * @param string[] $permission
	 *
	 * @return MenuItem
	 */
	public function setPermission(array $permission): MenuItem
	{
		$this->permission = $permission;

		return $this;
	}

	/**
	 * @return boolean
	 */
	public function isEnabled(): bool
	{
		return $this->enabled;
	}

	/**
	 * @param boolean $enabled
	 *
	 * @return MenuItem
	 */
	public function setEnabled(bool $enabled): MenuItem
	{
		$this->enabled = $enabled;

		return $this;
	}
}
