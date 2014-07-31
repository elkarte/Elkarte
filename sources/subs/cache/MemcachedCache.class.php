<?php
/**
 * This file contains functions that deal with getting and setting cache values.
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 1.0 Release Candidate 2
 *
 */

if (!defined('ELK'))
	die('No access...');

/**
 * Memcache and memcached.
 *
 * memcache is the first choice, if this is not available then memcached is used
 */
class Memcached_Cache extends Cache_Method_Abstract
{
	private $_memcache = null;

	/**
	 * {@inheritdoc }
	 */
	public function init()
	{
		if (!function_exists('memcache_get') && !function_exists('memcached_get'))
			return false;

		$memcached = get_memcached_server();

		if (!$memcached)
			return false;

		$this->_memcache = function_exists('memcache_get');
		$this->_options['memcached'] = $memcached;

		return true;
	}

	/**
	 * {@inheritdoc }
	 */
	public function put($key, $value, $ttl = 120)
	{
		memcache_set($this->_options['memcached'], $key, $value, 0, $ttl);
	}

	/**
	 * {@inheritdoc }
	 */
	public function get($key, $ttl = 120)
	{
		if ($this->_memcache)
			return memcache_get($this->_options['memcached'], $key);
		else
			return memcached_get($this->_options['memcached'], $key);
	}

	/**
	 * {@inheritdoc }
	 */
	public function clean($type = '')
	{
		// Clear it out, really invalidate whats there
		if ($this->_memcache)
			memcache_flush($this->_options['memcached']);
		else
			memcached_flush($this->_options['memcached']);
	}
}