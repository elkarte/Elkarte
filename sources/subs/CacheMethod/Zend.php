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
 * Zend caching engine.
 * Supports both zend_shm_cache_store and the deprecated output_cache_put
 */
class Zend extends Cache_Method_Abstract
{
	private $_shm = false;

	/**
	 * {@inheritdoc }
	 */
	public function init()
	{
		$this->_shm = function_exists('zend_shm_cache_store');

		return $this->_shm || function_exists('output_cache_put');
	}

	/**
	 * {@inheritdoc }
	 */
	public function put($key, $value, $ttl = 120)
	{
		// Zend Platform/ZPS/etc.
		if ($this->_shm)
			zend_shm_cache_store('ELK::' . $key, $value, $ttl);
		else
			output_cache_put($key, $value);
	}

	/**
	 * {@inheritdoc }
	 */
	public function get($key, $ttl = 120)
	{
		// Zend's pricey stuff.
		if ($this->_shm)
			return zend_shm_cache_fetch('ELK::' . $key);
		else
			return output_cache_get($key, $ttl);
	}

	/**
	 * {@inheritdoc }
	 */
	public function clean($type = '')
	{
		if ($this->_shm)
			zend_shm_cache_clear('ELK');
	}

	/**
	 * {@inheritdoc }
	 */
	public static function available()
	{
		return function_exists('zend_shm_cache_store') || function_exists('output_cache_put');
	}

	/**
	 * {@inheritdoc }
	 */
	public static function details()
	{
		return array('title' => self::title(), 'version' => zend_version());
	}

	/**
	 * {@inheritdoc }
	 */
	public static function title()
	{
		return 'Zend Platform/Performance Suite';
	}
}