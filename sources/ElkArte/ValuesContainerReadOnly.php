<?php

/**
 * Just a class to implement ArrayAccess and getter/setter and stuff like that.
 *
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * @version 2.0 dev
 *
 */

namespace ElkArte;

/**
 * Class ValuesContainerReadOnly
 *
 * - Implements generic ArrayAccess and getter bag for use.
 * - The setter is a dummy that makes the array basically read-only
 *
 * @package ElkArte
 */
class ValuesContainerReadOnly extends ValuesContainer
{
	/**
	 * Dummy setter.
	 * In order to make the array read-only this method does nothing.
	 *
	 * @param string|int $key
	 * @param mixed $val
	 */
	public function __set($key, $val)
	{
	}

	/**
	 * Dummy setter.
	 * In order to make the array read-only this method does nothing.
	 *
	 * @param mixed|array $offset
	 * @param string $value
	 */
	public function offsetSet($offset, $value) : void
	{
	}

	/**
	 * Dummy unset.
	 * In order to make the array read-only this method does nothing.
	 *
	 * @param string|int $offset
	 */
	public function offsetUnset($offset) : void
	{
	}

	/**
	 * Dummy merger.
	 * In order to make the array read-only this method does nothing.
	 *
	 * @param array $new_data
	 */
	public function mergeWith($new_data)
	{
	}
}
