<?php

/**
 * This file contains functions that deal with getting and setting memcacheD cache values.
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
 * Memcached
 */
class Memcached extends AbstractCacheMethod
{
	/** {@inheritDoc} */
	protected $title = 'Memcached';

	/** @var \Memcached Memcached instance representing the connection to the memcache servers. */
	protected $obj;

	/**
	 * {@inheritDoc}
	 */
	public function __construct($options)
	{
		if (empty($options['servers']))
		{
			$options['servers'] = [''];
		}

		parent::__construct($options);

		if ($this->isAvailable())
		{
			$this->obj = new \Memcached($this->_is_persist());
			$this->setOptions();
			$this->addServers();
		}
	}

	/**
	 * {@inheritDoc}
	 */
	public function isAvailable()
	{
		return class_exists('\\Memcached');
	}

	/**
	 * If this should be done as a persistent connection
	 *
	 * @return string|null
	 */
	private function _is_persist()
	{
		global $db_persist;

		return empty($db_persist) ? null : $this->prefix . '_memcached';
	}

	/**
	 * {@inheritDoc}
	 */
	protected function setOptions()
	{
		/*
		 * the timeout after which a server is considered DEAD.
		 */
		$this->obj->setOption(\Memcached::OPT_CONNECT_TIMEOUT, 100);

		/*
		 * If one memcached node is dead, its keys (and only its
		 * keys) will be evenly distributed to other nodes.
		 */
		$this->obj->setOption(\Memcached::OPT_DISTRIBUTION, \Memcached::DISTRIBUTION_CONSISTENT);

		/*
		 * number of connection issues before a server is marked
		 * as DEAD, and removed from the list of servers.
		 */
		$this->obj->setOption(\Memcached::OPT_SERVER_FAILURE_LIMIT, 2);

		/*
		 * enables the removal of dead servers.
		 */
		$this->obj->setOption(\Memcached::OPT_REMOVE_FAILED_SERVERS, true);

		/*
		 * after a node is declared DEAD, libmemcached will
		 * try it again after that many seconds.
		 */
		$this->obj->setOption(\Memcached::OPT_RETRY_TIMEOUT, 1);
	}

	/**
	 * Add memcache servers.
	 *
	 * Don't add servers if they already exist. Ideal for persistent connections.
	 *
	 * @return bool True if there are servers in the daemon, false if not.
	 */
	protected function addServers()
	{
		$serversList = $this->getServers();
		$retVal = !empty($serversList);

		foreach ($this->_options['servers'] as $server)
		{
			$server = explode(':', trim($server));
			$server[0] = empty($server[0]) ? 'localhost' : $server[0];
			$server[1] = empty($server[1]) ? 11211 : (int) $server[1];

			if (!in_array(implode(':', $server), $serversList, true))
			{
				$retVal |= $this->obj->addServer($server[0], $server[1]);
			}
		}

		return $retVal;
	}

	/**
	 * Get memcached servers.
	 *
	 * @return array A list of servers in the daemon.
	 */
	protected function getServers()
	{
		$list = $this->obj->getStats();

		return $list === false ? [] : array_keys((array) $this->obj->getStats());
	}

	/**
	 * Retrieves statistics about the cache.
	 *
	 * @return array An associative array containing the cache statistics.
	 *    The array has the following keys:
	 *      - curr_items: The number of items currently stored in the cache.
	 *      - get_hits: The number of successful cache hits.
	 *      - get_misses: The number of cache misses.
	 *      - curr_connections: The number of current open connections to the cache server.
	 *      - version: The version of the cache server.
	 *      - hit_rate: The cache hit rate as a decimal value with two decimal places.
	 *      - miss_rate: The cache miss rate as a decimal value with two decimal places.
	 *
	 * If the statistics cannot be obtained, an empty array is returned.
	 */
	public function getStats()
	{
		$results = [];

		$cache = $this->obj->getStats();
		if ($cache === false)
		{
			return $results;
		}

		// Only user the first server
		reset($cache);
		$server = current($cache);
		$elapsed = max($server['uptime'], 1);

		$results['curr_items'] = comma_format($server['curr_items'] ?? 0, 0);
		$results['get_hits'] = comma_format($server['get_hits'] ?? 0, 0);
		$results['get_misses'] = comma_format($server['get_misses'] ?? 0, 0);
		$results['curr_connections'] = $server['curr_connections'] ?? 0;
		$results['version'] = $server['version'];
		$results['hit_rate'] = sprintf("%.2f", $server['get_hits'] / $elapsed);
		$results['miss_rate'] = sprintf("%.2f", $server['get_misses'] / $elapsed);

		return $results;
	}

	/**
	 * {@inheritDoc}
	 */
	public function exists($key)
	{
		$this->get($key);

		return !$this->is_miss;
	}

	/**
	 * {@inheritDoc}
	 */
	public function get($key, $ttl = 120)
	{
		$result = $this->obj->get($key);
		$this->is_miss = $result === null || $result === false;

		return $result;
	}

	/**
	 * {@inheritDoc}
	 */
	public function put($key, $value, $ttl = 120)
	{
		if ($value === null)
		{
			$this->obj->delete($key);
		}

		$this->obj->set($key, $value, $ttl);
	}

	/**
	 * {@inheritDoc}
	 */
	public function clean($type = '')
	{
		// Clear it out, really invalidate whats there
		$this->obj->flush();
	}

	/**
	 * {@inheritDoc}
	 */
	public function details()
	{
		$version = $this->obj->getVersion();

		return [
			'title' => $this->title(),
			'version' => empty($version) ? '0.0.0' : current($version)
		];
	}

	/**
	 * Adds the settings to the settings page.
	 *
	 * Used by integrate_modify_cache_settings added in the title method
	 *
	 * @param array $config_vars
	 */
	public function settings(&$config_vars)
	{
		global $txt;

		$var = [
			'cache_memcached', $txt['cache_memcached'], 'file', 'text', 30, 'cache_memcached',
			'force_div_id' => 'memcached_cache_memcached',
		];

		$serversList = $this->getServers();
		$serversList = empty($serversList) ? [$txt['admin_search_results_none']] : $serversList;
		$var['postinput'] = $txt['cache_memcached_servers'] . implode('</li><li>', $serversList) . '</li></ul>';

		$config_vars[] = $var;
	}
}
