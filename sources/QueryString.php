<?php

/**
 * This file does a lot of important stuff.  Mainly, this means it handles
 * the query string, request variables, and session management.
 *
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * This file contains code covered by:
 * copyright: 2011 Simple Machines (http://www.simplemachines.org)
 *
 * @version 2.0 Beta 1
 *
 */

use ElkArte\Request;
use ElkArte\User;

/**
 * Clean the request variables - add HTML entities to GET.
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
	$req = Request::instance();

	$parser = initUrlGenerator()->getParser();

	// Make sure there are no problems with the request
	$req->cleanRequest($parser);

	// Parse the $_REQUEST and make sure things like board, topic don't have weird stuff
	$req->parseRequest();
}

/**
 * Escapes (replaces) characters in strings to make them safe for use in JavaScript
 *
 * @param string $string The string to escape
 *
 * @return string The escaped string
 */
function JavaScriptEscape($string)
{
	global $scripturl;

	return '\'' . strtr($string, [
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
		]) . '\'';
}

/**
 * Rewrite URLs in the output buffer for debugging purposes.
 *
 * What it does:
 *
 * - Almost nothing, but it does rewrite URLs to add ?debug to them, so that you can
 * easily use template debug mode.
 *
 * @param string $buffer The unmodified output buffer
 *
 * @return string The modified output buffer
 */
function ob_sessrewrite($buffer)
{
	global $scripturl;

	// If $scripturl is set to nothing, or not debugging, just return.
	if (!isset($_GET['debug']) || $scripturl === '')
	{
		return $buffer;
	}

	// Debugging templates, are we?
	return preg_replace('/(?<!<link rel="canonical" href=)"' . preg_quote($scripturl, '/') . '\\??/', '"' . $scripturl . '?debug;', $buffer);
}
