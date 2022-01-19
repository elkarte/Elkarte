<?php

/**
 * An event.
 * In fact a container that holds a list of classes to be called when an event
 * is triggered
 *
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * @version 2.0 dev
 *
 */

namespace ElkArte;

/**
 * An event.
 * In fact a container that holds a list of classes to be called when an event
 * is triggered
 */
class Event
{
	/** @var bool If the classes have already been sorted by priority. */
	protected $_sorted = false;

	/** @var string[] List of classes. */
	protected $_events = array();

	/** @var object The priority object. */
	protected $_priority = array();

	/**
	 * Initialize the class.
	 *
	 * @param Priority $priority the object that handles priorities
	 */
	public function __construct($priority)
	{
		$this->_priority = $priority;
	}

	/**
	 * Add a new event with a certain priority.
	 *
	 * @param mixed[] $event An array describing the event we want to trigger:
	 *    array(
	 *      0 => string - the position at which the event will be triggered
	 *      1 => string[] - the class and method we want to call:
	 *            array(
	 *              0 => string - name of the class to instantiate
	 *              1 => string - name of the method to call
	 *            )
	 *      2 => null|string[] - an array of dependencies in the form of strings representing the
	 *                           name of the variables the method requires.
	 *                           The variables can be from:
	 *                         - the default list of variables passed to the trigger
	 *                         - properties (private, protected, or public) of the object that
	 *                           instantiate the \ElkArte\EventManager (i.e. the controller)
	 *                         - globals
	 *      )
	 * @param int $priority A value that defines the relative priority at which
	 *            the event should be triggered.
	 */
	public function add($event, $priority)
	{
		$name = is_array($event[1]) ? $event[1][0] : $event[1];

		$this->_priority->add($name, $priority);
		$this->_events[$name] = $event;
	}

	/**
	 * Determines if there are events added or not.
	 *
	 * @return bool
	 */
	public function hasEvents()
	{
		if (!$this->_sorted)
		{
			$this->_doSorting();
		}

		return $this->_priority->hasEntities();
	}

	protected function _doSorting()
	{
		$this->_priority->sort();
		$this->_sorted = true;
	}

	/**
	 * Returns the list of sorted events to be triggered.
	 *
	 * @return mixed[]
	 */
	public function getEvents()
	{
		$return = array();

		if (!$this->_sorted)
		{
			$this->_doSorting();
		}

		foreach ($this->_priority->getSortedEntities() as $value)
		{
			$return[] = $this->_events[$value];
		}

		return $return;
	}
}
