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
 * MMCache, also known as Turck MMCache
 */
class Mmcache extends Cache_Method_Abstract
{
	/**
	 * {@inheritdoc }
	 */
	public function init()
	{
		return function_exists('mmcache_put');
	}

	/**
	 * {@inheritdoc }
	 */
	public function put($key, $value, $ttl = 120)
	{
		if (mt_rand(0, 10) == 1)
			mmcache_gc();

		if ($value === null)
			@mmcache_rm($key);
		else
		{
			mmcache_lock($key);
			mmcache_put($key, $value, $ttl);
			mmcache_unlock($key);
		}
	}

	/**
	 * {@inheritdoc }
	 */
	public function get($key, $ttl = 120)
	{
		return mmcache_get($key);
	}

	/**
	 * {@inheritdoc }
	 */
	public function clean($type = '')
	{
		// Removes all expired keys from shared memory, this is not a complete cache flush :(
		// @todo there is no clear function, should we try to find all of the keys and delete those? with mmcache_rm
		mmcache_gc();
	}

	/**
	 * {@inheritdoc }
	 */
	public static function available()
	{
		return defined('MMCACHE_VERSION');
	}

	/**
	 * {@inheritdoc }
	 */
	public static function details()
	{
		return array('title' => self::title(), 'version' => MMCACHE_VERSION);
	}

	/**
	 * {@inheritdoc }
	 */
	public static function title()
	{
		return 'Turck MMCache';
	}
}