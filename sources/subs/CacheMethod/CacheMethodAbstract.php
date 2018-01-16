<?php

/**
 * This file contains functions that deal with getting and setting cache values.
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 2.0 dev
 *
 */

namespace ElkArte\sources\subs\CacheMethod;

/**
 * Abstract cache class, implementing the Cache_Method_Interface interface.
 * Used to provide common methods and properties to the caching classes
 */
abstract class Cache_Method_Abstract implements Cache_Method_Interface
{
	/**
	 * The settings of the caching engine
	 *
	 * @var array
	 */
	public $_options = null;

	/**
	 * Cache hit or not
	 *
	 * @var bool
	 */
	protected $is_miss = true;

	/**
	 * the (human-readable) name of the caching engine.
	 *
	 * @var string
	 */
	protected $title = '';

	/**
	 * This is prefixed to all cache entries so that different
	 * applications won't interfere with each other.
	 *
	 * @var string
	 */
	protected $prefix = 'elkarte';

	/**
	 * {@inheritdoc}
	 */
	public function __construct($options)
	{
		$this->_options = $options;
	}

	/**
	 * {@inheritdoc}
	 */
	public function fixkey($key)
	{
		return $key;
	}

	/**
	 * Obtain the variables necessary to help build the final key for storage.
	 *
	 * @param string $key
	 * @return string
	 */
	public function getprefixedKey($key)
	{
		return $this->prefix . '::' . $key;
	}

	/**
	 * {@inheritdoc}
	 */
	public function isMiss()
	{
		return $this->is_miss;
	}

	/**
	 * {@inheritdoc}
	 */
	public function remove($key)
	{
		$this->put($key, null, 0);
	}

	/**
	 * {@inheritdoc}
	 */
	public function settings(&$config_vars)
	{
	}

	/**
	 * {@inheritdoc}
	 */
	public function title()
	{
		return $this->title;
	}
}
