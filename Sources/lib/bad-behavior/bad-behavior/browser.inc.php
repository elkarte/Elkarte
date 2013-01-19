<?php if (!defined('BB2_CORE')) die('I said no cheating!');

// Analyze user agents claiming to be Konqueror

function bb2_konqueror($package)
{
	// CafeKelsa is a dev project at Yahoo which indexes job listings for
	// Yahoo! HotJobs. It identifies as Konqueror so we skip these checks.
	if (stripos($package['headers_mixed']['User-Agent'], "YahooSeeker/CafeKelsa") === FALSE || match_cidr($package['ip'], "209.73.160.0/19") === FALSE) {
		if (!array_key_exists('Accept', $package['headers_mixed'])) {
			return "17566707";
		}
	}
	return false;
}

// Analyze user agents claiming to be Lynx

function bb2_lynx($package)
{
	if (!array_key_exists('Accept', $package['headers_mixed'])) {
		return "17566707";
	}
	return false;
}

// Analyze user agents claiming to be Mozilla

function bb2_mozilla($package)
{
	// First off, workaround for Google Desktop, until they fix it FIXME
	// Google Desktop fixed it, but apparently some old versions are
	// still out there. :(
	// Always check accept header for Mozilla user agents
	if (strpos($package['headers_mixed']['User-Agent'], "Google Desktop") === FALSE && strpos($package['headers_mixed']['User-Agent'], "PLAYSTATION 3") === FALSE) {
		if (!array_key_exists('Accept', $package['headers_mixed'])) {
			return "17566707";
		}
	}
	return false;
}

// Analyze user agents claiming to be MSIE

function bb2_msie($package)
{
	if (!array_key_exists('Accept', $package['headers_mixed'])) {
		return "17566707";
	}

	// MSIE does NOT send "Windows ME" or "Windows XP" in the user agent
	if (strpos($package['headers_mixed']['User-Agent'], "Windows ME") !== FALSE || strpos($package['headers_mixed']['User-Agent'], "Windows XP") !== FALSE || strpos($package['headers_mixed']['User-Agent'], "Windows 2000") !== FALSE || strpos($package['headers_mixed']['User-Agent'], "Win32") !== FALSE) {
		return "a1084bad";
	}

	// MSIE does NOT send Connection: TE but Akamai does
	// Bypass this test when Akamai detected
	// The latest version of IE for Windows CE also uses Connection: TE
	if (!array_key_exists('Akamai-Origin-Hop', $package['headers_mixed']) && strpos($package['headers_mixed']['User-Agent'], "IEMobile") === FALSE && @preg_match('/\bTE\b/i', $package['headers_mixed']['Connection'])) {
		return "2b90f772";
	}

	return false;
}

// Analyze user agents claiming to be Opera

function bb2_opera($package)
{
	if (!array_key_exists('Accept', $package['headers_mixed'])) {
		return "17566707";
	}
	return false;
}

// Analyze user agents claiming to be Safari

function bb2_safari($package)
{
	if (!array_key_exists('Accept', $package['headers_mixed'])) {
		return "17566707";
	}
	return false;
}
