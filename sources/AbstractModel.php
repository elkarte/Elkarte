<?php

/**
 * Most of the "models" require some common stuff (like a constructor).
 * Here it is.
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 1.1 dev
 *
 */

if (!defined('ELK'))
	die('No access...');

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
	 * Load the db to the class
	 * @param object $db
	 */
	public function __construct($db)
	{
		$this->_db = $db;
	}
}