<?php

/**
 * Just a class to implement ArrayAccess and getter/setter and stuff like that.
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 1.0.3
 *
 */

namespace ElkArte;

if (!defined('ELK'))
	die('No access...');

class ValuesContainer implements \ArrayAccess
{
	/**
	 * The array that holds all the data collected by the object.
	 *
	 * @var mixed[]
	 */
	protected $data = array();

	public function __construct($data = null)
	{
		if ($data !== null)
			$this->data = (array) $data;
	}

	/**
	 * Setter
	 *
	 * @param string|int $key
	 * @param string|int|bool|null|object $val
	 */
	public function __set($key, $val)
	{
		$this->data[$key] = $val;
	}

	/**
	 * Getter
	 *
	 * @param string|int $key
	 * @return string|int|bool|null|object
	 */
	public function __get($key)
	{
		if (isset($this->data[$key]))
			return $this->data[$key];
		else
			return null;
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
	 * Tests if an offeset key is set.
	 *
	 * @param string|int $offset
	 * @return bool
	 */
	public function offsetExists($offset)
	{
		return isset($this->data[$offset]);
	}

	/**
	 * Unsets a certain offeset key.
	 *
	 * @param string|int $offset
	 */
	public function offsetUnset($offset)
	{
		unset($this->data[$offset]);
	}

	/**
	 * Returns the value associated to a certain offeset.
	 *
	 * @param string|int $offset
	 * @return mixed|mixed[]
	 */
	public function offsetGet($offset)
	{
		return isset($this->data[$offset]) ? $this->data[$offset] : null;
	}
}