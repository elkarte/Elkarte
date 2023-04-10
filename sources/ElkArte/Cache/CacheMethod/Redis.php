<?php

/**
 * This file contains functions that deal with getting and setting RedisD cache values.
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
 * Redis
 */
class Redis extends AbstractCacheMethod
{
	/** {@inheritdoc} */
	protected $title = 'Redis';

	/** @var \Redis Redis instance representing the connection to the Redis servers. */
	protected $obj;

	/**
	 * {@inheritdoc}
	 */
	public function __construct($options)
	{
		require_once(EXTDIR . '/predis/autoload.php');

		if (empty($options['servers']))
		{
			$options['servers'] = array('');
		}

		parent::__construct($options);

		if ($this->isAvailable())
		{
			$this->obj	= new \Predis\Client('tcp://172.19.0.5:6379');
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
	 * If this should be done as a persistent connection
	 *
	 * @return string|null
	 */
	private function _is_persist()
	{
		global $db_persist;

		return !empty($db_persist) ? $this->prefix . '_redis' : null;
	}

	/**
	 * {@inheritdoc}
	 */
	protected function setOptions()
	{
	
	}

	/**
	 * Add Redis servers.
	 *
	 * Don't add servers if they already exist. Ideal for persistent connections.
	 *
	 * @return bool True if there are servers in the daemon, false if not.
	 */
	protected function addServers()
	{

	}

	/**
	 * Get redis servers.
	 *
	 * @return array A list of servers in the daemon.
	 */
	protected function getServers()
	{

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
		$result = $this->obj->get($key);
		$this->is_miss = $result == null;

		return $result;
	}

	/**
	 * {@inheritdoc}
	 */
	public function put($key, $value, $ttl = 120)
	{
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
		// Clear it out, really invalidate whats there
		$this->obj->flush();
	}

	/**
	 * {@inheritdoc}
	 */
	public function details()
	{

		$version = $this->obj->info()['Server'];

		return array(
			'title' => $this->title(),
			'version' => !empty($version['redis_version']) ? $version['redis_version'] : '0.0.0'
		);
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

		$var = array(
			'cache_redis', $txt['cache_redis'], 'file', 'text', 30, 'cache_redis',
			'force_div_id' => 'redis_cache_redis',
		);

		$serversmList = $this->getServers();
		$serversmList = empty($serversmList) ? array($txt['admin_search_results_none']) : $serversmList;
		$var['postinput'] = $txt['cache_redis_servers'] . implode('</li><li>', $serversmList) . '</li></ul>';

		$config_vars[] = $var;
	}
}
