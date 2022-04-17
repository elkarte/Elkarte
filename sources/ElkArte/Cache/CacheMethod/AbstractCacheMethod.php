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

use ElkArte\FileFunctions;

/**
 * Abstract cache class, implementing the Cache_Method_Interface interface.
 * Used to provide common methods and properties to the caching classes
 */
abstract class AbstractCacheMethod implements CacheMethodInterface
{
	/** @var array The settings of the caching engine */
	public $_options;

	/** @var bool Cache hit or not */
	protected $is_miss = true;

	/** @var string the (human-readable) name of the caching engine. */
	protected $title = '';

	/** @var string This is prefixed to all cache entries so that different
	    applications won't interfere with each other. */
	protected $prefix = 'elkarte';

	/** @var \ElkArte\FileFunctions instance of file functions for use in cache methods */
	protected $fileFunc;

	/**
	 * {@inheritdoc}
	 */
	public function __construct($options)
	{
		$this->_options = $options;
		$this->fileFunc = FileFunctions::instance();
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
