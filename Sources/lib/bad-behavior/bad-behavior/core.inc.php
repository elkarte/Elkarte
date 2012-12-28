<?php if (!defined('BB2_CWD')) die("I said no cheating!");
define('BB2_VERSION', "2.2.13");

// Bad Behavior entry point is bb2_start()
// If you're reading this, you are probably lost.
// Go read the bad-behavior-generic.php file.

define('BB2_CORE', dirname(__FILE__));
define('BB2_COOKIE', 'bb2_screener_');

require_once(BB2_CORE . "/functions.inc.php");

// Kill 'em all!
function bb2_banned($settings, $package, $key, $previous_key=false)
{
	// Some spambots hit too hard. Slow them down a bit.
	sleep(2);

	require_once(BB2_CORE . "/banned.inc.php");
	bb2_display_denial($settings, $package, $key, $previous_key);
	bb2_log_denial($settings, $package, $key, $previous_key);
	if (is_callable('bb2_banned_callback')) {
		bb2_banned_callback($settings, $package, $key);
	}
	// Penalize the spammers some more
	bb2_housekeeping($settings, $package);
	die();
}

function bb2_approved($settings, $package)
{
	// Dirk wanted this
	if (is_callable('bb2_approved_callback')) {
		bb2_approved_callback($settings, $package);
	}

	// Decide what to log on approved requests.
	if (($settings['verbose'] && $settings['logging']) || empty($package['user_agent'])) {
		bb2_db_query(bb2_insert($settings, $package, "00000000"));
	}
}

# If this is reverse-proxied or load balanced, obtain the actual client IP
function bb2_reverse_proxy($settings, $headers_mixed)
{
	# Detect if option is on when it should be off
	$header = uc_all($settings['reverse_proxy_header']);
	if (!array_key_exists($header, $headers_mixed)) {
		return false;
	}
	
	$addrs = @array_reverse(preg_split("/[\s,]+/", $headers_mixed[$header]));
	# Skip our known reverse proxies and private addresses
	if (!empty($settings['reverse_proxy_addresses'])) {
		foreach ($addrs as $addr) {
			if (!match_cidr($addr, $settings['reverse_proxy_addresses']) && !is_rfc1918($addr)) {
				return $addr;
			}
		}
	} else {
		foreach ($addrs as $addr) {
			if (!is_rfc1918($addr)) {
				return $addr;
			}
		}
	}
	# If we got here, someone is playing a trick on us.
	return false;
}

// Let God sort 'em out!
function bb2_start($settings)
{
	// Gather up all the information we need, first of all.
	$headers = bb2_load_headers();
	// Postprocess the headers to mixed-case
	// TODO: get the world to stop using PHP as CGI
	$headers_mixed = array();
	foreach ($headers as $h => $v) {
		$headers_mixed[uc_all($h)] = $v;
	}

	// IPv6 - IPv4 compatibility mode hack
	$_SERVER['REMOTE_ADDR'] = preg_replace("/^::ffff:/", "", $_SERVER['REMOTE_ADDR']);

	// Reconstruct the HTTP entity, if present.
	$request_entity = array();
	if (!strcasecmp($_SERVER['REQUEST_METHOD'], "POST") || !strcasecmp($_SERVER['REQUEST_METHOD'], "PUT")) {
		foreach ($_POST as $h => $v) {
			$request_entity[$h] = $v;
		}
	}

	$request_uri = $_SERVER["REQUEST_URI"];
	if (!$request_uri) $request_uri = $_SERVER['SCRIPT_NAME'];	# IIS

	if ($settings['reverse_proxy'] && $ip = bb2_reverse_proxy($settings, $headers_mixed)) {
		$headers['X-Bad-Behavior-Remote-Address'] = $_SERVER['REMOTE_ADDR'];
		$headers_mixed['X-Bad-Behavior-Remote-Address'] = $_SERVER['REMOTE_ADDR'];
	} else {
		$ip = $_SERVER['REMOTE_ADDR'];
	}

	@$package = array('ip' => $ip, 'headers' => $headers, 'headers_mixed' => $headers_mixed, 'request_method' => $_SERVER['REQUEST_METHOD'], 'request_uri' => $request_uri, 'server_protocol' => $_SERVER['SERVER_PROTOCOL'], 'request_entity' => $request_entity, 'user_agent' => $_SERVER['HTTP_USER_AGENT'], 'is_browser' => false,);

	$result = bb2_screen($settings, $package);
	if ($result && !defined('BB2_TEST')) bb2_banned($settings, $package, $result);
	return $result;
}

