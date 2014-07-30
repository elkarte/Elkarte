<?php

class Apc_Cache extends Cache_Method_Abstract
{
	public function init()
	{
		return function_exists('apc_store');
	}

	public function put($key, $value, $ttl)
	{
		// An extended key is needed to counteract a bug in APC.
		if ($value === null)
			apc_delete($key . 'elkarte');
		else
			apc_store($key . 'elkarte', $value, $ttl);
	}

	public function get($key, $ttl)
	{
		return apc_fetch($key . 'elkarte');
	}

	public function clean($type)
	{
		// If passed a type, clear that type out
		if ($type === '' || $type === 'data')
		{
			apc_clear_cache('user');
			apc_clear_cache('system');
		}
		elseif ($type === 'user')
			apc_clear_cache('user');
	}
}