<?php

/**
 * Most of the "models" require some common stuff (like a constructor).
 * Here it is.
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 1.1 beta 4
 *
 */

/**
 * Class AbstractModel
 *
 * Abstract base class for models.
 */
abstract class AbstractModel
{
	/**
	 * The database object
	 * @var database
	 */
	protected $_db = null;

	/**
	 * The modSettings
	 * @var object
	 */
	protected $_modSettings = array();

	/**
	 * Make "global" items available to the class
	 *
	 * @param object|null $db
	 */
	public function __construct($db = null)
	{
		global $modSettings;

		$this->_db = $db ?: database();
		$this->_modSettings = new ArrayObject($modSettings ?: array(), ArrayObject::ARRAY_AS_PROPS);
	}

	/**
	 * Method to return a $modSetting value
	 *
	 * - Returned value will be the value or null of the key is not set
	 * - If you simply want a value back access it directly as $this->_modSettings->name or $this->_modSettings[name]
	 *
	 * @param string $name The key name of the value to return
	 * @param mixed|null $default default value to return if key value is not found
	 */
	protected function _loadModsettings($name = '', $default = null)
	{
		if (isset($this->_modSettings->{$name}))
		{
			return $this->_modSettings->{$name};
		}
		else
		{
			return $default;
		}
	}
}
