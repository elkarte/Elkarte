<?php if (!defined('BB2_CORE')) die('I said no cheating!');

function bb2_run_whitelist($package)
{
	# FIXME: Transitional, until port maintainters implement bb2_read_whitelist
	if (function_exists('bb2_read_whitelist')) {
		$whitelists = bb2_read_whitelist();
	} else {
		$whitelists = @parse_ini_file(dirname(BB2_CORE) . "/whitelist.ini");
	}

	if (@!empty($whitelists['ip'])) {
		foreach (array_filter($whitelists['ip']) as $range) {
			if (match_cidr($package['ip'], $range)) return true;
		}
	}
	if (@!empty($whitelists['useragent'])) {
		foreach (array_filter($whitelists['useragent']) as $user_agent) {
			if (!strcmp($package['headers_mixed']['User-Agent'], $user_agent)) return true;
		}
	}
	if (@!empty($whitelists['url'])) {
		if (strpos($package['request_uri'], "?") === FALSE) {
			$request_uri = $package['request_uri'];
		} else {
			$request_uri = substr($package['request_uri'], 0, strpos($package['request_uri'], "?"));
		}
		foreach (array_filter($whitelists['url']) as $url) {
			$pos = strpos($request_uri, $url);
			if ($pos !== false && $pos == 0) return true;
		}
	}
	return false;
}
