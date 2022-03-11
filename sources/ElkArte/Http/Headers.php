<?php

/**
 *
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * @version 2.0 dev
 *
 */

namespace ElkArte\Http;

use ElkArte\HttpReq;

class Headers
{
	/** @var string Default content type */
	protected $contentType = 'text/html';

	/** @var string Default character set */
	protected $charset = 'UTF-8';

	/** @var int Default HTTP return code */
	protected $httpCode = 200;

	/** @var array Holds any normal headers collected */
	protected $headers = [];

	/** @var array Holds any special (raw) headers collected */
	protected $specialHeaders = [];

	/** @var \ElkArte\HttpReq|null */
	protected $req;

	/** @var \ElkArte\Http\Headers Sole private \ElkArte\Headers instance */
	private static $instance = null;

	/**
	 * Headers constructor.
	 */
	public function __construct()
	{
		$this->req = HttpReq::instance();
	}

	/**
	 * Sets a redirect location header
	 *
	 * What it does:
	 *
	 * - Adds in scripturl if needed
	 * - Calls call_integration_hook integrate_redirect before headers are sent
	 *
	 * @event integrate_redirect called before headers are sent
	 * @param string $setLocation = '' The URL to redirect to
	 * @param int $httpCode defaults to 200
	 */
	public function redirect($setLocation = '', $httpCode = null)
	{
		global $scripturl;

		// Convert relative URL to site url
		if (preg_match('~^(ftp|http)[s]?://~', $setLocation) === 0)
		{
			$setLocation = $scripturl . ($setLocation !== '' ? '?' . $setLocation : '');
		}

		// Put the session ID in.
		if (empty($_COOKIE) && defined('SID') && !empty(SID))
		{
			$setLocation = preg_replace('/^' . preg_quote($scripturl, '/') . '(?!\?' . preg_quote(SID, '/') . ')\\??/', $scripturl . '?' . SID . ';', $setLocation);
		}
		// Keep that debug in their for template debugging!
		elseif (isset($this->req->debug))
		{
			$setLocation = preg_replace('/^' . preg_quote($scripturl, '/') . '\\??/', $scripturl . '?debug;', $setLocation);
		}

		// Maybe integrations want to change where we are heading?
		call_integration_hook('integrate_redirect', array(&$setLocation));

		// Set the location header and code
		$this
			->header('Location', $setLocation)
			->httpCode = $httpCode ?? 302;

		return $this;
	}

	/**
	 * Run maintance function and then send the all collected headers
	 */
	public function send()
	{
		handleMaintance();
		$this->sendHeaders();
	}

	/**
	 * Normally used for a header that starts with the string "HTTP/" (case is not significant),
	 * which will be used to figure out the HTTP status code to send.  You could stuff in any
	 * complete header you wanted as the value is used directly as header($value)
	 *
	 * @param $value
	 * @return $this
	 */
	public function headerSpecial($value)
	{
		$this->specialHeaders[] = $value;

		return $this;
	}

	/**
	 * Adds headers to the header array for eventual output to browser
	 *
	 * @param string $name Name of the header
	 * @param string|null $value Value for the header
	 *
	 * @return $this
	 */
	public function header($name, $value = null)
	{
		$name = $this->standardizeHeaderName($name);

		// Add new or overwrite
		$this->headers[$name] = $value;

		return $this;
	}

	/**
	 * Converts / Fixes header names in to a standard format to enable consistent
	 * search replace etc.
	 *
	 * @param string $name
	 * @return string
	 */
	protected function standardizeHeaderName($name)
	{
		// Combine spaces and Convert dashes "clear    Site-Data" => "clear Site Data"
		$name = preg_replace('~\s+~', ' ', str_replace('-', ' ', trim($name)));

		// Now ucword the header and add back the dash => Clear-Site-Data
		return str_replace(' ', '-', ucwords($name));
	}

	/**
	 * Set the http header code, like 404, 200, 301, etc
	 * Only output if content type is not empty
	 *
	 * @param int $httpCode
	 * @return $this
	 */
	public function httpCode($httpCode)
	{
		$this->httpCode = intval($httpCode);

		return $this;
	}

	/**
	 * Sets the context type based on if this is an image or not.  Calls
	 * setDownloadFileNameHeader to set the proper content disposition.
	 *
	 * @param string $mime_type
	 * @param string $fileName
	 * @param string $disposition 'attachment' or 'inline';
	 * @return $this
	 */
	public function setAttachmentFileParams($mime_type, $fileName, $disposition = 'attachment')
	{
		// If an image, set the content type to the image/type defined in the mime_type
		if (!empty($mime_type) && strpos($mime_type, 'image/') === 0)
		{
			$this->contentType($mime_type, '');
		}
		// Otherwise, arbitrary binary data
		else
		{
			$this->contentType('application/octet-stream', '');
		}

		// Set the content disposition and name
		$this->setDownloadFileNameHeader($fileName, $disposition);

		return $this;
	}

	/**
	 * Set the proper filename header accounting for UTF-8 characters in the name
	 *
	 * @param string $fileName That would be the name
	 * @param string $disposition 'inline' or 'attachment'
	 */
	private function setDownloadFileNameHeader($fileName, $disposition = false)
	{
		$type = ($disposition ? 'inline' : 'attachment');

		$fileName = str_replace('"', '', $fileName);

		// Send as UTF-8 if the name requires that
		$altName = '';
		if (preg_match('~[\x80-\xFF]~', $fileName))
		{
			$altName = "; filename*=UTF-8''" . rawurlencode($fileName);
		}

		$this->header('Content-Disposition',$type . '; filename="' . $fileName . '"' . $altName);

		return $this;
	}

	/**
	 * Sets the content type and character set.  Replaces an existing one if called multiple times
	 * so the last call to this method will be what is output.
	 *
	 * @param string|null $contentType
	 * @param string|null $charset
	 * @return $this
	 */
	public function contentType($contentType, $charset = null)
	{
		$this->contentType = $contentType;

		if ($charset !== null)
		{
			$this->charset($charset);
		}

		return $this;
	}

	/**
	 * Sets the character set in use, defaults to utf-8
	 *
	 * @param string $charset
	 * @return $this
	 */
	public function charset($charset)
	{
		$this->charset = $charset;

		return $this;
	}

	/**
	 * Removes a single header if set or all headers if we need to restart
	 * the process, such as during an error or other.
	 *
	 * @param string $name
	 * @return $this
	 */
	public function removeHeader($name)
	{
		// Full reset like nothing had been sent
		if ($name === 'all')
		{
			$this->headers = [];
			$this->specialHeaders = [];
			$this->contentType = '';
			$this->charset = 'UTF-8';
			$this->httpCode = 200;
		}

		// Or remove a specific header
		$name = $this->standardizeHeaderName($name);
		unset($this->headers[$name]);

		return $this;
	}

	/**
	 * Send the collection of headers using standard php header() function
	 */
	public function sendHeaders()
	{
		if (headers_sent())
		{
			return;
		}

		foreach ($this->headers as $header => $value)
		{
			header("$header: $value", true);
		}

		foreach ($this->specialHeaders as $header)
		{
			header($header, true);
		}

		if ($this->contentType)
		{
			header('Content-Type: ' . $this->contentType
				. ($this->charset ? '; charset=' . $this->charset : ''), true, $this->httpCode);
		}
	}

	/**
	 * Retrieve the sole instance of this class.
	 *
	 * @return \ElkArte\Http\Headers
	 */
	public static function instance()
	{
		if (self::$instance === null)
		{
			self::$instance = new Headers();
		}

		return self::$instance;
	}
}
