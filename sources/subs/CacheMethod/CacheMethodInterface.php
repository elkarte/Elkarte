<?php

/**
 * The interface of the caching methods
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
 * In order to work with ElkArte any caching method must implement this
 * interface
 *
 */
interface Cache_Method_Interface
{
	/**
	 * The class is initialized passing the settings of the cache
	 *
	 * @param mixed $options
	 */
	public function __construct($options);

	/**
	 * A method that allows to "initialize" the caching engine if needed
	 */
	public function init();

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
	 * Certain caching engines (e.g. filesyste) may require fixes to the cache key
	 * this method is here to allow fixing the key appropriately.
	 *
	 * @param string $key
	 * @return string
	 */
	public function fixkey($key);

	/**
	 * Static method to determine if the engine is available
	 *
	 * @return bool
	 */
	public static function available();

	/**
	 * Static method to return available details on the server settings of the
	 * cache engine (title and version).
	 *
	 * Returns an array with two indexes:
	 *   - title
	 *   - version
	 *
	 * @return string[]
	 */
	public static function details();

	/**
	 * Gives the (human-readable) name of the caching engine
	 *
	 * @return string
	 */
	public static function title();
}