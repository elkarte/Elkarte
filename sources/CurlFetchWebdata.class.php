<?php

/**
 * Provides a cURL interface for fetching files and submitting requests to sites
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 1.1 beta 3
 *
 */

/**
 * Simple cURL class to fetch a web page
 * Properly redirects even with safe mode and basedir restrictions
 * Can provide simple post options to a page
 *
 * Load class
 * Initiate as
 *  - $fetch_data = new Curl_Fetch_Webdata();
 *  - optionally pass an array of cURL options and redirect count
 *  - Curl_Fetch_Webdata(cURL options array, Max redirects);
 *  - $fetch_data = new Curl_Fetch_Webdata(array(CURLOPT_SSL_VERIFYPEER => 1), 5);
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
class Curl_Fetch_Webdata
{
	/**
	 * Set the default items for this class
	 * @var mixed[]
	 */
	private $default_options = array(
		CURLOPT_RETURNTRANSFER	=> 1, // Get returned value as a string (don't output it)
		CURLOPT_HEADER			=> 1, // We need the headers to do our own redirect
		CURLOPT_FOLLOWLOCATION	=> 0, // Don't follow, we will do it ourselves so safe mode and open_basedir will dig it
		CURLOPT_USERAGENT		=> 'Mozilla/5.0 (compatible; MSIE 9.0; Windows NT 6.1; Trident/5.0)', // set a normal looking user agent
		CURLOPT_CONNECTTIMEOUT	=> 10, // Don't wait forever on a connection
		CURLOPT_TIMEOUT			=> 10, // A page should load in this amount of time
		CURLOPT_MAXREDIRS		=> 3, // stop after this many redirects
		CURLOPT_ENCODING		=> 'gzip,deflate', // accept gzip and decode it
		CURLOPT_SSL_VERIFYPEER	=> 0, // stop cURL from verifying the peer's certificate
		CURLOPT_SSL_VERIFYHOST	=> 0, // stop cURL from verifying the peer's host
		CURLOPT_POST			=> 0, // no post data unless its passed
	);

	/**
	 * Holds the passed or default value for redirects
	 * @var int
	 */
	private $_max_redirect = 3;

	/**
	 * Holds the current redirect count for the request
	 * @var int
	 */
	private $_current_redirect = 0;

	/**
	 * Holds the passed user options array
	 * @var mixed[]
	 */
	private $_user_options = array();

	/**
	 * Holds any data that will be posted to a form
	 * @var string
	 */
	private $_post_data = '';

	/**
	 * Holds the response to the cURL request, headers, data, code, etc
	 * @var string[]
	 */
	private $_response = array();

	/**
	 * Holds response headers to the request
	 * @var mixed[]
	 */
	private $_headers = array();

	/**
	 * Holds the options for this request
	 * @var mixed[]
	 */
	private $_options = array();

	/**
	 * Start the cURL object
	 *
	 * - Allow for user override values
	 *
	 * @param mixed[] $options cURL options as an array
	 * @param int $max_redirect Maximum number of redirects
	 */
	public function __construct($options = array(), $max_redirect = 3)
	{
		// Initialize class variables
		$this->_max_redirect = intval($max_redirect);
		$this->_user_options = $options;
	}

	/**
	 * Main calling function
	 *
	 * What it does:
	 * - Will request the page data from a given $url
	 * - Optionally will post data to the page form if post data is supplied
	 * - Passed arrays will be converted to a post string joined with &'s
	 * - Calls _setOptions to set the curl opts array values based on the defaults and user input
	 *
	 * @param string $url the site we are going to fetch
	 * @param mixed[]|string $post_data data to send in the curl request as post data
	 */
	public function get_url_data($url, $post_data = array())
	{
		// POSTing some data perhaps?
		if (!empty($post_data) && is_array($post_data))
			$this->_post_data = $this->_buildPostData($post_data);
		elseif (!empty($post_data))
			$this->_post_data = trim($post_data);

		// Set the options and get it
		$this->_setOptions();
		$this->_curlRequest(str_replace(' ', '%20', $url));

		return $this;
	}

	/**
	 * Makes the actual cURL call
	 *
	 * What it does
	 * - Stores responses (url, code, error, headers, body) in the response array
	 * - Detects 301, 302, 307 codes and will redirect to the given response header location
	 *
	 * @param string $url site to fetch
	 * @param bool $redirect flag to indicate if this was a redirect request or not
	 */
	private function _curlRequest($url, $redirect = false)
	{
		// We do have a url I hope
		if ($url == '')
			return false;
		else
			$this->_options[CURLOPT_URL] = $url;

		// If we have not already been redirected, set it up so we can
		if (!$redirect)
		{
			$this->_current_redirect = 1;
			$this->_response = array();
		}

		// Initialize the curl object and make the call
		$cr = curl_init();
		curl_setopt_array($cr, $this->_options);
		curl_exec($cr);

		// Get what was returned
		$curl_info = curl_getinfo($cr);
		$curl_content = curl_multi_getcontent($cr);
		$url = $curl_info['url']; // Last effective URL
		$http_code = $curl_info['http_code']; // Last HTTP code
		$body = (!curl_error($cr)) ? substr($curl_content, $curl_info['header_size']) : false;
		$error = (curl_error($cr)) ? curl_error($cr) : false;

		// Close this request
		curl_close($cr);

		// Store this 'loops' data, someone may want all of these :O
		$this->_response[] = array(
			'url' => $url,
			'code' => $http_code,
			'error' => $error,
			'size' => !empty($curl_info['download_content_length']) ? $curl_info['download_content_length'] : 0,
			'headers' => !empty($this->_headers) ? $this->_headers : false,
			'body' => $body,
		);

		// If this a redirect with a location header and we have not given up, then we play it again Sam
		if (preg_match('~30[127]~i', $http_code) === 1 && !empty($this->_headers['location']) && $this->_current_redirect <= $this->_max_redirect)
		{
			$this->_current_redirect++;
			$header_location = $this->_getRedirectURL($url, $this->_headers['location']);
			$this->_redirect($header_location, $url);
		}

		return true;
	}

	/**
	 * Used if being redirected to ensure we have a fully qualified address
	 *
	 * - Returns the new url location for the redirect
	 *
	 * @param string $last_url URL where we went to
	 * @param string $new_url URL where we were redirected to
	 */
	private function _getRedirectURL($last_url = '', $new_url = '')
	{
		// Get the elements for these urls
		$last_url_parse = parse_url($last_url);
		$new_url_parse  = parse_url($new_url);

		// Redirect headers are often incomplete / relative so we need to make sure they are fully qualified
		$new_url_parse['path'] = isset($new_url_parse['path']) ? $new_url_parse['path'] : (isset($new_url_parse['host']) ? '' : $last_url_parse['path']);
		$new_url_parse['scheme'] = isset($new_url_parse['scheme']) ? $new_url_parse['scheme'] : $last_url_parse['scheme'];
		$new_url_parse['host'] = isset($new_url_parse['host']) ? $new_url_parse['host'] : $last_url_parse['host'];
		$new_url_parse['query'] = isset($new_url_parse['query']) ? $new_url_parse['query'] : '';

		// Build the new URL that was in the http header
		return $new_url_parse['scheme'] . '://' . $new_url_parse['host'] . $new_url_parse['path'] . (!empty($new_url_parse['query']) ? '?' . $new_url_parse['query'] : '');
	}

	/**
	 * Used to return the results to the calling program
	 *
	 * What it does:
	 * - Called as ->result() will return the full final array
	 * - Called as ->result('body') to just return the page source of the result
	 *
	 * @param string $area used to return an area such as body, header, error
	 */
	public function result($area = '')
	{
		$max_result = count($this->_response) - 1;

		// Just return a specified area or the entire result?
		if ($area == '')
			return $this->_response[$max_result];
		else
			return isset($this->_response[$max_result][$area]) ? $this->_response[$max_result][$area] : $this->_response[$max_result];
	}

	/**
	 * Will return all results from all loops (redirects)
	 *
	 * What it does:
	 * - Can be called as ->result_raw(x) where x is a specific loop results.
	 * - Call as ->result_raw() for everything.
	 *
	 * @param int|string $response_number
	 */
	public function result_raw($response_number = '')
	{
		if (!is_numeric($response_number))
			return $this->_response;
		else
		{
			$response_number = min($response_number, count($this->_response) - 1);
			return $this->_response[$response_number];
		}
	}

	/**
	 * Takes supplied POST data and url encodes it
	 *
	 * What it does:
	 * - Forms the date (for post) in to a string var=xyz&var2=abc&var3=123
	 * - Drops vars with @ since we don't support sending files (uploading)
	 *
	 * @param mixed[] $post_data
	 */
	private function _buildPostData($post_data)
	{
		if (is_array($post_data))
		{
			$postvars = array();

			// Build the post data, drop ones with leading @'s since those can be used to send files, we don't support that.
			foreach ($post_data as $name => $value)
			{
				$postvars[] = $name . '=' . urlencode($value[0] == '@' ? '' : $value);
			}

			return implode('&', $postvars);
		}
		else
			return $post_data;
	}

	/**
	 * Sets the final cURL options for the current call
	 *
	 * What it does:
	 * - Overwrites our default values with user supplied ones or appends new user ones to what we have
	 * - Sets the callback function now that $this exists
	 *
	 * @uses _headerCallback()
	 */
	private function _setOptions()
	{
		// Callback to parse the returned headers, if any
		$this->default_options[CURLOPT_HEADERFUNCTION] = array($this, '_headerCallback');

		// Any user options to account for
		if (is_array($this->_user_options))
		{
			$keys = array_merge(array_keys($this->default_options), array_keys($this->_user_options));
			$vals = array_merge($this->default_options, $this->_user_options);
			$this->_options = array_combine($keys, $vals);
		}
		else
			$this->_options = $this->default_options;

		// POST data options, here we don't allow any override
		if (!empty($this->_post_data))
		{
			$this->_options[CURLOPT_POST] = 1;
			$this->_options[CURLOPT_POSTFIELDS] = $this->_post_data;
		}
	}

	/**
	 * Called to initiate a redirect from a 301, 302 or 307 header
	 *
	 * What it does
	 * - Resets the cURL options for the loop, sets the referrer flag
	 *
	 * @param string $target_url The URL of the target
	 * @param string $referer_url The URL of the link that referred us to the new target
	 */
	private function _redirect($target_url, $referer_url)
	{
		// No no I last saw that over there ... really, 301, 302, 307
		$this->_setOptions();
		$this->_options[CURLOPT_REFERER] = $referer_url;
		$this->_curlRequest($target_url, true);
	}

	/**
	 * Callback function to parse returned headers
	 *
	 * What it does:
	 * - lowercase everything to make it consistent
	 *
	 * @param object $cr Not used but passed by the cURL agent
	 * @param string $header The headers received
	 */
	private function _headerCallback($cr, $header)
	{
		$_header = trim($header);
		$temp = explode(': ', $_header, 2);

		// Set proper headers only
		if (isset($temp[0]) && isset($temp[1]))
			$this->_headers[strtolower($temp[0])] = trim($temp[1]);

		// Return the length of what was *passed* unless you want a Failed writing header error ;)
		return strlen($header);
	}
}