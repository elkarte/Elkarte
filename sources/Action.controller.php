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
	/**
	 * The event manager.
	 * @var object
	 */
	protected $_events = null;

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

	/**
	 * Used to set the event manager for the current action.
	 *
	 * @param Event_Manager $event_manager - An Event_Manager object
	 */
	public function setEventManager($event_manager)
	{
		$this->_events = $event_manager;
		$this->_events->setSource($this);
	}

	/**
	 * An odd function that allows events to request dependencies from properties
	 * of the class.
	 *
	 * @param string $dep - The name of the property the even wants
	 * @param mixed[] $dependencies - the array that will be filled with the
	 *                                references to the dependencies
	 */
	public function provideDependencies($dep, &$dependencies)
	{
		if (property_exists($this, $dep))
			$dependencies[$dep] = &$this->$dep;
		elseif (property_exists($this, '_' . $dep))
			$dependencies[$dep] = &$this->{'_' . $dep};
		elseif (array_key_exists($dep, $GLOBALS))
			$dependencies[$dep] = &$GLOBALS[$dep];
	}

	/**
	 * Shortcut to register an array of names as events triggered at a certain
	 * position in the code.
	 *
	 * @param string $name - Name of the trigger where the events will be executed.
	 * @param string $method - The method that will be executed.
	 * @param string[] $to_register - An array of classes to register.
	 */
	protected function _registerEvent($name, $method, $to_register)
	{
		foreach ($to_register as $class)
		{
			$this->_events->register($name, array($name, array($class, $method, 0)));
		}
	}
}