<?php

/**
 * This file contains functions that deal with getting and setting cache values.
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * This software is a derived product, based on:
 *
 * Simple Machines Forum (SMF)
 * copyright:	2011 Simple Machines (http://www.simplemachines.org)
 * license:  	BSD, See included LICENSE.TXT for terms and conditions.
 *
 * @version 1.0 Release Candidate 2
 *
 */

if (!defined('ELK'))
	die('No access...');

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
 * @return string
 *
 * @deprecated since 1.1 - use Cache::instance()->quick_get() instead
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
 *     Turck MMCache: http://turck-mmcache.sourceforge.net/index_old.html#api
 *     Xcache: http://xcache.lighttpd.net/wiki/XcacheApi
 *     memcache: http://www.php.net/memcache
 *     APC: http://www.php.net/apc
 *     eAccelerator: http://bart.eaccelerator.net/doc/phpdoc/
 *     Zend: http://files.zend.com/help/Zend-Platform/output_cache_functions.htm
 *     Zend: http://files.zend.com/help/Zend-Platform/zend_cache_functions.htm
 *
 * @param string $key
 * @param string|int|mixed[]|null $value
 * @param int $ttl = 120
 *
 * @deprecated since 1.1 - use Cache::instance()->put() instead
 */
function cache_put_data($key, $value, $ttl = 120)
{
	Cache::instance()->put($key, $value, $ttl);
}

/**
 * Gets the value from the cache specified by key, so long as it is not older than ttl seconds.
 * - It may often "miss", so shouldn't be depended on.
 * - It supports the same as cache_put_data().
 *
 * @param string $key
 * @param int $ttl = 120
 *
 * @deprecated since 1.1 - use Cache::instance()->get() instead
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
 *  - If no type is specified will perfom a complete cache clearing
 * For cache engines that do not distinguish on types, a full cache flush will be done
 *
 * @param string $type = ''
 *
 * @deprecated since 1.1 - use Cache::instance()->clean() instead
 */
function clean_cache($type = '')
{
	Cache::instance()->clean($type);
}

/**
 * Get the key for the cache.
 *
 * @param string $key
 * @return string
 *
 * @deprecated since 1.1 - no replacement, it is now a protected method of the Cache class
 */
function cache_get_key($key)
{
	global $boardurl, $cache_accelerator;
	static $key_prefix = null;

	// no need to do this every time, slows us down :P
	if (empty($key_prefix))
		$key_prefix = md5($boardurl . filemtime(SOURCEDIR . '/Load.php')) . '-ELK-';

	return $key_prefix . ((empty($cache_accelerator) || $cache_accelerator === 'filebased') ? strtr($key, ':/', '-_') : $key);
}

function loadCacheEngines($supported_only = true)
{
	global $modSettings, $txt;

	$engines = array();

	$classes = glob(SUBSDIR . '/cache/*.php');

	foreach ($classes as $file_path)
	{
		$parts = explode('.', basename($file_path));
		$engine_name = substr($parts[0], 0, -5);
		$class = $engine_name . '_Cache';

		if (class_exists($class))
		{
			if ($supported_only && $class::available())
				$engines[strtolower($engine_name)] = $class::details();
			elseif ($supported_only === false)
			{
				$engines[strtolower($engine_name)] = array(
					'title' => $class::title(),
					'supported' => $class::available()
				);
			}
		}
	}

	return $engines;
}