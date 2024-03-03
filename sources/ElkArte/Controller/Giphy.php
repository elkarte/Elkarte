<?php

/**
 * Functions to interact with the Giphy API and return JSON results to the giphy plugin
 *
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * @version 2.0 dev
 *
 */

namespace ElkArte\Controller;

use ElkArte\AbstractController;
use ElkArte\Action;
use ElkArte\Errors\Errors;

/**
 * Functions to interact with the Giphy API and return JSON results to the giphy plugin
 */
class Giphy extends AbstractController
{
	/** @var string $baseApiUrl The base API URL for Giphy. */
	protected $baseApiUrl = 'https://api.giphy.com/v1/';

	/** @var string The API key used for authentication. */
	protected $apiKey;

	/** @var array default values to pass to the Giphy API */
	protected $config = [
		'random_id' => null,
		'rating' => 'g',
		'lang' => 'en',
		'limit' => 28,
	];

	/**
	 * pre_dispatch, called before all other methods.  Sets the Giphy API key for the Dispatch class.
	 *
	 * This method retrieves the Giphy API key from the global $modSettings variable
	 * @return void
	 */
	public function pre_dispatch()
	{
		global $modSettings;

		// The default is a rate limited 42 search requests an hour and 1000 search requests a day
		$this->apiKey = $modSettings['giphyApiKey'] ?? 'fpjXDpZ1cJ0qoqol3BVZz76YHZlv1uB2';
	}

	/**
	 * Index action, based on the SA sends control to the right method.
	 *
	 * @return void
	 */
	public function action_index()
	{
		global $context, $modSettings;

		if (empty($modSettings['enableGiphy']))
		{
			return;
		}

		$this->setConfig();

		$subActions = [
			'search' => [$this, 'action_getSearchResults'],
			'trending' => [$this, 'action_getTrending'],
		];

		$action = new Action('giphy');
		$subAction = $action->initialize($subActions, 'trending');
		$context['sub_action'] = $subAction;
		$action->dispatch($subAction);
	}

	/**
	 * Sets the configuration settings for the object.
	 *
	 * @return array The updated configuration settings after merging with the existing configuration.
	 */
	public function setConfig()
	{
		global $modSettings;

		$config = [
			'rating' => $modSettings['giphyRating'] ?? 'g',
			'lang' => $modSettings['giphyLanguage'] ?? 'en',
		];

		$this->config = array_replace($this->config, $config);

		return $this->config;
	}

	/**
	 * Tracks the statistics for a given action.
	 *
	 * @return bool Returns false indicating that the statistics tracking is not needed
	 */
	public function trackStats($action = '')
	{
		return false;
	}

	/**
	 * Retrieves trending GIFs.
	 *
	 * @return bool The trending GIFs and pagination information.
	 */
	public function action_getTrending()
	{
		checkSession('get');

		is_not_guest();

		$result = $this->request('gifs/trending', [
			'random_id' => $this->config['random_id'],
			'rating' => $this->config['rating'],
			'limit' => $this->config['limit'],
			'offset' => $this->_req->getQuery('offset', 'intval', 0)
		], $error);

		if ($error)
		{
			return $this->sendResults([], []);
		}

		$images = $this->prepareImageResults($result);
		$result['pagination']['limit'] = $this->config['limit'];

		return $this->sendResults($images, $result);
	}

	/**
	 * Retrieves search results for GIFs based on the provided query.
	 *
	 * @return bool The search results and pagination information.
	 */
	public function action_getSearchResults()
	{
		checkSession('get');

		is_not_guest();

		$result = $this->request('gifs/search', [
			'q' => $this->_req->getQuery('q', 'trim', ''),
			'random_id' => $this->config['random_id'],
			'rating' => $this->config['rating'],
			'limit' => $this->config['limit'],
			'offset' => $this->_req->getQuery('offset', 'intval', 0)
		], $error);

		if ($error)
		{
			return $this->sendResults([], []);
		}

		$images =  $this->prepareImageResults($result);
		$result['pagination']['limit'] = $this->config['limit'];

		return $this->sendResults($images,$result);
	}

	/**
	 * Sets the results in context so the JSON template can deliver them.
	 *
	 * @param array $images An array of trending GIFs.
	 * @param array $result The pagination and meta information.
	 *
	 * @return bool Returns true after sending the results.
	 */
	public function sendResults($images, $result)
	{
		global $context;

		theme()->getLayers()->removeAll();
		theme()->getTemplates()->load('Json');
		$context['sub_template'] = 'send_json';

		$context['json_data'] = [
			'giphy' => $images,
			'data' => $result
		];

		return true;
	}

	/**
	 * Sends a request to the GIPHY API.
	 *
	 * @param string $path The API endpoint path.
	 * @param array $params The additional parameters for the request (optional).
	 * @param string &$error A variable to hold any error message (optional).
	 *
	 * @return array The response from the API as an associative array, or an empty array if there was an error.
	 */
	public function request(string $path, array $params = [], string &$error = null): array
	{
		$result = [];
		$params = ['api_key' => $this->apiKey] + $params;
		$path .= '?' . http_build_query($params, '','&');

		require_once(SUBSDIR . '/Package.subs.php');
		$body = fetch_web_data($this->baseApiUrl . $path);
		if ($body !== false)
		{
			$contents = json_decode($body, true);

			return is_array($contents) ? $contents : [];
		}

		$error = true;
		Errors::instance()->log_error('GIPHY API error');

		return $result;
	}

	/**
	 * Prepares the results from the API response.
	 *
	 * @param array $result The API response containing the image data.
	 * @return array The prepared image results.
	 */
	protected function prepareImageResults($result): array
	{
		$images = [];

		if (is_array($result))
		{
			foreach ($result['data'] as $data)
			{
				$fixedHeight = $data['images']['fixed_height']['url'];
				$fixedHeightStill = $data['images']['fixed_height_still']['url'];

				$fixedHeightSmall = $data['images']['fixed_height_small']['url'] ?? $fixedHeight;
				$fixedHeightSmallStill = $data['images']['fixed_height_small_still']['url'] ?? $fixedHeightStill;

				$images[$data['id']] = [
					'title' => $data['title'],
					'insert' => $this->normalizeUrl($fixedHeight),
					'src' => $this->normalizeUrl($fixedHeightSmall),
					'thumbnail' => $this->normalizeUrl($fixedHeightSmallStill),
				];
			}
		}

		return $images;
	}

	/**
	 * Normalizes a given URL.
	 *
	 * @param string $url The URL to be normalized.
	 * @return string The normalized URL without query parameters or fragments.
	 */
	protected function normalizeUrl($url)
	{
		$parts = parse_url($url);

		return sprintf('%s://%s%s', $parts['scheme'], $parts['host'], $parts['path']);
	}
}
