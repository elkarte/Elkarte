<?php

/**
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 1.0 Alpha
 *
 */

if (!defined('ELK'))
	die('Hacking attempt...');

/**
 *  This class is an experiment for the job of handling positioning of items.
 *  It has implementation for few simple things like:
 *    - add
 *    - addBefore
 *    - addAfter
 *    - remove
 *    - removeAll
 *  it should be extended in order to actually use its functionalities
 */
class Positioning_Items
{
	/**
	 * Holds the unique identifier of the item (a name).
	 *
	 * @var string
	 * @todo not used?
	 */
	private $_name = null;

	/**
	 * Holds all the items to add
	 *
	 * @var array
	 */
	private $_items = null;

	/**
	 * An array containing all the items added
	 *
	 * @var string
	 */
	private $_all_general = array();

	/**
	 * An array containing all the items that should go *after* another one
	 *
	 * @var string
	 */
	private $_all_after = array();

	/**
	 * An array containing all the items that should go *before* another one
	 *
	 * @var string
	 */
	private $_all_before = array();

	/**
	 * An array containing all the items that should go at the end of the list
	 *
	 * @var string
	 */
	private $_all_end = array();

	/**
	 * An array containing all the items that should go at the beginning
	 *
	 * @var string
	 */
	private $_all_begin = array();

	/**
	 * The highest priority assigned at a certain moment for $_all_general
	 *
	 * @var string
	 */
	private $_general_highest_priority = 0;

	/**
	 * The highest priority assigned at a certain moment for $_all_end
	 *
	 * @var string
	 */
	private $_end_highest_priority = 10000;

	/**
	 * The highest priority assigned at a certain moment for $_all_begin
	 * Highest priority at "begin" is sort of tricky, because the value is negative
	 *
	 * @var string
	 */
	private $_begin_highest_priority = -10000;

	/**
	 * Used in prepareContext to store the items in the order
	 * they will be rendered, and used in reverseItems to return
	 * the reverse order for the "below" loop
	 *
	 * @var array
	 */
	private $_sorted_items = null;

	/**
	 * Multipleton. This is an array of instances of Positioning_Items.
	 * All callers use an item id ('profile', 'menu', or 'default' if none chosen).
	 *
	 * @var array of Positioning_Items
	 */
	private static $_instance = null;

	/**
	 * Create and initialize an instance of the class
	 *
	 * @param string an identifier
	 */
	private function __construct ($id = 'default')
	{
		if (!empty($id))
			$this->_name = $id;

		// Just in case, let's initialize all the relevant variables
		$this->removeAll();
	}

	/**
	 * Add a new item to the pile
	 *
	 * @param string $item name of a item
	 * @param int $priority an integer defining the priority of the item.
	 */
	public function add($key, $item, $priority = null)
	{
		$this->_all_general[$key] = $priority === null ? $this->_general_highest_priority : (int) $priority;
		$this->_items[$key] = $item;
		$this->_general_highest_priority = max($this->_all_general) + 100;
	}

	/**
	 * Add a new item to the pile
	 *
	 * @param string $item name of a item
	 * @param int $priority an integer defining the priority of the item.
	 */
	public function addBulk($items)
	{
		foreach ($items as $key => $item)
		{
			$this->_all_general[$key] = $this->_general_highest_priority;
			$this->_items[$key] = $item;
			$this->_general_highest_priority = max($this->_all_general) + 100;
		}
	}

	/**
	 * Add a item to the pile before another existing item
	 *
	 * @param string $item the name of a item
	 * @param string $following the name of the item before which $item must be added
	 */
	public function addBefore($key, $item, $following)
	{
		$this->_all_before[$key] = $following;
		$this->_items[$key] = $item;
	}

	/**
	 * Add a item to the pile after another existing item
	 *
	 * @param string $item the name of a item
	 * @param string $following the name of the item after which $item must be added
	 */
	public function addAfter($key, $item, $previous)
	{
		$this->_all_after[$key] = $previous;
		$this->_items[$key] = $item;
	}

	/**
	 * Add a item at the end of the pile
	 *
	 * @param string $item name of a item
	 * @param int $priority an integer defining the priority of the item.
	 */
	public function addEnd($key, $item, $priority = null)
	{
		$this->_all_end[$key] = $priority === null ? $this->_end_highest_priority : (int) $priority;
		$this->_items[$key] = $item;
		$this->_end_highest_priority = max($this->_all_end) + 100;
	}

	/**
	 * Add a item at the beginning of the pile
	 *
	 * @param string $item name of a item
	 * @param int $priority an integer defining the priority of the item.
	 */
	public function addBegin($key, $item, $priority = null)
	{
		$this->_all_begin[$key] = $priority === null ? $this->_begin_highest_priority : (int) -$priority;
		$this->_items[$key] = $item;
		$this->_begin_highest_priority = max($this->_all_begin) + 100;
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
		$all_items = array_merge(
			$this->_all_begin,
			$this->_all_general,
			$this->_all_end
		);

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
					if (isset($all_items[$reference]))
					{
						$priority_threshold = $all_items[$reference];
						foreach ($all_items as $key => $val)
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
						unset($all[$where][$item]);
						$all_items[$item] = $priority_threshold;
					}
			}
		}

		asort($all_items);

		foreach ($all_items as $key => $priority)
			$this->_sorted_items[$key] = $this->_items[$key];

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

	public function preventReverse()
	{
		$this->_prevent_reversing = true;
	}

	/**
	 * Find and return Positioning_Items instance if it exists,
	 * or create a new instance for $id if it didn't already exist.
	 *
	 * @return an instance of the class
	 */
	public static function context($id = 'default')
	{
		if (self::$_instance === null)
			self::$_instance = array();
		if (!array_key_exists($id, self::$_instance))
			self::$_instance[$id] = new Positioning_Items($id);

		return self::$_instance[$id];
	}
}