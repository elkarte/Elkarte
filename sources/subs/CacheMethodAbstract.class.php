<?php

abstract class Cache_Method_Abstract
{
	private $_options = null;

	public function __construct($options)
	{
		$this->_options = $options;
	}

	public function fixkey($key)
	{
		return $key;
	}
}