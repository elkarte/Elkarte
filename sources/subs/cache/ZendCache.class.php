<?php

class Zend_Cache extends Cache_Method_Abstract
{
	private $_shm = false;

	public function init()
	{
		$this->_shm = function_exists('zend_shm_cache_store');

		return $this->_shm || function_exists('output_cache_put');
	}

	public function put($key, $value, $ttl)
	{
		// Zend Platform/ZPS/etc.
		if ($this->_shm)
			zend_shm_cache_store('ELK::' . $key, $value, $ttl);
		else
			output_cache_put($key, $value);
	}

	public function get($key, $ttl)
	{
		// Zend's pricey stuff.
		if ($this->_shm)
			return zend_shm_cache_fetch('ELK::' . $key);
		else
			return output_cache_get($key, $ttl);
	}

	public function clean($type)
	{
		if ($this->_shm)
			zend_shm_cache_clear('ELK');
	}
}