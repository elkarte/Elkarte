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
 * Class ValuesContainer
 *
 * - Implements generic ArrayAccess and getter/setter bag for use
 *
 * @package ElkArte
 */
class ValuesContainer implements \ArrayAccess
{
	/** @var array The array that holds all the data collected by the object. */
	protected $data = [];

	/**
	 * Constructor
	 *
	 * @param array|null $data Any array of data used to initialize the object (optional)
	 */
	public function __construct($data = null)
	{
		if ($data !== null)
		{
			$this->data = (array) $data;
		}
	}

	/**
	 * Sets the value of the specified key in the data array.
	 *
	 * @param string $key The key to be set.
	 * @param mixed $val The value to be set for the specified key.
	 * @return void
	 */
	public function __set($key, $val)
	{
		$this->data[$key] = $val;
	}

	/**
	 * Retrieves the value of the specified key from the data array.
	 * If the key does not exist, null is returned.
	 *
	 * @param string $key The key of the value to retrieve from the data array.
	 * @return mixed|null The value associated with the specified key, or null if the key does not exist.
	 */
	public function __get($key)
	{
		return $this->data[$key] ?? null;
	}

	/**
	 * Returns the value of the key, or the default if empty
	 *
	 * @param string|int $key
	 * @param mixed $default
	 * @return mixed
	 */
	public function getEmpty($key, $default)
	{
		if (!empty($this->data[$key]))
		{
			return $this->data[$key];
		}

		return $default;
	}

	/**
	 * Dynamically handles method calls on the object.
	 * Returns the value of the specified key in the internal data array if it exists,
	 * otherwise, returns the first argument passed to the method, or null if no arguments were provided.
	 *
	 * @param string $key The name of the key to retrieve from the internal data array.
	 * @param array $args Optional arguments that can be passed to the method.
	 * @return mixed|null The value of the specified key in the internal data array, the first argument passed to the method, or null if no arguments were provided.
	 */
	public function __call($key, $args)
	{
		return $this->data[$key] ?? $args[0] ?? null;
	}

	/**
	 * Checks if a specific key exists in the data array.
	 * Same as calling `isset()` on the data array with the given key.
	 *
	 * @param mixed $key The key to check for existence in the data array.
	 * @return bool Returns true if the key exists in the data array, false otherwise.
	 */
	public function __isset($key)
	{
		return isset($this->data[$key]);
	}

	/**
	 * Assigns a value to a certain offset.
	 *
	 * @param mixed|array $offset
	 * @param string $value
	 */
	public function offsetSet($offset, $value): void
	{
		if (is_null($offset))
		{
			$this->data[] = $value;
		}
		else
		{
			$this->data[$offset] = $value;
		}
	}

	/**
	 * Checks if the specified offset exists in the data array.
	 *
	 * @param mixed $offset The offset to check.
	 * @return bool Returns true if the offset exists, otherwise false.
	 */
	public function offsetExists($offset): bool
	{
		return isset($this->data[$offset]);
	}

	/**
	 * Unset a certain offset key.
	 *
	 * @param string|int $offset
	 */
	public function offsetUnset($offset): void
	{
		unset($this->data[$offset]);
	}

	/**
	 * Returns the value associated to a certain offset.
	 *
	 * @param string|int $offset
	 * @return mixed
	 */
	#[\ReturnTypeWillChange]
	public function offsetGet($offset)
	{
		return $this->data[$offset] ?? null;
	}

	/**
	 * Returns if the data array is empty or not
	 *
	 * @return bool
	 */
	public function isEmpty()
	{
		return empty($this->data);
	}

	/**
	 * Merges the passed array into the existing one.
	 * Works the same as array_merge.
	 *
	 * @param array $new_data
	 */
	public function mergeWith($new_data)
	{
		$this->data = array_merge($this->data, $new_data);
	}

	/**
	 * Returns the number of elements in the object
	 */
	public function count()
	{
		return $this->isEmpty() ? 0 : count($this->data);
	}

	/**
	 * Returns the data of the user in an array format
	 *
	 * @return array
	 */
	public function toArray()
	{
		return $this->data;
	}
}
