<?php

/**
 * This will fetch a web resource http/https and return the headers and page data.  It is capable of following
 * redirects and interpreting chunked data.  It will work with allow_url_fopen off.
 *
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * @version 2.0 Beta 1
 *
 */

namespace ElkArte\Http;

use Exception;

/**
 * Class FsockFetchWebdata
 *
 * @package ElkArte
 */
class FsockFetchWebdata
{
	/** @var bool Use the same connection on redirects */
	private $_keep_alive;

	/** @var int Holds the passed or default value for redirects */
	private $_max_redirect;

	/** @var int Holds the current redirect count for the request */
	private $_current_redirect = 0;

	/** @var null|string Used on redirect when keep alive is true */
	private $_keep_alive_host;

	/** @var null|resource the fp resource to reuse */
	private $_keep_alive_fp;

	/** @var int how much we will read */
	private $_content_length = 0;

	/** @var array the parsed url with host, port, path, etc. */
	private $_url = [];

	/** @var null|resource the fsockopen resource */
	private $_fp;

	/** @var array Holds the passed user options array (only option is max_length) */
	private $_user_options;

	/** @var string|string[] Holds any data that will be posted to a form */
	private $_post_data = '';

	/** @var string[] Holds the response to the request, headers, data, code */
	private $_response = ['url' => '', 'code' => 404, 'error' => '', 'redirects' => 0, 'size' => 0, 'headers' => [], 'body' => ''];

	/** @var array() Holds the last header response to the request */
	private $_headers = [];

	/** @var string the HTTP response from the server 200/404/302 etc. */
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
	public function __construct($options = [], $max_redirect = 3, $keep_alive = false)
	{
		// Initialize class variables
		$this->_max_redirect = (int) $max_redirect;
		$this->_user_options = $options;
		$this->_keep_alive = $keep_alive;
	}

	/**
	 * Prepares any post-data supplied and then makes the request for data
	 *
	 * @param string $url
	 * @param string|string[] $post_data
	 *
	 */
	public function get_url_data($url, $post_data = ''): void
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
				$this->_post_data = http_build_query([trim($post_data)], '', '&');
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
	private function _fopenRequest($url): bool
	{
		// We do have a url I hope
		$this->_setOptions($url);
		if (empty($this->_url))
		{
			return false;
		}

		// Reuse the socket if this is a keep alive
		if ($this->_keep_alive && $this->_url['host'] === $this->_keep_alive_host)
		{
			$this->_fp = $this->_keep_alive_fp;
		}

		// Open a connection to the host & port
		if (!$this->_sockOpen())
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
			$this->_response['code'] = isset($code[1]) ? (int) $code[1] : '???';

			// Make sure we ended up with a 200 OK.
			if (in_array($this->_response['code'], [200, 201, 206], true))
			{
				// Provide a common valid 200-return code to the caller
				$this->_response['code'] = 200;
			}

			$this->_fetchData();
			fclose($this->_fp);

			return true;
		}

		// To the new location we go
		$this->_fopenRequest($location);

