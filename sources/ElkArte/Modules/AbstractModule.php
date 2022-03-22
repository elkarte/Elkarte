<?php

/**
 * Abstract for modules.
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
 * Abstract AbstractModule Provides some standard values to modules
 *
 * @package ElkArte\sources\modules
 */
abstract class AbstractModule implements ModuleInterface
{
	/** @var \ElkArte\HttpReq|null Access to post/get data */
	protected $_req;

	/** @var \ElkArte\UserInfo|null User Info ValuesContainer */
	protected $user;

	/**
	 * AbstractModule constructor.
	 *
	 * @param \ElkArte\HttpReq $req
	 * @param \ElkArte\UserInfo $user
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

		return in_array($api, ['xml', 'json', 'html']) && !empty($_SERVER['HTTP_X_REQUESTED_WITH']) ? $api : false;
	}
}
