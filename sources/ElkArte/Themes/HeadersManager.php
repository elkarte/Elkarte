<?php

/**
 * HTTP Headers management for themes
 *
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * @version 2.0 Beta 1
 *
 */

namespace ElkArte\Themes;

use ElkArte\Helper\HttpReq;
use ElkArte\Http\Headers;

/**
 * Class HeadersManager
 *
 * Handles HTTP headers setup, content type detection, and API requests
 */
class HeadersManager
{
	/** @var string Default expiration date */
	public const DEFAULT_EXPIRES = 'Mon, 26 Jul 1997 05:00:00 GMT';

	/** @var array Content type mappings */
	private const CONTENT_TYPES = [
		'fatal_error' => 'text/html',
		'json' => 'application/json',
		'xml' => 'text/xml',
		'generic_xml' => 'text/xml',
		'html' => 'text/html',
	];

	/** @var HttpReq */
	private $request;

	/**
	 * HeadersManager constructor
	 *
	 * @param HttpReq $request
	 */
	public function __construct(HttpReq $request)
	{
		$this->request = $request;
	}

	/**
	 * Get the value of 'api' from the request
	 *
	 * What it does:
	 *  - Retrieves the value of the 'api' parameter from the request.
	 *  - Requires that the request was made via AJAX. Validated by checking for
	 * 'HTTP_X_REQUESTED_WITH' header which much be set in fetch API and/or XMLHttpRequest with
	 * setRequestHeader('X-Requested-With', automatically set by jQuery requests.
	 *
	 * @return string|false The value of the 'api' parameter from the request, trimmed.
	 */
	public function getRequestAPI(): string|false
	{
		$api = $this->request->getRequest('api', 'trim', '');

		return in_array($api, ['xml', 'json', 'html']) && !empty($_SERVER['HTTP_X_REQUESTED_WITH']) ? $api : false;
	}

	/**
	 * Set the headers expiration
	 *
	 * What it does:
	 *  - Sets the Expires and Last-Modified headers in the Headers object.
	 *
	 * @param Headers $header The Headers object to set the headers in.
	 */
	public function setupHeadersExpiration(Headers $header): void
	{
		global $context;

		if (empty($context['no_last_modified']))
		{
			$header
				->header('Expires', self::DEFAULT_EXPIRES)
				->header('Last-Modified', gmdate('D, d M Y H:i:s') . ' GMT');
		}
	}

	/**
	 * Set the headers content type
	 *
	 * What it does:
	 *  - Sets the content type of the headers based on the provided context and API.
	 *
	 * @param Headers $header The Headers instance used to set the content type.
	 * @param string|false $api The API string used to determine the content type.
	 */
	public function setupHeadersContentType(Headers $header, string|false $api): void
	{
		$contentType = self::CONTENT_TYPES[$api] ?? 'text/html';

		$header->contentType($contentType, 'UTF-8');
	}
}
