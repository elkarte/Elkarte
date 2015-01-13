<?php

/**
 * Initialize a controller and returns the corresponding object.
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
 * This class will instantiate a controller and then return it to the dispatcher.
 */
class Controller_Loader
{
	/**
	 * The name of the controller.
	 * @var string
	 */
	protected $_controller_name = '';

	/**
	 * The instantiated controller.
	 * @var object
	 */
	protected $_controller = '';

	/**
	 * Constructor...
	 * Requires the name of the controller we want to instantiate, lowercase and
	 * without the "_Controller" part.
	 *
	 * @param string $name - The name of the controller we want to instantiate
	 */
	public function __construct($name)
	{
		$this->_controller_name = (string) $name;
		$controller_name = ucfirst($this->_controller_name) . '_Controller';
		$this->_controller = new $controller_name();
	}

	/**
	 * Checks is the controller has a particular method.
	 *
	 * @param string $name - The method name
	 */
	public function methodExists($name)
	{
		return method_exists($this->_controller, $name);
	}

	/**
	 * Actually instantiate the new controller:
	 *  - require_once modules of the controller,
	 *  - creates the event manager and registers addons and modules,
	 *  - instantiate the controller
	 *  - runs pre_dispatch if necessary
	 * @return the controller's instance
	 */
	public function getController()
	{
		return $this->_controller;
	}

	public function initDispatch()
	{
		$this->_loadModules($this->_controller_name);
		$event_manager = $this->_setEventManager();

		$this->_controller->setEventManager($event_manager);

		// Pre-dispatch (load templates and stuff)
		if (method_exists($this->_controller, 'pre_dispatch'))
			$this->_controller->pre_dispatch();

		return $this->_controller;
	}

	/**
	 * Instantiate the event manager and registers the modules.
	 *
	 * @param string $controller The name of the controller
	 */
	protected function _setEventManager()
	{
		$event_manager = new Event_Manager($this->_controller_name);
		$event_manager->registerAddons('.+_' . ucfirst($this->_controller_name) . '_Addon');
		$event_manager->registerAddons('.+_' . ucfirst($this->_controller_name) . '_Module');

		return $event_manager;
	}

	/**
	 * Shortcut to require the files of the modules for a certain controller.
	 */
	protected function _loadModules()
	{
		foreach ($this->_getModuleFiles() as $require_file)
			require_once($require_file);
	}

	/**
	 * Finds the modules for a certain controller.
	 *
	 * @return string[] File names with full path
	 */
	protected function _getModuleFiles()
	{
		global $modSettings;

		$files = array();
		$setting_key = 'modules_' . strtolower($this->_controller_name);
		if (!empty($modSettings[$setting_key]))
		{
			$modules = explode(',', $modSettings[$setting_key]);
			foreach ($modules as $module)
			{
				$file = SUBSDIR . '/' . ucfirst($module) . ucfirst($this->_controller_name) . 'Module.class.php';
				if (file_exists($file))
					$files[] = $file;
			}
		}

		return $files;
	}
}