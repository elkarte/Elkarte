<?php

/**
 * Interface for modules.
 * Actually is just a way to write the hooks method documentation only once.
 *
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * @version 2.0 dev
 *
 */

namespace ElkArte\Modules;

use ElkArte\HttpReq;
use ElkArte\UserInfo;

/**
 * Interface ModuleInterface
 *
 * @package ElkArte\sources\modules
 */
abstract class AbstractModule implements ModuleInterface
{
	protected $_req = null;
	protected $user = null;

	/**
	 * AbstractModule constructor.
	 *
	 * @param \ElkArte\HttpReq $req
	 */
	public function __construct(HttpReq $req, UserInfo $user)
	{
		$this->_req = $req;
		$this->user = $user;
	}

	/**
	 * Helper function to see if a request is asking for any api processing
	 *
	 * @return string|false
	 */
	public function getApi()
	{
		// API Call?
		$api = $this->_req->getRequest('api', 'trim', '');

		return in_array($api, ['xml', 'json']) ? $api : false;
	}
}
