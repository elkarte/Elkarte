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
 * Class Cache - Methods that deal with getting and setting cache values.
 */
class Cache
{
	/**
	 * Holds our static instance of the class
	 * @var object
	 */
	protected static $_instance = null;

	/**
	 * Array of options for the methods (if needed)
	 * @var mixed[]
	 */
	protected $_options = array();

	/**
	 * If the cache is enabled or not.
	 * @var bool
	 */
	protected $enabled = false;

	/**
	 * The caching level
	 * @var int
	 */
	protected $level = 0;

	/**
	 * The prefix to append to the cache key
	 * @var string
	 */
	protected $_key_prefix = null;

	/**
	 * The accelerator in use
	 * @var string
	 */
	protected $_accelerator = null;

	/**
	 * The caching object
	 * @var object|boolean
	 */
	protected $_cache_obj = null;

	/**
	 * Initialize the class, defines the options and the caching method to use
	 *
	 * @param int $level The level of caching
	 * @param string $accelerator The accelerator used
	 * @param mixed[] $options Any setting necessary to the caching engine
	 */
	public function __construct($level, $accelerator, $options)
	{
		$this->setLevel($level);

		// Default to file based so we can slow everything down :P
		if (empty($accelerator))
		{
			$accelerator = 'filebased';
		}

		$this->_accelerator = $accelerator;

		if ($level > 0)
		{
			$this->enable(true);
			$this->level = $level;
		}

		// If the cache is disabled just go out
		if (!$this->isEnabled())
		{
			return;
		}

		// Set the cache options
		$this->_options = $options;

		$this->_init();
	}

