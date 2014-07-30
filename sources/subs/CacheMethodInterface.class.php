<?php

interface Cache_Method_Interface
{
	public function __construct($options);

	public function init();

	public function put($key, $value, $ttl);

	public function get($key, $ttl);

	public function clean($type);

	public function fixkey($key);
}