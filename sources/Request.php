<?php

/**
 * This parses PHP server variables, and initializes its own checking variables for use
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 1.0
 *
 */

/**
 * Class to parse $_REQUEST for always necessary data, such as 'action', 'board', 'topic', 'start'.
 *
 * What it does:
 * - Sanitizes the necessary data
 * - Determines the origin of $_REQUEST for use in security checks
 */
class Request
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
		// This is the pattern of a local (or unknown) IP address in both IPv4 and IPv6
		$local_ip_pattern = '((0|10|172\.(1[6-9]|2[0-9]|3[01])|192\.168|255|127)\.|unknown|::1|fe80::|fc00::)';

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

			// Just incase we have a legacy IPv4 address.
			// @ TODO: Convert to IPv6.
			if (filter_var($this->_client_ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) === false)
				$this->_client_ip = 'unknown';
		}
		else
			$this->_client_ip = $_SERVER['REMOTE_ADDR'];

		// Second IP, guesswork it is, try to get the best IP we can, when using proxies or such
		$this->_ban_ip = $this->_client_ip;

		// Forwarded, maybe?
		if (!empty($_SERVER['HTTP_X_FORWARDED_FOR']) && !empty($_SERVER['HTTP_CLIENT_IP']) && (preg_match('~^' . $local_ip_pattern . '~', $_SERVER['HTTP_CLIENT_IP']) == 0 || preg_match('~^' . $local_ip_pattern . '~', $this->_client_ip) != 0))
		{
			// check the first forwarded for as the block - only switch if it's better that way.
			if (strtok($_SERVER['HTTP_X_FORWARDED_FOR'], '.') != strtok($_SERVER['HTTP_CLIENT_IP'], '.') && '.' . strtok($_SERVER['HTTP_X_FORWARDED_FOR'], '.') == strrchr($_SERVER['HTTP_CLIENT_IP'], '.') && (preg_match('~^((0|10|172\.(1[6-9]|2[0-9]|3[01])|192\.168|255|127)\.|unknown)~', $_SERVER['HTTP_X_FORWARDED_FOR']) == 0 || preg_match('~^((0|10|172\.(1[6-9]|2[0-9]|3[01])|192\.168|255|127)\.|unknown)~', $this->_client_ip) != 0))
				$this->_ban_ip = implode('.', array_reverse(explode('.', $_SERVER['HTTP_CLIENT_IP'])));
			else
				$this->_ban_ip = $_SERVER['HTTP_CLIENT_IP'];
		}

		if (!empty($_SERVER['HTTP_CLIENT_IP']) && (preg_match('~^' . $local_ip_pattern . '~', $_SERVER['HTTP_CLIENT_IP']) == 0 || preg_match('~^' . $local_ip_pattern . '~', $this->_client_ip) != 0))
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
					if (preg_match('~^' . $local_ip_pattern . '~', $ip) != 0 && preg_match('~^' . $local_ip_pattern . '~', $this->_client_ip) == 0)
						continue;

					// Otherwise, we've got an IP!
					$this->_ban_ip = trim($ip);
					break;
				}
			}
			// Otherwise just use the only one.
			elseif (preg_match('~^' . $local_ip_pattern . '~', $_SERVER['HTTP_X_FORWARDED_FOR']) == 0 || preg_match('~^' . $local_ip_pattern . '~', $this->_client_ip) != 0)
				$this->_ban_ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
		}

		// Some final checking.
		if (filter_var($this->_ban_ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) === false && !isValidIPv6($this->_ban_ip))
			$this->_ban_ip = '';

		if ($this->_client_ip == 'unknown')
			$this->_client_ip = '';

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
	 * Parse the $_REQUEST, for always necessary data, such as 'action', 'board', 'topic', 'start'.
	 * Also figures out if this is an xml request.
	 */
	public function parseRequest()
	{
		global $board, $topic;

		// Parse the request for our dear globals, I know
		// they're in there somewhere...

		// Look for $board first
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

		// Look for threadid, old YaBB SE links have those. Just read it as a topic.
		if (isset($_REQUEST['threadid']) && !isset($_REQUEST['topic']))
			$_REQUEST['topic'] = $_REQUEST['threadid'];

		// Look for $topic
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
			$_REQUEST['start'] = isset($_REQUEST['start']) && preg_match('~^((from|msg)?\d+|new)$~', $_REQUEST['start']) ? $_REQUEST['start'] : 0;

			// Now make sure the online log gets the right number.
			$_GET['topic'] = $topic;
		}
		// No topic? Well, set something, and that something is 0.
		else
			$topic = 0;

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

/**
 * This handy function retrieves a Request instance and passes it on.
 *
 * - To get hold of a Request, you can use this function or directly Request::instance().
 * - This is for convenience, it simply delegates to Request::instance().
 */
function request()
{
	return Request::instance();
}
