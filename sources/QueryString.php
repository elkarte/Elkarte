<?php

/**
 * This file does a lot of important stuff.  Mainly, this means it handles
 * the query string, request variables, and session management.
 *
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause  (see accompanying LICENSE.txt file)
 *
 * This file contains code covered by:
 * copyright:	2011 Simple Machines (http://www.simplemachines.org)
 * license:		BSD, See included LICENSE.TXT for terms and conditions.
 *
 * @version 2.0 dev
 *
 */

/**
 * Clean the request variables - add html entities to GET.
 *
 * What it does:
 *
 * - Uses Request to determine as best it can client IPs for the current request.
 * - Uses Request cleanRequest() to:
 *   - Clean the request variables (ENV, GET, POST, COOKIE, SERVER)
 *   - Makes sure the query string was parsed correctly.
 *   - Handles the URLs passed by the queryless URLs option.
 *   - Makes sure, regardless of php.ini, everything has slashes.
 * - Uses Request parseRequest() to clean and set up variables like $board or $_REQUEST'start'].
 */
function cleanRequest()
{
	// Make sure REMOTE_ADDR, other IPs, and the like are parsed
	$req = \ElkArte\Request::instance();

	$parser = initUrlGenerator()->getParser();

	// Make sure there are no problems with the request
	$req->cleanRequest($parser);

	// Parse the $_REQUEST and make sure things like board, topic don't have weird stuff
	$req->parseRequest();
}

/**
 * Validates a IPv6 address. returns true if it is ipv6.
 *
 * @param string $ip ip address to be validated
 *
 * @return boolean true|false
 */
function isValidIPv6($ip)
{
	return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false;
}

/**
 * Converts IPv6s to numbers.  This makes ban checks much easier.
 *
 * @param string $ip ip address to be converted
 *
 * @return int[] array
 */
function convertIPv6toInts($ip)
{
	static $expanded = array();

	// Check if we have done this already.
	if (isset($expanded[$ip]))
		return $expanded[$ip];

	// Expand the IP out.
	$expanded_ip = explode(':', expandIPv6($ip));

	$new_ip = array();
	foreach ($expanded_ip as $int)
		$new_ip[] = hexdec($int);

	// Save this in case of repeated use.
	$expanded[$ip] = $new_ip;

	return $expanded[$ip];
}

/**
 * Expands a IPv6 address to its full form.
 *
 * @param string $addr ipv6 address string
 * @param boolean $strict_check checks length to expanded address for compliance
 *
 * @return boolean|string expanded ipv6 address.
 */
function expandIPv6($addr, $strict_check = true)
{
	static $converted = array();

	// Check if we have done this already.
	if (isset($converted[$addr]))
		return $converted[$addr];

	// Check if there are segments missing, insert if necessary.
	if (strpos($addr, '::') !== false)
	{
		$part = explode('::', $addr);
		$part[0] = explode(':', $part[0]);
		$part[1] = explode(':', $part[1]);
		$missing = array();

		// Looks like this is an IPv4 address
		if (isset($part[1][1]) && strpos($part[1][1], '.') !== false)
		{
			$ipoct = explode('.', $part[1][1]);
			$p1 = dechex($ipoct[0]) . dechex($ipoct[1]);
			$p2 = dechex($ipoct[2]) . dechex($ipoct[3]);

			$part[1] = array(
				$part[1][0],
				$p1,
				$p2
			);
		}

		$limit = count($part[0]) + count($part[1]);
		for ($i = 0; $i < (8 - $limit); $i++)
			array_push($missing, '0000');

		$part = array_merge($part[0], $missing, $part[1]);
	}
	else
		$part = explode(':', $addr);

	// Pad each segment until it has 4 digits.
	foreach ($part as &$p)
		while (strlen($p) < 4)
			$p = '0' . $p;

	unset($p);

	// Join segments.
	$result = implode(':', $part);

	// Save this in case of repeated use.
	$converted[$addr] = $result;

	// Quick check to make sure the length is as expected.
	if (!$strict_check || strlen($result) == 39)
		return $result;
	else
		return false;
}

/**
 * Adds html entities to the array/variable.  Uses two underscores to guard against overloading.
 *
 * What it does:
 *
 * - Adds entities (&quot;, &lt;, &gt;) to the array or string var.
 * - Importantly, does not effect keys, only values.
 * - Calls itself recursively if necessary.
 * - Does not go deeper than 25 to prevent loop exhaustion
 *
 * @param array|string $var The string or array of strings to add entities
 * @param int $level = 0 The current level we're at within the array (if called recursively)
 *
 * @return array|string The string or array of strings with entities added
 */
