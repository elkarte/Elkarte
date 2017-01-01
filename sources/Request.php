<?php

/**
 * This parses PHP server variables, and initializes its own checking variables for use
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 1.1 beta 4
 *
 */

/**
 * Class to parse $_REQUEST for always necessary data, such as 'action', 'board', 'topic', 'start'.
 *
 * What it does:
 * - Sanitizes the necessary data
 * - Determines the origin of $_REQUEST for use in security checks
 */
final class Request
{
	/**
	 * Remote IP, if we can know it easily (as found in $_SERVER['REMOTE_ADDR'])
	 * @var string
	 */
	private $_client_ip;

	/**
	 * Secondary IP, a double-check for the most accurate client IP we can get
	 * @var string
	 */
	private $_ban_ip;

	/**
	 * HTTP or HTTPS scheme
	 * @var string
	 */
	private $_scheme;

	/**
	 * User agent
	 * @var string
	 */
	private $_user_agent;

	/**
	 * Whether the request is an XmlHttpRequest
	 * @var bool
	 */
	private $_xml;

	/**
	 * Web server software
	 * @var string
	 */
	private $_server_software;

	/**
	 * This is the pattern of a local (or unknown) IP address in both IPv4 and IPv6
	 * @var string
	 */
	private $_local_ip_pattern = '((0|10|172\.(1[6-9]|2[0-9]|3[01])|192\.168|255|127)\.|unknown|::1|fe80::|fc00::)';

	/**
	 * Local copy of the server query string
	 * @var string
	 */
	private $_server_query_string;

	/**
	 * Creates the global and method internal
	 * @var string
	 */
	private $_scripturl;

	/**
	 * Sole private Request instance
	 * @var Request
	 */
	private static $_req = null;

	/**
	 * Retrieves client IP
	 */
	public function client_ip()
	{
		return $this->_client_ip;
	}

	/**
	 * Return a secondary IP, result of a deeper check for the IP
	 *
	 * - It can be identical with client IP (and many times it will be).
	 * - If the secondary IP is empty, then the client IP is returned
	 */
	public function ban_ip()
	{
		return !empty($this->_ban_ip) ? $this->_ban_ip : $this->client_ip();
	}

	/**
	 * Return the HTTP scheme
	 */
	public function scheme()
	{
		return $this->_scheme;
	}

	/**
	 * Return the user agent
	 */
	public function user_agent()
	{
		return $this->_user_agent;
	}

	/**
	 * Returns whether the request is XML
	 */
	public function is_xml()
	{
		return $this->_xml;
	}

	/**
	 * Returns server software (or empty string if it wasn't set for PHP)
	 */
	public function server_software()
	{
		return $this->_server_software;
	}

	/**
	 * Private constructor.
	 * It parses PHP server variables, and initializes its variables.
	 */
	private function __construct()
	{
		// Client IP: REMOTE_ADDR, unless missing
		$this->_getClientIP();

		// Second IP, guesswork it is, try to get the best IP we can, when using proxies or such
		$this->_getBanIP();

		// Keep compatibility with the uses of $_SERVER['REMOTE_ADDR']...
		$_SERVER['REMOTE_ADDR'] = $this->_client_ip;

		// Set the scheme, for later use
		$this->_scheme = (!empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) == 'on') ? 'https' : 'http';

		// Make sure we know everything about you... HTTP_USER_AGENT!
		$this->_user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? htmlspecialchars($_SERVER['HTTP_USER_AGENT'], ENT_QUOTES, 'UTF-8') : '';

		// Keep compatibility with the uses of $_SERVER['HTTP_USER_AGENT']...
		$_SERVER['HTTP_USER_AGENT'] = $this->_user_agent;

