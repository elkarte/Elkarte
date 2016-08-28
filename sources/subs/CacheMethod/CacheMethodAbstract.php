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
 * Abstract cache class, implementing the Cache_Method_Interface interface.
 * Used to provide common methods and properties to the caching classes
 */
abstract class Cache_Method_Abstract implements Cache_Method_Interface
{
	/**
	 * The settings of the caching engine
	 * @var array
	 */
	public $_options = null;

	protected $is_miss = true;

	/**
	 * {@inheritdoc }
	 */
	public function __construct($options)
	{
		$this->_options = $options;
	}

	/**
	 * {@inheritdoc }
	 */
	public function fixkey($key)
	{
		return $key;
	}

	/**
	 * {@inheritdoc }
	 */
	public function isMiss()
	{
		return $this->is_miss;
	}

	/**
	 * {@inheritdoc }
	 */
	public function remove($key)
	{
		$this->put($key, null, 0);
	}

	/**
	 * {@inheritdoc }
	 */
	public function settings(&$confing_vars)
	{
	}
}
