<?php if (!defined('BB2_CORE')) die('I said no cheating!');

// Specialized screening for trackbacks
function bb2_trackback($package)
{
	// Web browsers don't send trackbacks
	if ($package['is_browser']) {
		return 'f0dcb3fd';
	}

	// Proxy servers don't send trackbacks either
	if (array_key_exists('Via', $package['headers_mixed']) || array_key_exists('Max-Forwards', $package['headers_mixed']) || array_key_exists('X-Forwarded-For', $package['headers_mixed']) || array_key_exists('Client-Ip', $package['headers_mixed'])) {
		return 'd60b87c7';
	}

	// Fake WordPress trackbacks
	// Real ones do not contain Accept:, and have a charset defined
	// Real WP trackbacks may contain Accept: depending on the HTTP
	// transport being used by the sending host
	if (strpos($package['headers_mixed']['User-Agent'], "WordPress/") !== FALSE) {
		if (strpos($package['headers_mixed']['Content-Type'], "charset=") === FALSE) {
			return 'e3990b47';
		}
	}
	return false;
}

// All tests which apply specifically to POST requests
function bb2_post($settings, $package)
{
	// Check blackhole lists for known spam/malicious activity
	// require_once(BB2_CORE . "/blackhole.inc.php");
	// if ($r = bb2_blackhole($package)) return $r;

	// MovableType needs specialized screening
	if (stripos($package['headers_mixed']['User-Agent'], "MovableType") !== FALSE) {
		if (strcmp($package['headers_mixed']['Range'], "bytes=0-99999")) {
			return "7d12528e";
		}
	}

	// Trackbacks need special screening
	$request_entity = $package['request_entity'];
	if (isset($request_entity['title']) && isset($request_entity['url']) && isset($request_entity['blog_name'])) {
		return bb2_trackback($package);
	}

	// Catch a few completely broken spambots
	foreach ($request_entity as $key => $value) {
		$pos = strpos($key, "	document.write");
		if ($pos !== FALSE) {
			return "dfd9b1ad";
		}
	}

	// If Referer exists, it should refer to a page on our site
	if (!$settings['offsite_forms'] && array_key_exists('Referer', $package['headers_mixed'])) {
		$url = parse_url($package['headers_mixed']['Referer']);
		$url['host'] = preg_replace('|^www\.|', '', $url['host']);
		$host = preg_replace('|^www\.|', '', $package['headers_mixed']['Host']);
		# Strip port
		$host = preg_replace('|:\d+$|', '', $host);
		if (strcasecmp($host, $url['host'])) {
			return "cd361abb";
		}
	}

	return false;
}
