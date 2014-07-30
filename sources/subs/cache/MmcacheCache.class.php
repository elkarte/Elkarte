<?php

class Mmcached_Cache extends Cache_Method_Abstract
{
	public function init()
	{
		return function_exists('mmcache_put');
	}

	public function put$key, $value, $ttl)
	{
		if (mt_rand(0, 10) == 1)
			mmcache_gc();

		if ($value === null)
			@mmcache_rm($key);
		else
		{
			mmcache_lock($key);
			mmcache_put($key, $value, $ttl);
			mmcache_unlock($key);
		}
	}

	public function get($key, $ttl)
	{
		return mmcache_get($key);
	}

	public function clean($type)
	{
		// Removes all expired keys from shared memory, this is not a complete cache flush :(
		// @todo there is no clear function, should we try to find all of the keys and delete those? with mmcache_rm
		mmcache_gc();
	}
}