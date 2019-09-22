<?php

/**
 * This file has the functions "describing" the server.
 *
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * This file contains code covered by:
 * copyright:	2011 Simple Machines (http://www.simplemachines.org)
 *
 * @version 2.0 dev
 *
 */

namespace ElkArte;

/**
 * Class Server
 *
 * Wrapper around many common server functions and server information
 */
class Server extends \ArrayObject
{
	/** @var mixed */
	public $SERVER_SOFTWARE;

	/** @var mixed */
	public $HTTPS;

	/** @var mixed */
	public $SERVER_PORT;

 	/**
	 * Server constructor.
	 *
	 * @param null|array $server
	 */
	public function __construct($server = null)
	{
		if (!is_array($server))
		{
			$server = isset($_SERVER) ? $_SERVER : array();
		}

		parent::__construct($server, \ArrayObject::ARRAY_AS_PROPS);
	}

	/**
	 * Helper function to set the system memory to a needed value
	 *
	 * What it does:
	 *
	 * - If the needed memory is greater than current, will attempt to get more
	 * - If in_use is set to true, will also try to take the current memory usage in to account
	 *
	 * @param string $needed The amount of memory to request, if needed, like 256M
	 * @param bool $in_use Set to true to account for current memory usage of the script
	 *
	 * @return boolean true if we have at least the needed memory
	 */
	public function setMemoryLimit($needed, $in_use = false)
	{
		// Everything in bytes
		$memory_current = memoryReturnBytes(ini_get('memory_limit'));
		$memory_needed = memoryReturnBytes($needed);

		// Should we account for how much is currently being used?
		if ($in_use)
			$memory_needed += memory_get_usage();

		// If more is needed, request it
		if ($memory_current < $memory_needed)
		{
			@ini_set('memory_limit', ceil($memory_needed / 1048576) . 'M');
			$memory_current = memoryReturnBytes(ini_get('memory_limit'));
		}

		$memory_current = max($memory_current, memoryReturnBytes(get_cfg_var('memory_limit')));

		// Return success or not
		return (bool) ($memory_current >= $memory_needed);
	}

	/**
	 * Wrapper function for set_time_limit
	 *
	 * When called, attempts to restart the timeout counter from zero.
	 *
	 * This sets the maximum time in seconds a script is allowed to run before it is terminated by the parser.
	 * You can not change this setting with ini_set() when running in safe mode.
	 * Your web server can have other timeout configurations that may also interrupt PHP execution.
	 * Apache has a Timeout directive and IIS has a CGI timeout function.
	 * Security extension may also disable this function, such as Suhosin
	 * Hosts may add this to the disabled_functions list in php.ini
	 *
	 * If the current time limit is not unlimited it is possible to decrease the
	 * total time limit if the sum of the new time limit and the current time spent
	 * running the script is inferior to the original time limit. It is inherent to
	 * the way set_time_limit() works, it should rather be called with an
	 * appropriate value every time you need to allocate a certain amount of time
	 * to execute a task than only once at the beginning of the script.
	 *
	 * Before calling set_time_limit(), we check if this function is available
	 *
	 * @param int $time_limit The time limit
	 * @param bool $server_reset whether to reset the server timer or not
	 *
	 * @return string
	 */
	public function setTimeLimit($time_limit, $server_reset = true)
	{
		// Make sure the function exists, it may be in the ini disable_functions list
		if (function_exists('set_time_limit'))
		{
			$current = (int) ini_get('max_execution_time');

			// Do not set a limit if it is currently unlimited.
			if ($current !== 0)
			{
				// Set it to the maximum that we can, not more, not less
				$time_limit = min($current, max($time_limit, $current));

				// Still need error suppression as some security addons many prevent this action
				@set_time_limit($time_limit);
			}
		}

		// Don't let apache close the connection
		if ($server_reset && function_exists('apache_reset_timeout'))
			@apache_reset_timeout();

		return ini_get('max_execution_time');
	}

	/**
	 * Checks the type of software the webserver is functioning under
	 *
	 * @param $server
	 *
	 * @return bool
	 */
	public function is($server)
	{
		switch ($server)
		{
			case 'apache':
				return $this->_is_web_server('Apache');
			case 'cgi':
				return isset($this->SERVER_SOFTWARE) && strpos(php_sapi_name(), 'cgi') !== false;
			case 'iis':
				return $this->_is_web_server('Microsoft-IIS');
			case 'iso_case_folding':
				return ord(strtolower(chr(138))) === 154;
			case 'lighttpd':
				return $this->_is_web_server('lighttpd');
			case 'litespeed':
				return $this->_is_web_server('LiteSpeed');
			case 'needs_login_fix':
				return $this->is('cgi') && $this->_is_web_server('Microsoft-IIS');
			case 'nginx':
				return $this->_is_web_server('nginx');
			case 'windows':
				return strpos(PHP_OS, 'WIN') === 0;
			default:
				return false;
		}
	}

	/**
	 * Search $_SERVER['SERVER_SOFTWARE'] for a give $type
	 *
	 * @param string $type
	 *
	 * @return bool
	 */
	private function _is_web_server($type)
	{
		return isset($this->SERVER_SOFTWARE) && strpos($this->SERVER_SOFTWARE, $type) !== false;
	}

	/**
	 * Checks if the webserver supports rewrite
	 *
	 * @return bool
	 */
	public function supportRewrite()
	{
		return (!$this->is('cgi') || ini_get('cgi.fix_pathinfo') == 1 || @get_cfg_var('cgi.fix_pathinfo') == 1)
			&& ($this->is('apache') || $this->is('nginx') || $this->is('lighttpd') || $this->is('litespeed'));
	}

	/**
	 * Returns if the system supports output compression
	 *
	 * @return bool
	 */
	public function outPutCompressionEnabled()
	{
		return ini_get('zlib.output_compression') >= 1 || ini_get('output_handler') == 'ob_gzhandler';
	}

	/**
	 * Returns if the system supports / is using https connections
	 *
	 * @return bool
	 */
	public function supportsSSL()
	{
		return isset($this->HTTPS) &&
			($this->HTTPS === 'on' || $this->HTTPS === 1 || $this->SERVER_PORT === 443);
	}
}
