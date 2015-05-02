<?php

/**
 * Most of the "models" require some common stuff (like a constructor).
 * Here it is.
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 1.0.3
 *
 */

if (!defined('ELK'))
	die('No access...');

abstract class AbstractModel
{
	/**
	 * The database object
	 * @var object
	 */
	protected $_db = null;

	public function __construct($db)
	{
		$this->_db = $db;
	}
}