	/**
	 * Initialize a cache class and call its initialization method
	 */
	protected function _init()
	{
		$cache_class = '\\ElkArte\\sources\\subs\\CacheMethod\\' . ucfirst($this->_accelerator);

		if (class_exists($cache_class))
		{
			$this->_cache_obj = new $cache_class($this->_options);
			$this->enabled = $this->_cache_obj->isAvailable();
		}
		else
		{
			$this->_cache_obj = false;

			$this->enabled = false;
		}

		$this->_build_prefix();
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
	 *
	 * @return string
	 */
	public function quick_get($key, $file, $function, $params, $level = 1)
	{
		call_integration_hook('pre_cache_quick_get', array(&$key, &$file, &$function, &$params, &$level));

		/* Refresh the cache if either:
			1. Caching is disabled.
			2. The cache level isn't high enough.
			3. The item has not been cached or the cached item expired.
			4. The cached item has a custom expiration condition evaluating to true.
			5. The expire time set in the cache item has passed (needed for Zend).
		*/
		if (!$this->isEnabled() || $this->level < $level || !is_array($cache_block = $this->get($key, 3600)) || (!empty($cache_block['refresh_eval']) && eval($cache_block['refresh_eval'])) || (!empty($cache_block['expires']) && $cache_block['expires'] < time()))
		{
			require_once(SOURCEDIR . '/' . $file);
			$cache_block = call_user_func_array($function, $params);

			if ($this->level >= $level)
			{
				$this->put($key, $cache_block, $cache_block['expires'] - time());
			}
		}

		// Some cached data may need a freshening up after retrieval.
		if (!empty($cache_block['post_retri_eval']))
		{
			eval($cache_block['post_retri_eval']);
		}

		call_integration_hook('post_cache_quick_get', array($cache_block));

		return $cache_block['data'];
	}

	/**
	 * Puts value in the cache under key for ttl seconds.
	 *
	 * - It may "miss" so shouldn't be depended on
	 * - Uses the cache engine chosen in the ACP and saved in settings.php
	 * - It supports:
	 *   - Xcache: http://xcache.lighttpd.net/wiki/XcacheApi
	 *   - memcache: http://www.php.net/memcache
	 *   - memcached: http://www.php.net/memcached
	 *   - APC: http://www.php.net/apc
	 *   - APCu: http://us3.php.net/manual/en/book.apcu.php
	 *   - Zend: http://files.zend.com/help/Zend-Platform/output_cache_functions.htm
	 *   - Zend: http://files.zend.com/help/Zend-Platform/zend_cache_functions.htm
	 *
	 * @param string $key
	 * @param string|int|mixed[]|null $value
	 * @param int $ttl = 120
	 */
	public function put($key, $value, $ttl = 120)
	{
		global $db_show_debug;

		if (!$this->isEnabled())
		{
			return;
		}

		// If we are showing debug information we have some data to collect
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

		$this->_cache_obj->put($key, $value, $ttl);

		call_integration_hook('cache_put_data', array($key, $value, $ttl));

		// Show the debug cache hit information
		if ($db_show_debug === true)
		{
			$cache_hit['t'] = microtime(true) - $st;
			Debug::get()->cache($cache_hit);
		}
	}

	/**
	 * Gets the value from the cache specified by key, so long as it is not older than ttl seconds.
	 *
	 * - It may often "miss", so shouldn't be depended on.
	 * - It supports the same as cache::put().
	 *
	 * @param string $key
	 * @param int $ttl = 120
	 *
	 * @return null|boolean if it was a hit
	 */
	public function get($key, $ttl = 120)
	{
		global $db_show_debug;

		if (!$this->isEnabled())
		{
			return;
		}

		if ($db_show_debug === true)
		{
			$cache_hit = array(
				'k' => $key,
				'd' => 'get'
			);
			$st = microtime(true);
		}

		$key = $this->_key($key);
		$value = $this->_cache_obj->get($key, $ttl);

		if ($db_show_debug === true)
		{
			$cache_hit['t'] = microtime(true) - $st;
			$cache_hit['s'] = isset($value) ? strlen($value) : 0;
			Debug::get()->cache($cache_hit);
		}

		call_integration_hook('cache_get_data', array($key, $ttl, $value));

		return empty($value) ? null : Util::unserialize($value);
	}

	/**
	 * Same as $this->get but sets $var to the result and return if it was a hit
	 *
	 * @param mixed $var The variable to be assigned the result
	 * @param string $key
	 * @param int $ttl
	 *
	 * @return null|boolean if it was a hit
	 */
	public function getVar(&$var, $key, $ttl = 120)
	{
		$var = $this->get($key, $ttl);

		return !$this->isMiss();
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
	public function clean($type = '')
	{
		if (!$this->isEnabled())
		{
			return;
		}

		$this->_cache_obj->clean($type);

		// Invalidate cache, to be sure!
		// ... as long as CACHEDIR/index.php can be modified, anyway.
		@touch(CACHEDIR . '/index.php');

		// Give addons a way to trigger cache cleaning.
		call_integration_hook('integrate_clean_cache');

		clearstatcache();
	}

	/**
	 * Enable or disable caching
	 *
	 * @param bool $enable
	 *
	 * @return $this
	 */
	public function enable($enable)
	{
		// Enable it if we can
		if ($this->enabled === false && $this->_cache_obj === null)
		{
			$this->_init();
		}

		$this->enabled = (bool) $enable;

		return $this;
	}

	/**
	 * Check if caching is enabled
	 * @return bool
	 */
	public function isEnabled()
	{
		return $this->enabled;
	}

	/**
	 * Set the caching level. Setting it to <= 0 disables caching
	 *
	 * @param int $level
	 *
	 * @return $this
	 */
	public function setLevel($level)
	{
		$this->level = (int) $level;

		if ($this->level <= 0)
		{
			$this->enable(false);
		}

		return $this;
	}

	/**
	 * @return int
	 */
	public function getLevel()
	{
		return $this->level;
	}

	/**
	 * Checks if the system level is set to a value strictly higher than the
	 * required level of the cache request.
	 *
	 * @param int $level
	 *
	 * @return bool
	 */
	public function levelHigherThan($level)
	{
		return $this->isEnabled() && $this->level > $level;
	}

	/**
	 * Checks if the system level is set to a value strictly lower than the
	 * required level of the cache request.
	 * Returns true also if the cache is disabled (it's lower than any level).
	 *
	 * @param int $level
	 *
	 * @return bool
	 */
	public function levelLowerThan($level)
	{
		return !$this->isEnabled() || $this->level < $level;
	}

	/**
	 * @var bool If the result of the last get was a miss
	 */
	public function isMiss()
	{
		return $this->isEnabled() ? $this->_cache_obj->isMiss() : true;
	}

	/**
	 * @param $key
	 */
	public function remove($key)
	{
		if (!$this->isEnabled())
		{
			return;
		}

		$key = $this->_key($key);
		$this->_cache_obj->remove($key);
	}

	/**
	 * Get the key for the cache.
	 *
	 * @param string $key
	 *
	 * @return string
	 */
	protected function _key($key)
	{
		return $this->_key_prefix . $this->_cache_obj->fixkey($key);
	}

	/**
	 * Set $_key_prefix to a "unique" value based on timestamp of a file
	 */
	protected function _build_prefix()
	{
		global $boardurl;

		if (!file_exists(CACHEDIR . '/index.php'))
		{
			touch(CACHEDIR . '/index.php');
		}
		$this->_key_prefix = md5($boardurl . filemtime(CACHEDIR . '/index.php')) . '-ELK-';
	}

	/**
	 * Find and return the instance of the Cache class if it exists,
	 * or create it if it doesn't exist
	 */
	public static function instance()
	{
		if (self::$_instance === null)
		{
			global $cache_accelerator, $cache_enable, $cache_uid, $cache_password, $cache_memcached;

			$options = array();
			if ($cache_accelerator === 'xcache')
			{
				$options = array(
					'cache_uid' => $cache_uid,
					'cache_password' => $cache_password,
				);
			}
			elseif (substr($cache_accelerator, 0, 8) === 'memcache')
			{
				$options = array(
					'servers' => explode(',', $cache_memcached),
				);
			}

			Elk_Autoloader::getInstance()->register(SUBSDIR . '/CacheMethod', '\\ElkArte\\sources\\subs\\CacheMethod');
			self::$_instance = new Cache($cache_enable, $cache_accelerator, $options);
		}

		return self::$_instance;
	}
}