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
 * Class MenuSubsection
 *
 * This class implements a standard way of creating menus
 *
 * @package ElkArte\Menu
 */
class MenuSubsection extends MenuItem
{
	/** @var bool $default Is this the default subaction - if not set for any will default to first... */
	protected $default = false;

	/** @var string[] $active Set the button active for other subsections. */
	protected $active = [];

	/**
	 * @param array $arr
	 *
	 * @return MenuSubsection
	 */
	protected function buildMoreFromArray(array $arr): MenuSubsection
	{
		$this->label = $arr[0];
		$this->permission = isset($arr[1]) ? (array) $arr[1] : [];
		$this->default = isset($arr[2]) ? (bool) $arr[2] : false;

		return $this;
	}

	/**
	 * @return boolean
	 */
	public function isDefault(): bool
	{
		return $this->default;
	}

	/**
	 * @param boolean $default
	 *
	 * @return MenuSubsection
	 */
	public function setDefault(bool $default): MenuSubsection
	{
		$this->default = $default;

		return $this;
	}

	/**
	 * @return string[]
	 */
	public function getActive(): array
	{
		return $this->active;
	}

	/**
	 * @param string[] $active
	 *
	 * @return MenuSubsection
	 */
	public function setActive(array $active): MenuSubsection
	{
		$this->active = $active;

		return $this;
	}
}
