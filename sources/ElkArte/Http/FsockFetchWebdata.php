<?php

/**
 * This will fetch a web resource http/https and return the headers and page data.  It is capable of following
 * redirects and interpreting chunked data.  It will work with allow_url_fopen off.
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
 * Class FsockFetchWebdata
 *
 * @package ElkArte
 */
class FsockFetchWebdata
{
	/** @var bool Use the same connection on redirects */
	private $_keep_alive = false;

	/** @var int Holds the passed or default value for redirects */
	private $_max_redirect = 3;

	/** @var int Holds the current redirect count for the request */
	private $_current_redirect = 0;

	/** @var null|string Used on redirect when keep alive is true */
	private $_keep_alive_host = null;

	/** @var null|resource the fp resource to reuse */
	private $_keep_alive_fp = null;

	/** @var int how much we will read */
	private $_content_length = 0;

	/** @var array the parsed url with host, port, path, etc */
	private $_url = array();

	/** @var null|resource the fsockopen resource */
	private $_fp = null;

	/** @var mixed[] Holds the passed user options array (only option is max_length) */
	private $_user_options = array();

	/** @var string|string[] Holds any data that will be posted to a form */
	private $_post_data = '';

	/** @var string[] Holds the response to the request, headers, data, code */
	private $_response = array('url' => '', 'code' => 404, 'error' => '', 'redirects' => 0, 'size' => 0, 'headers' => array(), 'body' => '');

	/** @var array() Holds the last headers response to the request */
	private $_headers = array();

	/** @var string the HTTP response from the server 200/404/302 etc */
	private $_server_response;

	/** @var bool if the response body is transfer encoded chunked */
	private $_chunked = false;

	/**
	 * FsockFetchWebdata constructor.
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
	 *
	 */
	public function get_url_data($url, $post_data = '')
	{
		// Prepare any given post data
		if (!empty($post_data))
		{
			if (is_array($post_data))
			{
				$this->_post_data = http_build_query($post_data, '', '&');
			}
			else
			{
				$this->_post_data = http_build_query(array(trim($post_data)), '', '&');
			}
		}

		// Set the options and get it
		$this->_current_redirect = 0;
		$this->_fopenRequest($url);
	}

	/**
	 * Main processing loop, connects, parses responses, redirects, fetches body
	 *
	 * @param string $url site to fetch
	 *
	 * @return bool
	 */
	private function _fopenRequest($url)
	{
		// We do have a url I hope
		$this->_setOptions($url);
		if (empty($this->_url))
		{
			return false;
		}

		// reuse the socket if this is a keep alive
		if ($this->_keep_alive && $this->_url['host'] === $this->_keep_alive_host)
		{
			$this->_fp = $this->_keep_alive_fp;
		}

		// Open a connection to the host & port
		if ($this->_sockOpen() === false)
		{
			return false;
		}

		// I want this, from there, and I'm not going to be bothering you for more (probably.)
		$this->_makeRequest();

		// Is it where we thought?
		$this->_readHeaders();
		$location = $this->_checkRedirect();
		if (empty($location))
		{
			preg_match('~^HTTP/\S+\s+(\d{3})~i', $this->_server_response, $code);
			$this->_response['code'] = isset($code[1]) ? intval($code[1]) : '???';

			// Make sure we ended up with a 200 OK.
			if (in_array($this->_response['code'], array(200, 201, 206)))
			{
				// Provide a common valid 200 return code to the caller
				$this->_response['code'] = 200;
			}

			$this->_fetchData();
			fclose($this->_fp);

			return true;
		}
		else
		{
			// To the new location we go
			$this->_fopenRequest($location);
		}

		return false;
	}

	/**
	 * Parses a url into the components we need
	 *
	 * @param string $url
	 */
	private function _setOptions($url)
	{
		$this->_url = array();
		$this->_response['url'] = $url;
		$this->_content_length = !empty($this->_user_options['max_length']) ? intval($this->_user_options['max_length']) : 0;

		// Make sure its valid before we parse it out
		if (filter_var($url, FILTER_VALIDATE_URL))
		{
			// Get the elements for this url
			$url_parse = parse_url($url);
			$this->_url['host_raw'] = $url_parse['host'];

			// Handle SSL connections
			if ($url_parse['scheme'] === 'https')
			{
				$this->_url['host'] = 'ssl://' . $url_parse['host'];
				$this->_url['port'] = !empty($this->_url['port']) ? $this->_url['port'] : 443;
			}
			else
			{
				$this->_url['host'] = $url_parse['host'];
				$this->_url['port'] = !empty($this->_url['port']) ? $this->_url['port'] : 80;
			}

			// Fix/Finalize the data path
			$this->_url['path'] = (isset($url_parse['path']) ? $url_parse['path'] : '/') . (isset($url_parse['query']) ? '?' . $url_parse['query'] : '');
		}
	}

	/**
	 * Connect to the host/port as requested
	 *
	 * @return bool
	 */
	private function _sockOpen()
	{
		// no socket, then we need to open one to do much
		if (!is_resource($this->_fp))
		{
			try
			{
				$this->_fp = fsockopen($this->_url['host'], $this->_url['port'], $errno, $errstr, 5);
				$this->_response['error'] = empty($errstr) ? false : $errno . ' :: ' . $errstr;
			}
			catch (\Exception $e)
			{
				return false;
			}
		}

		return is_resource($this->_fp);
	}

