<?php

/**
 * This class contains a standard way of preparing the subsections of the menu
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
 * 'xyz' => [$txt['abc'], 'admin_forum', optional bool, 'enabled' => true/false, 'url' => getUrl()]
 * $subsections sub array is both an unnamed index array and named array ... interpreted as follows,
 *   - string 0     => Label for this subsection
 *   - array 1      => Array of permissions to check for this subsection.
 *   - bool 2       => Optional. Is this the default subaction - if not set for any will default to first...
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
	 * Check if the object is a default value.
	 *
	 * This method returns the value of the "default" property of the object.
	 *
	 * @return bool Returns true if the object is a default value, false otherwise.
	 */
	public function isDefault()
	{
		return $this->default;
	}

	/**
	 * Set the default value of the object.
	 *
	 * This method sets the value of the "default" property of the object.
	 *
	 * @param mixed $default The new default value to be set.
	 * @return $this This method returns the current instance of the object, allowing for method chaining.
	 */
	public function setDefault($default)
	{
		$this->default = $default;

		return $this;
	}

	/**
	 * Get the value of the "active" property of the object.
	 *
	 * This method returns the value of the "active" property of the object.
	 *
	 * @return bool Returns true if the object is active, false otherwise.
	 */
	public function getActive()
	{
		return $this->active;
	}

	/**
	 * Set the active state of the object.
	 *
	 * This method sets the value of the "active" property of the object.
	 *
	 * @param bool $active The new active state of the object.
	 *
	 * @return $this The modified object instance.
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
		$this->default = isset($arr[2]) && (bool) $arr[2];

		// Support for boolean here, wrong but has been used
		if ($this->getActive() === true)
		{
			$this->setActive([$sa]);
		}

		return $this;
	}
}
