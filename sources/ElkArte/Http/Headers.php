<?php

/**
 *
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * @version 2.0 Beta 1
 *
 */

namespace ElkArte\Http;

use ElkArte\Helper\HttpReq;

/**
 * Class Headers
 *
 * Handles HTTP headers for the application.
 */
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

	/** @var HttpReq|null */
	protected $req;

	/** @var Headers Sole private \ElkArte\Headers instance */
	private static $instance;

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
	public function redirect($setLocation = '', $httpCode = null): Headers
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
		// Keep that debug in there for template debugging!
		elseif (isset($this->req->debug))
		{
			$setLocation = preg_replace('/^' . preg_quote($scripturl, '/') . '\\??/', $scripturl . '?debug;', $setLocation);
		}

		// Maybe integrations want to change where we are heading?
		call_integration_hook('integrate_redirect', [&$setLocation]);

		// Set the location header and code
		$this
			->header('Location', $setLocation)
			->httpCode = $httpCode ?? 302;

		return $this;
	}

	/**
	 * Run a maintenance function and then send the all collected headers
	 */
	public function send(): void
	{
		handleMaintenance();
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
	public function headerSpecial($value): self
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
	public function header($name, $value = null): self
	{
		$name = $this->standardizeHeaderName($name);

		// Add new or overwrite
		$this->headers[$name] = $value;

		return $this;
	}

	/**
	 * Converts / Fixes header names to a standard format, so we have consistent search replace etc.
	 *
	 * @param string $name
	 * @return string
	 */
	protected function standardizeHeaderName($name): string
	{
		// Combine spaces and Convert dashes "clear    Site-Data" => "clear Site Data"
		$name = preg_replace('~\s+~', ' ', str_replace('-', ' ', trim($name)));

		// Now ucword the header and add back the dash => Clear-Site-Data
		return str_replace(' ', '-', ucwords($name));
	}

	/**
	 * Set the http header code, like 404, 200, 301, etc.
	 * Only output if the content type is empty
	 *
	 * @param int $httpCode
	 * @return $this
	 */
	public function httpCode($httpCode): self
	{
		$this->httpCode = (int) $httpCode;

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
	public function setAttachmentFileParams($mime_type, $fileName, $disposition = 'attachment'): self
	{
		// If an image, set the content type to the image/type defined in the mime_type
		if (!empty($mime_type) && str_starts_with($mime_type, 'image/'))
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
	private function setDownloadFileNameHeader($fileName, $disposition = false): void
	{
		$type = ($disposition ? 'inline' : 'attachment');

		$fileName = str_replace('"', '', $fileName);

		// Send as UTF-8 if the name requires that
		$altName = '';
		if (preg_match('~[\x80-\xFF]~', $fileName))
		{
			$altName = "; filename*=UTF-8''" . rawurlencode($fileName);
		}

		$this->header('Content-Disposition', $type . '; filename="' . $fileName . '"' . $altName);
	}

	/**
	 * Sets the content type and character set.  Replaces an existing one if called multiple times,
	 * so the last call to this method will be what is output.
	 *
	 * @param string|null $contentType
	 * @param string|null $charset
	 * @return $this
	 */
	public function contentType($contentType, $charset = null): self
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
	public function charset($charset): self
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
	public function removeHeader($name): self
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
	 * Send the collection of headers using standard php header() function.  If you need to send
	 * a response header, set the return code via httpCode with no contentType header set.
	 */
	public function sendHeaders(): void
	{
		if (headers_sent())
		{
			return;
		}

		foreach ($this->headers as $header => $value)
		{
			header("$header: $value");
		}

		foreach ($this->specialHeaders as $header)
		{
			header($header);
		}

		if ($this->contentType)
		{
			header('Content-Type: ' . $this->contentType . ($this->charset ? '; charset=' . $this->charset : ''), true, $this->httpCode);
		}
		else
		{
			$this->setResponse();
		}
	}

	/**
	 * Sets the HTTP response header based on the provided HTTP status code.
	 * If the status code is not in the predefined list, defaults to 500 Internal Server Error.
	 *
	 * @return void
	 */
	public function setResponse(): void
	{
		$responseHeaders = [
			200 => '200 OK',
			206 => '206 Partial Content',
			301 => '301 Moved Permanently',
			302 => '302 Found',
			304 => '304 Not Modified',
			400 => '400 Bad Request',
			403 => '403 Forbidden',
			404 => '404 Not Found',
			406 => '406 Not Acceptable',
			410 => '403 Gone',
			416 => '416 Requested Range Not Satisfiable',
			500 => '500 Internal Server Error',
			503 => '503 Service Temporarily Unavailable',
		];

		if (!isset($responseHeaders[$this->httpCode]))
		{
			$this->httpCode = 500;
		}

		header(detectServer()->getProtocol() . ' ' . $responseHeaders[$this->httpCode]);
	}

	/**
	 * Retrieve the sole instance of this class.
	 *
	 * @return Headers
	 */
	public static function instance(): Headers
	{
		if (self::$instance === null)
		{
			self::$instance = new Headers();
		}

		return self::$instance;
	}
}
