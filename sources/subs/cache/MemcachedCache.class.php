<?php

class Memcached_Cache extends Cache_Method_Abstract
{
	private $_memcache = null;

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

	public function put($key, $value, $ttl)
	{
		memcache_set($this->_options['memcached'], $key, $value, 0, $ttl);
	}

	public function get($key, $ttl)
	{
		if ($this->_memcache)
			return memcache_get($this->_options['memcached'], $key);
		else
			return memcached_get($this->_options['memcached'], $key);
	}

	public function clean($type)
	{
		// Clear it out, really invalidate whats there
		if ($this->_memcache)
			memcache_flush($this->_options['memcached']);
		else
			memcached_flush($this->_options['memcached']);
	}
}