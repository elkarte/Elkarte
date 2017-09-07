<?php

/**
 * Functions used to manage template layers
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 1.1
 *
 */

namespace ElkArte\Themes;

use Priority;

/**
 * Class used to manage template layers
 *
 * An instance of the class can be retrieved with the static method getInstance
 */
class TemplateLayers extends Priority
{
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
	 * @param string   $layer    name of a layer
	 * @param int|null $priority an integer defining the priority of the layer.
	 */
	public function add($layer, $priority = null)
	{
		parent::add($layer, $priority);

		if (self::$_error_safe)
		{
			$this->_error_safe_layers[] = $layer;
		}
	}

	/**
	 * Add a layer to the pile before another existing layer
	 *
	 * @param string $layer     the name of a layer
	 * @param string $following the name of the layer before which $layer must be added
	 */
	public function addBefore($layer, $following)
	{
		parent::addBefore($layer, $following);

		if (self::$_error_safe)
		{
			$this->_error_safe_layers[] = $layer;
		}
	}

	/**
	 * Add a layer to the pile after another existing layer
	 *
	 * @param string $layer    the name of a layer
	 * @param string $previous the name of the layer after which $layer must be added
	 */
	public function addAfter($layer, $previous)
	{
		parent::addAfter($layer, $previous);

		if (self::$_error_safe)
		{
			$this->_error_safe_layers[] = $layer;
		}
	}

	/**
	 * Add a layer at the end of the pile
	 *
	 * @param string   $layer    name of a layer
	 * @param int|null $priority an integer defining the priority of the layer.
	 */
	public function addEnd($layer, $priority = null)
	{
		parent::addEnd($layer, $priority);

		if (self::$_error_safe)
		{
			$this->_error_safe_layers[] = $layer;
		}
	}

	/**
	 * Add a layer at the beginning of the pile
	 *
	 * @param string   $layer    name of a layer
	 * @param int|null $priority an integer defining the priority of the layer.
	 */
	public function addBegin($layer, $priority = null)
	{
		parent::addBegin($layer, $priority);

		if (self::$_error_safe)
		{
			$this->_error_safe_layers[] = $layer;
		}
	}

	/**
	 * Prepares the layers so that they are usable by the template
	 * The function sorts the layers according to the priority and saves the
	 * result in $_sorted_entities
	 *
	 * @return array the sorted layers
	 */
	public function prepareContext()
	{
		$all_layers = $this->sort();

		// If we are dealing with an error page (fatal_error) then we have to prune all the unwanted layers
		if ($this->_is_error)
		{
			$dummy = $all_layers;
			$all_layers = [];

			foreach ($dummy as $key => $val)
			{
				if (in_array($key, $this->_error_safe_layers))
				{
					$all_layers[$key] = $val;
				}
			}
		}

		asort($all_layers);
		$this->_sorted_entities = array_keys($all_layers);

		return $this->_sorted_entities;
	}

	/**
	 * Reverse the layers order
	 *
	 * @return array the reverse ordered layers
	 */
	public function reverseLayers()
	{
		if ($this->_sorted_entities === null)
		{
			$this->prepareContext();
		}

		return array_reverse($this->_sorted_entities);
	}

	/**
	 * Check if at least one layer has been added
	 *
	 * @param boolean $base if true will not consider body and html layers in result
	 *
	 * @return bool true if at least one layer has been added
	 * @todo at that moment _all_after and _all_before are not considered because they may not be "forced"
	 */
	public function hasLayers($base = false)
	{
		if (!$base)
		{
			return (!empty($this->_all_general) || !empty($this->_all_begin) || !empty($this->_all_end));
		}
		else
		{
			return array_diff_key(array_merge($this->_all_general, $this->_all_begin, $this->_all_end), [
				'body' => 0,
				'html' => 0,
			]);
		}
	}

	/**
	 * Return the layers that have been loaded
	 */
	public function getLayers()
	{
		return array_keys(array_merge($this->_all_general, $this->_all_begin, $this->_all_end, $this->_all_after,
			$this->_all_before));
	}

	/**
	 * Turns "error mode" on, so that only the allowed layers are displayed
	 */
	public function isError()
	{
		$this->_is_error = true;
	}

	/**
	 * Find and return ElkArte\Theme\TemplateLayers instance if it exists,
	 * or create a new instance if it didn't already exist.
	 *
	 * @deprecated use the theme object
	 *
	 * @param boolean $error_safe if error mode is on or off
	 *
	 * @return ElkArte\Theme\TemplateLayers instance of the class
	 */
	public static function getInstance($error_safe = false)
	{
		if (self::$_instance === null)
		{
			self::$_instance = theme()->getLayers();
		}

		self::$_error_safe = $error_safe;

		return theme()->getLayers();
	}
}
