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

	public function __construct($hook, $events)
	{
		$this->_hook = $hook;
		$this->_register($events);
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
			$class_name = is_array($class) ? $class[0] : $class;
			$deps = isset($event[2]) ? $event[2] : array();
			unset($dependecies);

			if (!class_exists($class_name))
				return;

			// Any dependecy you want? In any order you want!
			if (!empty($deps))
			{
				$missing = array();
				foreach ($deps as $dep)
				{
					if (isset($args[$dep]))
						$dependecies[$dep] = &$args[$dep];
					else
						$missing[] = $dep;
				}

				if (!empty($missing))
				{
					$this->_source->provideDependencies($missing, $dependecies);
				}
			}
			else
				$dependecies = &$args;

			$instance = new $class_name($dependecies);

			// Do what we know we should do... if we find it.
			if (method_exists($instance, 'execute'))
				$instance->execute();
		}
	}

	protected function _register($classes)
	{
		foreach ($classes as $class)
		{
			$hooks = $class::hooks();
			foreach ($hooks as $hook)
			{
				$priority = is_array($hook[1]) ? $hook[1][1] : null;
				$position = $hook[0];

				if (!isset($this->_registered_events[$position]))
					$this->_registered_events[$position] = new Event(new Priority());

				$this->_registered_events[$position]->add($hook, $priority);
			}
		}
	}
}