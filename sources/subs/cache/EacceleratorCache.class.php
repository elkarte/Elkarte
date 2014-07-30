<?php

class Eaccelerator_Cache extends Cache_Method_Abstract
{
	public function init()
	{
		return function_exists('eaccelerator_put');
	}

	public function put($key, $value, $ttl)
	{
		if (mt_rand(0, 10) == 1)
			eaccelerator_gc();

		if ($value === null)
			@eaccelerator_rm($key);
		else
			eaccelerator_put($key, $value, $ttl);
	}

	public function get($key, $ttl)
	{
		if (function_exists('eaccelerator_get'))
			return eaccelerator_get($key);
	}

	public function clean($type)
	{
		// Clean out the already expired items
		@eaccelerator_clean();

		// Remove all unused scripts and data from shared memory and disk cache,
		// e.g. all data that isn't used in the current requests.
		@eaccelerator_clear();
	}
}