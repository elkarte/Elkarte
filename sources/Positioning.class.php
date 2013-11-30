<?php

/**
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 1.0 Beta
 *
 */

if (!defined('ELK'))
	die('No access...');

/**
 *  This class is an experiment for the job of handling positioning of items.
 *  It has implementation for few simple things like:
 *    - add
 *    - addBefore
 *    - addAfter
 *    - remove
 *    - removeAll
 *  it should be extended in order to actually use its functionality
 */
abstract class Positioning_Items
{
	/**
	 * Used when adding: it holds the "relative" position of the item added
	 * (i.e. "before", "after", "end" or "begin")
	 *
	 * @var string
	 */
	protected $_position = null;

	/**
	 * An (unique) id to identify the "item"
	 *
	 * @var string
	 */
	protected $_name = null;

	/**
	 * Known positions the item can be added
	 *
	 * @var array
	 */
	protected $_known_positions = array();

	/**
	 * The index of the item after or before which another one should be placed
	 *
	 * @var string
	 */
	protected $_relative = '';

	/**
	 * Reset or not the position where items as added
	 *
	 * @var bool
	 */
	protected $_reset = false;

	/**
	 * Holds all the items to add
	 *
	 * @var array
	 */
	protected $_items = null;

	/**
	 * An array containing all the items added
	 *
	 * @var string
	 */
	protected $_all_general = array();

	/**
	 * An array containing all the items that should go *after* another one
	 *
	 * @var string
	 */
	protected $_all_after = array();

	/**
	 * An array containing all the items that should go *before* another one
	 *
	 * @var string
	 */
	protected $_all_before = array();

	/**
	 * An array containing all the items that should go at the end of the list
	 *
	 * @var string
	 */
	protected $_all_end = array();

	/**
	 * An array containing all the items that should go at the beginning
	 *
	 * @var string
	 */
	protected $_all_begin = array();

	/**
	 * The highest priority assigned at a certain moment for $_all_general
	 *
	 * @var string
	 */
	protected $_general_highest_priority = 0;

	/**
	 * The highest priority assigned at a certain moment for $_all_end
	 *
	 * @var string
	 */
	protected $_end_highest_priority = 10000;

	/**
	 * The highest priority assigned at a certain moment for $_all_begin
	 * Highest priority at "begin" is sort of tricky, because the value is negative
	 *
	 * @var string
	 */
	protected $_begin_highest_priority = -10000;

	/**
	 * Used in prepareContext to store the items in the order
	 * they will be rendered, and used in reverseItems to return
	 * the reverse order for the "below" loop
	 *
	 * @var array
	 */
	protected $_sorted_items = null;

	/**
	 * All the child items of the object
	 *
	 * @var array pairs of strings: the index is the item, the value is an identifier of Positioning_Items, child of another Positioning_Items
	 */
	protected $_children = null;

	/**
	 * Create and initialize an instance of the class
	 *
	 * @param string an identifier
	 */
	public function __construct($id = 'default')
	{
		if (!empty($id))
			$this->_name = $id;

		// Just in case, let's initialize all the relevant variables
		$this->removeAll();

		$this->_known_positions = array('after', 'before', 'end', 'begin');
		return $this;
	}

	/**
	 * Add a new item to the pile
	 *
	 * @param string $key index of a item
	 * @param string $item the item (usually is an array, but it could be anything)
	 * @param int $priority an integer defining the priority of the item.
	 */
	abstract public function add($key, $item = null, $priority = null);

	/**
	 * Do we want to add the item after another one?
	 */
	public function after($position)
	{
		$this->_position = 'after';
		$this->_relative = $position;

		return $this;
	}

	/**
	 * Or before?
	 */
	public function before($position)
	{
		$this->_position = 'before';
		$this->_relative = $position;

		return $this;
	}

	/**
	 * Maybe at the end?
	 */
	public function end()
	{
		$this->_position = 'end';
		$this->_relative = '';

		return $this;
	}

	/**
	 * Or at the beginning of the pile?
	 */
	public function begin()
	{
		$this->_position = 'begin';
		$this->_relative = '';

		return $this;
	}

	/**
	 * Add a bunch of items in one go
	 *
	 * @param string $item name of a item
	 * @param int $priority an integer defining the priority of the item.
	 */
	public function addBulk($items)
	{
		$this->_reset = false;

		foreach ($items as $key => $item)
			$this->add($key, $item);

		$this->_reset = true;
		$this->_position = null;
	}

	/**
	 * Or at the beginning of the pile?
	 */
	public function childOf($parent)
	{
		$this->_position = 'child';
		$this->_relative = $parent;

		return $this;
	}

	public function get($name)
	{
		// First the easy ones
		if ($this->_name === $name)
			return $this;

		// If not, then let's have some fun
		if (!empty($this->_children))
		{
			foreach ($this->_children as $key => $item)
			{
				$found = $item->get($name);

				if ($found)
					return $found;
			}
		}

		return false;
	}