function bb2_screen($settings, $package)
{
	// Please proceed to the security checkpoint, have your identification
	// and boarding pass ready, and prepare to be nakedized or fondled.

	// CloudFlare-specific checks not handled by reverse proxy code
	// Thanks to butchs at Simple Machines
	if (array_key_exists('Cf-Connecting-Ip', $package['headers_mixed'])) {
		require_once(BB2_CORE . "/cloudflare.inc.php");
		$r = bb2_cloudflare($package);
		if ($r !== false && $r != $package['ip']) return $r;
	}

	// First check the whitelist
	require_once(BB2_CORE . "/whitelist.inc.php");
	if (!bb2_run_whitelist($package)) {
		// Now check the blacklist
		require_once(BB2_CORE . "/blacklist.inc.php");
		if ($r = bb2_blacklist($package)) return $r;

		// Check the http:BL
		require_once(BB2_CORE . "/blackhole.inc.php");
		if ($r = bb2_httpbl($settings, $package)) {
			if ($r == 1) return false;	# whitelisted
			return $r;
		}

		// Check for common stuff
		require_once(BB2_CORE . "/common_tests.inc.php");
		if ($r = bb2_protocol($settings, $package)) return $r;
		if ($r = bb2_cookies($settings, $package)) return $r;
		if ($r = bb2_misc_headers($settings, $package)) return $r;

		// Specific checks
		@$ua = $package['user_agent'];
		// Search engine checks come first
		if (stripos($ua, "bingbot") !== FALSE || stripos($ua, "msnbot") !== FALSE || stripos($ua, "MS Search") !== FALSE) {
			require_once(BB2_CORE . "/searchengine.inc.php");
			if ($r = bb2_msnbot($package)) {
				if ($r == 1) return false;	# whitelisted
				return $r;
			}
			return false;
		} elseif (stripos($ua, "Googlebot") !== FALSE || stripos($ua, "Mediapartners-Google") !== FALSE || stripos($ua, "Google Web Preview") !== FALSE) {
			require_once(BB2_CORE . "/searchengine.inc.php");
			if ($r = bb2_google($package)) {
				if ($r == 1) return false;	# whitelisted
				return $r;
			}
			return false;
		} elseif (stripos($ua, "Yahoo! Slurp") !== FALSE || stripos($ua, "Yahoo! SearchMonkey") !== FALSE) {
			require_once(BB2_CORE . "/searchengine.inc.php");
			if ($r = bb2_yahoo($package)) {
				if ($r == 1) return false;	# whitelisted
				return $r;
			}
			return false;
		} elseif (stripos($ua, "Baidu") !== FALSE) {
			require_once(BB2_CORE . "/searchengine.inc.php");
			if ($r = bb2_baidu($package)) {
				if ($r == 1) return false;	# whitelisted
				return $r;
			}
			return false;
		}
		// MSIE checks
		if (stripos($ua, "; MSIE") !== FALSE) {
			$package['is_browser'] = true;
			require_once(BB2_CORE . "/browser.inc.php");
			if (stripos($ua, "Opera") !== FALSE) {
				if ($r = bb2_opera($package)) return $r;
			} else {
				if ($r = bb2_msie($package)) return $r;
			}
		} elseif (stripos($ua, "Konqueror") !== FALSE) {
			$package['is_browser'] = true;
			require_once(BB2_CORE . "/browser.inc.php");
			if ($r = bb2_konqueror($package)) return $r;
		} elseif (stripos($ua, "Opera") !== FALSE) {
			$package['is_browser'] = true;
			require_once(BB2_CORE . "/browser.inc.php");
			if ($r = bb2_opera($package)) return $r;
		} elseif (stripos($ua, "Safari") !== FALSE) {
			$package['is_browser'] = true;
			require_once(BB2_CORE . "/browser.inc.php");
			if ($r = bb2_safari($package)) return $r;
		} elseif (stripos($ua, "Lynx") !== FALSE) {
			$package['is_browser'] = true;
			require_once(BB2_CORE . "/browser.inc.php");
			if ($r = bb2_lynx($package)) return $r;
		} elseif (stripos($ua, "MovableType") !== FALSE) {
			require_once(BB2_CORE . "/movabletype.inc.php");
			if ($r = bb2_movabletype($package)) return $r;
		} elseif (stripos($ua, "Mozilla") !== FALSE && stripos($ua, "Mozilla") == 0) {
			$package['is_browser'] = true;
			require_once(BB2_CORE . "/browser.inc.php");
			if ($r = bb2_mozilla($package)) return $r;
		}

		// More intensive screening applies to POST requests
		if (!strcasecmp('POST', $package['request_method'])) {
			require_once(BB2_CORE . "/post.inc.php");
			if ($r = bb2_post($settings, $package)) return $r;
		}
	}

	// Last chance screening.
	require_once(BB2_CORE . "/screener.inc.php");
	bb2_screener($settings, $package);

	// And that's about it.
	bb2_approved($settings, $package);
	return false;
}
