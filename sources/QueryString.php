<?php

/**
 * This file does a lot of important stuff.  Mainly, this means it handles
 * the query string, request variables, and session management.
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * This file contains code also covered by:
 *
 * copyright:	2011 Simple Machines (http://www.simplemachines.org)
 * license:		BSD, See included LICENSE.TXT for terms and conditions.
 *
 * @version 1.0 Release Candidate 1
 *
 */

if (!defined('ELK'))
	die('No access...');

/**
 * Clean the request variables - add html entities to GET and slashes if magic_quotes_gpc is Off.
 *
 * What it does:
 * - cleans the request variables (ENV, GET, POST, COOKIE, SERVER) and
 *   makes sure the query string was parsed correctly.
 * - handles the URLs passed by the queryless URLs option.
 * - makes sure, regardless of php.ini, everything has slashes.
 * - uses Request parseRequest() to clean and set up variables like $board or $_REQUEST'start'].
 * - uses Request to try to determine client IPs for the current request.
 */
function cleanRequest()
{
	global $boardurl, $scripturl;

	// Makes it easier to refer to things this way.
	$scripturl = $boardurl . '/index.php';

	// We'll need this fairly badly
	require_once(SOURCEDIR . '/Request.php');

	// Reject magic_quotes_sybase='on'.
	if (ini_get('magic_quotes_sybase') || strtolower(ini_get('magic_quotes_sybase')) == 'on')
		die('magic_quotes_sybase=on was detected: your host is using an unsecure PHP configuration, deprecated and removed in current versions. Please upgrade PHP.');

	if (function_exists('get_magic_quotes_gpc') && get_magic_quotes_gpc() != 0)
		die('magic_quotes_gpc=on was detected: your host is using an unsecure PHP configuration, deprecated and removed in current versions. Please upgrade PHP.');

	// Save some memory.. (since we don't use these anyway.)
	unset($GLOBALS['HTTP_POST_VARS'], $GLOBALS['HTTP_POST_VARS']);
	unset($GLOBALS['HTTP_POST_FILES'], $GLOBALS['HTTP_POST_FILES']);

	// These keys shouldn't be set...ever.
	if (isset($_REQUEST['GLOBALS']) || isset($_COOKIE['GLOBALS']))
		die('Invalid request variable.');

	// Same goes for numeric keys.
	foreach (array_merge(array_keys($_POST), array_keys($_GET), array_keys($_FILES)) as $key)
		if (is_numeric($key))
			die('Numeric request keys are invalid.');

	// Numeric keys in cookies are less of a problem. Just unset those.
	foreach ($_COOKIE as $key => $value)
		if (is_numeric($key))
			unset($_COOKIE[$key]);

	// Get the correct query string.  It may be in an environment variable...
	if (!isset($_SERVER['QUERY_STRING']))
		$_SERVER['QUERY_STRING'] = getenv('QUERY_STRING');

	// It seems that sticking a URL after the query string is mighty common, well, it's evil - don't.
	if (strpos($_SERVER['QUERY_STRING'], 'http') === 0)
	{
		header('HTTP/1.1 400 Bad Request');
		die;
	}

	// Are we going to need to parse the ; out?
	if (strpos(ini_get('arg_separator.input'), ';') === false && !empty($_SERVER['QUERY_STRING']))
	{
		// Get rid of the old one! You don't know where it's been!
		$_GET = array();

		// Was this redirected? If so, get the REDIRECT_QUERY_STRING.
		// Do not urldecode() the querystring, unless you so much wish to break OpenID implementation. :)
		$_SERVER['QUERY_STRING'] = substr($_SERVER['QUERY_STRING'], 0, 5) === 'url=/' ? $_SERVER['REDIRECT_QUERY_STRING'] : $_SERVER['QUERY_STRING'];

		// Some german webmailers need a decoded string, so let's decode the string for action=activate and action=reminder
		if (strpos($_SERVER['QUERY_STRING'], 'activate') !== false || strpos($_SERVER['QUERY_STRING'], 'reminder') !== false)
			$_SERVER['QUERY_STRING'] = urldecode($_SERVER['QUERY_STRING']);

		// Replace ';' with '&' and '&something&' with '&something=&'.  (this is done for compatibility...)
		parse_str(preg_replace('/&(\w+)(?=&|$)/', '&$1=', strtr($_SERVER['QUERY_STRING'], array(';?' => '&', ';' => '&', '%00' => '', "\0" => ''))), $_GET);
	}
	elseif (strpos(ini_get('arg_separator.input'), ';') !== false)
	{
		// Search engines will send action=profile%3Bu=1, which confuses PHP.
		foreach ($_GET as $k => $v)
		{
			if ((string) $v === $v && strpos($k, ';') !== false)
			{
				$temp = explode(';', $v);
				$_GET[$k] = $temp[0];

				for ($i = 1, $n = count($temp); $i < $n; $i++)
				{
					@list ($key, $val) = @explode('=', $temp[$i], 2);
					if (!isset($_GET[$key]))
						$_GET[$key] = $val;
				}
			}

			// This helps a lot with integration!
			if (strpos($k, '?') === 0)
			{
				$_GET[substr($k, 1)] = $v;
				unset($_GET[$k]);
			}
		}
	}

	// There's no query string, but there is a URL... try to get the data from there.
	if (!empty($_SERVER['REQUEST_URI']))
	{
		// Remove the .html, assuming there is one.
		if (substr($_SERVER['REQUEST_URI'], strrpos($_SERVER['REQUEST_URI'], '.'), 4) == '.htm')
			$request = substr($_SERVER['REQUEST_URI'], 0, strrpos($_SERVER['REQUEST_URI'], '.'));
		else
			$request = $_SERVER['REQUEST_URI'];

		// Replace 'index.php/a,b,c/d/e,f' with 'a=b,c&d=&e=f' and parse it into $_GET.
		if (strpos($request, basename($scripturl) . '/') !== false)
		{
			parse_str(substr(preg_replace('/&(\w+)(?=&|$)/', '&$1=', strtr(preg_replace('~/([^,/]+),~', '/$1=', substr($request, strpos($request, basename($scripturl)) + strlen(basename($scripturl)))), '/', '&')), 1), $temp);
			$_GET += $temp;
		}
	}

	// Add entities to GET.  This is kinda like the slashes on everything else.
	$_GET = htmlspecialchars__recursive($_GET);

	// Let's not depend on the ini settings... why even have COOKIE in there, anyway?
	$_REQUEST = $_POST + $_GET;

	// Make sure REMOTE_ADDR, other IPs, and the like are parsed
	$req = request();

	// Parse the $_REQUEST and make sure things like board, topic don't have weird stuff
	$req->parseRequest();

	// Make sure we know the URL of the current request.
	if (empty($_SERVER['REQUEST_URI']))
		$_SERVER['REQUEST_URL'] = $scripturl . (!empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '');
	elseif (preg_match('~^([^/]+//[^/]+)~', $scripturl, $match) == 1)
		$_SERVER['REQUEST_URL'] = $match[1] . $_SERVER['REQUEST_URI'];
	else
		$_SERVER['REQUEST_URL'] = $_SERVER['REQUEST_URI'];

}

