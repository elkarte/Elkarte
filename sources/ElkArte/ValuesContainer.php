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
	/**
	 * The array that holds all the data collected by the object.
	 *
	 * @var mixed[]
	 */
	protected $data = array();

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
	 * Setter
	 *
	 * @param string|int $key
	 * @param mixed $val
	 */
	public function __set($key, $val)
	{
		$this->data[$key] = $val;
	}

	/**
	 * Getter
	 *
	 * @param string|int $key
	 * @return mixed
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
	 * Calling as method uses the argument as default
	 *
	 * @param string|int $key
	 * @param array $args
	 * @return mixed
	 */
	public function __call($key, $args)
	{
		if (isset($this->data[$key]))
		{
			return $this->data[$key];
		}

		return $args[0] ?? null;
	}

	/**
	 * Tests if the key is set.
	 *
	 * @param string|int $key
	 * @return bool
	 */
	public function __isset($key)
	{
		return isset($this->data[$key]);
	}

	/**
	 * Assigns a value to a certain offset.
	 *
	 * @param mixed|mixed[] $offset
	 * @param string $value
	 */
	public function offsetSet($offset, $value)
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
	 * Tests if an offset key is set.
	 *
	 * @param string|int $offset
	 * @return bool
	 */
	public function offsetExists($offset)
	{
		return isset($this->data[$offset]);
	}

	/**
	 * Unset a certain offset key.
	 *
	 * @param string|int $offset
	 */
	public function offsetUnset($offset)
	{
		unset($this->data[$offset]);
	}

	/**
	 * Returns the value associated to a certain offset.
	 *
	 * @param string|int $offset
	 * @return mixed|array
	 */
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
	 * @param mixed[] $new_data
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
	 * @return mixed[]
	 */
	public function toArray()
	{
		return $this->data;
	}
}
