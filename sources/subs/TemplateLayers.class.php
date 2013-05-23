<?php

/**
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 1.0 Alpha
 *
 */

if (!defined('ELKARTE'))
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
	private $_all_layers = array();

	/**
	 * The highest priority assigned at a certain moment
	 */
	private $_highest_priority = 100;

	/**
	 * Used in prepareContext to store the layers in the order
	 * they will be rendered, and used in reverseLayers to return
	 * the reverse order for the "below" loop
	 */
	private $_sorted_layers = null;

	/**
	 * Instance of the class
	 */
	private static $_instance = null;


	/**
	 * Add a new layer to the pile
	 *
	 * @param mixed $layers can be a single layer or an array of layers
	 *              if an array is passed it must be in the form:
	 *                array('layer_name' => PRIORITY)
	 *              where PRIORITY is an integer
	 * @param int $priority an integer defining the priority of the layer.
	 *            If $layers is an array $priority is ignored
	 */
	public function add($layers = array(), $priority = null)
	{
		if (!is_array($layers))
			$layers = array($layers => $priority === null ? $this->_highest_priority : (int) $priority);

		$this->_all_layers = array_merge($this->_all_layers, $layers);
		$this->_highest_priority = max($this->_all_layers) + 100;
	}

	/**
	 * Add a layer to the pile before another existing layer
	 *
	 * @param string $layer the name of a layer
	 * @param string $following the name of the layer before which $layer must be added
	 * @param bool $force if true $layer is added even if $following doesn't exist (default false)
	 */
	public function addBefore($layer, $following, $force = false)
	{
		if (isset($this->_all_layers[$following]))
			$this->add(array($layer => $this->_all_layers[$following] - 1));
		elseif ($force)
			$this->add($layer);
	}

	/**
	 * Add a layer to the pile after another existing layer
	 *
	 * @param string $layer the name of a layer
	 * @param string $following the name of the layer after which $layer must be added
	 * @param bool $force if true $layer is added even if $following doesn't exist (default false)
	 */
	public function addAfter($layer, $previous, $force = false)
	{
		if (isset($this->_all_layers[$previous]))
			$this->add(array($layer => $this->_all_layers[$previous] + 1));
		elseif ($force)
			$this->add($layer);
	}

	/**
	 * Remove a layer by name
	 *
	 * @param string $layer the name of a layer
	 */
	public function remove($layer)
	{
		if (isset($this->_all_layers[$layer]))
			unset($this->_all_layers[$layer]);
	}

	/**
	 * Remove all the layers added up to the moment is run
	 */
	public function removeAll()
	{
		$this->_all_layers = array();
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

		asort($this->_all_layers);
		$this->_sorted_layers = array_keys($this->_all_layers);

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
	 * @return bool true if at least one layer has been added
	 */
	public function hasLayers()
	{
		return !empty($this->_all_layers);
	}

	/**
	 * Find and return Template_Layers instance if it exists,
	 * or create a new instance if it didn't already exist.
	 *
	 * @return an instance of the class
	 */
	public static function getInstance()
	{
		if (self::$_instance === null)
			self::$_instance = new Template_Layers();

		return self::$_instance;
	}
}