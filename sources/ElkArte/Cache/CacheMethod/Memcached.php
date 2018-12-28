<?php

/**
 * This file contains functions that deal with getting and setting memcacheD cache values.
 *
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
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
	/**
	 * {@inheritdoc}
	 */
	protected $title = 'Memcached';

	/**
	 * Memcached instance representing the connection to the memcache servers.
	 *
	 * @var \Memcached
	 */
	protected $obj;

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
			$this->obj = new \Memcached($this->_is_persist());
			$this->setOptions();
			$this->addServers();
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
		if ($value === null)
		{
			$this->obj->delete($key);
		}

		$this->obj->set($key, $value, $ttl);
	}

	/**
	 * {@inheritdoc}
	 */
	public function get($key, $ttl = 120)
	{
		$result = $this->obj->get($key);
		$this->is_miss = $result === null || $result === false;

		return $result;
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
		return array_keys((array) $this->obj->getStats());
	}

	/**
	 * {@inheritdoc}
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
	 * {@inheritdoc}
	 */
	public function isAvailable()
	{
		return class_exists('\\Memcached');
	}

	/**
	 * {@inheritdoc}
	 */
	public function details()
	{
		$version = $this->obj->getVersion();

		return array(
			'title' => $this->title(),
			'version' => !empty($version) ? current($version) : '0.0.0'
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
			'cache_memcached', $txt['cache_memcached'], 'file', 'text', 30, 'cache_memcached',
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
	 * @return string|null
	 */
	private function _is_persist()
	{
		global $db_persist;

		return !empty($db_persist) ? $this->prefix . '_memcached' : null;
	}
}
