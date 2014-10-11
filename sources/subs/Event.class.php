<?php

/**
 * An event.
 * In fact a container that holds a list of classes to be called when an event
 * is triggered
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 1.1 alpha 1
 *
 */

class Event
{
	protected $_sorted = false;
	protected $_events = array();
	protected $_priority = array();

	public function __construct($priority)
	{
		$this->_priority = $priority;
	}

	public function add($event, $priority)
	{
		if (is_array($event[1]))
			$name = $event[1][0];
		else
			$name = $event[1];

		$this->_priority->add($name, $priority);
		$this->_events[$name] = $event;
	}

	public function hasEvents()
	{
		if ($this->_sorted === false)
		{
			$this->_priority->sort();
			$this->_sorted = true;
		}

		return $this->_priority->hasEntities();
	}

	public function getEvents()
	{
		$return = array();
		foreach ($this->_priority->getSortedEntities() as $value)
			$return[] = $this->_events[$value];

		return $return;
	}
}