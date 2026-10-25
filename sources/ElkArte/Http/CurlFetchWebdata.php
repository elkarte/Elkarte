<?php

/**
 * Provides a cURL interface for fetching files and submitting requests to sites
 *
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * @version 2.0 Beta 2
 *
 */

namespace ElkArte\Http;

/**
 * Simple cURL class to fetch a web page
 * Properly redirects even with safe mode and basedir restrictions
 * Can provide simple post-options to a page
 *
 * Load class
 * Initiate as
 *  - $fetch_data = new CurlFetchWebdata();
 *  - Optionally pass an array of cURL options and redirect count
 *  - CurlFetchWebdata(cURL options array, Max redirects);
 *  - $fetch_data = new CurlFetchWebdata(array(CURLOPT_SSL_VERIFYPEER => 1), 5);
 *
 * Make the call
 *  - $fetch_data->get_url_data('http://www.adomain.org'); // fetch a page
 *  - $fetch_data->get_url_data('http://www.adomain.org', array('user' => 'name', 'password' => 'password')); // post to a page
 *  - $fetch_data->get_url_data('http://www.adomain.org', parameter1&parameter2&parameter3); // post to a page
 *
 * Get the data
 *  - $fetch_data->result('body'); // just the page content
 *  - $fetch_data->result(); // an array of results, body, header, http result codes
 *  - $fetch_data->result_raw(); // show all results of all calls (in the event of a redirect)
 *  - $fetch_data->result_raw(x); // show all results of call x
 */
class CurlFetchWebdata
{
	/**
	 * Set the default items for this class
	 *
	 * @var array
	 */
	private $default_options = [
		CURLOPT_RETURNTRANSFER => true, // Get the returned value as a string (don't output it)
		CURLOPT_HEADER => true, // We need the headers to do our own redirect
		CURLOPT_FOLLOWLOCATION => false, // Don't follow, we will do it ourselves so safe mode and open_basedir will dig it
		CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36', // set a normal-looking user agent
		CURLOPT_CONNECTTIMEOUT => 10, // Don't wait forever on a connection
		CURLOPT_TIMEOUT => 20, // A page should load in this amount of time
		CURLOPT_MAXREDIRS => 3, // stop after this many redirects
		CURLOPT_ENCODING => 'gzip,deflate', // accept gzip and decode it
		CURLOPT_SSL_VERIFYPEER => true, // Allow cURL to verify the peer's certificate
		CURLOPT_SSL_VERIFYHOST => 2, // Allow cURL to verify the peer's host
		CURLOPT_POST => false, // no post-data unless it's passed
		CURLOPT_HTTPHEADER => [
			'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
			'Accept-Language: en-US,en;q=0.9',
		], // standard browser headers to prevent 403 blocks
	];

	/** @var int Holds the passed or default value for redirects */
	private $_max_redirect;

	/** @var int Holds the current redirect count for the request */
	private $_current_redirect = 0;

	/** @var array Holds the passed user options array */
	private $_user_options;

	/** @var string Holds any data that will be posted to a form */
	private $_post_data = '';

	/** @var string[] Holds the response to the cURL request, headers, data, code, etc. */
	private $_response = [];

	/** @var array Holds response headers to the request */
	private $_headers = [];

	/** @var array Holds the options for this request */
	private $_options = [];

	/**
	 * Start the cURL object
	 *
	 * - Allow for user override values
	 *
	 * @param array $options cURL options as an array
	 * @param int $max_redirect Maximum number of redirects
	 */
	public function __construct($options = [], $max_redirect = 3)
	{
		// Initialize class variables
		$this->_max_redirect = (int) $max_redirect;
		$this->_user_options = $options;
	}

	/**
	 * Main calling function
	 *
	 * What it does:
	 *
	 * - Will request the page data from a given $url
	 * - Optionally will post data to the page form if post-data is supplied
	 * - Passed arrays will be converted to a post-string joined with &'s
	 * - Calls _setOptions to set the curl opts array values based on the defaults and user input
	 *
	 * @param string $url the site we are going to fetch
	 * @param array|string $post_data data to send in the curl request as post-data
	 *
	 * @return CurlFetchWebdata
	 */
	public function get_url_data($url, $post_data = []): CurlFetchWebdata
	{
		// POSTing some data perhaps?
		if (!empty($post_data) && is_array($post_data))
		{
			$this->_post_data = $this->_buildPostData($post_data);
		}
		elseif (!empty($post_data))
		{
			$this->_post_data = trim($post_data);
		}

		$url = str_replace(' ', '%20', $url);
		$validated = UrlValidator::validate($url, $this->_user_options['validator_options'] ?? []);
		if ($validated === false)
		{
			$this->_setErrorResponse($url, 'Invalid URL or target host not allowed', 400, true);

			return $this;
		}

		// Set the options and get it
		$this->_setOptions();
		$this->_curlRequest($validated['url']);

		return $this;
	}

