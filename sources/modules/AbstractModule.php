<?php

/**
 * Interface for modules.
 * Actually is just a way to write the hooks method documentation only once.
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 1.1 Release Candidate 2
 *
 */

namespace ElkArte\sources\modules;

/**
 * Interface Module_Interface
 *
 * @package ElkArte\sources\modules
 */
abstract class Abstract_Module implements Module_Interface
{
	protected $_req = null;

	/**
	 * Abstract_Module constructor.
	 * @param \HttpReq $req
	 */
	public function __construct(\HttpReq $req)
	{
		$this->_req = $req;
	}
}
