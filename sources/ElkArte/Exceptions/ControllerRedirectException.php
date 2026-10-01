<?php

/**
 * Extension of the default Exception class to handle controllers redirection.
 *
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * @version 2.0 Beta 1
 *
 */

namespace ElkArte\Exceptions;

use ElkArte\EventManager;
use ElkArte\User;

/**
 * In certain cases a module of a controller may want to "redirect" to another
 * controller (e.g., from Calendar to Post).
 * This exception class catches these "redirects", then instantiates a new controller
 * taking into account loading of addons and pre_dispatch and returns.
 */
class ControllerRedirectException extends \Exception
{
	/**
	 * Redefine the initialization.
	 * Do note that parent::__construct() is not called.
	 *
	 * @param string $_controller Is the name of controller (lowercase and with namespace,
	 * for example 'post', or 'calendar') that should be instantiated
	 * @param string $_method The method to call.
	 */
	public function __construct(protected $_controller, protected $_method)
	{
	}

	/**
	 * Returns the target controller name or class.
	 *
	 * @return string
	 */
	public function getController(): string
	{
		return $this->_controller;
	}

	/**
	 * Returns the target method to call.
	 *
	 * @return string
	 */
	public function getMethod(): string
	{
		return $this->_method;
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
		if (empty($this->_controller) && empty($this->_method))
		{
			return null;
		}

		$targetController = $this->_controller;
		if (!empty($targetController) && !class_exists($targetController))
		{
			if (class_exists('\\ElkArte\\Controller\\' . ucfirst($targetController)))
			{
				$targetController = '\\ElkArte\\Controller\\' . ucfirst($targetController);
			}
			elseif (class_exists('\\ElkArte\\AdminController\\' . ucfirst($targetController)))
			{
				$targetController = '\\ElkArte\\AdminController\\' . ucfirst($targetController);
			}
		}

		if (empty($targetController) || ltrim($source::class, '\\') === ltrim($targetController, '\\'))
		{
			return $this->_callControllerAction($source);
		}

		$controller = new $targetController(new EventManager(), User::$info);
		$controller->setUser(User::$info);
		$controller->pre_dispatch();

		return $this->_callControllerAction($controller);
	}

	/**
	 * Executes the target method on the controller instance, triggering lifecycle integration hooks.
	 *
	 * @param object $controller The controller instance to invoke
	 * @return mixed
	 */
	protected function _callControllerAction($controller)
	{
		$hook = is_callable([$controller, 'getHook']) ? $controller->getHook() : '';
		if (!empty($hook) && function_exists('call_integration_hook'))
		{
			call_integration_hook('integrate_action_' . $hook . '_before', [$this->_method]);
		}

		$result = $controller->{$this->_method}();

		if (!empty($hook) && function_exists('call_integration_hook'))
		{
			call_integration_hook('integrate_action_' . $hook . '_after', [$this->_method]);
		}

		return $result;
	}
}