	/**
	 * Takes supplied POST data and url encodes it
	 *
	 * What it does:
	 *
	 * - Forms the date (for post) in to a string var=xyz&var2=abc&var3=123
	 * - Drops vars with @ since we don't support sending files (uploading)
	 *
	 * @param array $post_data
	 *
	 * @return array|string
	 */
	private function _buildPostData($post_data)
	{
		if (is_array($post_data))
		{
			$post_vars = [];

			// Build the post-data, drop ones with leading @'s since those can be used to send files,
			// we don't support that.
			foreach ($post_data as $name => $value)
			{
				$post_vars[] = $name . '=' . urlencode(($value === '' || $value[0] === '@') ? '' : $value);
			}

			return implode('&', $post_vars);
		}

		return $post_data;
	}

	/**
	 * Sets the final cURL options for the current call
	 *
	 * What it does:
	 *
	 * - Overwrites our default values with user-supplied ones or appends new user ones to what we have
	 * - Sets the callback function now that $this exists
	 *
	 * @uses _headerCallback()
	 */
	private function _setOptions(): void
	{
		// Callback to parse the returned headers, if any
		$this->default_options[CURLOPT_HEADERFUNCTION] = fn($cr, $header) => $this->_headerCallback($cr, $header);

		// Any user options to account for
		if (is_array($this->_user_options))
		{
			$keys = array_merge(array_keys($this->default_options), array_keys($this->_user_options));
			$vals = array_merge($this->default_options, $this->_user_options);
			$this->_options = array_combine($keys, $vals);
		}
		else
		{
			$this->_options = $this->default_options;
		}

		// POST-data options, here we don't allow any override
		if (!empty($this->_post_data))
		{
			$this->_options[CURLOPT_POST] = 1;
			$this->_options[CURLOPT_POSTFIELDS] = $this->_post_data;
		}
	}

	/**
	 * Callback function to parse returned headers
	 *
	 * What it does:
	 *
	 * - Lowercase everything to make it consistent
	 *
	 * @param object $cr Not used but passed by the cURL agent
	 * @param string $header The headers received
	 *
	 * @return int
	 */
	private function _headerCallback($cr, $header): int
	{
		$_header = trim($header);
		$temp = explode(': ', $_header, 2);

		// Set proper headers only
		if (isset($temp[0], $temp[1]))
		{
			$this->_headers[strtolower($temp[0])] = trim($temp[1]);
		}

		// Return the length of what was *passed* unless you want a Failed writing header error ;)
		return strlen($header);
	}