		return false;
	}

	/**
	 * Parses a url into the components we need
	 *
	 * @param string $url
	 */
	private function _setOptions($url): void
	{
		$this->_url = [];
		$this->_response['url'] = $url;
		$this->_content_length = $this->_user_options['max_length'] ?? 0;

		// Use parse_url only once and cache results
		if (filter_var($url, FILTER_VALIDATE_URL))
		{
			$url_parse = parse_url($url);
			if ($url_parse === false)
			{
				return;
			}

			$this->_url['host_raw'] = $url_parse['host'];
			$scheme_is_https = ($url_parse['scheme'] === 'https');

			$this->_url['host'] = ($scheme_is_https ? 'ssl://' : '') . $url_parse['host'];
			$this->_url['port'] = $url_parse['port'] ?? ($scheme_is_https ? 443 : 80);

			// Combine path and query efficiently
			$this->_url['path'] = $url_parse['path'] ?? '/';
			if (isset($url_parse['query']))
			{
				$this->_url['path'] .= '?' . $url_parse['query'];
			}
		}
	}


	/**
	 * Connect to the host/port as requested
	 *
	 * @return bool
	 */
	private function _sockOpen(): bool
	{
		// no socket, then we need to open one to do much
		if (!is_resource($this->_fp))
		{
			stream_context_create([
				'socket' => [
					'tcp_nodelay' => true,
				]
			]);

			set_error_handler(static function () { /* ignore errors */
			});
			try
			{
				$this->_fp = fsockopen($this->_url['host'], $this->_url['port'], $errno, $errstr, 5);
				$this->_response['error'] = empty($errstr) ? false : $errno . ' :: ' . $errstr;
			}
			catch (Exception)
			{
				return false;
			}
			finally
			{
				restore_error_handler();
			}
		}

		return is_resource($this->_fp);
	}

	/**
	 * Make the request to the host, either get or post, and get the initial response.
	 */
	private function _makeRequest(): void
	{
		$request = (empty($this->_post_data) ? 'GET ' : 'POST ') . $this->_url['path'] . ' HTTP/1.1' . "\r\n";
		$request .= 'Host: ' . $this->_url['host_raw'] . "\r\n";
		$request .= $this->_keepAlive();
		$request .= 'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36' . "\r\n";
		$request .= 'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8' . "\r\n";
		$request .= 'Accept-Language: en-US,en;q=0.9' . "\r\n";
		$request .= (!empty($this->_post_data)) ? 'Content-Type: application/x-www-form-urlencoded' . "\r\n" : '';

		if (!empty($this->_content_length))
		{
			$request .= 'Range: bytes=0-' . ($this->_content_length - 1) . "\r\n";
		}

		if (!empty($this->_post_data))
		{
			$request .= 'Content-Length: ' . strlen($this->_post_data) . "\r\n\r\n";
			$request .= $this->_post_data;
		}
		else
		{
			$request .= "\r\n";
		}

		// Make the request and read the first line of the server response, ending at the first CRLF
		fwrite($this->_fp, $request);
		$this->_server_response = fgets($this->_fp);
	}

	/**
	 * Sets the proper Keep-Alive header and sets the fp/host if the option is enabled
	 */
	private function _keepAlive(): string
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
	private function _readHeaders(): void
	{
		$this->_headers = [];

		while (!feof($this->_fp))
		{
			$header = fgets($this->_fp);
			if ($header === "\r\n" || $header === "\n")
			{
				break;
			}

			if ($header === false)
			{
				break;
			}

			// Process a single header at a time instead of concatenating
			if (str_contains($header, ':'))
			{
				[$name, $value] = explode(':', $header, 2);
				$name = strtolower(trim($name));
				$value = trim($value);

				if (isset($this->_headers[$name]))
				{
					if (is_string($this->_headers[$name]))
					{
						$this->_headers[$name] = [$this->_headers[$name]];
					}
					$this->_headers[$name][] = $value;
				}
				else
				{
					$this->_headers[$name] = $value;
				}
			}
		}
	}


	/**
	 * It looks at the server response and header array to determine if we are redirecting
	 *
	 * @return string
	 */
	private function _checkRedirect(): string
	{
		// Redirect in case this location is permanently or temporarily moved (301, 302, 303, 307, 308)
		if ($this->_current_redirect < $this->_max_redirect && preg_match('~^HTTP/\S+\s+(30[12378])~i', $this->_server_response, $code) === 1)
		{
			// Maintain our status responses
			$this->_response['code'] = (int) $code[1];
			$this->_response['redirects'] = ++$this->_current_redirect;
			$this->_response['headers'] = $this->_headers;

			// redirection with no location, just like working in a corporation
			if (empty($this->_headers['location']))
			{
				return '';
			}

			// Use the same connection or new?
			if (!$this->_keep_alive)
			{
				fclose($this->_fp);
			}

			return $this->_headers['location'];
		}

		return '';
	}

	/**
	 * Fetch the data for the selected site.
	 */
	private function _fetchData(): void
	{
		$this->_processHeaders();

		// Use a fixed buffer size for reading
		$buffer_size = 8192;
		$response = '';

		if (!empty($this->_content_length))
		{
			$remaining = $this->_content_length;
			while ($remaining > 0 && !feof($this->_fp))
			{
				$read = min($buffer_size, $remaining);
				$response .= stream_get_contents($this->_fp, $read);
				$remaining -= $read;
			}
		}
		else
		{
			while (!feof($this->_fp))
			{
				$response .= stream_get_contents($this->_fp, $buffer_size);
			}
		}

		$this->_response['body'] = $this->_chunked ? $this->_unChunk($response) : $response;
		$this->_response['size'] = strlen($this->_response['body']);
	}


	/**
	 * Read the response up to the end of the headers
	 */
	private function _processHeaders(): void
	{
		// If informed to close the connection, do so
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
	private function _unChunk($body): string
	{
		if (!$this->_chunked)
		{
			return $body;
		}

		$decoded_body = '';
		while (trim($body))
		{
			// It only claimed to be chunked, but it's not.
			if (!preg_match('~^([\da-fA-F]+)[^\r\n]*\r\n~m', $body, $match))
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
	 * @return string|string[]|int
	 */
	public function result($area = '')
	{
		// Return a specified area or the entire result?
		if (trim($area) === '')
		{
			return $this->_response;
		}

		return $this->_response[$area] ?? $this->_response;
	}
}