		// We want to know who we are, too :P
		$this->_server_software = isset($_SERVER['SERVER_SOFTWARE']) ? $_SERVER['SERVER_SOFTWARE'] : '';
	}

	/**
	 * Finds the claimed client IP for this connection
	 */
	private function _getClientIP()
	{
		// Client IP: REMOTE_ADDR, unless missing
		if (!isset($_SERVER['REMOTE_ADDR']))
		{
			// Command line, or else.
			$this->_client_ip = '';
		}
		// Perhaps we have a IPv6 address.
		elseif (!isValidIPv6($_SERVER['REMOTE_ADDR']) || preg_match('~::ffff:\d+\.\d+\.\d+\.\d+~', $_SERVER['REMOTE_ADDR']) !== 0)
		{
			$this->_client_ip = preg_replace('~^::ffff:(\d+\.\d+\.\d+\.\d+)~', '\1', $_SERVER['REMOTE_ADDR']);

			// Just in case we have a legacy IPv4 address.
			// @ TODO: Convert to IPv6.
			if (filter_var($this->_client_ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) === false)
				$this->_client_ip = 'unknown';
		}
		else
			$this->_client_ip = $_SERVER['REMOTE_ADDR'];

		// Final check
		if ($this->_client_ip == 'unknown')
			$this->_client_ip = '';
	}

	/**
	 * Hunts in most request areas for connection IP's for use in banning
	 */
	private function _getBanIP()
	{
		// Start off the same as the client ip
		$this->_ban_ip = $this->_client_ip;

		// Forwarded, maybe?
		if (!empty($_SERVER['HTTP_X_FORWARDED_FOR']) && !empty($_SERVER['HTTP_CLIENT_IP']) && (preg_match('~^' . $this->_local_ip_pattern . '~', $_SERVER['HTTP_CLIENT_IP']) == 0 || preg_match('~^' . $this->_local_ip_pattern . '~', $this->_client_ip) != 0))
		{
			// Check the first forwarded for as the block - only switch if it's better that way.
			if (strtok($_SERVER['HTTP_X_FORWARDED_FOR'], '.') != strtok($_SERVER['HTTP_CLIENT_IP'], '.')
					&& '.' . strtok($_SERVER['HTTP_X_FORWARDED_FOR'], '.') == strrchr($_SERVER['HTTP_CLIENT_IP'], '.')
					&& (preg_match('~^((0|10|172\.(1[6-9]|2[0-9]|3[01])|192\.168|255|127)\.|unknown)~', $_SERVER['HTTP_X_FORWARDED_FOR']) == 0 || preg_match('~^((0|10|172\.(1[6-9]|2[0-9]|3[01])|192\.168|255|127)\.|unknown)~', $this->_client_ip) != 0))
				$this->_ban_ip = implode('.', array_reverse(explode('.', $_SERVER['HTTP_CLIENT_IP'])));
			else
				$this->_ban_ip = $_SERVER['HTTP_CLIENT_IP'];
		}

		if (!empty($_SERVER['HTTP_CLIENT_IP']) && (preg_match('~^' . $this->_local_ip_pattern . '~', $_SERVER['HTTP_CLIENT_IP']) == 0 || preg_match('~^' . $this->_local_ip_pattern . '~', $this->_client_ip) != 0))
		{
			// Since they are in different blocks, it's probably reversed.
			if (strtok($this->_client_ip, '.') != strtok($_SERVER['HTTP_CLIENT_IP'], '.'))
				$this->_ban_ip = implode('.', array_reverse(explode('.', $_SERVER['HTTP_CLIENT_IP'])));
			else
				$this->_ban_ip = $_SERVER['HTTP_CLIENT_IP'];
		}
		elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR']))
		{
			// If there are commas, get the last one.. probably.
			if (strpos($_SERVER['HTTP_X_FORWARDED_FOR'], ',') !== false)
			{
				$ips = array_reverse(explode(', ', $_SERVER['HTTP_X_FORWARDED_FOR']));

				// Go through each IP...
				foreach ($ips as $i => $ip)
				{
					// Make sure it's in a valid range...
					if (preg_match('~^' . $this->_local_ip_pattern . '~', $ip) != 0 && preg_match('~^' . $this->_local_ip_pattern . '~', $this->_client_ip) == 0)
						continue;

					// Otherwise, we've got an IP!
					$this->_ban_ip = trim($ip);
					break;
				}
			}
			// Otherwise just use the only one.
			elseif (preg_match('~^' . $this->_local_ip_pattern . '~', $_SERVER['HTTP_X_FORWARDED_FOR']) == 0 || preg_match('~^' . $this->_local_ip_pattern . '~', $this->_client_ip) != 0)
				$this->_ban_ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
		}

		// Some final checking.
		if (filter_var($this->_ban_ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) === false && !isValidIPv6($this->_ban_ip))
			$this->_ban_ip = '';
	}

	/**
	 * Parse the $_REQUEST, for always necessary data, such as 'action', 'board', 'topic', 'start'.
	 * Also figures out if this is an xml request.
	 *
	 * - Parse the request for our dear globals, I know they're in there somewhere...
	 */
	public function parseRequest()
	{
		global $board, $topic;

		// Look for $board first
		$board = $this->_checkBoard();

		// Look for $topic
		$topic = $this->_checkTopic();

		// There should be a $_REQUEST['start'], some at least.  If you need to default to other than 0, use $_GET['start'].
		if (empty($_REQUEST['start']) || $_REQUEST['start'] < 0 || (int) $_REQUEST['start'] > 2147473647)
			$_REQUEST['start'] = 0;

		// The action needs to be a string, too.
		if (isset($_REQUEST['action']))
			$_REQUEST['action'] = (string) $_REQUEST['action'];

		if (isset($_GET['action']))
			$_GET['action'] = (string) $_GET['action'];

		$this->_xml = (isset($_SERVER['X_REQUESTED_WITH']) && $_SERVER['X_REQUESTED_WITH'] == 'XMLHttpRequest') || isset($_REQUEST['xml']);
	}

	/**
	 * Finds and returns the board numeric if its been requested
	 *
	 * - helper function for parseRequest
	 */
	private function _checkBoard()
	{
		if (isset($_REQUEST['board']))
		{
			// Make sure it's a string (not an array, say)
			$_REQUEST['board'] = (string) $_REQUEST['board'];

			// If we have ?board=3/10, that's... board=3, start=10! (old, compatible links.)
			if (strpos($_REQUEST['board'], '/') !== false)
				list ($_REQUEST['board'], $_REQUEST['start']) = explode('/', $_REQUEST['board']);
			// Or perhaps we have... ?board=1.0...
			elseif (strpos($_REQUEST['board'], '.') !== false)
				list ($_REQUEST['board'], $_REQUEST['start']) = explode('.', $_REQUEST['board']);

			// $board and $_REQUEST['start'] are always numbers.
			$board = (int) $_REQUEST['board'];
			$_REQUEST['start'] = isset($_REQUEST['start']) ? (int) $_REQUEST['start'] : 0;

			// This is for "Who's Online" because it might come via POST - and it should be an int here.
			$_GET['board'] = $board;
		}
		// None? We still need *something*, and it'd better be a number
		else
			$board = 0;

		return $board;
	}

	/**
	 * Finds and returns the topic numeric if its been requested
	 *
	 * helper function for parseRequest
	 */
	private function _checkTopic()
	{
		// Look for threadid, old YaBB SE links have those. Just read it as a topic.
		if (isset($_REQUEST['threadid']) && !isset($_REQUEST['topic']))
			$_REQUEST['topic'] = $_REQUEST['threadid'];

		if (isset($_REQUEST['topic']))
		{
			// Make sure it's a string (not an array, say)
			$_REQUEST['topic'] = (string) $_REQUEST['topic'];

			// It might come as ?topic=1/15, from an old, SMF beta style link
			if (strpos($_REQUEST['topic'], '/') !== false)
				list ($_REQUEST['topic'], $_REQUEST['start']) = explode('/', $_REQUEST['topic']);
			// Or it might come as ?topic=1.15.
			elseif (strpos($_REQUEST['topic'], '.') !== false)
				list ($_REQUEST['topic'], $_REQUEST['start']) = explode('.', $_REQUEST['topic']);

			// $topic and $_REQUEST['start'] are numbers, numbers I say.
			$topic = (int) $_REQUEST['topic'];

			// @todo in Display $_REQUEST['start'] is not always a number
			$_REQUEST['start'] = isset($_REQUEST['start']) && preg_match('~^(:?(:?from|msg)?\d+|new)$~', $_REQUEST['start']) ? $_REQUEST['start'] : 0;

			// Now make sure the online log gets the right number.
			$_GET['topic'] = $topic;
		}
		// No topic? Well, set something, and that something is 0.
		else
			$topic = 0;

		return $topic;
	}

	/**
	 * Clean the request variables - add html entities to GET and slashes if magic_quotes_gpc is Off.
	 *
	 * What it does:
	 * - cleans the request variables (ENV, GET, POST, COOKIE, SERVER) and
	 *   makes sure the query string was parsed correctly.
	 * - handles the URLs passed by the queryless URLs option.
	 * - makes sure, regardless of php.ini, everything has slashes.
	 * - use with ->parseRequest() to clean and set up variables like $board or $_REQUEST['start'].
	 * - uses Request to try to determine client IPs for the current request.
	 */
	public function cleanRequest()
	{
		global $boardurl, $scripturl;

		// Makes it easier to refer to things this way.
		$scripturl = $boardurl . '/index.php';
		$this->_scripturl = $scripturl;

		// Live to die another day
		$this->_checkExit();

		// Process server_query_string as needed
		$this->_cleanArg();

		// Process request_uri
		$this->_cleanRequest();

		// Add entities to GET.  This is kinda like the slashes on everything else.
		$_GET = htmlspecialchars__recursive($_GET);

		// Let's not depend on the ini settings... why even have COOKIE in there, anyway?
		$_REQUEST = $_POST + $_GET;

		// Make sure REMOTE_ADDR, other IPs, and the like are parsed
		// Parse the $_REQUEST and make sure things like board, topic don't have weird stuff
		$this->parseRequest();

		// Make sure we know the URL of the current request.
		if (empty($_SERVER['REQUEST_URI']))
			$_SERVER['REQUEST_URL'] = $this->_scripturl . (!empty($this->_server_query_string) ? '?' . $this->_server_query_string : '');
		elseif (preg_match('~^([^/]+//[^/]+)~', $this->_scripturl, $match) == 1)
			$_SERVER['REQUEST_URL'] = $match[1] . $_SERVER['REQUEST_URI'];
		else
			$_SERVER['REQUEST_URL'] = $_SERVER['REQUEST_URI'];
	}

	/**
	 * Checks the request and abruptly stops processing if issues are found
	 *
	 * - No magic quotes allowed
	 * - Don't try to set a GLOBALS key in globals
	 * - No numeric keys in $_GET, $_POST or $_FILE
	 * - No URL's appended to the query string
	 */
	private function _checkExit()
	{
		// Save some memory.. (since we don't use these anyway.)
		unset($GLOBALS['HTTP_POST_VARS'], $GLOBALS['HTTP_POST_VARS']);
		unset($GLOBALS['HTTP_POST_FILES'], $GLOBALS['HTTP_POST_FILES']);

		// These keys shouldn't be set...ever.
		$this->_checkNumericKeys();

		// Get the correct query string.  It may be in an environment variable...
		if (!isset($_SERVER['QUERY_STRING']))
			$_SERVER['QUERY_STRING'] = getenv('QUERY_STRING');

		// It seems that sticking a URL after the query string is mighty common, well, it's evil - don't.
		if (strpos($_SERVER['QUERY_STRING'], 'http') === 0)
		{
			header('HTTP/1.1 400 Bad Request');
			throw new Elk_Exception('', false);
		}

		$this->_server_query_string = $_SERVER['QUERY_STRING'];
	}

	/**
	 * Check for illegal numeric keys
	 *
	 * - Fail on illegal keys
	 * - Clear ones that should not be allowed
	 */
	private function _checkNumericKeys()
	{
		if (isset($_REQUEST['GLOBALS']) || isset($_COOKIE['GLOBALS']))
			throw new Elk_Exception('Invalid request variable.', false);

		// Same goes for numeric keys.
		foreach (array_merge(array_keys($_POST), array_keys($_GET), array_keys($_FILES)) as $key)
		{
			if (is_numeric($key))
				throw new Elk_Exception('Numeric request keys are invalid.', false);
		}

		// Numeric keys in cookies are less of a problem. Just unset those.
		foreach ($_COOKIE as $key => $value)
		{
			if (is_numeric($key))
				unset($_COOKIE[$key]);
		}
	}

	/**
	 * Helper method used to clean $_GET arguments
	 */
	private function _cleanArg()
	{
		// Are we going to need to parse the ; out?
		if (strpos(ini_get('arg_separator.input'), ';') === false && !empty($this->_server_query_string))
		{
			// Get rid of the old one! You don't know where it's been!
			$_GET = array();

			// Was this redirected? If so, get the REDIRECT_QUERY_STRING.
			// Do not urldecode() the querystring, unless you so much wish to break OpenID implementation. :)
			$this->_server_query_string = substr($this->_server_query_string, 0, 5) === 'url=/' ? $_SERVER['REDIRECT_QUERY_STRING'] : $this->_server_query_string;

			// Some german webmailers need a decoded string, so let's decode the string for sa=activate and action=reminder
			if (strpos($this->_server_query_string, 'activate') !== false || strpos($this->_server_query_string, 'reminder') !== false)
				$this->_server_query_string = urldecode($this->_server_query_string);

			// Replace ';' with '&' and '&something&' with '&something=&'.  (this is done for compatibility...)
			parse_str(preg_replace('/&(\w+)(?=&|$)/', '&$1=', strtr($this->_server_query_string, array(';?' => '&', ';' => '&', '%00' => '', "\0" => ''))), $_GET);

			// reSet the global in case an addon grabs it
			$_SERVER['SERVER_QUERY_STRING'] = $this->_server_query_string;
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
						list ($key, $val) = array_pad(explode('=', $temp[$i], 2), 2, '');
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
	}

	/**
	 * If a request URI is present, this will prepare it for use
	 */
	private function _cleanRequest()
	{
		// There's no query string, but there is a URL... try to get the data from there.
		if (!empty($_SERVER['REQUEST_URI']))
		{
			// Remove the .html, assuming there is one.
			if (substr($_SERVER['REQUEST_URI'], strrpos($_SERVER['REQUEST_URI'], '.'), 4) === '.htm')
				$request = substr($_SERVER['REQUEST_URI'], 0, strrpos($_SERVER['REQUEST_URI'], '.'));
			else
				$request = $_SERVER['REQUEST_URI'];

			// Replace 'index.php/a,b,c/d/e,f' with 'a=b,c&d=&e=f' and parse it into $_GET.
			if (strpos($request, basename($this->_scripturl) . '/') !== false)
			{
				parse_str(substr(preg_replace('/&(\w+)(?=&|$)/', '&$1=', strtr(preg_replace('~/([^,/]+),~', '/$1=', substr($request, strpos($request, basename($this->_scripturl)) + strlen(basename($this->_scripturl)))), '/', '&')), 1), $temp);
				$_GET += $temp;
			}
		}
	}

	/**
	 * Retrieve easily the sole instance of this class.
	 *
	 * @return Request
	 */
	public static function instance()
	{
		if (self::$_req === null)
			self::$_req = new Request();

		return self::$_req;
	}
}