<?php
/**
 * This file contains functions that deal with getting and setting cache values.
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 1.0 Release Candidate 2
 *
 */

if (!defined('ELK'))
	die('No access...');

/**
 * Alternative PHP Cache or APC
 */
class Apc_Cache extends Cache_Method_Abstract
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
	public function put($key, $value, $ttl)
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
	public function get($key, $ttl)
	{
		return apc_fetch($key . 'elkarte');
	}

	/**
	 * {@inheritdoc }
	 */
	public function clean($type)
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
}