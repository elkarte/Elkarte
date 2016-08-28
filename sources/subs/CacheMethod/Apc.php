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
 * Alternative PHP Cache or APC / APCu
 */
class Apc extends Cache_Method_Abstract
{
	/**
	 * This is prefixed to all cacahe entries so that different
	 * applications won't interfere with each other.
	 *
	 * @var string
	 */
	protected $namespace = 'elkarte';

	/**
	 * Whether to use the APCu functions or the original APC ones.
	 *
	 * @var string
	 */
	protected $apcu = false;

	/**
	 * {@inheritdoc }
	 */
	public function init()
	{
		$this->apcu = function_exists('apcu_store');
	}

	/**
	 * {@inheritdoc }
	 */
	public function put($key, $value, $ttl = 120)
	{
		$namespacedKey = $this->namespace . ':' . $key;
		// An extended key is needed to counteract a bug in APC.
		if ($this->apcu)
		{
			if ($value === null)
				apcu_delete($namespacedKey);
			else
				apcu_store($namespacedKey, $value, $ttl);
		}
		else
		{
			if ($value === null)
				apc_delete($namespacedKey);
			else
				apc_store($namespacedKey, $value, $ttl);
		}
	}

	/**
	 * {@inheritdoc }
	 */
	public function get($key, $ttl = 120)
	{
		$namespacedKey = $this->namespace . ':' . $key;
		$success = false;
		if ($this->apcu)
			$result = apcu_fetch($namespacedKey, $success);
		else
			$result = apc_fetch($namespacedKey, $success);
		$this->is_miss = !$success;

		return $result;
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
		elseif ($this->apcu)
			apcu_clear_cache();
	}

	/**
	 * {@inheritdoc }
	 */
	public function isAvailable()
	{
		return function_exists('apc_store') || function_exists('apcu_store');
	}

	/**
	 * {@inheritdoc }
	 */
	public function details()
	{
		return array('title' => $this->title(), 'version' => phpversion('apc'));
	}

	/**
	 * {@inheritdoc }
	 */
	public function title()
	{
		return 'Alternative PHP Cache';
	}
}
