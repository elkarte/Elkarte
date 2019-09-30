<?php

/**
 * Extension of the default Exception class to handle controllers redirection.
 *
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * @version 2.0 dev
 *
 */

namespace ElkArte\Exceptions;

use ElkArte\EventManager;
use ElkArte\User;

/**
 * In certain cases a module of a controller my want to "redirect" to another
 * controller (e.g. from Calendar to Post).
 * This exception class catches these "redirects", then instantiate a new controller
 * taking into account loading of addons and pre_dispatch and returns.
 */
class ControllerRedirectException extends \Exception
{
	protected $_controller;
	protected $_method;

	/**
	 * Redefine the initialization.
	 * Do note that parent::__construct() is not called.
	 *
	 * @param string $controller Is the name of controller (lowercase and with namespace,
	 *                 for example 'post', or 'calendar') that should be instantiated
	 * @param string $method The method to call.
	 */
	public function __construct($controller, $method)
	{
		$this->_controller = $controller;
		$this->_method = $method;
	}

	/**
	 * Takes care of doing the redirect to the other controller.
	 *
	 * @param object $source The controller object that called the method
	 *                ($this in the calling class)
	 * @return mixed
	 */
	public function doRedirect($source)
	{
		if (ltrim(get_class($source), '\\') === ltrim($this->_controller, '\\'))
		{
			return $source->{$this->_method}();
		}

		$controller = new $this->_controller(new EventManager());
		$controller->setUser(User::$info);
		$controller->pre_dispatch();

		return $controller->{$this->_method}();
	}
}
