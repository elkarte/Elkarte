<?php

/**
 * Functions used to manage template layers
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 2.0 dev
 *
 */

namespace ElkArte\Themes;

use \Priority;

/**
 * Class used to manage template layers
 *
 * An instance of the class can be retrieved with the static method instance
 */
class TemplateLayers extends Priority
{
	/**
	 * Layers not removed in case of errors
	 */
	private $_error_safe_layers = [];

	/**
	 * Are we handling an error?
	 * Hopefully not, so default is false
	 */
	private $_is_error = false;

	/**
	 * @return string[]
	 */
	public function getErrorSafeLayers()
	{
		return $this->_error_safe_layers;
	}

	/**
	 * @param string[] $error_safe_layers
	 */
	public function setErrorSafeLayers(array $error_safe_layers)
	{
		$this->_error_safe_layers = $error_safe_layers;
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
}
