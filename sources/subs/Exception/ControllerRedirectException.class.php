<?php

/**
 * Extension of the default Exception class to handle controllers redirection.
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 1.0
 *
 */

if (!defined('ELK'))
	die('No access...');

/**
 * In certain cases a module of a controller my want to "redirect" to another
 * controller (e.g. from Calendar to Post).
 * This exception class catches these "redirects", theninstantiate a new controller
 * taking into account loading of addons and pre_dispatch and returns.
 */
class Controller_Redirect_Exception extends Exception
{
	protected $_controller;
	protected $_method;

	/**
	 * Redefine the initialization.
	 * Do note that parent::__construct() is not called.
	 *
	 * @param string $controller Is the name of controller (lowercase and without "_Controller",
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
	 * @return The return of the method called.
	 */
	public function doRedirect($source)
	{
		$controller_name = ucfirst($this->_controller) . '_Controller';

		if (get_class($source) === $controller_name)
			return $source->{$this->_method}();

		$controller = $this->_loadController();

		return $controller->{$this->_method}();
	}

	/**
	 * Shortcut to instantiate the new controller:
	 *  - require_once modules of the controller (not addons because these are
	 *    always all require'd by the dispatcher),
	 *  - creates the event manager and registers addons and modules,
	 *  - instantiate the controller
	 *  - runs pre_dispatch if necessary
	 * @return the controller's instance
	 */
	protected function _loadController()
	{
		$this->_loadModules($this->_controller);
		$event_manager = new Event_Manager($this->_controller);
		$event_manager->registerAddons('Addon_' . ucfirst($this->_controller) . '.+');
		$event_manager->registerAddons('.+_' . ucfirst($this->_controller) . '_Module');

		$controller_name = ucfirst($this->_controller) . '_Controller';
		$controller = new $controller_name();
		$controller->setEventManager($event_manager);

		if (method_exists($controller, 'pre_dispatch'))
			$controller->pre_dispatch();

		return $controller;
	}

	/**
	 * Shortcut to require the files of the modules for a certain controller.
	 *
	 * @param string $hook The name of the controller
	 */
	protected function _loadModules($hook)
	{
		foreach (glob(SUBSDIR . '/' . ucfirst($hook) . '*Module.class.php') as $require_file)
			require_once($require_file);
	}
}