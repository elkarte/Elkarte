<?php

/**
 * This file contains functions that deal with getting and setting cache values.
 *
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * @version 2.0 dev
 *
 */

namespace ElkArte\Cache\CacheMethod;

/**
 * Zend caching engine.
 */
class Zend extends AbstractCacheMethod
{
	/**
	 * {@inheritdoc}
	 */
	protected $title = 'Zend Platform/Performance Suite';

	/**
	 * {@inheritdoc}
	 */
	public function exists($key)
	{
		$this->get($this->getprefixedKey($key));

		return !$this->is_miss;
	}

	/**
	 * {@inheritdoc}
	 */
	public function put($key, $value, $ttl = 120)
	{
		zend_shm_cache_store($this->getprefixedKey($key), $value, $ttl);
	}

	/**
	 * {@inheritdoc}
	 */
	public function get($key, $ttl = 120)
	{
		$result = zend_shm_cache_fetch($this->getprefixedKey($key));
		$this->is_miss = $result === null;

		return $result;
	}

	/**
	 * {@inheritdoc}
	 */
	public function clean($type = '')
	{
		zend_shm_cache_clear($this->prefix);
	}

	/**
	 * {@inheritdoc}
	 */
	public function isAvailable()
	{
		return function_exists('zend_shm_cache_store');
	}

	/**
	 * {@inheritdoc}
	 */
	public function details()
	{
		return array('title' => $this->title, 'version' => zend_version());
	}
}
