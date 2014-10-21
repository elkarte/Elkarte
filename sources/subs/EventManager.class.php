<?php

/**
 * Handle events in controller and classes
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 1.1 alpha 1
 *
 */

class Event_Manager
{
	protected $_registered_events = array();
	protected $_inverted_classes = null;
	protected $_hook = '';

	/**
	 * List of classes already registered.
	 * @var string[]
	 */
	protected $_classes = array();

	public function __construct($hook)
	{
		$this->_hook = $hook;
	}

	public function setSource($source)
	{
		$this->_source = $source;
	}

	public function trigger($position, $args)
	{
		if (!isset($this->_registered_events[$position]))
			return;

		if (!$this->_registered_events[$position]->hasEvents())
			return;

		foreach ($this->_registered_events[$position]->getEvents() as $event)
		{
			$class = $event[1];
			$class_name = $class[0];
			$method_name = $class[1];
			$deps = isset($event[2]) ? $event[2] : array();
			unset($dependencies);

			if (!class_exists($class_name))
				return;

			// Any dependecy you want? In any order you want!
			if (!empty($deps))
			{
				$missing = array();
				foreach ($deps as $dep)
				{
					if (isset($args[$dep]))
						$dependencies[$dep] = &$args[$dep];
					else
						$missing[] = $dep;
				}

				if (!empty($missing))
				{
					$this->_source->provideDependencies($missing, $dependencies);
				}
			}
			else
				$dependencies = &$args;

			$instance = new $class_name();
			$instance->setHook($this->_hook . '.' . $position);

			// Do what we know we should do... if we find it.
			if (method_exists($instance, $method_name))
				$instance->$method_name($dependencies);
		}
	}

	public function register($position, $event, $priority = 0)
	{
		if (!isset($this->_registered_events[$position]))
			$this->_registered_events[$position] = new Event(new Priority());

		$this->_registered_events[$position]->add($event, $priority);
	}

	public function registerAddons($prefix)
	{
		$classes = get_declared_classes();
		$prefix_len = strlen($prefix);
		$to_register = array();

		foreach ($classes as $class)
		{
			if (substr($class, 0, $prefix_len) === $prefix && !in_array($class, $this->_classes))
			{
				$to_register[] = $class;
				$this->_classes[] = $class;
			}
		}
		$this->_register_events($to_register);
	}

	protected function _register_events($classes)
	{
		foreach ($classes as $class)
		{
			$events = $class::hooks();
			foreach ($events as $event)
			{
				$priority = isset($event[1][2]);
				$position = $event[0];

				$this->register($position, $event, $priority);
			}
		}
	}
}