function htmlspecialchars__recursive($var, $level = 0)
{
	if (!is_array($var))
		return \ElkArte\Util::htmlspecialchars($var, ENT_QUOTES);

	// Add the htmlspecialchars to every element.
	foreach ($var as $k => $v)
		$var[$k] = $level > 25 ? null : htmlspecialchars__recursive($v, $level + 1);

	return $var;
}

/**
 * Trim a string including the HTML space, character 160.  Uses two underscores to guard against overloading.
 *
 * What it does:
 *
 * - Trims a string or an array using html characters as well.
 * - Remove spaces (32), tabs (9), returns (13, 10, and 11), nulls (0), and hard spaces. (160)
 * - Does not effect keys, only values.
 * - May call itself recursively if needed.
 * - Does not go deeper than 25 to prevent loop exhaustion
 *
 * @param array|string $var The string or array of strings to trim
 * @param int $level = 0 How deep we're at within the array (if called recursively)
 *
 * @return mixed[]|string The trimmed string or array of trimmed strings
 */
function htmltrim__recursive($var, $level = 0)
{
	// Remove spaces (32), tabs (9), returns (13, 10, and 11), nulls (0), and hard spaces. (160)
	if (!is_array($var))
		return \ElkArte\Util::htmltrim($var);

	// Go through all the elements and remove the whitespace.
	foreach ($var as $k => $v)
		$var[$k] = $level > 25 ? null : htmltrim__recursive($v, $level + 1);

	return $var;
}

/**
 * Clean up the XML to make sure it doesn't contain invalid characters.
 *
 * What it does:
 *
 * - Removes invalid XML characters to assure the input string being
 * parsed properly.
 *
 * @param string $string The string to clean
 *
 * @return string The clean string
 */
function cleanXml($string)
{
	// http://www.w3.org/TR/2000/REC-xml-20001006#NT-Char
	return preg_replace('~[\x00-\x08\x0B\x0C\x0E-\x19\x{FFFE}\x{FFFF}]~u', '', $string);
}

/**
 * Escapes (replaces) characters in strings to make them safe for use in javascript
 *
 * @param string $string The string to escape
 *
 * @return string The escaped string
 */
function JavaScriptEscape($string)
{
	global $scripturl;

	return '\'' . strtr($string, array(
		"\r" => '',
		"\n" => '\\n',
		"\t" => '\\t',
		'\\' => '\\\\',
		'\'' => '\\\'',
		'</' => '<\' + \'/',
		'<script' => '<scri\'+\'pt',
		'<body>' => '<bo\'+\'dy>',
		'<a href' => '<a hr\'+\'ef',
		$scripturl => '\' + elk_scripturl + \'',
	)) . '\'';
}

/**
 * Rewrite URLs to include the session ID.
 *
 * What it does:
 *
 * - Rewrites the URLs outputted to have the session ID, if the user
 *   is not accepting cookies and is using a standard web browser.
 * - Handles rewriting URLs for the queryless URLs option.
 * - Can be turned off entirely by setting $scripturl to an empty
 *   string, ''. (it would not work well like that anyway.)
 *
 * @param string $buffer The unmodified output buffer
 *
 * @return string The modified output buffer
 */
function ob_sessrewrite($buffer)
{
	global $scripturl, $modSettings;

	// If $scripturl is set to nothing, or the SID is not defined (SSI?) just quit.
	if ($scripturl == '' || !defined('SID'))
		return $buffer;

	// Do nothing if the session is cookied, or they are a crawler - guests are caught by redirectexit().
	if (empty($_COOKIE) && SID != '' && !isBrowser('possibly_robot'))
		$buffer = preg_replace('/(?<!<link rel="canonical" href=)"' . preg_quote($scripturl, '/') . '(?!\?' . preg_quote(SID, '/') . ')\\??/', '"' . $scripturl . '?' . SID . '&amp;', $buffer);

	// Debugging templates, are we?
	elseif (isset($_GET['debug']))
		$buffer = preg_replace('/(?<!<link rel="canonical" href=)"' . preg_quote($scripturl, '/') . '\\??/', '"' . $scripturl . '?debug;', $buffer);

	// Return the changed buffer.
	return $buffer;
}

/**
 * Callback function for the Rewrite URLs preg_replace_callback
 *
 * @param mixed[] $matches
 *
 * @return string
 */
function buffer_callback($matches)
{
	global $scripturl;

	if (!isBrowser('possibly_robot') && empty($_COOKIE) && defined('SID') && SID != '')
		return '"' . $scripturl . '/' . strtr($matches[1], '&;=', '//,') . '.html?' . SID . (isset($matches[2]) ? $matches[2] : '') . '"';
	else
		return '"' . $scripturl . '/' . strtr($matches[1], '&;=', '//,') . '.html' . (isset($matches[2]) ? $matches[2] : '') . '"';
}
