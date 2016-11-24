<?php

/**
 * An abstract class to deal with priority
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 1.1 beta 3
 *
 */

/**
 */
class Priority
{
	/**
	 * An array containing all the entities added
	 */
	protected $_all_general = array();

	/**
	 * An array containing all the entities that should go *after* another one
	 */
	protected $_all_after = array();

	/**
	 * An array containing all the entities that should go *before* another one
	 */
	protected $_all_before = array();

	/**
	 * An array containing all the entities that should go at the end of the list
	 */
	protected $_all_end = array();

	/**
	 * An array containing all the entities that should go at the beginning
	 */
	protected $_all_begin = array();

	/**
	 * The highest priority assigned at a certain moment for $_all_general
	 */
	protected $_general_highest_priority = 0;

	/**
	 * The highest priority assigned at a certain moment for $_all_end
	 */
	protected $_end_highest_priority = 10000;

	/**
	 * The highest priority assigned at a certain moment for $_all_begin
	 * Highest priority at "begin" is sort of tricky, because the value is negative
	 */
	protected $_begin_highest_priority = -10000;

	/**
	 * Array of sorted entities
	 */
	protected $_sorted_entities = null;

	const STDPRIORITY = 0;

	/**
	 * Add a new entity to the pile
	 *
	 * @param string $entity name of a entity
	 * @param int|null $priority an integer defining the priority of the entity.
	 */
	public function add($entity, $priority = null)
	{
		$this->_all_general[$entity] = $priority === null ? $this->_general_highest_priority : (int) $priority;
		$this->_general_highest_priority = max($this->_all_general) + 100;
	}

	/**
	 * Add an entity to the pile before another existing entity
	 *
	 * @param string $entity the name of a entity
	 * @param string $following the name of the entity before which $entity must be added
	 */
	public function addBefore($entity, $following)
	{
		$this->_all_before[$entity] = $following;
	}

	/**
	 * Add a entity to the pile after another existing entity
	 *
	 * @param string $entity the name of a entity
	 * @param string $previous the name of the entity after which $entity must be added
	 */
	public function addAfter($entity, $previous)
	{
		$this->_all_after[$entity] = $previous;
	}

	/**
	 * Add a entity at the end of the pile
	 *
	 * @param string $entity name of a entity
	 * @param int|null $priority an integer defining the priority of the entity.
	 */
	public function addEnd($entity, $priority = null)
	{
		$this->_all_end[$entity] = $priority === null ? $this->_end_highest_priority : (int) $priority;
		$this->_end_highest_priority = max($this->_all_end) + 100;
	}

	/**
	 * Add a entity at the beginning of the pile
	 *
	 * @param string $entity name of a entity
	 * @param int|null $priority an integer defining the priority of the entity.
	 */
	public function addBegin($entity, $priority = null)
	{
		$this->_all_begin[$entity] = $priority === null ? $this->_begin_highest_priority : (int) -$priority;
		$this->_begin_highest_priority = max($this->_all_begin) + 100;
	}

	/**
	 * Remove a entity by name
	 *
	 * @param string $entity the name of a entity
	 */
	public function remove($entity)
	{
		if (isset($this->_all_general[$entity]))
			unset($this->_all_general[$entity]);
		elseif (isset($this->_all_after[$entity]))
			unset($this->_all_after[$entity]);
		elseif (isset($this->_all_before[$entity]))
			unset($this->_all_before[$entity]);
		elseif (isset($this->_all_end[$entity]))
			unset($this->_all_end[$entity]);
		elseif (isset($this->_all_begin[$entity]))
			unset($this->_all_begin[$entity]);
	}

	/**
	 * Remove all the entities added up to the moment the function is called
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
	 * The function sorts the entities according to the priority and saves the
	 * result in $_sorted_entities
	 *
	 * @return array the sorted entities with priority
	 */
	public function sort()
	{
		$this->_sorted_entities = array();

		// Sorting
		asort($this->_all_begin);
		asort($this->_all_general);
		asort($this->_all_end);

		// The easy ones: just merge
		$all_entities = array_merge(
			$this->_all_begin,
			$this->_all_general,
			$this->_all_end
		);

		// Now the funny part, let's start with some cleanup: collecting all the entities we know and pruning those that cannot be placed somewhere
		$all_known = array_merge(array_keys($all_entities), array_keys($this->_all_after), array_keys($this->_all_before));

		$all = array(
			'before' => array(),
			'after' => array()
		);
		foreach ($this->_all_before as $key => $value)
			if (in_array($value, $all_known))
				$all['before'][$key] = $value;

		foreach ($this->_all_after as $key => $value)
			if (in_array($value, $all_known))
				$all['after'][$key] = $value;

		// This is terribly optimized, though it shouldn't loop over too many things (hopefully)
		// It "iteratively" adds all the after/before entities shifting priority
		// of all the other entities to ensure each one has a different value
		while (!empty($all['after']) || !empty($all['before']))
		{
			foreach (array('after' => 1, 'before' => -1) as $where => $inc)
			{
				if (empty($all[$where]))
					continue;

				foreach ($all[$where] as $entity => $reference)
					if (isset($all_entities[$reference]))
					{
						$priority_threshold = $all_entities[$reference];
						foreach ($all_entities as $key => $val)
							switch ($where)
							{
								case 'after':
									if ($val <= $priority_threshold)
										$all_entities[$key] -= $inc;
									break;
								case 'before':
									if ($val >= $priority_threshold)
										$all_entities[$key] -= $inc;
									break;
							}
						unset($all[$where][$entity]);
						$all_entities[$entity] = $priority_threshold;
					}
			}
		}

		asort($all_entities);
		$this->_sorted_entities = array_keys($all_entities);

		return $all_entities;
	}

	/**
	 * Check if at least one entity has been added
	 *
	 * @return bool true if at least one entity has been added
	 * @todo at that moment _all_after and _all_before are not considered because they may not be "forced"
	 */
	public function hasEntities()
	{
		if ($this->_sorted_entities === null)
			return (!empty($this->_all_general) || !empty($this->_all_begin) || !empty($this->_all_end));
		else
			return !empty($this->_sorted_entities);
	}

	/**
	 * Return the entities that have been loaded
	 */
	public function getEntities()
	{
		return array_keys(array_merge($this->_all_general, $this->_all_begin, $this->_all_end, $this->_all_after, $this->_all_before));
	}

	/**
	 * Return the entities that have been loaded
	 */
	public function getSortedEntities()
	{
		return $this->_sorted_entities;
	}
}