/**
 * Validates a IPv6 address. returns true if it is ipv6.
 *
 * @param string $ip ip address to be validated
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

	// Save this incase of repeated use.
	$expanded[$ip] = $new_ip;

	return $expanded[$ip];
}

/**
 * Expands a IPv6 address to its full form.
 *
 * @param string $addr ipv6 address string
 * @param boolean $strict_check checks lenght to expaned address for compliance
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
		if (strpos($part[1][1], '.') !== false)
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

	// Save this incase of repeated use.
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
 * - adds entities (&quot;, &lt;, &gt;) to the array or string var.
 * - importantly, does not effect keys, only values.
 * - calls itself recursively if necessary.
 *
 * @param string[]|string $var
 * @param int $level = 0
 * @return mixed[]|string
 */
function htmlspecialchars__recursive($var, $level = 0)
{
	if (!is_array($var))
		return Util::htmlspecialchars($var, ENT_QUOTES);

	// Add the htmlspecialchars to every element.
	foreach ($var as $k => $v)
		$var[$k] = $level > 25 ? null : htmlspecialchars__recursive($v, $level + 1);

	return $var;
}

/**
 * Trim a string including the HTML space, character 160.  Uses two underscores to guard against overloading.
 *
 * What it does:
 * - trims a string or an the var array using html characters as well.
 * - does not effect keys, only values.
 * - may call itself recursively if needed.
 *
 * @param string[]|string $var
 * @param int $level = 0
 * @return mixed[]|string
 */
