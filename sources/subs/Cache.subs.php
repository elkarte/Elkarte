<?php

/**
 * This file contains functions that deal with getting and setting cache values.
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * This file contains code covered by:
 * copyright:	2011 Simple Machines (http://www.simplemachines.org)
 * license:  	BSD, See included LICENSE.TXT for terms and conditions.
 *
 * @version 1.1 beta 4
 *
 */

/**
 * Try to retrieve a cache entry. On failure, call the appropriate function.
 * This callback is sent as $file to include, and $function to call, with
 * $params parameters.
 *
 * @param string $key cache entry key
 * @param string $file file to include
 * @param string $function function to call
 * @param mixed[] $params parameters sent to the function
 * @param int $level = 1
 *
 * @return mixed
 */
function cache_quick_get($key, $file, $function, $params, $level = 1)
{
	return Cache::instance()->quick_get($key, $file, $function, $params, $level);
}

/**
 * Puts value in the cache under key for ttl seconds.
 *
 * - It may "miss" so shouldn't be depended on
 * - Uses the cache engine chosen in the ACP and saved in settings.php
 * - It supports:
 *     Xcache: http://xcache.lighttpd.net/wiki/XcacheApi
 *     memcache: http://www.php.net/memcache
 *     APC: http://www.php.net/apc
 *     Zend: http://files.zend.com/help/Zend-Platform/zend_cache_functions.htm
 *
 * @param string $key
 * @param string|int|mixed[]|null $value
 * @param int $ttl = 120
 */
function cache_put_data($key, $value, $ttl = 120)
{
	Cache::instance()->put($key, $value, $ttl);
}

/**
 * Gets the value from the cache specified by key, so long as it is not older than ttl seconds.
 *
 * - It may often "miss", so shouldn't be depended on.
 * - It supports the same as Cache::instance()->put().
 *
 * @param string $key
 * @param int $ttl = 120
 */
function cache_get_data($key, $ttl = 120)
{
	return Cache::instance()->get($key, $ttl);
}

/**
 * Empty out the cache in use as best it can
 *
 * It may only remove the files of a certain type (if the $type parameter is given)
 * Type can be user, data or left blank
 *  - user clears out user data
 *  - data clears out system / opcode data
 *  - If no type is specified will perform a complete cache clearing
 * For cache engines that do not distinguish on types, a full cache flush will be done
 *
 * @param string $type = ''
 */
function clean_cache($type = '')
{
	Cache::instance()->clean($type);
}

/**
 * Finds all the caching engines available and loads some details depending on
 * parameters.
 *
 * - Caching engines must follow the naming convention of XyzCache.class.php and
 * have a class name of Xyz_Cache
 *
 * @param bool $supported_only If true, for each engine supported by the server
 *             an array with 'title' and 'version' is returned.
 *             If false, for each engine available an array with 'title' (string)
 *             and 'supported' (bool) is returned.
 *
 * @return mixed[]
 */
function loadCacheEngines($supported_only = true)
{
	$engines = array();

	$classes = new GlobIterator(SUBSDIR . '/CacheMethod/*.php', FilesystemIterator::SKIP_DOTS);

	foreach ($classes as $file_path)
	{
		// Get the engine name from the file name
		$parts = explode('.', $file_path->getBasename());
		$engine_name = $parts[0];
		$class = '\\ElkArte\\sources\\subs\\CacheMethod\\' . $parts[0];

		// Validate the class name exists
		if (class_exists($class))
		{
			$obj = new $class(array());
			if ($obj instanceof ElkArte\sources\subs\CacheMethod\Cache_Method_Abstract)
			{
				if ($supported_only && $obj->isAvailable())
				{
					$engines[strtolower($engine_name)] = $obj->details();
				}
				elseif ($supported_only === false)
				{
					$engines[strtolower($engine_name)] = $obj;
				}
			}
		}
	}

	return $engines;
}