	/**
	 * Make the request to the host, either get or post, and get the initial response.
	 */
	private function _makeRequest()
	{
		$request = '';
		$request .= (empty($this->_post_data) ? 'GET ' : 'POST ') . $this->_url['path'] . ' HTTP/1.1' . "\r\n";
		$request .= 'Host: ' . $this->_url['host_raw'] . "\r\n";
		$request .= $this->_keepAlive();
		$request .= 'User-Agent: PHP/ELK' . "\r\n";
		$request .= 'Content-Type: application/x-www-form-urlencoded' . "\r\n";

		if (!empty($this->_content_length))
		{
			$request .= 'Range: bytes=0-' . $this->_content_length - 1 . "\r\n";
		}

		if (!empty($this->_post_data))
		{
			$request .= 'Content-Length: ' . strlen($this->_post_data) . "\r\n\r\n";
			$request .= $this->_post_data;
		}
		else
		{
			$request .= "\r\n\r\n";
		}

		// Make the request and read the first line of the server response, ending at the first CRLF
		fwrite($this->_fp, $request);
		$this->_server_response = fgets($this->_fp);
	}

	/**
	 * Sets the proper Keep-Alive header and sets the fp/host if the option is enabled
	 */
	private function _keepAlive()
	{
		if ($this->_keep_alive)
		{
			$request = 'Connection: Keep-Alive' . "\r\n";
			$this->_keep_alive_host = $this->_url['host'];
			$this->_keep_alive_fp = $this->_fp;
		}
		else
		{
			$request = 'Connection: close' . "\r\n";
		}

		return $request;
	}

	/**
	 * Reads the stream until the end of the headers section and then parses those headers
	 */
	private function _readHeaders()
	{
		$this->_headers = array();
		$headers = '';

		// Read / request more data, Looking for a blank line which separates headers from body
		while (!feof($this->_fp) && trim($header = fgets($this->_fp)) !== '')
		{
			$headers .= $header;
		}

		// Separate the data into standard headers
		$headers = explode("\r\n", $headers);
		array_pop($headers);
		foreach ($headers as $header)
		{
			// Get name and value
			list($name, $value) = explode(':', $header, 2);

			// Normalize / clean
			$name = strtolower($name);
			$value = trim($value);

			// If its already there, then add to it as an array
			if (isset($this->_headers[$name]))
			{
				if (is_string($this->_headers[$name]))
				{
					$this->_headers[$name] = array($this->_headers[$name]);
				}

				$this->_headers[$name][] = $value;
			}
			else
			{
				$this->_headers[$name] = $value;
			}
		}
	}

	/**
	 * Looks at the server response and header array to determine if we are redirecting
	 *
	 * @return string
	 */
	private function _checkRedirect()
	{
		// Redirect in case this location is permanently or temporarily moved (301, 302, 307)
		if ($this->_current_redirect < $this->_max_redirect && preg_match('~^HTTP/\S+\s+(30[127])~i', $this->_server_response, $code) === 1)
		{
			// Maintain our status responses
			$this->_response['code'] = intval($code[1]);
			$this->_response['redirects'] = ++$this->_current_redirect;
			$this->_response['headers'] = $this->_headers;

			// redirection with no location, just like working in a corporation
			if (empty($this->_headers['location']))
			{
				return '';
			}
			else
			{
				// Use the same connection or new?
				if (!$this->_keep_alive)
				{
					fclose($this->_fp);
				}

				return $this->_headers['location'];
			}
		}

		return '';
	}

	/**
	 * Fetch the data for the selected site.
	 */
	private function _fetchData()
	{
		// Respect the headers
		$this->_processHeaders();

		// Now the body of the response
		$response = '';

		if (!empty($this->_content_length))
		{
			$response = stream_get_contents($this->_fp, $this->_content_length);
		}
		else
		{
			$response .= stream_get_contents($this->_fp);
		}

		$this->_response['body'] = $this->_unChunk($response);
		$this->_response['size'] = strlen($this->_response['body']);
	}

	/**
	 * Read the response up to the end of the headers
	 */
	private function _processHeaders()
	{
		// If told to close the connection, do so
		if (isset($this->_headers['connection']) && $this->_headers['connection'] === 'close')
		{
			$this->_keep_alive_host = null;
			$this->_keep_alive = false;
		}

		// If its chunked we need to decode the body
		if (isset($this->_headers['transfer-encoding']) && $this->_headers['transfer-encoding'] === 'chunked')
		{
			$this->_chunked = true;
		}

		$this->_response['headers'] = $this->_headers;
	}

	/**
	 * Decodes the response body if its transfer-encoded as chunked
	 *
	 * @param string $body
	 * @return string
	 */
	private function _unChunk($body)
	{
		if (!$this->_chunked)
		{
			return $body;
		}

		$decoded_body = '';
		while (trim($body))
		{
			// It only claimed to be chunked, but its not.
			if (!preg_match('~^([\da-fA-F]+)[^\r\n]*\r\n~sm', $body, $match))
			{
				$decoded_body = $body;
				break;
			}

			$length = hexdec(trim($match[1]));

			if ($length === 0)
			{
				break;
			}

			$cut = strlen($match[0]);
			$decoded_body .= substr($body, $cut, $length);
			$body = substr($body, $cut + $length + 2);
		}

		return $decoded_body;
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
	 * @return string|string[]
	 */
	public function result($area = '')
	{
		// Just return a specified area or the entire result?
		if ($area == '')
		{
			return $this->_response;
		}
		else
		{
			return isset($this->_response[$area]) ? $this->_response[$area] : $this->_response;
		}
	}
}