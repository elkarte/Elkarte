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
	/** @var \ElkArte\Database\QueryInterface The database object */
	protected $_db = null;

	/** @var \ElkArte\UserInfo The current user data */
	protected $user = null;

	/** @var object The modSettings */
	protected $_modSettings = array();

	/** @var \ElkArte\HttpReq The request values */
	protected $_req;

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
		$this->_req = HttpReq::instance();
	}
}
