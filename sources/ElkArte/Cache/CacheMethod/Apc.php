<?php

/**
 * This file contains functions that deal with getting and setting cache values.
 *
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * @version 2.0 dev
 *
 */

namespace ElkArte\Cache\CacheMethod;

/**
 * Alternative PHP Cache APCu
 */
class Apc extends AbstractCacheMethod
{
	/** {@inheritdoc} */
	protected $title = 'Alternative PHP Cache';

	/** {@inheritdoc} */
	public function __construct($options)
	{
		parent::__construct($options);
	}

	/**
	 * Retrieves statistics about APCu cache.
	 *
	 * @return array An associative array containing the following cache statistics:
	 *     - 'curr_items' : The number of items currently in the cache (default value is 0 if cache is not available).
	 *     - 'get_hits' : The number of successful cache item fetches.
	 *     - 'get_misses' : The number of cache item fetches that did not find a matching item.
	 *     - 'curr_connections' : The current number of connections to APCu cache (always 1).
	 *     - 'version' : The version of APCu extension installed.
	 *     - 'hit_rate_user' : The user-defined hit rate, expressed as a percentage (rounded to two decimal places).
	 *     - 'miss_rate_user' : The user-defined miss rate, expressed as a percentage (rounded to two decimal places).
	 *
	 *  If the statistics cannot be obtained, an empty array is returned.
     */
	public function getStats()
	{
		$results = [];

		$cache = function_exists('apcu_cache_info') ? apcu_cache_info() : false;
		if ($cache === false)
		{
			return $results;
		}

		// Just a few basics
		$results['curr_items'] = comma_format($cache['num_entries'] ?? 0, 0);
		$results['get_hits'] = comma_format($cache['num_hits'] ?? 0, 0);
		$results['get_misses'] = comma_format($cache['num_misses'] ?? 0, 0);
		$results['curr_connections'] = 1;
		$results['version'] = phpversion('apcu');

		// Seems start_time is really up_time, at least going by its value ?
		$elapsed = max($cache['start_time'], 1);
		$results['hit_rate'] = sprintf("%.2f", $cache['num_hits'] / $elapsed);
		$results['miss_rate'] = sprintf("%.2f", $cache['num_misses'] / $elapsed);

		return $results;
	}

	/**
	 * {@inheritdoc}
	 */
	public function exists($key)
	{
		$this->get($key);

		return !$this->is_miss;
	}

	/**
	 * {@inheritdoc}
	 */
	public function get($key, $ttl = 120)
	{
		$prefixedKey = $this->getprefixedKey($key);
		$success = false;
		$result = apcu_fetch($prefixedKey, $success);
		$this->is_miss = !$success;

		/*
		 * Let's be consistent, yes?
		 * All other cache methods supported by ElkArte return null on failure to grab the specified cache entry.
		 */
		if ($this->is_miss)
		{
			return;
		}

		return $result;
	}

	/**
	 * {@inheritdoc}
	 */
	public function put($key, $value, $ttl = 120)
	{
		$prefixedKey = $this->getprefixedKey($key);

		if ($value === null)
		{
			apcu_delete($prefixedKey);
		}
		else
		{
			apcu_store($prefixedKey, $value, $ttl);
		}
	}

	/**
	 * {@inheritdoc}
	 */
	public function clean($type = '')
	{
		apcu_clear_cache();
	}

	/**
	 * {@inheritdoc}
	 */
	public function isAvailable()
	{
		return function_exists('apcu_store');
	}

	/**
	 * {@inheritdoc}
	 */
	public function details()
	{
		return ['title' => $this->title, 'version' => phpversion('apcu')];
	}
}
