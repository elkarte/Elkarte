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
 * Memcached and Memcache.
 */
class Memcache extends AbstractCacheMethod
{
	/** {@inheritDoc} */
	protected $title = 'Memcache';

	/** @var \Memcache Creates a Memcache instance representing the connection to the memcache servers. */
	protected $obj;

	/** @var bool If the daemon has valid servers in it pool */
	protected $_is_running;

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
			$this->obj = new \Memcache();
			$this->_is_running = $this->addServers();
		}
	}

	/**
	 * {@inheritDoc}
	 */
	public function isAvailable()
	{
		return class_exists('\\Memcache');
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
			$server[1] = empty($server[1]) ? 11211 : $server[1];

			if (!in_array(implode(':', $server), $serversList, true))
			{
				$retVal |= $this->obj->addServer($server[0], $server[1], $this->_is_persist());
				$this->setOptions($server[0], $server[1]);
			}
		}

		return $retVal;
	}

	/**
	 * Get memcache servers.
	 *
	 * @return array A list of servers in the daemon.
	 */
	protected function getServers()
	{
		$servers = @$this->obj->getExtendedStats();

		return empty($servers) ? [] : array_keys((array) $servers);
	}

	/**
	 * If this should be done as a persistent connection
	 *
	 * @return bool
	 */
	private function _is_persist()
	{
		global $db_persist;

		return !empty($db_persist);
	}

	/**
	 * Set a few server specific options.  Could be done as part of setServer
	 * but left here for convenience
	 *
	 * @param string $server
	 * @param int $port
	 */
	protected function setOptions($server, $port)
	{
		// host, port, timeout, retry_interval, status
		$this->obj->setServerParams($server, $port, 1, 5, true);
	}

	/**
	 * Retrieves statistics from the cache server.
	 *
	 * @return array An array containing the cache server statistics.
	 *   - 'curr_items': The number of items currently in the cache.
	 *   - 'get_hits': The number of successful cache lookups.
	 *   - 'get_misses': The number of cache lookups that did not find a matching item.
	 *   - 'curr_connections': The number of currently open connections to the cache server.
	 *   - 'version': The version of the cache server.
	 *   - 'hit_rate': The rate of successful cache lookups per second.
	 *   - 'miss_rate': The rate of cache lookups that did not find a matching item per second.
	 *
	 *  If the statistics cannot be obtained, an empty array is returned.
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
		if (!$this->_is_running)
		{
			return false;
		}

		$result = $this->obj->get($key);
		$this->is_miss = $result === null || $result === false;

		return $result;
	}

	/**
	 * {@inheritDoc}
	 */
	public function put($key, $value, $ttl = 120)
	{
		if (!$this->_is_running)
		{
			return false;
		}

		if ($value === null)
		{
			$this->obj->delete($key);
		}

		$this->obj->set($key, $value, MEMCACHE_COMPRESSED, $ttl);
	}

	/**
	 * {@inheritDoc}
	 */
	public function clean($type = '')
	{
		if (!$this->_is_running)
		{
			return false;
		}

		// Clear it out, really invalidate whats there
		$this->obj->flush();
	}

	/**
	 * {@inheritDoc}
	 */
	public function details()
	{
		$version = @$this->obj->getVersion();

		return [
			'title' => $this->title(),
			'version' => empty($version) ? '0.0.0' : $version
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
			'cache_memcached', $txt['cache_memcache'], 'file', 'text', 30, 'cache_memcached',
			'force_div_id' => 'memcache_cache_memcache',
		];

		$serversmList = $this->getServers();

		if (!empty($serversmList))
		{
			$var['postinput'] = $txt['cache_memcached_servers'] . implode('</li><li>', $serversmList) . '</li></ul>';
		}

		$config_vars[] = $var;
	}
}
