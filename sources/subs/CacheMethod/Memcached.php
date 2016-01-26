<?php

/**
 * This file contains functions that deal with getting and setting cache values.
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 1.1 dev
 *
 */

namespace ElkArte\sources\subs\CacheMethod;

if (!defined('ELK'))
	die('No access...');

/**
 * Memcache and memcached.
 *
 * memcache is the first choice, if this is not available then memcached is used
 */
class Memcached extends Cache_Method_Abstract
{
	private $_memcache = null;
	private $_memcachec = null;

	/**
	 * {@inheritdoc }
	 */
	public function init()
	{
		if (!class_exists('Memcached') && !function_exists('memcache_get') && !function_exists('memcached_get'))
			return false;

		$memcached = self::get_memcached_server();
		if (!$memcached)
			return false;

		$this->_memcachec = class_exists('Memcached');
		$this->_memcache = function_exists('memcache_get');
		$this->_options['memcached'] = $memcached;

		return true;
	}

	/**
	 * {@inheritdoc }
	 */
	public function put($key, $value, $ttl = 120)
	{
		if ($this->_memcachec)
			$this->_options['memcached']->set($key, $value, $ttl);
		else
			memcache_set($this->_options['memcached'], $key, $value, 0, $ttl);
	}

	/**
	 * {@inheritdoc }
	 */
	public function get($key, $ttl = 120)
	{
		if ($this->_memcachec)
		{
			$result = $this->_options['memcached']->get($key);
		}
		elseif ($this->_memcache)
		{
			$result = memcache_get($this->_options['memcached'], $key);
		}
		else
		{
			$result = memcached_get($this->_options['memcached'], $key);
		}

		$this->is_miss = $result === null;

		return $result;
	}

	/**
	 * {@inheritdoc }
	 */
	public function clean($type = '')
	{
		// Clear it out, really invalidate whats there
		if ($this->_memcachec)
			$this->_options['memcached']->flush();
		elseif ($this->_memcache)
			memcache_flush($this->_options['memcached']);
		else
			memcached_flush($this->_options['memcached']);
	}

	/**
	 * Get memcache servers.
	 *
	 * - This function is used by Cache::instance() and Cache::put().
	 * - It attempts to connect to a random server in the cache_memcached setting.
	 * - It recursively calls itself up to $level times.
	 *
	 * @param int $level = 3
	 */
	public static function get_memcached_server($level = 3)
	{
		global $db_persist, $cache_memcached;

		$servers = explode(',', $cache_memcached);
		$server = explode(':', trim($servers[array_rand($servers)]));
		$port = !isset($server[1]) ? 11211 : $server[1];

		// Don't try more times than we have servers!
		$level = min(count($servers), $level);

		if (class_exists('Memcached'))
		{
			$serversm = array();
			foreach ($servers as $server)
			{
				$server = explode(':', trim($server));
				$serversm[] = $server;
			}
			$memcached = new Memcached;
			$memcached->addServers($serversm);
		}
		// Don't wait too long: yes, we want the server, but we might be able to run the query faster!
		elseif (empty($db_persist))
		{
			if (function_exists('memcache_get'))
				$memcached = memcache_connect($server[0], $port);
			else
				$memcached = memcached_connect($server[0], $port);
		}
		else
		{
			if (function_exists('memcache_get'))
				$memcached = memcache_pconnect($server[0], $port);
			else
				$memcached = memcached_pconnect($server[0], $port);
		}

		if (!$memcached && $level > 0)
			self::get_memcached_server($level - 1);

		return $memcached;
	}

	/**
	 * {@inheritdoc }
	 */
	public static function available()
	{
		global $modSettings;

		return (class_exists('Memcached') || function_exists('memcache_get')) && isset($modSettings['cache_memcached']) && trim($modSettings['cache_memcached']) != '';
	}

	/**
	 * {@inheritdoc }
	 */
	public static function details()
	{
		$memcached = self::get_memcached_server();

		return array('title' => self::title(), 'version' => empty($memcached) ? '???' : (class_exists('Memcached') ? $memcached->getVersion() : memcache_get_version()));
	}

	/**
	 * {@inheritdoc }
	 */
	public static function title()
	{
		if (self::available())
			add_integration_function('integrate_modify_cache_settings', 'Memcached_Cache::settings', '', false);

		return 'Memcached';
	}

	/**
	 * Adds the settings to the settings page.
	 *
	 * Used by integrate_modify_cache_settings added in the title method
	 *
	 * @param array() $config_vars
	 */
	public static function settings(&$config_vars)
	{
		global $txt;

		$config_vars[] = array('cache_memcached', $txt['cache_memcached'], 'file', 'text', $txt['cache_memcached'], 'cache_memcached', 'force_div_id' => 'memcached_cache_memcached');
	}
}
