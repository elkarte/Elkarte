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
 * @version 1.0 Release Candidate 1
 *
 */

if (!defined('ELK'))
	die('No access...');

class Cache
{
	/**
	 * Holds our static instance of the class
	 * @var object
	 */
	private static $_instance = null;

	private $_method = null;
	private $_options = array();
	private $_cache_enable = false;
	private $_key_prefix = null;

	public function __construct()
	{
		global $cache_memcached, $db_show_debug;
		global $cache_accelerator, $cache_enable, $expired;

		$this->_cache_enable = (int) $cache_enable;

		// If the cache is disabled just go out
		if (!$this->_cache_enable)
			return;

		$args = func_get_args();

		// Removes $cache_enable
		array_shift($args);
		// Removes $cache_accelerator
		array_shift($args);

		if (empty($cache_accelerator))
			$cache_accelerator = 'filebased';

		if (!empty($args))
			$this->_options = $args;

		$methods = array(
			'memcached' => array(
				'init' => function($options) {
					if (!function_exists('memcache_get') && !function_exists('memcached_get'))
						return false;

					$memcached = get_memcached_server();

					if (!$memcached)
						return false;
					else
						return array('memcached' => $memcached);
				},
				'put' => function($key, $value, $ttl, $options) {
					memcache_set($options['memcached'], $key, $value, 0, $ttl);
				},
				'get' => function($key, $ttl, $options) {
					return function_exists('memcache_get') ? memcache_get($options['memcached'], $key) : memcached_get($options['memcached'], $key);
				},
				'clean' => function($type, $options) {
					// Clear it out, really invalidate whats there
					if (function_exists('memcache_flush'))
						memcache_flush($options['memcached']);
					else
						memcached_flush($options['memcached']);
				},
			),
			'eaccelerator' => array(
				'init' => function($options) {
					return function_exists('eaccelerator_put');
				},
				'put' => function($key, $value, $ttl, $options) {
					if (mt_rand(0, 10) == 1)
						eaccelerator_gc();

					if ($value === null)
						@eaccelerator_rm($key);
					else
						eaccelerator_put($key, $value, $ttl);
				},
				'get' => function($key, $ttl, $options) {
					if (function_exists('eaccelerator_get'))
						return eaccelerator_get($key);
				},
				'clean' => function($type, $options) {
					// Clean out the already expired items
					@eaccelerator_clean();

					// Remove all unused scripts and data from shared memory and disk cache,
					// e.g. all data that isn't used in the current requests.
					@eaccelerator_clear();
				},
			),
			'mmcache' => array(
				'init' => function($options) {
					return function_exists('mmcache_put');
				},
				'put' => function($key, $value, $ttl, $options) {
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
				},
				'get' => function($key, $ttl, $options) {
					return mmcache_get($key);
				},
				'clean' => function($type, $options) {
					// Removes all expired keys from shared memory, this is not a complete cache flush :(
					// @todo there is no clear function, should we try to find all of the keys and delete those? with mmcache_rm
					mmcache_gc();
				},
			),
			'apc' => array(
				'init' => function($options) {
					return function_exists('apc_store');
				},
				'put' => function($key, $value, $ttl, $options) {
					// An extended key is needed to counteract a bug in APC.
					if ($value === null)
						apc_delete($key . 'elkarte');
					else
						apc_store($key . 'elkarte', $value, $ttl);
				},
				'get' => function($key, $ttl, $options) {
					return apc_fetch($key . 'elkarte');
				},
				'clean' => function($type, $options) {
					// If passed a type, clear that type out
					if ($type === '' || $type === 'data')
					{
						apc_clear_cache('user');
						apc_clear_cache('system');
					}
					elseif ($type === 'user')
						apc_clear_cache('user');
				},
			),
			'zend' => array(
				'init' => function($options) {
					return function_exists('zend_shm_cache_store') || function_exists('output_cache_put');
				},
				'put' => function($key, $value, $ttl, $options) {
					// Zend Platform/ZPS/etc.
					if (function_exists('zend_shm_cache_store'))
						zend_shm_cache_store('ELK::' . $key, $value, $ttl);
					elseif (function_exists('output_cache_put'))
						output_cache_put($key, $value);
				},
				'get' => function($key, $ttl, $options) {
					// Zend's pricey stuff.
					if (function_exists('zend_shm_cache_fetch'))
						return zend_shm_cache_fetch('ELK::' . $key);
					elseif (function_exists('output_cache_get'))
						return output_cache_get($key, $ttl);
				},
				'clean' => function($type, $options) {
					if (function_exists('zend_shm_cache_clear'))
						zend_shm_cache_clear('ELK');
				},
			),
			'xcache' => array(
				'init' => function($options) {
					// Xcache may need auth credentials, depending on how its been set up
					if (!empty($options['cache_uid']) && !empty($options['cache_password']))
					{
						$_SERVER['PHP_AUTH_USER'] = $options['cache_uid'];
						$_SERVER['PHP_AUTH_PW'] = $options['cache_password'];
					}

					return function_exists('xcache_set') && ini_get('xcache.var_size') > 0;
				},
				'put' => function($key, $value, $ttl, $options) {
					if ($value === null)
						xcache_unset($key);
					else
						xcache_set($key, $value, $ttl);
				},
				'get' => function($key, $ttl, $options) {
					return xcache_get($key);
				},
				'clean' => function($type, $options) {
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
				},
			),
			'filebased' => array(
				'init' => function($options) {
					return @is_dir(CACHEDIR) && @is_writable(CACHEDIR);
				},
				'put' => function($key, $value, $ttl, $options) {
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
				},
				'get' => function($key, $ttl, $options) {
					global $expired, $value;

					// Otherwise it's ElkArte data!
					if (file_exists(CACHEDIR . '/data_' . $key . '.php') && filesize(CACHEDIR . '/data_' . $key . '.php') > 10)
					{
						// php will cache file_exists et all, we can't 100% depend on its results so proceed with caution
						@include(CACHEDIR . '/data_' . $key . '.php');
						if (!empty($expired) && isset($value))
						{
							@unlink(CACHEDIR . '/data_' . $key . '.php');
							$return = null;
						}
						else
							$return = $value;

						unset($value);

						return $return;
					}
				},
				'clean' => function($type, $options) {
					// To be complete, we also clear out the cache dir so we get any js/css hive files
					// Remove the cache files in our disk cache directory
					$dh = opendir(CACHEDIR);
					while ($file = readdir($dh))
					{
						if ($file != '.' && $file != '..' && $file != 'index.php' && $file != '.htaccess' && (!$type || substr($file, 0, strlen($type)) == $type))
							@unlink(CACHEDIR . '/' . $file);
					}

					closedir($dh);
				},
				'fixkey' => function($key) {
					return strtr($key, ':/', '-_');
				}
			),
		);

		// This will let anyone add new caching methods very easily
		call_integration_hook('integrate_init_cache', array(&$methods));

		if (isset($methods[$cache_accelerator]))
		{
			$init = $methods[$cache_accelerator]['init']($this->_options);

			// Three can be the results.
			// true: everything is fine, let's use the method
			if ($init === true)
				$this->_method = $methods[$cache_accelerator];
			// an array: means the method works and we have settings
			elseif (is_array($init))
			{
				$this->_options = array_merge($this->_options, $init);
				$this->_method = $methods[$cache_accelerator];
			}
			// false: this is bad, the method failed! Too bad, we can't use it
			// @todo: test for file based?
			else
				$this->_cache_enable = false;
		}
		$this->_key_prefix = $this->_build_prefix();
	}

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
	public function quick_get($key, $file, $function, $params, $level = 1)
	{
		global $modSettings;

		call_integration_hook('pre_cache_quick_get', array(&$key, &$file, &$function, &$params, &$level));

		/* Refresh the cache if either:
			1. Caching is disabled.
			2. The cache level isn't high enough.
			3. The item has not been cached or the cached item expired.
			4. The cached item has a custom expiration condition evaluating to true.
			5. The expire time set in the cache item has passed (needed for Zend).
		*/
		if ($this->_cache_enable < $level || !is_array($cache_block = $this->get($key, 3600)) || (!empty($cache_block['refresh_eval']) && eval($cache_block['refresh_eval'])) || (!empty($cache_block['expires']) && $cache_block['expires'] < time()))
		{
			require_once(SOURCEDIR . '/' . $file);
			$cache_block = call_user_func_array($function, $params);

			if ($this->_cache_enable >= $level)
				$this->put($key, $cache_block, $cache_block['expires'] - time());
		}

		// Some cached data may need a freshening up after retrieval.
		if (!empty($cache_block['post_retri_eval']))
			eval($cache_block['post_retri_eval']);

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
	 * @param string|int|mixed[]|null $value
	 * @param int $ttl = 120
	 */
	public function put($key, $value, $ttl = 120)
	{
		global $db_show_debug;

		if (!$this->_cache_enable)
			return;

		if ($db_show_debug === true)
		{
			$cache_hit = array(
				'k' => $key,
				'd' => 'put',
				's' => $value === null ? 0 : strlen(serialize($value))
			);
			$st = microtime(true);
		}

		$key = $this->_key($key);
		$value = $value === null ? null : serialize($value);

		$this->_method['put']($key, $value, $ttl, $this->_options);

		call_integration_hook('cache_put_data', array($key, $value, $ttl));

		if ($db_show_debug === true)
		{
			$cache_hit['t'] = microtime(true) - $st;
			Debug::get()->cache($cache_hit);
		}
	}

	/**
	 * Gets the value from the cache specified by key, so long as it is not older than ttl seconds.
	 * - It may often "miss", so shouldn't be depended on.
	 * - It supports the same as cache::put().
	 *
	 * @param string $key
	 * @param int $ttl = 120
	 */
	public function get($key, $ttl = 120)
	{
		global $db_show_debug;

		if (!$this->_cache_enable)
			return;

		if ($db_show_debug === true)
		{
			$cache_hit = array(
				'k' => $key,
				'd' => 'get'
			);
			$st = microtime(true);
		}

		$key = $this->_key($key);
		$value = $this->_method['get']($key, $ttl, $this->_options);

		if ($db_show_debug === true)
		{
			$cache_hit['t'] = microtime(true) - $st;
			$cache_hit['s'] = isset($value) ? strlen($value) : 0;
			Debug::get()->cache($cache_hit);
		}

		call_integration_hook('cache_get_data', array($key, $ttl, $value));

		return empty($value) ? null : @unserialize($value);
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
	public function clean($type = '')
	{
		if (!$this->_cache_enable)
			return;

		$this->_method['clean']($type, $this->_options);

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
	protected function _key($key)
	{
		return $this->_key_prefix . (isset($this->_method['fixkey']) ? $this->_method['fixkey']($key) : $key);
	}

		// no need to do this every time, slows us down :P
	protected function _build_prefix()
	{
		global $boardurl;

		$this->_key_prefix = md5($boardurl . filemtime(SOURCEDIR . '/Load.php')) . '-ELK-';
	}

	/**
	 * Find and return the instance of the Cache class if it exists,
	 * or create it if it doesn't exist
	 */
	public static function get()
	{
		if (self::$_instance === null)
			self::$_instance = new Cache();

		return self::$_instance;
	}
}

/**
 * Get memcache servers.
 *
 * - This function is used by Cache::get() and Cache::put().
 * - It attempts to connect to a random server in the cache_memcached setting.
 * - It recursively calls itself up to $level times.
 *
 * @param int $level = 3
 */
function get_memcached_server($level = 3)
{
	global $db_persist, $cache_memcached;

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

	return $memcached;
}