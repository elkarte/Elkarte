<?php

class Errors
{
	public function fatal_error($msg)
	{
		print_r($msg);
	}
	public function log_error()
	{
	}

	public static function instance()
	{
		return new Errors();
	}
}