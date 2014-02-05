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
 * @version 1.0 Beta 2
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
 */
function cache_quick_get($key, $file, $function, $params, $level = 1)
{
	global $modSettings;

	// @todo Why are we doing this if caching is disabled?

	if (function_exists('call_integration_hook'))
		call_integration_hook('pre_cache_quick_get', array(&$key, &$file, &$function, &$params, &$level));

	/* Refresh the cache if either:
		1. Caching is disabled.
		2. The cache level isn't high enough.
		3. The item has not been cached or the cached item expired.
		4. The cached item has a custom expiration condition evaluating to true.
		5. The expire time set in the cache item has passed (needed for Zend).
	*/
	if (empty($modSettings['cache_enable']) || $modSettings['cache_enable'] < $level || !is_array($cache_block = cache_get_data($key, 3600)) || (!empty($cache_block['refresh_eval']) && eval($cache_block['refresh_eval'])) || (!empty($cache_block['expires']) && $cache_block['expires'] < time()))
	{
		require_once(SOURCEDIR . '/' . $file);
		$cache_block = call_user_func_array($function, $params);

		if (!empty($modSettings['cache_enable']) && $modSettings['cache_enable'] >= $level)
			cache_put_data($key, $cache_block, $cache_block['expires'] - time());
	}

	// Some cached data may need a freshening up after retrieval.
	if (!empty($cache_block['post_retri_eval']))
		eval($cache_block['post_retri_eval']);

	if (function_exists('call_integration_hook'))
		call_integration_hook('post_cache_quick_get', array($cache_block));

	return $cache_block['data'];
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
 * @param mixed $value
 * @param int $ttl = 120
 */
function cache_put_data($key, $value, $ttl = 120)
{
	global $cache_memcached, $memcached, $cache_hits, $cache_count, $db_show_debug;
	global $cache_accelerator, $cache_enable;

	if (empty($cache_enable))
		return;

	$cache_count = isset($cache_count) ? $cache_count + 1 : 1;
	if (isset($db_show_debug) && $db_show_debug === true)
	{
		$cache_hits[$cache_count] = array('k' => $key, 'd' => 'put', 's' => $value === null ? 0 : strlen(serialize($value)));
		$st = microtime(true);
	}

	$key = cache_get_key($key);
	$value = $value === null ? null : serialize($value);

	switch ($cache_accelerator)
	{
		case 'memcached':
			// The simple yet efficient memcached.
			if (function_exists('memcached_set') || function_exists('memcache_set') && !empty($cache_memcached))
			{
				// Not connected yet?
				if (empty($memcached))
					get_memcached_server();
				if (!$memcached)
					return;

				memcache_set($memcached, $key, $value, 0, $ttl);
			}
			break;
		case 'eaccelerator':
			// eAccelerator...
			if (function_exists('eaccelerator_put'))
			{
				if (mt_rand(0, 10) == 1)
					eaccelerator_gc();

				if ($value === null)
					@eaccelerator_rm($key);
				else
					eaccelerator_put($key, $value, $ttl);
			}
			break;
		case 'mmcache':
			// Turck MMCache?
			if (function_exists('mmcache_put'))
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
			break;
		case 'apc':
			// Alternative PHP Cache, ahoy!
			if (function_exists('apc_store'))
			{
				// An extended key is needed to counteract a bug in APC.
				if ($value === null)
					apc_delete($key . 'elkarte');
				else
					apc_store($key . 'elkarte', $value, $ttl);
			}
			break;
		case 'zend':
			// Zend Platform/ZPS/etc.
			if (function_exists('zend_shm_cache_store'))
				zend_shm_cache_store('ELK::' . $key, $value, $ttl);
			elseif (function_exists('output_cache_put'))
				output_cache_put($key, $value);
			break;
		case 'xcache':
			if (function_exists('xcache_set') && ini_get('xcache.var_size') > 0)
			{
				if ($value === null)
					xcache_unset($key);
				else
					xcache_set($key, $value, $ttl);
			}
			break;
		default:
			// Otherwise custom cache?
			if ($value === null)
				@unlink(CACHEDIR . '/data_' . $key . '.php');
			else
			{
				$cache_data = '<' . '?' . 'php if (!defined(\'ELK\')) die; if (' . (time() + $ttl) . ' < time()) $expired = true; else{$expired = false; $value = \'' . addcslashes($value, '\\\'') . '\';}';

				// Write out the cache file, check that the cache write was successful; all the data must be written
				// If it fails due to low diskspace, or other, remove the cache file
				if (@file_put_contents(CACHEDIR . '/data_' . $key . '.php', $cache_data, LOCK_EX) !== strlen($cache_data))
					@unlink(CACHEDIR . '/data_' . $key . '.php');
			}
			break;
	}

	if (function_exists('call_integration_hook'))
		call_integration_hook('cache_put_data', array($key, $value, $ttl));

	if (isset($db_show_debug) && $db_show_debug === true)
		$cache_hits[$cache_count]['t'] = microtime(true) - $st;
}

/**
 * Gets the value from the cache specified by key, so long as it is not older than ttl seconds.
 * - It may often "miss", so shouldn't be depended on.
 * - It supports the same as cache_put_data().
 *
 * @param string $key
 * @param int $ttl = 120
 * @return string
 */
function cache_get_data($key, $ttl = 120)
{
	global $cache_memcached, $memcached, $cache_hits, $cache_count, $db_show_debug;
	global $cache_accelerator, $cache_enable, $expired;

	if (empty($cache_enable))
		return;

	$cache_count = isset($cache_count) ? $cache_count + 1 : 1;
	if (isset($db_show_debug) && $db_show_debug === true)
	{
		$cache_hits[$cache_count] = array(
			'k' => $key,
			'd' => 'get'
		);
		$st = microtime(true);
	}

	$key = cache_get_key($key);

	switch ($cache_accelerator)
	{
		case 'memcached':
			// Okay, let's go for it memcached!
			if ((function_exists('memcache_get') || function_exists('memcached_get')) && !empty($cache_memcached))
			{
				// Not connected yet?
				if (empty($memcached))
					get_memcached_server();
				if (!$memcached)
					return null;

				$value = (function_exists('memcache_get')) ? memcache_get($memcached, $key) : memcached_get($memcached, $key);
			}
			break;
		case 'eaccelerator':
			// Again, eAccelerator.
			if (function_exists('eaccelerator_get'))
				$value = eaccelerator_get($key);
			break;
		case 'mmcache':
			// The older, but ever-stable, Turck MMCache...
			if (function_exists('mmcache_get'))
				$value = mmcache_get($key);
			break;
		case 'apc':
			// This is the free APC from PECL.
			if (function_exists('apc_fetch'))
				$value = apc_fetch($key . 'elkarte');
			break;
		case 'zend':
			// Zend's pricey stuff.
			if (function_exists('zend_shm_cache_fetch'))
				$value = zend_shm_cache_fetch('ELK::' . $key);
			elseif (function_exists('output_cache_get'))
				$value = output_cache_get($key, $ttl);
			break;
		case 'xcache':
			if (function_exists('xcache_get') && ini_get('xcache.var_size') > 0)
				$value = xcache_get($key);
			break;
		default:
			// Otherwise it's ElkArte data!
			if (file_exists(CACHEDIR . '/data_' . $key . '.php') && filesize(CACHEDIR . '/data_' . $key . '.php') > 10)
			{
				// php will cache file_exists et all, we can't 100% depend on its results so proceed with caution
				@include(CACHEDIR . '/data_' . $key . '.php');
				if (!empty($expired) && isset($value))
				{
					@unlink(CACHEDIR . '/data_' . $key . '.php');
					unset($value);
				}
			}
			break;
	}

	if (isset($db_show_debug) && $db_show_debug === true)
	{
		$cache_hits[$cache_count]['t'] = microtime(true) - $st;
		$cache_hits[$cache_count]['s'] = isset($value) ? strlen($value) : 0;
	}

	if (function_exists('call_integration_hook') && isset($value))
		call_integration_hook('cache_get_data', array($key, $ttl, $value));

	return empty($value) ? null : @unserialize($value);
}

/**
 * Get memcache servers.
 *
 * - This function is used by cache_get_data() and cache_put_data().
 * - It attempts to connect to a random server in the cache_memcached setting.
 * - It recursively calls itself up to $level times.
 *
 * @param int $level = 3
 */
function get_memcached_server($level = 3)
{
	global $memcached, $db_persist, $cache_memcached;

	$servers = explode(',', $cache_memcached);
	$server = explode(':', trim($servers[array_rand($servers)]));
	$cache = (function_exists('memcache_get')) ? 'memcache' : ((function_exists('memcached_get') ? 'memcached' : ''));

	// Don't try more times than we have servers!
	$level = min(count($servers), $level);

	// Don't wait too long: yes, we want the server, but we might be able to run the query faster!
	if (empty($db_persist))
	{
		if ($cache === 'memcached')
			$memcached = memcached_connect($server[0], empty($server[1]) ? 11211 : $server[1]);
		if ($cache === 'memcache')
			$memcached = memcache_connect($server[0], empty($server[1]) ? 11211 : $server[1]);
	}
	else
	{
		if ($cache === 'memcached')
			$memcached = memcached_pconnect($server[0], empty($server[1]) ? 11211 : $server[1]);
		if ($cache === 'memcache')
			$memcached = memcache_pconnect($server[0], empty($server[1]) ? 11211 : $server[1]);
	}

	if (!$memcached && $level > 0)
		get_memcached_server($level - 1);
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
 */
function clean_cache($type = '')
{
	global $cache_accelerator, $cache_uid, $cache_password, $cache_memcached, $memcached;

	switch ($cache_accelerator)
	{
		case 'memcached':
			if ((function_exists('memcache_flush') || function_exists('memcached_flush')) && !empty($cache_memcached))
			{
				// Not connected yet?
				if (empty($memcached))
					get_memcached_server();
				if (!$memcached)
					return;

				// Clear it out, really invalidate whats there
				if (function_exists('memcache_flush'))
					memcache_flush($memcached);
				else
					memcached_flush($memcached);
			}
			break;
		case 'eaccelerator':
			if (function_exists('eaccelerator_clear') && function_exists('eaccelerator_clean') )
			{
				// Clean out the already expired items
				@eaccelerator_clean();

				// Remove all unused scripts and data from shared memory and disk cache,
				// e.g. all data that isn't used in the current requests.
				@eaccelerator_clear();
			}
		case 'mmcache':
			if (function_exists('mmcache_gc'))
			{
				// Removes all expired keys from shared memory, this is not a complete cache flush :(
				// @todo there is no clear function, should we try to find all of the keys and delete those? with mmcache_rm
				mmcache_gc();
			}
			break;
		case 'apc':
			if (function_exists('apc_clear_cache'))
			{
				// If passed a type, clear that type out
				if ($type === '' || $type === 'data')
				{
					apc_clear_cache('user');
					apc_clear_cache('system');
				}
				elseif ($type === 'user')
					apc_clear_cache('user');
			}
			break;
		case 'zend':
			if (function_exists('zend_shm_cache_clear'))
				zend_shm_cache_clear('ELK');
			break;
		case 'xcache':
			if (function_exists('xcache_clear_cache') && function_exists('xcache_count'))
			{
				// Xcache may need auth credentials, depending on how its been set up
				if (!empty($cache_uid) && !empty($cache_password))
				{
					$_SERVER["PHP_AUTH_USER"] = $cache_uid;
					$_SERVER["PHP_AUTH_PW"] = $cache_password;
				}

				// Get the counts so we clear each instance
				$pcnt = xcache_count(XC_TYPE_PHP);
				$vcnt = xcache_count(XC_TYPE_VAR);

				// Time to clear the user vars and/or the opcache
				if ($type === '' || $type === 'user')
				{
					for ($i = 0; $i < $vcnt; $i++)
						xcache_clear_cache(XC_TYPE_VAR, $i);
				}

				if ($type === '' || $type === 'data')
				{
					for ($i = 0; $i < $pcnt; $i++)
						xcache_clear_cache(XC_TYPE_PHP, $i);
				}
			}
			break;
	}

	// To be complete, we also clear out the cache dir so we get any js/css hive files
	if (is_dir(CACHEDIR))
	{
		// Remove the cache files in our disk cache directory
		$dh = opendir(CACHEDIR);
		while ($file = readdir($dh))
		{
			if ($file != '.' && $file != '..' && $file != 'index.php' && $file != '.htaccess' && (!$type || substr($file, 0, strlen($type)) == $type))
				@unlink(CACHEDIR . '/' . $file);
		}

		closedir($dh);
	}

	// Invalidate cache, to be sure!
	// ... as long as Load.php can be modified, anyway.
	@touch(SOURCEDIR . '/Load.php');

	// Give addons a way to trigger cache cleaning.
	call_integration_hook('integrate_clean_cache');

	clearstatcache();
}

/**
 * Get the key for the cache.
 *
 * @param string $key
 * @return string
 */
function cache_get_key($key)
{
	global $boardurl, $cache_accelerator;
	static $key_prefix;

	// no need to do this every time, slows us down :P
	if (empty($key_prefix))
		$key_prefix = md5($boardurl . filemtime(SOURCEDIR . '/Load.php')) . '-ELK-';

	return $key_prefix . ((empty($cache_accelerator) || $cache_accelerator === 'filebased') ? strtr($key, ':/', '-_') : $key);
}