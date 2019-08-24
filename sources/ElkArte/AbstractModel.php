<?php

/**
 * Most of the "models" require some common stuff (like a constructor).
 * Here it is.
 *
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * @version 2.0 dev
 *
 */

namespace ElkArte;

/**
 * Class AbstractModel
 *
 * Abstract base class for models.
 */
abstract class AbstractModel
{
	/**
	 * The database object
	 * @var \ElkArte\Database\QueryInterface
	 */
	protected $_db = null;

	/**
	 * The current user data
	 * @var \ElkArte\UserInfo
	 */
	protected $user = null;

	/**
	 * The modSettings
	 * @var object
	 */
	protected $_modSettings = array();

	/**
	 * Make "global" items available to the class
	 *
	 * @param object|null $db
	 * @param object|null $user
	 */
	public function __construct($db = null, $user = null)
	{
		global $modSettings;

		$this->_db = $db ?: database();
		$this->user = $user;
		$this->_modSettings = new ValuesContainer($modSettings ?: array());
	}
}
