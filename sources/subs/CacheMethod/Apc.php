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
 * Alternative PHP Cache or APC / APCu
 */
class Apc extends Cache_Method_Abstract
{
	/**
	 * {@inheritdoc }
	 */
	public function init()
	{
		return function_exists('apc_store');
	}

	/**
	 * {@inheritdoc }
	 */
	public function put($key, $value, $ttl = 120)
	{
		// An extended key is needed to counteract a bug in APC.
		if ($value === null)
			apc_delete($key . 'elkarte');
		else
			apc_store($key . 'elkarte', $value, $ttl);
	}

	/**
	 * {@inheritdoc }
	 */
	public function get($key, $ttl = 120)
	{
		return apc_fetch($key . 'elkarte');
	}

	/**
	 * {@inheritdoc }
	 */
	public function clean($type = '')
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

	/**
	 * {@inheritdoc }
	 */
	public static function available()
	{
		return extension_loaded('apc') || extension_loaded('apcu');
	}

	/**
	 * {@inheritdoc }
	 */
	public static function details()
	{
		return array('title' => self::title(), 'version' => phpversion('apc'));
	}

	/**
	 * {@inheritdoc }
	 */
	public static function title()
	{
		return 'Alternative PHP Cache';
	}
}