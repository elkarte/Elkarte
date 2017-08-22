<?php

/**
 * Most of the "models" require some common stuff (like a constructor).
 * Here it is.
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 1.1 Release Candidate 1
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
		$this->_modSettings = new ValuesContainer($modSettings ?: array());
	}
}
