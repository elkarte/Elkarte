<?php

/**
 * Functions used to manage template layers
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 1.0 Release Candidate 1
 *
 */

if (!defined('ELK'))
	die('No access...');

/**
 * Class used to manage template layers
 *
 * An instance of the class can be retrieved with the static method getInstance
 */
class Template_Layers
{
	/**
	 * An array containing all the layers added
	 */
	private $_all_general = array();

	/**
	 * An array containing all the layers that should go *after* another one
	 */
	private $_all_after = array();

	/**
	 * An array containing all the layers that should go *before* another one
	 */
	private $_all_before = array();

	/**
	 * An array containing all the layers that should go at the end of the list
	 */
	private $_all_end = array();

	/**
	 * An array containing all the layers that should go at the beginning
	 */
	private $_all_begin = array();

	/**
	 * The highest priority assigned at a certain moment for $_all_general
	 */
	private $_general_highest_priority = 0;

	/**
	 * The highest priority assigned at a certain moment for $_all_end
	 */
	private $_end_highest_priority = 10000;

	/**
	 * The highest priority assigned at a certain moment for $_all_begin
	 * Highest priority at "begin" is sort of tricky, because the value is negative
	 */
	private $_begin_highest_priority = -10000;

	/**
	 * Used in prepareContext to store the layers in the order
	 * they will be rendered, and used in reverseLayers to return
	 * the reverse order for the "below" loop
	 */
	private $_sorted_layers = null;

	/**
	 * Layers not removed in case of errors
	 */
	private $_error_safe_layers = null;

	/**
	 * Are we handling an error?
	 * Hopefully not, so default is false
	 */
	private $_is_error = false;

	/**
	 * The layers added when this is true will be used in the error screen
	 */
	private static $_error_safe = false;

	/**
	 * Instance of the class
	 */
	private static $_instance = null;

	/**
	 * Add a new layer to the pile
	 *
	 * @param string $layer name of a layer
	 * @param int|null $priority an integer defining the priority of the layer.
	 */
	public function add($layer, $priority = null)
	{
		$this->_all_general[$layer] = $priority === null ? $this->_general_highest_priority : (int) $priority;
		$this->_general_highest_priority = max($this->_all_general) + 100;

		if (self::$_error_safe)
			$this->_error_safe_layers[] = $layer;
	}

	/**
	 * Add a layer to the pile before another existing layer
	 *
	 * @param string $layer the name of a layer
	 * @param string $following the name of the layer before which $layer must be added
	 */
	public function addBefore($layer, $following)
	{
		$this->_all_before[$layer] = $following;

		if (self::$_error_safe)
			$this->_error_safe_layers[] = $layer;
	}

	/**
	 * Add a layer to the pile after another existing layer
	 *
	 * @param string $layer the name of a layer
	 * @param string $previous the name of the layer after which $layer must be added
	 */
	public function addAfter($layer, $previous)
	{
		$this->_all_after[$layer] = $previous;

		if (self::$_error_safe)
			$this->_error_safe_layers[] = $layer;
	}

	/**
	 * Add a layer at the end of the pile
	 *
	 * @param string $layer name of a layer
	 * @param int|null $priority an integer defining the priority of the layer.
	 */
	public function addEnd($layer, $priority = null)
	{
		$this->_all_end[$layer] = $priority === null ? $this->_end_highest_priority : (int) $priority;
		$this->_end_highest_priority = max($this->_all_end) + 100;

		if (self::$_error_safe)
			$this->_error_safe_layers[] = $layer;
	}

	/**
	 * Add a layer at the beginning of the pile
	 *
	 * @param string $layer name of a layer
	 * @param int|null $priority an integer defining the priority of the layer.
	 */
	public function addBegin($layer, $priority = null)
	{
		$this->_all_begin[$layer] = $priority === null ? $this->_begin_highest_priority : (int) -$priority;
		$this->_begin_highest_priority = max($this->_all_begin) + 100;

		if (self::$_error_safe)
			$this->_error_safe_layers[] = $layer;
	}

	/**
	 * Remove a layer by name
	 *
	 * @param string $layer the name of a layer
	 */
	public function remove($layer)
	{
		if (isset($this->_all_general[$layer]))
			unset($this->_all_general[$layer]);
		elseif (isset($this->_all_after[$layer]))
			unset($this->_all_after[$layer]);
		elseif (isset($this->_all_before[$layer]))
			unset($this->_all_before[$layer]);
		elseif (isset($this->_all_end[$layer]))
			unset($this->_all_end[$layer]);
		elseif (isset($this->_all_begin[$layer]))
			unset($this->_all_begin[$layer]);
	}

