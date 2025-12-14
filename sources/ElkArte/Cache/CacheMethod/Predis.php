<?php

/**
 * This file contains functions that deal with getting and setting RedisD cache values.
 *
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * @version 2.0 Beta 1
 *
 */

namespace ElkArte\Cache\CacheMethod;

/**
 * Predis
 */
class Predis extends AbstractCacheMethod
{
	/** {@inheritdoc} */
	protected $title = 'Predis';

	/** @var \Predis Predis instance representing the connection to the Redis servers. */
	protected $obj;

	/** @var array */
	protected $server = [];

	/**
	 * {@inheritdoc}
	 */
	public function __construct($options)
	{
	//	require_once(EXTDIR . '/predis/autoload.php');

		if ($this->isAvailable())
		{
			parent::__construct($options);
			$this->addServers();
		}
	}

	/**
	 * {@inheritdoc}
	 */
	public function isAvailable()
	{
		return class_exists('\Predis\Client');
	}

	/**
	 * Add Redis servers.
	 *
	 * Don't add servers if they already exist. Ideal for persistent connections.
	 *
	 * @return bool|null
	 */
	protected function addServers(): ?bool
	{
		if (!empty($this->_options['servers']))
		{
			$server = reset($this->_options['servers']);
			$server = explode(':', trim($server));
			$server[0] = !empty($server[0]) ? $server[0] : 'localhost';
			$server[1] = !empty($server[1]) ? $server[1] : 6379;

			$params = [
				'scheme' => 'tcp',
				'host' => $server[0],
				'port' => $server[1],
			];

			try
			{
				$this->obj = new \Predis\Client($params);
				$this->obj->connect();
				$this->server[] = "tcp://{$server[0]}:{$server[1]}";
			}
			catch (\Predis\Connection\ConnectionException $e)
			{
				// Clear the object, should we log an error here?
				$this->obj = null;
			}
		}

		return false;
	}

	/**
	 * Get redis servers.
	 *
	 * @return array A list of servers in the daemon.
	 */
	protected function getServers(): array
	{
		return reset($this->_options['servers']);
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

		$cache = $this->obj->info();

		if ($cache === false)
		{
			return $results;
		}

		$elapsed = max($cache['Server']['uptime_in_seconds'], 1) / 60;
		$cache['Stats']['tracking_total_keys'] = count($this->obj->keys('*'));

		$results['curr_items'] = comma_format($cache['Stats']['tracking_total_keys'] ?? 0, 0);
		$results['get_hits'] = comma_format($cache['Stats']['keyspace_hits'] ?? 0, 0);
		$results['get_misses'] = comma_format($cache['Stats']['keyspace_misses'] ?? 0, 0);
		$results['curr_connections'] = $cache['Server']['connected_clients'] ?? 0;
		$results['version'] = $cache['Server']['redis_version'] ?? '0.0.0';
		$results['hit_rate'] = sprintf("%.2f", $cache['keyspace_hits'] / $elapsed);
		$results['miss_rate'] = sprintf("%.2f", $cache['keyspace_misses'] / $elapsed);

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
		if (!is_object($this->obj))
		{
			return null;
		}

		$result = $this->obj->get($key);
		$this->is_miss = $result === null;

		return $result;
	}

	/**
	 * {@inheritdoc}
	 */
	public function put($key, $value, $ttl = 120)
	{
		if (!is_object($this->obj))
		{
			return null;
		}

		if ($value === null)
		{
			$this->obj->del($key);
		}

		$this->obj->set($key, $value);
	}

	/**
	 * {@inheritdoc}
	 */
	public function clean($type = '')
	{
		if (!is_object($this->obj))
		{
			return null;
		}

		// Clear it out, really invalidate what's there
		$this->obj->flush();
	}

	/**
	 * {@inheritdoc}
	 */
	public function details()
	{
		if (!is_object($this->obj))
		{
			return [
				'title' => $this->title(),
				'version' => 'n/a'
			];
		}

		$version = $this->obj->info()['Server'];

		return [
			'title' => $this->title(),
			'version' => !empty($version['redis_version']) ? $version['redis_version'] : '0.0.0'
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
			'cache_servers_redis', $txt['cache_redis'], 'file', 'text', 30, 'cache_redis', 'force_div_id' => 'redis_cache_redis',
		];

		$serversmList = $this->getServers();
		$serversmList = empty($serversmList) ? [$txt['admin_search_results_none']] : $serversmList;
		$var['postinput'] = $txt['cache_redis_servers'] . implode('</li><li>', $serversmList) . '</li></ul>';

		$config_vars[] = $var;
	}
}
