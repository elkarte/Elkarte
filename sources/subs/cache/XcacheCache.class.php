<?php

class Xcache_Cache extends Cache_Method_Abstract
{
	public function init()
	{
		// Xcache may need auth credentials, depending on how its been set up
		if (!empty($this->_options['cache_uid']) && !empty($this->_options['cache_password']))
		{
			$_SERVER['PHP_AUTH_USER'] = $this->_options['cache_uid'];
			$_SERVER['PHP_AUTH_PW'] = $this->_options['cache_password'];
		}

		return function_exists('xcache_set') && ini_get('xcache.var_size') > 0;
	}

	public function put($key, $value, $ttl)
	{
		if ($value === null)
			xcache_unset($key);
		else
			xcache_set($key, $value, $ttl);
	}

	public function get($key, $ttl)
	{
		return xcache_get($key);
	}

	public function clean($type)
	{
		// Get the counts so we clear each instance
		$pcnt = xcache_count(XC_TYPE_PHP);
		$vcnt = xcache_count(XC_TYPE_VAR);

		// Time to clear the user vars and/or the opcache
		if ($type === '' || $type === 'user')
		{
			for ($i = 0; $i < $vcnt; $i++)
				xcache_clear_cache(XC_TYPE_VAR, $i);
		}

		if ($type === '' || $type === 'data')
		{
			for ($i = 0; $i < $pcnt; $i++)
				xcache_clear_cache(XC_TYPE_PHP, $i);
		}
	}
}