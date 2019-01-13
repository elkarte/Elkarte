<?php

/**
 * This will fetch a web resource http/https and return the headers and page data.  It is capable of following
 * redirects and interpreting chunked data, etc.  It will NOT work with allow_url_fopen off.
 *
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * @version 2.0 dev
 *
 */

namespace ElkArte\Http;

/**
 * Class StreamFetchWebdata
 *
 * @package ElkArte
 */
class StreamFetchWebdata
{
	/** @var bool Use the same connection on redirects */
	private $_keep_alive = false;

	/** @var int Holds the passed or default value for redirects */
	private $_max_redirect = 3;

	/** @var int how much we will read */
	private $_content_length = 0;

	/** @var array the parsed url with host, port, path, etc */
	private $_url = array();

	/** @var null|resource the fopen resource */
	private $_fp = null;

	/** @var mixed[] Holds the passed user options array (only option is max_length) */
	private $_user_options = array();

	/** @var string|string[] Holds any data that will be posted to a form */
	private $_post_data = '';

	/** @var string[] Holds the response to the request, headers, data, code */
	private $_response = array('url' => '', 'code' => 404, 'error' => '', 'redirects' => 0, 'size' => 0, 'headers' => array(), 'body' => '');

	/** @var array the context options for the stream */
	private $_options = array();

	/**
	 * StreamFetchWebdata constructor.
	 *
	 * @param array $options
	 * @param int $max_redirect
	 * @param bool $keep_alive
	 */
	public function __construct($options = array(), $max_redirect = 3, $keep_alive = false)
	{
		// Initialize class variables
		$this->_max_redirect = intval($max_redirect);
		$this->_user_options = $options;
		$this->_keep_alive = $keep_alive;
	}

	/**
	 * Prepares any post data supplied and then makes the request for data
	 *
	 * @param string $url
	 * @param string|string[] $post_data
	 */
	public function get_url_data($url, $post_data = '')
	{
		// Prepare any given post data
		if (!empty($post_data))
		{
			if (is_array($post_data))
			{
				$this->_post_data = http_build_query($post_data);
			}
			else
			{
				$this->_post_data = http_build_query(array(trim($post_data)));
			}
		}

		// Set the options and get it
		$this->_openRequest($url);
	}

	/**
	 * Makes the actual data call
	 *
	 * What it does
	 * - Calls setOptions to build the stream context array
	 * - Makes the data request and parses the results
	 *
	 * @param string $url site to fetch
	 *
	 * @return bool
	 */
	private function _openRequest($url)
	{
		// Build the stream options array
		$this->_setOptions($url);

		// We do have a url I hope
		if (empty($this->_url))
		{
			return false;
		}

		// I want this, from there, and I'm not going to be bothering you for more (probably.)
		if ($this->_makeRequest() !== false)
		{
			$this->_parseRequest();
			$this->_processHeaders();

			return $this->_fetchData();
		}

		return false;
	}

	/**
	 * Prepares all of the options needed from this request
	 *
	 * @param string $url
	 */
	private function _setOptions($url)
	{
		$this->_url = array();

		// Ensure the url is valid
		if (filter_var($url, FILTER_VALIDATE_URL))
		{
			// Get the elements for the url
			$this->_url = parse_url($url);

			$this->_url['path'] = (isset($this->_url['path']) ? $this->_url['path'] : '/') . (isset($this->_url['query']) ? '?' . $this->_url['query'] : '');
			$this->_response['url'] = $this->_url['scheme'] . '://' . $this->_url['host'] . $this->_url['path'];
		}

		// Build out the options for our context stream
		$this->_options = array(
			'http' =>
				array(
					'method' => empty($this->_post_data) ? 'GET' : 'POST',
					'max_redirects' => $this->_max_redirect,
					'ignore_errors' => true,
					'protocol_version' => 1.1,
					'follow_location' => 1,
					'header' => array(
						'Connection: ' . ($this->_keep_alive ? 'Keep-Alive' : 'close'),
						'User-Agent: PHP/ELK',
					),
				)
		);

		// Try to limit the body of the response?
		if (!empty($this->_user_options['max_length']))
		{
			$this->_content_length = intval($this->_user_options['max_length']);
			$this->_options['http']['header'][] = 'Range: bytes=0-' . $this->_content_length - 1;
		}
	}

