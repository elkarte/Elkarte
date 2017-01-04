<?php

/**
 * This file contains functions that deal with getting and setting cache values.
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 1.1 beta 4
 *
 */

namespace ElkArte\sources\subs\CacheMethod;

/**
 * Memcached and Memcache.
 */
class Memcache extends Cache_Method_Abstract
{
	/**
	 * {@inheritdoc}
	 */
	protected $title = 'Memcache';

	/**
	 * Creates a Memcache instance representing the connection to the memcache servers.
	 *
	 * @var \Memcache
	 */
	protected $obj;

	/**
	 * If the daemon has valid servers in it pool
	 *
	 * @var bool
	 */
	protected $_is_running;

	/**
	 * {@inheritdoc}
	 */
	public function __construct($options)
	{
		if (empty($options['servers']))
		{
			$options['servers'] = array('');
		}

		parent::__construct($options);

		if ($this->isAvailable())
		{
			$this->obj = new \Memcache;
			$this->_is_running = $this->addServers();
		}
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

		$this->obj->set($key, $value, 0, $ttl);
	}

	/**
	 * {@inheritdoc}
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
	 * {@inheritdoc}
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
	 * Add memcache servers.
	 *
	 * Don't add servers if they already exist. Ideal for persistent connections.
	 *
	 * @return bool True if there are servers in the daemon, false if not.
	 */
	protected function addServers()
	{
		$serversmList = $this->getServers();
		$retVal = !empty($serversmList);

		foreach ($this->_options['servers'] as $server)
		{
			$server = explode(':', trim($server));
			$server[0] = !empty($server[0]) ? $server[0] : 'localhost';
			$server[1] = !empty($server[1]) ? $server[1] : 11211;

			if (!in_array(implode(':', $server), $serversmList, true))
			{
				$retVal |= $this->obj->addServer($server[0], $server[1], $this->is_persit());
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

		return !empty($servers) ? array_keys((array) $servers) : array();
	}

	/**
	 * Set a few server specific options.  Could be done as part of setServer
	 * but left here for convenience
	 *
	 * @param string $server
	 * @param int    $port
	 */
	protected function setOptions($server, $port)
	{
		// host, port, timeout, retry_interval, status
		$this->obj->setServerParams($server, $port, 1, 5, true);
	}

	/**
	 * {@inheritdoc}
	 */
	public function isAvailable()
	{
		return class_exists('\\Memcache');
	}

	/**
	 * {@inheritdoc}
	 */
	public function details()
	{
		global $txt;

		$version = @$this->obj->getVersion();

		return array(
			'title' => $this->title(),
			'version' => !empty($version) ? $version : $txt['not_applicable']
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
			'cache_memcached', $txt['cache_memcached'], 'file', 'text', $txt['cache_memcached'], 'cache_memcached',
			'force_div_id' => 'memcached_cache_memcached',
		);

		$serversmList = $this->getServers();

		if (!empty($serversmList))
		{
			$var['postinput'] = $txt['cache_memcached_servers'] . implode('</li><li>', $serversmList) . '</li></ul>';
		}

		$config_vars[] = $var;
	}

	/**
	 * If this should be done as a persistent connection
	 *
	 * @return string
	 */
	private function is_persit()
	{
		global $db_persist;

		return !empty($db_persist);
	}
}
