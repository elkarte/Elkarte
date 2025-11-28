<?php

/**
 * Functions used to manage template layers
 *
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * @version 2.0 dev
 *
 */

namespace ElkArte\Themes;

use ElkArte\Priority;

/**
 * Class used to manage template layers
 *
 * An instance of the class can be retrieved with the static method instance
 */
class TemplateLayers extends Priority
{
	/** @var array Layers aren't removed in case of errors */
	private $_error_safe_layers = [];

	/** @var bool Are we handling an error? Hopefully not, so default is false */
	private $_is_error = false;

	/**
	 * @return string[]
	 */
	public function getErrorSafeLayers(): array
	{
		return $this->_error_safe_layers;
	}

	/**
	 * @param string[] $error_safe_layers
	 */
	public function setErrorSafeLayers(array $error_safe_layers): void
	{
		$this->_error_safe_layers = $error_safe_layers;
	}

	/**
	 * Reverse the layer order
	 *
	 * @return array The reverse-ordered layers
	 */
	public function reverseLayers(): array
	{
		if ($this->_sorted_entities === null)
		{
			$this->prepareContext();
		}

		return array_reverse($this->_sorted_entities);
	}

	/**
	 * Prepares the layers so that they are usable by the template
	 * The function sorts the layers according to the priority and saves the
	 * result in $_sorted_entities
	 *
	 * @return array the sorted layers
	 */
	public function prepareContext(): array
	{
		$all_layers = $this->sort();

		// If we are dealing with an error page (fatal_error) then we have to prune all the unwanted layers
		if ($this->_is_error)
		{
			$dummy = $all_layers;

			$all_layers = array_filter($dummy, function ($key) {
				return in_array($key, $this->_error_safe_layers);
			}, ARRAY_FILTER_USE_KEY);
		}

		asort($all_layers);
		$this->_sorted_entities = array_keys($all_layers);

		return $this->_sorted_entities;
	}

	/**
	 * Check if at least one layer has been added
	 *
	 * @param bool $base if true will not consider body and html layers in result
	 *
	 * @return array|bool true if at least one layer has been added
	 * @todo at that moment _all_after and _all_before are not considered because they may not be "forced"
	 */
	public function hasLayers($base = false)
	{
		if (!$base)
		{
			return (!empty($this->_all_general) || !empty($this->_all_begin) || !empty($this->_all_end));
		}

		return array_diff_key(array_merge($this->_all_general, $this->_all_begin, $this->_all_end), [
			'body' => 0,
			'html' => 0,
		]);
	}

	/**
	 * Checks if a specific layer has been loaded
	 *
	 * @param string $layerName The name of the layer to check
	 * @return bool True if the layer exists, false otherwise
	 */
	public function hasLayer(string $layerName): bool
	{
		return array_key_exists($layerName, $this->getLoadedLayers());
	}

	/**
	 * Retrieves all the currently loaded layers by combining various categories of layers.
	 * The method merges general layers, begin layers, end layers, after layers, and before layers into a single array.
	 *
	 * @return array the combined list of all loaded layers
	 */
	public function getLoadedLayers(): array
	{
		return array_merge($this->_all_general, $this->_all_begin, $this->_all_end, $this->_all_after, $this->_all_before);
	}

	/**
	 * Retrieves all layers by merging different layer groups and returning their keys.
	 *
	 * @return array the keys of the merged layer groups
	 */
	public function getLayers(): array
	{
		return array_keys(array_merge($this->_all_general, $this->_all_begin, $this->_all_end, $this->_all_after, $this->_all_before));
	}

	/**
	 * Turns "error mode" on, so that only the allowed layers are displayed
	 */
	public function isError(): void
	{
		$this->_is_error = true;
	}
}