	/**
	 * Remove all the layers added up to the moment is run
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
	 * Prepares the layers so that they are usable by the template
	 * The function sorts the layers according to the priority and saves the
	 * result in $_sorted_layers
	 *
	 * @return array the sorted layers
	 */
	public function prepareContext()
	{
		$this->_sorted_layers = array();

		// Sorting
		asort($this->_all_begin);
		asort($this->_all_general);
		asort($this->_all_end);

		// The easy ones: just merge
		$all_layers = array_merge(
			$this->_all_begin,
			$this->_all_general,
			$this->_all_end
		);

		// Now the funny part, let's start with some cleanup: collecting all the layers we know and pruning those that cannot be placed somewhere
		$all_known = array_merge(array_keys($all_layers), array_keys($this->_all_after), array_keys($this->_all_before));

		$all['before'] = array();
		foreach ($this->_all_before as $key => $value)
			if (in_array($value, $all_known))
				$all['before'][$key] = $value;

		$all['after'] = array();
		foreach ($this->_all_after as $key => $value)
			if (in_array($value, $all_known))
				$all['after'][$key] = $value;

		// This is terribly optimized, though it shouldn't loop over too many things (hopefully)
		// It "iteratively" adds all the after/before layers shifting priority
		// of all the other layers to ensure each one has a different value
		while (!empty($all['after']) || !empty($all['before']))
		{
			foreach (array('after' => 1, 'before' => -1) as $where => $inc)
			{
				if (empty($all[$where]))
					continue;

				foreach ($all[$where] as $layer => $reference)
					if (isset($all_layers[$reference]))
					{
						$priority_threshold = $all_layers[$reference];
						foreach ($all_layers as $key => $val)
							switch ($where)
							{
								case 'after':
									if ($val <= $priority_threshold)
										$all_layers[$key] -= $inc;
									break;
								case 'before':
									if ($val >= $priority_threshold)
										$all_layers[$key] -= $inc;
									break;
							}
						unset($all[$where][$layer]);
						$all_layers[$layer] = $priority_threshold;
					}
			}
		}

		// If we are dealing with an error page (fatal_error) then we have to prune all the unwanted layers
		if ($this->_is_error)
		{
			$dummy = $all_layers;
			$all_layers = array();
			foreach ($dummy as $key => $val)
				if (in_array($key, $this->_error_safe_layers))
					$all_layers[$key] = $val;
		}

		asort($all_layers);
		$this->_sorted_layers = array_keys($all_layers);

		return $this->_sorted_layers;
	}

	/**
	 * Reverse the layers order
	 *
	 * @return array the reverse ordered layers
	 */
	public function reverseLayers()
	{
		if ($this->_sorted_layers === null)
			$this->prepareContext();

		return array_reverse($this->_sorted_layers);
	}

	/**
	 * Check if at least one layer has been added
	 *
	 * @param boolean $base if true will not consider body and html layers in result
	 * @return bool true if at least one layer has been added
	 * @todo at that moment _all_after and _all_before are not considered because they may not be "forced"
	 */
	public function hasLayers($base = false)
	{
		if (!$base)
			return (!empty($this->_all_general) || !empty($this->_all_begin) || !empty($this->_all_end));
		else
			return array_diff_key(array_merge($this->_all_general, $this->_all_begin, $this->_all_end), array('body' => 0, 'html' => 0));
	}

	/**
	 * Return the layers that have been loaded
	 */
	public function getLayers()
	{
		return array_keys(array_merge($this->_all_general, $this->_all_begin, $this->_all_end, $this->_all_after, $this->_all_before));
	}

	/**
	 * Turns "error mode" on, so that only the allowed layers are displayed
	 */
	public function isError()
	{
		$this->_is_error = true;
	}

	/**
	 * Find and return Template_Layers instance if it exists,
	 * or create a new instance if it didn't already exist.
	 *
	 * @param boolean $error_safe if error mode is on or off
	 * @return Template_Layers instance of the class
	 */
	public static function getInstance($error_safe = false)
	{
		if (self::$_instance === null)
			self::$_instance = new Template_Layers();

		self::$_error_safe = $error_safe;

		return self::$_instance;
	}
}