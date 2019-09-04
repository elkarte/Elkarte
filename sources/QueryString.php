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
 * @version 2.0 dev
 *
 */

use ElkArte\Request;
use ElkArte\Util;

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
	$req = Request::instance();

	$parser = initUrlGenerator()->getParser();

	// Make sure there are no problems with the request
	$req->cleanRequest($parser);

	// Parse the $_REQUEST and make sure things like board, topic don't have weird stuff
	$req->parseRequest();
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
	global $scripturl;

	// If $scripturl is set to nothing, or the SID is not defined (SSI?) just quit.
	if ($scripturl == '' || !defined('SID'))
	{
		return $buffer;
	}

	// Do nothing if the session is cookied, or they are a crawler - guests are caught by redirectexit().
	if (empty($_COOKIE) && SID != '' && !isBrowser('possibly_robot'))
	{
		$buffer = preg_replace('/(?<!<link rel="canonical" href=)"' . preg_quote($scripturl, '/') . '(?!\?' . preg_quote(SID, '/') . ')\\??/', '"' . $scripturl . '?' . SID . '&amp;', $buffer);
	}

	// Debugging templates, are we?
	elseif (isset($_GET['debug']))
	{
		$buffer = preg_replace('/(?<!<link rel="canonical" href=)"' . preg_quote($scripturl, '/') . '\\??/', '"' . $scripturl . '?debug;', $buffer);
	}

	// Return the changed buffer.
	return $buffer;
}
