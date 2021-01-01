<?php

/**
 * Handle events in controller and classes
 *
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * @version 2.0 dev
 */

namespace ElkArte;

/**
 * Handle events in controller and classes
 */
class EventManager
{
	/**
	 * An array of events, each entry is a different position.
	 *
	 * @var object[] Event
	 */
	protected $_registered_events = array();

	/**
	 * Instances of addons already loaded.
	 *
	 * @var object[]
	 */
	protected $_instances = array();

	/**
	 * Instances of the controller.
	 *
	 * @var object
	 */
	protected $_source = null;

	/**
	 * List of classes already registered.
	 *
	 * @var string[]
	 */
	protected $_classes = array();

	/**
	 * List of classes declared, kept here just to avoid
	 * call get_declared_classes at each trigger
	 *
	 * @var null|string[]
	 */
	protected $_declared_classes = null;

	/**
	 * Just a dummy for the time being.
	 */
	public function __construct()
	{
	}

	/**
	 * Allows to set the object that instantiated the \ElkArte\EventManager.
	 *
	 * - Necessary in order to be able to provide the dependencies later on
	 *
	 * @param object $source The controller that instantiated the \ElkArte\EventManager
	 */
	public function setSource($source)
	{
		$this->_source = $source;
	}

	/**
	 * This is the function use to... trigger an event.
	 *
	 * - Called from many areas in the code where events can be raised
	 * $this->_events->trigger('area', args)
	 *
	 * @param string $position The "identifier" of the event, such as prepare_post
	 * @param mixed[] $args The arguments passed to the methods registered
	 *
	 * @return bool
	 */
	public function trigger($position, $args = array())
	{
		// Nothing registered against this event, just return
		if (!array_key_exists($position, $this->_registered_events) || !$this->_registered_events[$position]->hasEvents())
		{
			return false;
		}

		// For all areas that that registered against this event, let them know its been triggered
		foreach ($this->_registered_events[$position]->getEvents() as $event)
		{
			$class = $event[1];
			$class_name = $class[0];
			$method_name = $class[1];
			$deps = isset($event[2]) ? $event[2] : array();
			$dependencies = null;

			if (!class_exists($class_name))
			{
				return false;
			}

			// Any dependency you want? In any order you want!
			if (!empty($deps))
			{
				foreach ($deps as $dep)
				{
					if (array_key_exists($dep, $args))
					{
						$dependencies[$dep] = &$args[$dep];
					}
					else
					{
						$this->_source->provideDependencies($dep, $dependencies);
					}
				}
			}
			else
			{
				foreach ($args as $key => $val)
				{
					$dependencies[$key] = &$args[$key];
				}
			}

			$instance = $this->_getInstance($class_name);

			// Do what we know we should do... if we find it.
			if (is_callable(array($instance, $method_name)))
			{
				// Don't send $dependencies if there are none / the method can't use them
				if (empty($dependencies))
				{
					call_user_func(array($instance, $method_name));
				}
				else
				{
					$this->_checkParameters($class_name, $method_name, $dependencies);
					call_user_func_array(array($instance, $method_name), $dependencies);
				}
			}
		}
	}

	/**
	 * Retrieves or creates the instance of an object.
	 *
	 * What it does:
	 *
	 * - Objects are stored in order to be shared between different triggers
	 * in the same \ElkArte\EventManager.
	 * - If the object doesn't exist yet, it is created
	 *
	 * @param string $class_name The name of the class.
	 * @return object
	 */
	protected function _getInstance($class_name)
	{
		if (isset($this->_instances[$class_name]))
		{
			return $this->_instances[$class_name];
		}
		else
		{
			$instance = new $class_name(HttpReq::instance(), User::$info);
			$this->_setInstance($class_name, $instance);

			return $instance;
		}
	}

	/**
	 * Stores the instance of a class created by _getInstance.
	 *
	 * @param string $class_name The name of the class.
	 * @param object $instance The object.
	 */
	protected function _setInstance($class_name, $instance)
	{
		if (!isset($this->_instances[$class_name]))
		{
			$this->_instances[$class_name] = $instance;
		}
	}

	/**
	 * Loads addons and modules based on a pattern.
	 *
	 * - The pattern defines the names of the classes that will be registered
	 * to this \ElkArte\EventManager.
	 *
	 * @param string[] $classes A set of class names that should be attached
	 */
	public function registerClasses($classes)
	{
		$this->_register_events($classes);
	}

	/**
	 * Takes care of registering the classes/methods to the different positions
	 * of the \ElkArte\EventManager.
	 *
	 * What it does:
	 *
	 * - Each class must have a static Method ::hooks
	 * - Method ::hooks must return an array defining where and how the class
	 * will interact with the object that started the \ElkArte\EventManager.
	 *
	 * @param string[] $classes A list of class names.
	 */
	protected function _register_events($classes)
	{
		foreach ($classes as $class)
		{
			// Load the events for this area/class combination
			$events = $class::hooks($this);
			if (!is_array($events))
			{
				continue;
			}

			foreach ($events as $event)
			{
				// Check if a priority (ordering) was specified
				$priority = isset($event[1][2]) ? $event[1][2] : 0;
				$position = $event[0];

				// Register the "action" to take when the event is triggered
				$this->register($position, $event, $priority);
			}
		}
	}

	/**
	 * Registers an event at a certain position with a defined priority.
	 *
	 * @param string $position The position at which the event will be triggered
	 * @param mixed[] $event An array describing the event we want to trigger:
	 *   0 => string - the position at which the event will be triggered
	 *   1 => string[] - the class and method we want to call:
	 *      array(
	 *        0 => string - name of the class to instantiate
	 *        1 => string - name of the method to call
	 *      )
	 *   2 => null|string[] - an array of dependencies in the form of strings representing the
	 *        name of the variables the method requires.
	 *        The variables can be from:
	 *          - the default list of variables passed to the trigger
	 *          - properties (private, protected, or public) of the object that instantiate the \ElkArte\EventManager
	 *            (i.e. the controller)
	 *          - globals
	 * @param int $priority Defines the order the method is called.
	 */
	public function register($position, $event, $priority = 0)
	{
		if (!isset($this->_registered_events[$position]))
		{
			$this->_registered_events[$position] = new Event(new Priority());
		}

		$this->_registered_events[$position]->add($event, $priority);
	}

	/**
	 * Gets the names of all the classes already loaded.
	 *
	 * @return string[]
	 */
	protected function _declared_classes()
	{
		if ($this->_declared_classes === null)
		{
			$this->_declared_classes = get_declared_classes();
		}

		return $this->_declared_classes;
	}

	/**
	 * Reflects a specific class method to see what parameters are needed
	 *
	 * Currently only checks on number required, can be expanded to make use of
	 * $params = $r->getParameters() and then $param-> getName isOptional etc
	 * to ensure required named are being passed.
	 *
	 * @param string $class_name
	 * @param string $method_name
	 * @param array $dependencies the dependencies the event registered
	 */
	protected function _checkParameters($class_name, $method_name, &$dependencies)
	{
		// Lets check on the actual methods parameters
		try
		{
			$r = new \ReflectionMethod($class_name, $method_name);
			$number_params = $r->getNumberOfParameters();
			unset($r);
		}
		catch (\Exception $e)
		{
			$number_params = 0;
		}

		// Php8 will not like passing parameters to a method that takes none
		if ($number_params == 0 && !empty($dependencies))
		{
			$dependencies = array();
		}
	}
}