	/**
	 * Connect to the host/port with the steam options defined
	 *
	 * @return bool
	 */
	private function _makeRequest()
	{
		try
		{
			$context = stream_context_create($this->_options);
			$this->_fp = fopen($this->_response['url'], 'r', false, $context);
		}
		catch (\Exception $e)
		{
			$this->_response['error'] = $e->getMessage();

			return false;
		}

		return is_resource($this->_fp);
	}

	/**
	 * Fetch the headers and parse the meta data into the results we need
	 */
	private function _parseRequest()
	{
		// header information as well as meta data
		$headers = stream_get_meta_data($this->_fp);
		$this->_response['headers'] = array();
		$this->_response['redirects'] = 0;
		$this->_response['code'] = '???';

		// Loop and process the headers
		foreach ($headers['wrapper_data'] as $header)
		{
			// Create the final header array
			list($name, $value) = explode(':', $header, 2);

			// Normalize / clean
			$name = strtolower($name);
			$value = trim($value);

			// How many redirects
			if ($name === 'location')
			{
				$this->_response['redirects']++;
			}

			// Server response is mixed in with the real headers
			if (empty($value))
			{
				$this->_response['headers']['status'] = $name;
			}
			// If its already there overwrite with the new value, unless its a cookie
			elseif (isset($this->_response['headers'][$name]) && $name === 'set-cookie')
			{
				if (is_string($this->_response['headers'][$name]))
				{
					$this->_response['headers'][$name] = array($this->_response['headers'][$name]);
				}

				$this->_response['headers'][$name][] = $value;
			}
			else
			{
				$this->_response['headers'][$name] = $value;
			}
		}
	}

	/**
	 * Read the response up to the end of the headers
	 */
	private function _processHeaders()
	{
		// Were we redirected, if so lets find out where
		if (!empty($this->_response['headers']['location']))
		{
			// update $url with where we were ultimately redirected to
			$this->_response['url'] = $this->_response['headers']['location'];
		}

		// What about our status code?
		if (!empty($this->_response['headers']['status']))
		{
			// Update with last status code found, its for this final navigated point
			$this->_response['code'] = substr($this->_response['headers']['status'], 9, 3);
		}

		// Provide a common "valid" return code to the caller
		if (in_array($this->_response['code'], array(200, 201, 206)))
		{
			$this->_response['code_orig'] = $this->_response['code'];
			$this->_response['code'] = 200;
		}
	}

	/**
	 * Fetch the body for the selected site.
	 */
	private function _fetchData()
	{
		// Get the contents of the url
		if (!empty($this->_content_length))
		{
			$this->_response['body'] = stream_get_contents($this->_fp, $this->_content_length);
		}
		else
		{
			$this->_response['body'] = stream_get_contents($this->_fp);
		}

		fclose($this->_fp);

		$this->_response['size'] = strlen($this->_response['body']);

		return $this->_response['body'];
	}

	/**
	 * Used to return the results to the calling program
	 *
	 * What it does:
	 *
	 * - Called as ->result() will return the full final array
	 * - Called as ->result('body') to just return the page source of the result
	 *
	 * @param string $area used to return an area such as body, header, error
	 *
	 * @return string
	 */
	public function result($area = '')
	{
		// Just return a specified area or the entire result?
		if ($area === '')
		{
			return $this->_response;
		}
		else
		{
			return isset($this->_response[$area]) ? $this->_response[$area] : $this->_response;
		}
	}
}