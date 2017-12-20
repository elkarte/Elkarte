<?php

/**
 * The interface of the caching methods
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 2.0 dev
 *
 */

namespace ElkArte\sources\subs\CacheMethod;

/**
 * In order to work with ElkArte any caching method must implement this
 * interface
 *
 */
interface Cache_Method_Interface
{
	/**
	 * The class is initialized passing the settings of the cache
	 *
	 * allows to "initialize" the caching engine if needed
	 *
	 * @param mixed $options
	 */
	public function __construct($options);

	/**
	 * Check that the specified cache entry exists on the filesystem.
	 *
	 * @param string $key
	 * @return bool
	 */
	public function exists($key);

	/**
	 * Puts value in the cache under key for ttl seconds.
	 *
	 * @param string $key
	 * @param string|int|mixed[]|null $value
	 * @param int $ttl = 120
	 */
	public function put($key, $value, $ttl = 120);

	/**
	 * Gets the value from the cache specified by key,
	 * so long as it is not older than ttl seconds.
	 *
	 * @param string $key
	 * @param int $ttl = 120
	 * @return null|mixed[]
	 */
	public function get($key, $ttl = 120);

	/**
	 * Empty out the cache in use as best it can.
	 *
	 * It may only remove the files of a certain type (if the $type parameter is given)
	 * Type can be user, data or left blank
	 *  - user clears out user data
	 *  - data clears out system / opcode data
	 *  - If no type is specified will perform a complete cache clearing
	 * For cache engines that do not distinguish on types, a full cache flush
	 * should be done.
	 *
	 * @param string $type = ''
	 */
	public function clean($type = '');

	/**
	 * Certain caching engines (e.g. filesystem) may require fixes to the cache key
	 * this method is here to allow fixing the key appropriately.
	 *
	 * @param string $key
	 * @return string
	 */
	public function fixkey($key);

	/**
	 * method to determine if the engine is available
	 *
	 * @return bool
	 */
	public function isAvailable();

	/**
	 * method to return available details on the server settings of the
	 * cache engine (title and version).
	 *
	 * Returns an array with two indexes:
	 *   - title
	 *   - version
	 *
	 * @return string[]
	 */
	public function details();

	/**
	 * Gives the (human-readable) name of the caching engine
	 *
	 * @return string
	 */
	public function title();

	/**
	 * Check if the last result was a miss
	 * @return bool
	 */
	public function isMiss();

	/**
	 * Remove a item from the cache
	 * @param string $key
	 * @return void
	 */
	public function remove($key);
}
