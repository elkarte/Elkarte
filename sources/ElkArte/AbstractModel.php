<?php

/**
 * Most of the "models" require some common stuff (like a constructor). Here it is.
 *
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * @version 2.0 dev
 *
 */

namespace ElkArte;

use ElkArte\Database\QueryInterface;
use ElkArte\Helper\HttpReq;
use ElkArte\Helper\ValuesContainer;

/**
 * Class AbstractModel
 *
 * This is an abstract base class for models in the application.
 * It provides a database object, modSettings object, and request values object.
 * Child classes can extend this class to inherit these properties and methods.
 */
abstract class AbstractModel
{
	/** @var QueryInterface The database object */
	protected $_db;

	/** @var UserInfo The current user data */
	protected $user;

	/** @var object The modSettings */
	protected $_modSettings = [];

	/** @var HttpReq The request values */
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
		$this->_modSettings = new ValuesContainer($modSettings ?: []);
		$this->_req = HttpReq::instance();
	}
}
