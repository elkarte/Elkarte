<?php

/**
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 1.0 Alpha
 *
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
	 * Sole private Request instance
	 * @var Request
	 */
	private static $_req = null;

	/**
	 * Retrieves client ip
	 */
	public function client_ip()
	{
		return $this->_client_ip;
	}

	/**
	 * Return a secondary IP, result of a deeper check for the IP
	 * It can be identical with client IP (and many times it will be).
	 */
	public function ban_ip()
	{
		return $this->_ban_ip;
	}

	/**
	 * Return the HTTP scheme
	 */
	public function scheme()
	{
		return $this->_scheme;
	}

	/**
	 * Private constructor.
	 * It parses PHP server variables, and initializes its variables.
	 */
	private function __construct()
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

			// Just incase we have a legacy IPv4 address.
			// @ TODO: Convert to IPv6.
			if (preg_match('~^((([1]?\d)?\d|2[0-4]\d|25[0-5])\.){3}(([1]?\d)?\d|2[0-4]\d|25[0-5])$~', $this->_client_ip) === 0)
				$this->_client_ip = 'unknown';
		}
		else
			$this->_client_ip = $_SERVER['REMOTE_ADDR'];

		// second IP, guesswork it is, try to get the best IP we can, when using proxies or such
		$this->_ban_ip = $this->_client_ip;

		// Forwarded, maybe?
		if (!empty($_SERVER['HTTP_X_FORWARDED_FOR']) && !empty($_SERVER['HTTP_CLIENT_IP']) && (preg_match('~^((0|10|172\.(1[6-9]|2[0-9]|3[01])|192\.168|255|127)\.|unknown|::1|fe80::|fc00::)~', $_SERVER['HTTP_CLIENT_IP']) == 0 || preg_match('~^((0|10|172\.(1[6-9]|2[0-9]|3[01])|192\.168|255|127)\.|unknown|::1|fe80::|fc00::)~', $this->_client_ip) != 0))
		{
			// check the first forwarded for as the block - only switch if it's better that way.
			if (strtok($_SERVER['HTTP_X_FORWARDED_FOR'], '.') != strtok($_SERVER['HTTP_CLIENT_IP'], '.') && '.' . strtok($_SERVER['HTTP_X_FORWARDED_FOR'], '.') == strrchr($_SERVER['HTTP_CLIENT_IP'], '.') && (preg_match('~^((0|10|172\.(1[6-9]|2[0-9]|3[01])|192\.168|255|127)\.|unknown)~', $_SERVER['HTTP_X_FORWARDED_FOR']) == 0 || preg_match('~^((0|10|172\.(1[6-9]|2[0-9]|3[01])|192\.168|255|127)\.|unknown)~', $this->_client_ip) != 0))
				$this->_ban_ip = implode('.', array_reverse(explode('.', $_SERVER['HTTP_CLIENT_IP'])));
			else
				$this->_ban_ip = $_SERVER['HTTP_CLIENT_IP'];
		}
		if (!empty($_SERVER['HTTP_CLIENT_IP']) && (preg_match('~^((0|10|172\.(1[6-9]|2[0-9]|3[01])|192\.168|255|127)\.|unknown|::1|fe80::|fc00::)~', $_SERVER['HTTP_CLIENT_IP']) == 0 || preg_match('~^((0|10|172\.(1[6-9]|2[0-9]|3[01])|192\.168|255|127)\.|unknown|::1|fe80::|fc00::)~', $this->_client_ip) != 0))
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
					if (preg_match('~^((0|10|172\.(1[6-9]|2[0-9]|3[01])|192\.168|255|127)\.|unknown|::1|fe80::|fc00::)~', $ip) != 0 && preg_match('~^((0|10|172\.(1[6-9]|2[0-9]|3[01])|192\.168|255|127)\.|unknown|::1|fe80::|fc00::)~', $this->_client_ip) == 0)
						continue;

					// Otherwise, we've got an IP!
					$this->_ban_ip = trim($ip);
					break;
				}
			}
			// Otherwise just use the only one.
			elseif (preg_match('~^((0|10|172\.(1[6-9]|2[0-9]|3[01])|192\.168|255|127)\.|unknown|::1|fe80::|fc00::)~', $_SERVER['HTTP_X_FORWARDED_FOR']) == 0 || preg_match('~^((0|10|172\.(1[6-9]|2[0-9]|3[01])|192\.168|255|127)\.|unknown|::1|fe80::|fc00::)~', $this->_client_ip) != 0)
				$this->_ban_ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
		}


		// Some final checking.
		if (preg_match('~^((([1]?\d)?\d|2[0-4]\d|25[0-5])\.){3}(([1]?\d)?\d|2[0-4]\d|25[0-5])$~', $this->_ban_ip) === 0 || !isValidIPv6($this->_ban_ip))
			$this->_ban_ip = '';
		if ($this->_client_ip == 'unknown')
			$this->_client_ip = '';

		// keep compatibility with the uses of $_SERVER['REMOTE_ADDR']...
		$_SERVER['REMOTE_ADDR'] = $this->_client_ip;

		// set the scheme, for later use
		$this->_scheme = 'http';

		// make sure we know everything about you... HTTP_USER_AGENT!
		$this->_user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? htmlspecialchars($_SERVER['HTTP_USER_AGENT'], ENT_QUOTES) : '';

		// keep compatibility with the uses of $_SERVER['HTTP_USER_AGENT']...
		$_SERVER['HTTP_USER_AGENT'] = $this->_user_agent;

	}

	/**
	 * Retrieve easily the sole instance of this class.
	 *
	 * @return Request
	 */
	public static function request()
	{
		if (self::$_req === null)
			self::$_req = new Request();

		return self::$_req;
	}
}

/**
 * This handy function retrieves a Request instance and passes it on.
 * To get hold of a Request, you can use this function or directly Request::request().
 * This is for convenience, it simply delegates to Request::request().
 */
function request()
{
	return Request::request();
}