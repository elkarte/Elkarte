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

use ElkArte\Errors\Errors;

/**
 * Class MenuArea
 *
 * This class will set and access the menu area options. The supported options are:
 *
 * areas is a named index as follows:
 *   - array $permission  => Array of permissions to determine who can access this area
 *   - string $label      => Optional text string for link (Otherwise $txt[$index] will be used)
 *   - string $controller => Name of controller required for this area
 *   - string $function   => Method in controller to call when area is selected
 *   - string $icon       => File name of an icon to use on the menu, if using a class set as transparent.png
 *   - string $class      => CSS class name to apply to the icon img, used to apply a sprite icon
 *   - string $custom_url => URL to call for this menu item
 *   - string $token      => token name to use
 *   - string $token_type => where to look for the returned token (get/post)
 *   - string $sc         => session check where to look for returned session data (get/post)
 *   - bool $password     => if the user will be required to enter login password
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
	protected $select = '';

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

	/** @var string $token Name of the token to validate */
	protected $token = '';

	/** @var string $tokenType where to look for our token, get, request, post. */
	protected $tokenType = '';

	/** @var string $sc session check where to look for the session data, get or post */
	protected $sc = '';

	/** @var string $customUrl custom URL to use for this menu item. */
	protected $customUrl;

	/** @var bool $password is the user password required to make a change, profile only use? */
	protected $password = false;

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
	 * Set the url value
	 *
	 * @param string $url
	 *
	 * @return MenuItem
	 */
	public function setCustomUrl($url)
	{
		$this->customUrl = $url;

		return $this;
	}

	/**
	 * @return bool
	 */
	public function isHidden()
	{
		return $this->hidden;
	}

	/**
	 * @param bool $hidden
	 *
	 * @return MenuArea
	 */
	public function setHidden($hidden)
	{
		$this->hidden = $hidden;

		return $this;
	}

	/**
	 * @return bool
	 */
	public function isPassword()
	{
		return $this->password;
	}

	/**
	 * @param bool $password
	 *
	 * @return MenuArea
	 */
	public function setPassword($password)
	{
		$this->password = $password;

		return $this;
	}

	/**
	 * Converts an object and any branches to an array, recursive.
	 *
	 * @param mixed $obj
	 *
	 * @return array
	 */
	public function toArray($obj)
	{
		if (!is_object($obj) && !is_array($obj))
		{
			return $obj;
		}

		return array_map([$this, 'toArray'], is_array($obj) ? $obj : get_object_vars($obj));
	}

	/**
	 * Get the subsections of the MenuArea
	 *
	 * @return array The array of subsections
	 */
	public function getSubsections()
	{
		return $this->subsections;
	}

	/**
	 * Get the token for this instance
	 *
	 * @return string The token for this instance
	 */
	public function getToken()
	{
		return $this->token;
	}

	/**
	 * Sets the token for authentication.
	 *
	 * @param string $token The authentication token.
	 *
	 * @return MenuArea
	 */
	public function setToken($token)
	{
		$this->token = $token;

		return $this;
	}

	/**
	 * Get the token type of this instance.
	 *
	 * @return string The token type.
	 */
	public function getTokenType()
	{
		return $this->tokenType;
	}

	/**
	 * Set the token type for this object.
	 *
	 * @param string $tokenType The token type to be set.
	 *
	 * @return MenuArea
	 */
	public function setTokenType($tokenType)
	{
		$this->tokenType = $tokenType;

		return $this;
	}

	/**
	 * Get the value of sc
	 *
	 * @return string The value of sc
	 */
	public function getSc()
	{
		return $this->sc;
	}

	/**
	 * Sets the value of sc property
	 *
	 * @param mixed $sc The value to set for sc property
	 *
	 * @return MenuArea
	 */
	public function setSc($sc)
	{
		$this->sc = $sc;

		return $this;
	}

	/**
	 * Account for unique setter/getter items for this area
	 *
	 * @param array $arr
	 *
	 * @return MenuArea
	 * @throws \Exception
	 */
	protected function buildMoreFromArray($arr)
	{
		$this->url = $this->customUrl ?: $this->url;

		if (isset($arr['subsections']))
		{
			foreach ($arr['subsections'] as $var => $subsection)
			{
				$this->addSubsection($var, $subsection);
			}
		}

		// Anything left over, currently for debug purposes
		$this->anythingMissed($arr);

		return $this;
	}

	/**
	 * Right now this is here just for debug.  Do any addons create keys that we have not accounted for
	 * in the class?  Should we simply just set anything that is not a defined var?
	 *
	 * @param array $arr
	 */
	private function anythingMissed($arr)
	{
		$missing = array_diff_key($arr, get_object_vars($this));
		foreach ($missing as $key => $value)
		{
			// Excluding our private key, anything missed?
			if (!in_array($key, ['subsections', 'messages']))
			{
				Errors::instance()->log_error('Depreciated: menu key: ' . $key . ' value: ' . $value, 'depreciated');
			}
		}
	}

	/**
	 * Add a subsection to the menu area
	 *
	 * @param mixed $id The identifier of the subsection
	 * @param mixed $subsection The subsection to be added
	 *
	 * @return MenuArea Returns the current instance of MenuArea
	 */
	public function addSubsection($id, $subsection)
	{
		$this->subsections[$id] = $subsection;

		return $this;
	}
}