function htmltrim__recursive($var, $level = 0)
{
	// Remove spaces (32), tabs (9), returns (13, 10, and 11), nulls (0), and hard spaces. (160)
	if (!is_array($var))
		return Util::htmltrim($var);

	// Go through all the elements and remove the whitespace.
	foreach ($var as $k => $v)
		$var[$k] = $level > 25 ? null : htmltrim__recursive($v, $level + 1);

	return $var;
}

/**
 * Clean up the XML to make sure it doesn't contain invalid characters.
 *
 * What it does:
 * - removes invalid XML characters to assure the input string being
 * - parsed properly.
 *
 * @param string $string
 * @return string
 */
function cleanXml($string)
{
	// http://www.w3.org/TR/2000/REC-xml-20001006#NT-Char
	return preg_replace('~[\x00-\x08\x0B\x0C\x0E-\x19\x{FFFE}\x{FFFF}]~u', '', $string);
}

/**
 * Escapes (replaces) characters in strings to make them safe for use in javascript
 *
 * @param string $string
 * @return string
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
 * - rewrites the URLs outputted to have the session ID, if the user
 *   is not accepting cookies and is using a standard web browser.
 * - handles rewriting URLs for the queryless URLs option.
 * - can be turned off entirely by setting $scripturl to an empty
 *   string, ''. (it wouldn't work well like that anyway.)
 *
 * @param string $buffer
 * @return string
 */
function ob_sessrewrite($buffer)
{
	global $scripturl, $modSettings, $context;

	// If $scripturl is set to nothing, or the SID is not defined (SSI?) just quit.
	if ($scripturl == '' || !defined('SID'))
		return $buffer;

	// Do nothing if the session is cookied, or they are a crawler - guests are caught by redirectexit().
	if (empty($_COOKIE) && SID != '' && !isBrowser('possibly_robot'))
		$buffer = preg_replace('/(?<!<link rel="canonical" href=)"' . preg_quote($scripturl, '/') . '(?!\?' . preg_quote(SID, '/') . ')\\??/', '"' . $scripturl . '?' . SID . '&amp;', $buffer);

	// Debugging templates, are we?
	elseif (isset($_GET['debug']))
		$buffer = preg_replace('/(?<!<link rel="canonical" href=)"' . preg_quote($scripturl, '/') . '\\??/', '"' . $scripturl . '?debug;', $buffer);

	// This should work even in 4.2.x, just not CGI without cgi.fix_pathinfo.
	if (!empty($modSettings['queryless_urls']) && (!$context['server']['is_cgi'] || ini_get('cgi.fix_pathinfo') == 1 || @get_cfg_var('cgi.fix_pathinfo') == 1) && ($context['server']['is_apache'] || $context['server']['is_lighttpd'] || $context['server']['is_litespeed']))
	{
		// Let's do something special for session ids!
		$buffer = preg_replace_callback('~"' . preg_quote($scripturl, '/') . '\?((?:board|topic)=[^#"]+?)(#[^"]*?)?"~', 'buffer_callback', $buffer);
	}

	// Return the changed buffer.
	return $buffer;
}

/**
 * Callback function for the Rewrite URLs preg_replace_callback
 *
 * @param mixed[] $matches
 */
function buffer_callback($matches)
{
	global $scripturl;

	if (defined('SID') && SID != '')
		return '"' . $scripturl . '/' . strtr($matches[1], '&;=', '//,') . '.html?' . SID . (isset($matches[2]) ? $matches[2] : '') . '"';
	else
		return '"' . $scripturl . '/' . strtr($matches[1], '&;=', '//,') . '.html' . (isset($matches[2]) ? $matches[2] : '') . '"';
}