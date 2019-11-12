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
 * Class MenuSubsection
 *
 * This class will set and access the menu subsection options. The supported options are:
 *
 * $subsections sub array is a unnamed index array interpreted as follows,
 *   - string 0     => Label for this subsection
 *   - array 1      => Array of permissions to check for this subsection.
 *   - bool 2       => Is this the default subaction - if not set for any will default to first...
 *   - bool enabled => Enabled or not
 *   - array active => Show the button active for other subsections.
 *   - string url   => Custom url for the subsection
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
	 * @return boolean
	 */
	public function isDefault()
	{
		return $this->default;
	}

	/**
	 * @param boolean $default
	 *
	 * @return MenuSubsection
	 */
	public function setDefault($default)
	{
		$this->default = $default;

		return $this;
	}

	/**
	 * @return string[]
	 */
	public function getActive()
	{
		return $this->active;
	}

	/**
	 * @param string[] $active
	 *
	 * @return MenuSubsection
	 */
	public function setActive($active)
	{
		$this->active = $active;

		return $this;
	}

	/**
	 * Standard name for our function, but for subsections unique areas they are unnamed
	 * index values and have to be processed as such.
	 *
	 * @param array $arr
	 * @param string $sa
	 *
	 * @return MenuSubsection
	 */
	public function buildMoreFromArray($arr, $sa)
	{
		// These are special due to the non named index so there is no generic setter
		$this->label = $arr[0];
		$this->permission = isset($arr[1]) ? (array) $arr[1] : [];
		$this->default = isset($arr[2]) ? (bool) $arr[2] : false;

		// Support for boolean here, wrong but has been used
		if ($this->getActive() === true)
		{
			$this->setActive([$sa]);
		}

		return $this;
	}
}
