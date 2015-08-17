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
 * eAccelerator
 */
class Eaccelerator extends Cache_Method_Abstract
{
	/**
	 * {@inheritdoc }
	 */
	public function init()
	{
		return function_exists('eaccelerator_put');
	}

	/**
	 * {@inheritdoc }
	 */
	public function put($key, $value, $ttl = 120)
	{
		if (mt_rand(0, 10) == 1)
			eaccelerator_gc();

		if ($value === null)
			@eaccelerator_rm($key);
		else
			eaccelerator_put($key, $value, $ttl);
	}

	/**
	 * {@inheritdoc }
	 */
	public function get($key, $ttl = 120)
	{
		if (function_exists('eaccelerator_get'))
			return eaccelerator_get($key);

		return false;
	}

	/**
	 * {@inheritdoc }
	 */
	public function clean($type = '')
	{
		// Clean out the already expired items
		@eaccelerator_clean();

		// Remove all unused scripts and data from shared memory and disk cache,
		// e.g. all data that isn't used in the current requests.
		@eaccelerator_clear();
	}

	/**
	 * {@inheritdoc }
	 */
	public static function available()
	{
		return defined('EACCELERATOR_VERSION');
	}

	/**
	 * {@inheritdoc }
	 */
	public static function details()
	{
		return array('title' => self::title(), 'version' => EACCELERATOR_VERSION);
	}

	/**
	 * {@inheritdoc }
	 */
	public static function title()
	{
		return 'eAccelerator';
	}
}