<?php

/**
 * Just a class to implement ArrayAccess and getter/setter and stuff like that.
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 1.1.9
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
	 * @param mixed[]|null $data Any array of data used to initialize the object (optional)
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
		if (isset($this->data[$key]))
			return $this->data[$key];
		else
			return null;
	}

	/**
	 * Calling as method uses the argument as default
	 *
	 * @param string|int $key
	 * @param mixed[] $args
	 * @return mixed
	 */
	public function __call($key, $args)
	{
		if (isset($this->data[$key]))
		{
			return $this->data[$key];
		}
		else
		{
			if (isset($args[0]))
			{
				return $args[0];
			}
			else
			{
				return null;
			}
		}
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
	#[\ReturnTypeWillChange]
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
	#[\ReturnTypeWillChange]
	public function offsetExists($offset)
	{
		return isset($this->data[$offset]);
	}

	/**
	 * Unset a certain offset key.
	 *
	 * @param string|int $offset
	 */
	#[\ReturnTypeWillChange]
	public function offsetUnset($offset)
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
		return isset($this->data[$offset]) ? $this->data[$offset] : null;
	}
}
