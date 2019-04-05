<?php

/**
 * This file contains functions that deal with getting and setting cache values.
 *
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * @version 2.0 dev
 *
 */

namespace ElkArte\Cache\CacheMethod;

/**
 * Alternative PHP Cache or APC / APCu
 */
class Apc extends AbstractCacheMethod
{
	/**
	 * {@inheritdoc}
	 */
	protected $title = 'Alternative PHP Cache';

	/**
	 * Whether to use the APCu functions or the original APC ones.
	 *
	 * @var bool
	 */
	protected $apcu = false;

	/**
	 * {@inheritdoc}
	 */
	public function __construct($options)
	{
		parent::__construct($options);
		$this->apcu = function_exists('apcu_store');
	}

	/**
	 * {@inheritdoc}
	 */
	public function exists($key)
	{
		$this->get($key);
		return !$this->is_miss;
	}

	/**
	 * {@inheritdoc}
	 */
	public function put($key, $value, $ttl = 120)
	{
		$prefixedKey = $this->getprefixedKey($key);
		// An extended key is needed to counteract a bug in APC.
		if ($this->apcu)
		{
			if ($value === null)
				apcu_delete($prefixedKey);
			else
				apcu_store($prefixedKey, $value, $ttl);
		}
		else
		{
			if ($value === null)
				apc_delete($prefixedKey);
			else
				apc_store($prefixedKey, $value, $ttl);
		}
	}

	/**
	 * {@inheritdoc}
	 */
	public function get($key, $ttl = 120)
	{
		$prefixedKey = $this->getprefixedKey($key);
		$success = false;
		if ($this->apcu)
			$result = apcu_fetch($prefixedKey, $success);
		else
			$result = apc_fetch($prefixedKey, $success);
		$this->is_miss = !$success;

		/*
		 * Let's be conssistent, yes? All other cache methods
		 * supported by ElkArte return null on failure to grab
		 * the specified cache entry.
		 */
		if ($this->is_miss)
			return;

		return $result;
	}

	/**
	 * {@inheritdoc}
	 */
	public function clean($type = '')
	{
		if ($this->apcu)
			apcu_clear_cache();
		// If passed a type, clear that type out
		elseif ($type === '' || $type === 'data')
		{
			apc_clear_cache('user');
			apc_clear_cache('system');
		}
		elseif ($type === 'user')
			apc_clear_cache('user');
	}

	/**
	 * {@inheritdoc}
	 */
	public function isAvailable()
	{
		return function_exists('apc_store') || function_exists('apcu_store');
	}

	/**
	 * {@inheritdoc}
	 */
	public function details()
	{
		return array('title' => $this->title, 'version' => phpversion($this->apcu ? 'apcu' : 'apc'));
	}
}
