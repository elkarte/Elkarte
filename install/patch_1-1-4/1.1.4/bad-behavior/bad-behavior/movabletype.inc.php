<?php if (!defined('BB2_CORE')) die('I said no cheating!');

function bb2_movabletype($package)
{
	// Is it a trackback?
	if (strcasecmp($package['request_method'], "POST")) {
		if (strcmp($package['headers_mixed']['Range'], "bytes=0-99999")) {
			return "7d12528e";
		}
	}
	return false;
}