	/**
	 * Remove a item by name
	 *
	 * @param string $item the name of a item
	 */
	public function remove($key)
	{
		if (isset($this->_all_general[$key]))
			unset($this->_all_general[$key]);
		if (isset($this->_all_after[$key]))
			unset($this->_all_after[$key]);
		if (isset($this->_all_before[$key]))
			unset($this->_all_before[$key]);
		if (isset($this->_all_end[$key]))
			unset($this->_all_end[$key]);
		if (isset($this->_all_begin[$key]))
			unset($this->_all_begin[$key]);
	}

	/**
	 * Remove all the items added up to the moment is run
	 */
	public function removeAll()
	{
		$this->_all_general = array();
		$this->_all_after = array();
		$this->_all_before = array();
		$this->_all_end = array();
		$this->_all_begin = array();
		$this->_general_highest_priority = 0;
		$this->_end_highest_priority = 10000;
		$this->_begin_highest_priority = -10000;
	}

	/**
	 * Prepares the items so that they are usable by the template
	 * The function sorts the items according to the priority and saves the
	 * result in $_sorted_items
	 *
	 * @return array the sorted items
	 */
	public function prepareContext()
	{
		$this->_sorted_items = array();

		// Sorting
		asort($this->_all_begin);
		asort($this->_all_general);
		asort($this->_all_end);

		// The easy ones: just merge
		$all_items = array_merge($this->_all_begin, $this->_all_general, $this->_all_end);

		// Now the funny part, let's start with some cleanup: collecting all the items we know and pruning those that cannot be placed somewhere
		$all_known = array_merge(array_keys($all_items), array_keys($this->_all_after), array_keys($this->_all_before));

		$all['before'] = array();
		foreach ($this->_all_before as $key => $value)
			if (in_array($value, $all_known))
				$all['before'][$key] = $value;

		$all['after'] = array();
		foreach ($this->_all_after as $key => $value)
			if (in_array($value, $all_known))
				$all['after'][$key] = $value;

		// This is terribly optimized, though it shouldn't loop over too many things (hopefully)
		// It "iteratively" adds all the after/before items shifting priority
		// of all the other items to ensure each one has a different value
		while (!empty($all['after']) || !empty($all['before']))
		{
			foreach (array('after' => 1, 'before' => -1) as $where => $inc)
			{
				if (empty($all[$where]))
					continue;

				foreach ($all[$where] as $item => $reference)
				{
					if (isset($all_items[$reference]))
					{
						$priority_threshold = $all_items[$reference];
						foreach ($all_items as $key => $val)
						{
							switch ($where)
							{
								case 'after':
									if ($val <= $priority_threshold)
										$all_items[$key] -= $inc;
									break;
								case 'before':
									if ($val >= $priority_threshold)
										$all_items[$key] -= $inc;
									break;
							}
						}
						unset($all[$where][$item]);
						$all_items[$item] = $priority_threshold;
					}
				}
			}
		}

		asort($all_items);

		foreach ($all_items as $key => $priority)
		{
			$this->_sorted_items[$key] = $this->_items[$key];
			if ($this->hasChildren($key))
				$this->_sorted_items[$key]['children'] = $this->_children[$key];
		}

		return $this->_sorted_items;
	}

	/**
	 * Reverse the items order
	 *
	 * @return array the reverse ordered items
	 */
	public function reverseItems()
	{
		if ($this->_prevent_reversing)
			return array();

		if ($this->_sorted_items === null)
			$this->prepareContext();

		return array_reverse($this->_sorted_items);
	}

	/**
	 * Check if at least one item has been added
	 *
	 * @return bool true if at least one item has been added
	 * @todo at that moment _all_after and _all_before are not considered because they may not be "forced"
	 */
	public function hasItems()
	{
		return (!empty($this->_all_general) || !empty($this->_all_begin) || !empty($this->_all_end));
	}

	/**
	 * Check if the current item has any child
	 *
	 * @param string $key the identifier of the menu item
	 * @return bool true if at least one child is found and this child has items
	 */
	public function hasChildren($key)
	{
		if (!empty($this->_children) && !empty($this->_children[$key]))
		{
			return true;
			$has_children = false;
			$children = $this->_children[$key];
			$instance = Positioning_Items::context($children);

			// If there are children then go and check them (recursion)
			if ($instance->hasChildren())
				$has_children = $instance->hasChildren();
			// No children, means it's the last one, so let's come back to the origin telling if there is at least one item
			else
				return $instance->hasItems();

			// If a child is present we can stop here, otherwise we check the next in the pile
			if ($has_children)
				return true;
		}
		else
			return false;
	}

	public function preventReverse()
	{
		$this->_prevent_reversing = true;
	}
}