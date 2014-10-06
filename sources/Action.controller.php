<?php

/**
 * Abstract base class for controllers. Holds action_index and pre_dispatch
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
 * Abstract base class for controllers.
 *
 * - Requires a default action handler, action_index().
 * - Defines an empty implementation for pre_dispatch() method.
 */
abstract class Action_Controller
{
	protected $_registered_extensions = array();
	protected $_hooks = array();

	/**
	 * Default action handler.
	 *
	 * - This will be called by the dispatcher in many cases.
	 * - It may set up a menu, sub-dispatch at its turn to the method matching ?sa= parameter
	 * or simply forward the request to a known default method.
	 */
	abstract public function action_index();

	/**
	 * Called before any other action method in this class.
	 *
	 * - Allows for initializations, such as default values or
	 * loading templates or language files.
	 */
	public function pre_dispatch()
	{
		// By default, do nothing.
		// Sub-classes may implement their prerequisite loading,
		// such as load the template, load the language(s) file(s)
	}

	protected function runExtension($position, $args)
	{
		if (empty($this->_registered_extensions[$position]))
			return;

		foreach ($this->_registered_extensions[$position] as $class_name)
		{
			if (!class_exists($class_name))
				return;

			// Any dependecy you want? In any order you want!
			if (!empty($class_name::$dep))
			{
				$dependecies = array();
				foreach ($class_name::$dep as $dep)
					if (isset($args[$dep]))
						$dependecies[$dep] = &$args[$dep];
					elseif (property_exists($this, $dep))
						$dependecies[$dep] = &$this->$dep;
					elseif (property_exists($this, '_' . $dep))
						$dependecies[$dep] = &$this->{'_' . $dep};
			}
			else
				$dependecies = &$args;

			$instance = new $class_name($dependecies);

			// Do what we know we should do... if we find it.
			if (method_exists($instance, 'execute'))
				$instance->execute();
		}
	}

	public function register($extensions)
	{
		foreach ($this->_hooks as $hook)
			$this->addExtension($hook, $this->getExtensions($hook));
	}

	protected function getExtensions($position)
	{
		global $modSettings;

		$hook = str_replace('_controller', '', strtolower(__CLASS__)) . '_' . $position;

		if (!empty($modSettings[$hook]))
			$methods = @unserialize($modSettings[$hook]);

		if (empty($methods))
			return array();

		$return = array();
		foreach ($methods as $method)
		{
			if (is_array($method))
				$return[] = array($method[0], $method[1]);
			else
				$return[] = array($method, array());
		}

		return $return;
	}

	protected function addExtension($position, $extensions)
	{
		if (empty($extensions))
			return;

		$this->_registered_extensions[$position] = $extensions;
	}
}