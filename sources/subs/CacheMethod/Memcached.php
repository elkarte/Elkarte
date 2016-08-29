<?php

/**
 * This file contains functions that deal with getting and setting cache values.
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 1.1 beta 2
 *
 */

namespace ElkArte\sources\subs\CacheMethod;

/**
 * Memcache and memcached.
 *
 * memcache is the first choice, if this is not available then memcached is used
 */
class Memcached extends Cache_Method_Abstract
{
	protected $obj;

	/**
	 * {@inheritdoc }
	 */
	public function __construct($options)
	{
		parent::__construct($options);

		if ($this->isAvailable())
		{
			if (class_exists('Memcached', false))
			{
				$this->obj = new \Memcached;
			}
			elseif (class_exists('Memcache', false))
			{
				$this->obj = new \Memcache;
			}
			$this->setOptions();
			$this->addServers();
		}
	}

	/**
	 * {@inheritdoc }
	 */
	public function put($key, $value, $ttl = 120)
	{
		$this->obj->set($key, $value, $ttl);
	}

	/**
	 * {@inheritdoc }
	 */
	public function get($key, $ttl = 120)
	{
		$result = $this->obj->get($key);
		$this->is_miss = $result === null || $result === false;

		return $result;
	}

	/**
	 * {@inheritdoc }
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
		$serversm = array();
		$serversmList = $this->getServers();
		foreach ($this->_options['servers'] as $server)
		{
			$server = explode(':', trim($server));
			$server[1] = !empty($server[1]) ? $server[1] : 11211;
			$serversm[] = $server;
		}
		$serversm = array_intersect($serversm, $serversmList);
		if (!empty($serversm))
		{
			return $this->obj->addServers($serversm);
		}

		return !empty($serversmList);
	}

	/**
	 * Get memcache servers.
	 *
	 * @return array A list of servers in the daemon.
	 */
	protected function getServers()
	{
		$servers = $this->obj->getServerList();
		$serversm = array();
		if (is_array($servers))
		{
			foreach ($servers as $server)
				$serversm[] = array($server['host'], $server['port']);
		}
		return $serversm;
	}

	/**
	 * {@inheritdoc }
	 */
	protected function setOptions()
	{
		if (class_exists('Memcached', false))
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
		elseif (class_exists('Memcache', false))
		{
			$this->obj->setOption(\Memcache::OPT_CONNECT_TIMEOUT, 100);
			$this->obj->setOption(\Memcache::OPT_DISTRIBUTION, \Memcache::DISTRIBUTION_CONSISTENT);
			$this->obj->setOption(\Memcache::OPT_SERVER_FAILURE_LIMIT, 2);
			$this->obj->setOption(\Memcache::OPT_REMOVE_FAILED_SERVERS, true);
			$this->obj->setOption(\Memcache::OPT_RETRY_TIMEOUT, 1);
		}
	}

	/**
	 * {@inheritdoc }
	 */
	public function isAvailable()
	{
		return class_exists('Memcached') || class_exists('Memcache');
	}

	/**
	 * {@inheritdoc }
	 */
	public function details()
	{
		return array(
			'title' => $this->title(),
			'version' => current($this->obj->getVersion())
		);
	}

	/**
	 * {@inheritdoc }
	 */
	public function title()
	{
		return 'Memcached';
	}

	/**
	 * Adds the settings to the settings page.
	 *
	 * Used by integrate_modify_cache_settings added in the title method
	 *
	 * @param array() $config_vars
	 */
	public function settings(&$config_vars)
	{
		global $txt;

		$config_vars[] = array('cache_memcached', $txt['cache_memcached'], 'file', 'text', $txt['cache_memcached'], 'cache_memcached', 'force_div_id' => 'memcached_cache_memcached');
	}
}