	/**
	 * Makes the actual cURL call
	 *
	 * What it does
	 * - Store responses (url, code, error, headers, body) in the response array
	 * - Detects 301, 302, 307 codes and will redirect to the given response header location
	 *
	 * @param string $url site to fetch
	 * @param bool $redirect flag to indicate if this was a redirect request or not
	 *
	 * @return bool
	 */
	private function _curlRequest($url, $redirect = false): bool
	{
		// We do have a url I hope
		if (trim($url) === '')
		{
			return false;
		}

		$validated = UrlValidator::validate($url, $this->_user_options['validator_options'] ?? []);
		if ($validated === false)
		{
			$this->_setErrorResponse($url, 'Invalid URL or target host not allowed', 400);

			return false;
		}

		$this->_options[CURLOPT_URL] = $validated['url'];

		// DNS rebinding protection via CURLOPT_RESOLVE if IP is resolved
		if (defined('CURLOPT_RESOLVE'))
		{
			$resolveEntry = UrlValidator::resolveEntry($validated);
			if ($resolveEntry !== '')
			{
				$this->_options[CURLOPT_RESOLVE] = [$resolveEntry];
			}
			else
			{
				unset($this->_options[CURLOPT_RESOLVE]);
			}
		}

		// If we have not already been redirected, set it up so we can
		if (!$redirect)
		{
			$this->_current_redirect = 1;
			$this->_response = [];
		}

		// Initialize the curl object and make the call
		$cr = curl_init();
		curl_setopt_array($cr, $this->_options);
		$result = curl_exec($cr);
		$curl_info = curl_getinfo($cr);
		$error = ($result === false) ? curl_error($cr) : false;
		$body = $error ? false : substr($result, $curl_info['header_size']);

		// Close this request
		curl_close($cr);

		// Store this 'loops' data, someone may want all of these :O
		$this->_response[] = $this->_buildResponse(
			$curl_info['url'],
			(int) $curl_info['http_code'],
			$error,
			$curl_info['download_content_length'] ?: 0,
			$this->_headers ?: false,
			$body
		);

		// If this a redirect with a location header and we have not given up, then we play it again Sam
		if (!empty($this->_headers['location'])
			&& $this->_current_redirect <= $this->_max_redirect
			&& preg_match('~30[12378]~', (string) $curl_info['http_code']) === 1)
		{
			$this->_current_redirect++;
			$location = is_array($this->_headers['location']) ? end($this->_headers['location']) : $this->_headers['location'];
			$redirectUrl = $this->_getRedirectURL($curl_info['url'], $location);
			if (UrlValidator::isValid($redirectUrl, $this->_user_options['validator_options'] ?? []))
			{
				$this->_redirect($redirectUrl, $curl_info['url']);
			}
			else
			{
				$this->_setErrorResponse($redirectUrl, 'Redirect to prohibited target host blocked', 400);
			}
		}

		return true;
	}

	/**
	 * Build fully qualified redirect URL from location header
	 *
	 * @param string $last_url
	 * @param string $new_url
	 * @return string
	 */
	private function _getRedirectURL($last_url = '', $new_url = ''): string
	{
		return UrlValidator::buildAbsoluteUrl($last_url, $new_url);
	}

	/**
	 * Build a standardized response structure array
	 *
	 * @param string $url The URL for this response
	 * @param int $code HTTP response code
	 * @param string|false $error Error string or false if no error
	 * @param int $size Download size
	 * @param array|false $headers Response headers or false
	 * @param string|false $body Response body or false
	 * @return array
	 */
	private function _buildResponse(string $url, int $code = 400, $error = false, int $size = 0, $headers = false, $body = false): array
	{
		return [
			'url' => $url,
			'code' => $code,
			'error' => $error,
			'size' => $size,
			'headers' => $headers,
			'body' => $body,
		];
	}

	/**
	 * Set or append an error response
	 *
	 * @param string $url The target URL
	 * @param string $error Error message description
	 * @param int $code HTTP status code (default 400)
	 * @param bool $overwrite Whether to overwrite the entire response array (true) or append (false)
	 */
	private function _setErrorResponse(string $url, string $error, int $code = 400, bool $overwrite = false): void
	{
		$response = $this->_buildResponse($url, $code, $error, 0, false, false);

		if ($overwrite)
		{
			$this->_response = [$response];
		}
		else
		{
			$this->_response[] = $response;
		}
	}

	/**
	 * Called to initiate a redirect from a 301, 302, or 307 header
	 *
	 * What it does
	 * - Resets the cURL options for the loop, sets the referrer flag
	 *
	 * @param string $target_url The URL of the target
	 * @param string $referer_url The URL of the link that referred us to the new target
	 */
	private function _redirect($target_url, $referer_url): void
	{
		// No, I last saw that over there ... really, 301, 302, 307
		$this->_setOptions();
		$this->_options[CURLOPT_REFERER] = $referer_url;
		$this->_curlRequest($target_url, true);
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
		$max_result = count($this->_response) - 1;

		// Just return a specified area or the entire result?
		if (trim($area) === '')
		{
			return $this->_response[$max_result];
		}

		return $this->_response[$max_result][$area] ?? $this->_response[$max_result];
	}

	/**
	 * Will return all results from all loops (redirects)
	 *
	 * What it does:
	 *
	 * - Can be called as ->result_raw(x) where x is a specific loop result.
	 * - Call as ->result_raw() for everything.
	 *
	 * @param int|string $response_number
	 *
	 * @return string|string[]
	 */
	public function result_raw($response_number = '')
	{
		if (!is_numeric($response_number))
		{
			return $this->_response;
		}

		$response_number = min($response_number, count($this->_response) - 1);

		return $this->_response[$response_number];
	}
}
