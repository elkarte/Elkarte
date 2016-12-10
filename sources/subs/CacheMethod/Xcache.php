<?php

/**
 * This file contains functions that deal with getting and setting cache values.
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 1.1 beta 4
 *
 */

namespace ElkArte\sources\subs\CacheMethod;

/**
 * Xcache.
 *
 * Xcache may need auth credentials, depending on how its been set up,
 * these credentials must be passed with the options array during the
 * initialization of the class in the indexes:
 *   - cache_uid
 *   - cache_password
 */
class Xcache extends Cache_Method_Abstract
{
	/**
	 * {@inheritdoc}
	 */
	protected $title = 'XCache';

	/**
	 * {@inheritdoc}
	 */
	public function __construct($options)
	{
		parent::__construct($options);

		// Xcache may need auth credentials, depending on how its been set up
		if (!empty($this->_options['cache_uid']) && !empty($this->_options['cache_password']) && $this->isAvailable())
		{
			$_SERVER['PHP_AUTH_USER'] = $this->_options['cache_uid'];
			$_SERVER['PHP_AUTH_PW'] = $this->_options['cache_password'];
		}
	}

	/**
	 * {@inheritdoc}
	 */
	public function exists($key)
	{
		return xcache_isset($key);
	}

	/**
	 * {@inheritdoc}
	 */
	public function put($key, $value, $ttl = 120)
	{
		if ($value === null)
			xcache_unset($key);
		else
			xcache_set($key, $value, $ttl);
	}

	/**
	 * {@inheritdoc}
	 */
	public function get($key, $ttl = 120)
	{
		$result = xcache_get($key);
		$this->is_miss = $result === null;

		return $result;
	}

	/**
	 * {@inheritdoc}
	 */
	public function clean($type = '')
	{
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

	/**
	 * {@inheritdoc}
	 */
	public function isAvailable()
	{
		return function_exists('xcache_set') && ini_get('xcache.var_size') > 0;
	}

	/**
	 * {@inheritdoc}
	 */
	public function details()
	{
		return array('title' => $this->title, 'version' => XCACHE_VERSION);
	}

	/**
	 * Adds the settings to the settings page.
	 *
	 * Used by integrate_modify_cache_settings added in the title method
	 *
	 * @param array $config_vars
	 */
	public function settings(&$config_vars)
	{
		global $txt;

		$config_vars[] = array('cache_uid', $txt['cache_uid'], 'file', 'text', $txt['cache_uid'], 'cache_uid', 'force_div_id' => 'xcache_cache_uid');
		$config_vars[] = array('cache_password', $txt['cache_password'], 'file', 'password', $txt['cache_password'], 'cache_password', 'force_div_id' => 'xcache_cache_password');
	}
}
