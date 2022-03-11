<?php

class Errors
{
	public function fatal_error($msg)
	{
		print_r($msg);
	}

	public function fatal_lang_error($msg)
	{
		global $txt;

		if (isset($txt[$msg]))
		{
			$msg = $txt[$msg];
		}

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
