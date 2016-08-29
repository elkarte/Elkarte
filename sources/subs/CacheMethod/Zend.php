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
 * Zend caching engine.
 */
class Zend extends Cache_Method_Abstract
{
	/**
	 */

	/**
	 * {@inheritdoc }
	 */
	public function put($key, $value, $ttl = 120)
	{
		zend_shm_cache_store('ELK::' . $key, $value, $ttl);
	}

	/**
	 * {@inheritdoc }
	 */
	public function get($key, $ttl = 120)
	{
		$result = zend_shm_cache_fetch('ELK::' . $key);
		$this->is_miss = $result === null;

		return $result;
	}

	/**
	 * {@inheritdoc }
	 */
	public function clean($type = '')
	{
		zend_shm_cache_clear('ELK');
	}

	/**
	 * {@inheritdoc }
	 */
	public function isAvailable()
	{
		return function_exists('zend_shm_cache_store');
	}

	/**
	 * {@inheritdoc }
	 */
	public function details()
	{
		return array('title' => $this->title(), 'version' => zend_version());
	}

	/**
	 * {@inheritdoc }
	 */
	public function title()
	{
		return 'Zend Platform/Performance Suite';
	}
}
