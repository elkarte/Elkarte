<?php

/**
 * This file contains functions that deal with getting and setting Redis cache values.
 *
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * @version 2.0 Beta 1
 *
 */

namespace ElkArte\Cache\CacheMethod;

use ElkArte\Helper\HttpReq;

/**
 * Redis
 */
class Redis extends AbstractCacheMethod
{
	/** {@inheritDoc} */
	protected $title = 'Redis';

	/** @var \Redis instance representing the connection to the redis servers. */
	protected $obj;

	/** @var bool If the connection to the server is successful */
	protected $isConnected = false;

	/**
	 * {@inheritDoc}
	 */
	public function __construct($options)
	{
		parent::__construct($options);

		if ($this->isAvailable())
		{
			$this->obj = new \Redis();
			$this->addServers();
			$this->setOptions();
			$this->setSerializerValue();
			$this->isConnected();
		}
	}

	/**
	 * {@inheritDoc}
	 */
	public function isAvailable()
	{
		return class_exists(\Redis::class);
	}

	/**
	 * Check if the connection to Redis server is active.
	 *
	 * @return bool Returns true if the connection is active, false otherwise.
	 */
	public function isConnected(): bool
	{
		try
		{
			$this->isConnected = $this->obj->ping();
		}
		catch (\RedisException $e)
		{
			$this->isConnected = false;
		}

		return $this->isConnected;
	}

	/**
	 * If this should be done as a persistent connection
	 *
	 * @return string|null
	 */
	private function _is_persist(): ?string
	{
		global $db_persist;

		return empty($db_persist) ? null : $this->prefix . '_redis';
	}

	/**
	 * Set the options for the redis connection.
	 */
	protected function setOptions(): void
	{
		try
		{
			if (!empty($this->_options['cache_password']))
			{
				$this->obj->auth($this->_options['cache_password']);
			}

			if (!empty($this->_options['cache_uid']))
			{
				$this->obj->select($this->_options['cache_uid']);
			}
		}
		catch (\RedisException $e)
		{
			$this->isConnected = false;
		}
	}

	/**
	 * Returns the redis serializer value based on certain conditions.
	 */
	private function setSerializerValue(): void
	{
		$serializer = $this->obj::SERIALIZER_PHP;
		if (defined('Redis::SERIALIZER_IGBINARY') && extension_loaded('igbinary'))
		{
			$serializer = $this->obj::SERIALIZER_IGBINARY;
		}

		try
		{
			$this->obj->setOption($this->obj::OPT_SERIALIZER, $serializer);
		}
		catch (\RedisException $e)
		{
			$this->isConnected = false;
		}
	}

	/**
	 * Add redis server.  Currently, does not support RedisArray / RedisCluster
	 *
	 * @return bool True if there are servers in the daemon, false if not.
	 */
	protected function addServers(): bool
	{
		$retVal = false;

		$server = reset($this->_options['servers']);
		if ($server !== false)
		{
			$server = explode(':', trim($server));
			$host = empty($server[0]) ? 'localhost' : $server[0];
			$port = empty($server[1]) ? 6379 : (int) $server[1];

			set_error_handler(static function () { /* ignore php_network_getaddresses errors */ });
			try
			{
				if ($this->_is_persist())
				{
					$retVal = $this->obj->pconnect($host, $port, 0.0, $this->_is_persist());
				}
				else
				{
					$retVal = $this->obj->connect($host, $port, 0.0);
				}
			}
			catch (\RedisException $e)
			{
				$retVal = false;
			}
			finally
			{
				restore_error_handler();
			}
		}

		return $retVal;
	}

	/**
	 * Get redis servers.
	 *
	 * @return string A server name if we are attached.
	 */
	protected function getServers(): string
	{
		$server = '';

		if ($this->isConnected())
		{
			$server = reset($this->_options['servers']);
		}

		return $server;
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
	public function getStats(): array
	{
		$results = [];

		try
		{
			$cache = $this->obj->info();
		}
		catch (\RedisException $e)
		{
			$cache = false;
		}

		if ($cache === false)
		{
			return $results;
		}

		$elapsed = max($cache['uptime_in_seconds'], 1) / 60;
		$cache['tracking_total_keys'] = count($this->obj->keys('*'));

		$results['curr_items'] = comma_format($cache['tracking_total_keys'] ?? 0, 0);
		$results['get_hits'] = comma_format($cache['keyspace_hits'] ?? 0, 0);
		$results['get_misses'] = comma_format($cache['keyspace_misses'] ?? 0, 0);
		$results['curr_connections'] = $cache['connected_clients'] ?? 0;
		$results['version'] = $cache['redis_version'] ?? '0.0.0';
		$results['hit_rate'] = sprintf("%.2f", $cache['keyspace_hits'] / $elapsed);
		$results['miss_rate'] = sprintf("%.2f", $cache['keyspace_misses'] / $elapsed);

		return $results;
	}

	/**
	 * {@inheritDoc}
	 */
	public function exists($key)
	{
		return $this->obj->exists($key);
	}

	/**
	 * {@inheritDoc}
	 */
	public function get($key, $ttl = 120)
	{
		if (!$this->isConnected)
		{
			return false;
		}

		try
		{
			$result = $this->obj->get($key);
		}
		catch (\RedisException $e)
		{
			$result = null;
		}

		$this->is_miss = $result === null || $result === false;

		return $result;
	}

	/**
	 * {@inheritDoc}
	 */
	public function put($key, $value, $ttl = 120)
	{
		if (!$this->isConnected)
		{
			return null;
		}

		try
		{
			if ($value === null)
			{
				$this->obj->del($key);
			}

			if ($ttl > 0)
			{
				return $this->obj->setex($key, $ttl, $value);
			}

			return $this->obj->set($key, $value);
		}
		catch (\RedisException $e)
		{
			return false;
		}
	}

	/**
	 * {@inheritDoc}
	 */
	public function clean($type = '')
	{
		// Clear it out
		$this->obj->flushDB();
	}

	/**
	 * {@inheritDoc}
	 */
	public function details()
	{
		return [
			'title' => $this->title(),
			'version' => phpversion('redis')
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
		global $txt, $cache_servers, $cache_servers_redis;

		$var = [
			'cache_servers_redis', $txt['cache_redis'], 'file', 'text', 30, 'cache_redis', 'force_div_id' => 'redis_cache_redis',
		];

		// Use generic global cache_servers value to load the initial form value
		if (HttpReq::instance()->getQuery('save') === null)
		{
			$cache_servers_redis = $cache_servers;
		}

		$serversList = $this->getServers();
		$serversList = empty($serversList) ? $txt['admin_search_results_none'] : $serversList;
		$var['postinput'] = $txt['cache_redis_servers'] . $serversList . '</li></ul>';

		$config_vars[] = $var;
		$config_vars[] = ['cache_uid', $txt['cache_uid'], 'file', 'text', $txt['cache_uid'], 'cache_uid', 'force_div_id' => 'redis_cache_uid'];
		$config_vars[] = ['cache_password', $txt['cache_password'], 'file', 'password', $txt['cache_password'], 'cache_password', 'force_div_id' => 'redis_cache_password', 'skip_verify_pass' => true];
	}
}
