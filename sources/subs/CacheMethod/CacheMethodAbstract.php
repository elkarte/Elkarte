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
}