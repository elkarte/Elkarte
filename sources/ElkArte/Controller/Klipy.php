<?php

/**
 * Functions to interact with the Klipy API and return JSON results to the klipy plugin
 *
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * @version 2.0 Beta 1
 *
 */

namespace ElkArte\Controller;

use ElkArte\AbstractController;
use ElkArte\Action;
use ElkArte\Errors\Errors;

/**
 * Functions to interact with the Klipy API and return JSON results to the klipy plugin
 */
class Klipy extends AbstractController
{
	/** @var string $baseApiUrl The base API URL for Klipy */
	protected $baseApiUrl = 'https://api.klipy.com/api/v1';

	/** @var string The API key used for authentication. */
	protected $apiKey;

	/** @var array default values to pass to the Klipy API */
	protected $config = [
		'customer_id' => 'elkarte',
		'contentfilter' => 'medium',
		'locale' => 'en',
		'per_page' => 20,
	];

	/**
	 * Pre-dispatch, called before all other methods.  Sets the Klipy API key for the Dispatch class.
	 *
	 * This method retrieves the Klipy API key from the global $modSettings variable
	 * @return void
	 */
	public function pre_dispatch(): void
	{
		global $modSettings;

		$this->apiKey = $modSettings['klipyApiKey'] ?? '';
	}

	/**
	 * Index action, based on the SA, sends control to the right method.
	 *
	 * @return void
	 */
	public function action_index(): void
	{
		global $context, $modSettings;

		if (empty($modSettings['enableKlipy']) || empty($this->apiKey))
		{
			return;
		}

		$this->setConfig();

		$subActions = [
			'search' => [$this, 'action_getSearchResults'],
			'trending' => [$this, 'action_getFeatured'],
		];

		$action = new Action('klipy');
		$subAction = $action->initialize($subActions, 'trending');
		$context['sub_action'] = $subAction;
		$action->dispatch($subAction);
	}

	/**
	 * Sets the configuration settings for the object.
	 *
	 * @return array The updated configuration settings after merging with the existing configuration.
	 */
	public function setConfig(): array
	{
		global $modSettings;

		$config = [
			'contentfilter' => $modSettings['klipyRating'] ?? 'medium',
			'locale' => $modSettings['klipyLanguage'] ?? 'en',
		];

		$this->config = array_replace($this->config, $config);

		return $this->config;
	}

	/**
	 * Tracks the statistics for a given action.
	 *
	 * @return bool Returns false indicating that the statistics tracking is not needed
	 */
	public function trackStats($action = ''): bool
	{
		return false;
	}

	/**
	 * Retrieves featured GIFs (trending).
	 *
	 * @return bool The featured GIFs and pagination information.
	 */
	public function action_getFeatured(): bool
	{
		checkSession('get');

		is_not_guest();

		$result = $this->request('/gifs/trending', [
			'contentfilter' => $this->config['contentfilter'],
			'per_page' => $this->config['per_page'],
			'page' => $this->_req->getQuery('page', 'trim', '1')
		], $error);

		if ($error)
		{
			return $this->sendResults([], []);
		}

		$images = $this->prepareImageResults($result);

		return $this->sendResults($images, $result);
	}

	/**
	 * Retrieves search results for GIFs based on the provided query.
	 *
	 * @return bool The search results and pagination information.
	 */
	public function action_getSearchResults(): bool
	{
		checkSession('get');

		is_not_guest();

		$result = $this->request('/gifs/search', [
			'q' => $this->_req->getQuery('q', 'trim', ''),
			'contentfilter' => $this->config['contentfilter'],
			'per_page' => $this->config['per_page'],
			'page' => $this->_req->getQuery('page', 'trim', '1')
		], $error);

		if ($error)
		{
			return $this->sendResults([], []);
		}

		$images =  $this->prepareImageResults($result);

		return $this->sendResults($images, $result);
	}

	/**
	 * Sets the results in context so the JSON template can deliver them.
	 *
	 * @param array $images An array of GIFs.
	 * @param array $result The pagination and meta-information.
	 *
	 * @return bool Returns true after sending the results.
	 */
	public function sendResults($images, $result): bool
	{
		global $context;

		// Extract pagination info if available in the new structure
		if (isset($result['data']) && !isset($result['data'][0]))
		{
			$result['next'] = $result['data']['has_next'] ? ($result['data']['current_page'] + 1) : '';
		}

		setJsonTemplate();
		$context['json_data'] = [
			'klipy' => $images,
			'data' => $result
		];

		return true;
	}

	/**
	 * Sends a request to the Klipy API.
	 *
	 * @param string $path The API endpoint path.
	 * @param array $params The additional parameters for the request (optional).
	 * @param bool|null $error A flag to indicate any error message (optional).
	 *
	 * @return array The response from the API as an associative array, or an empty array if there was an error.
	 */
	public function request(string $path, array $params = [], bool|null &$error = null): array
	{
		$result = [];
		$params = ['customer_id' => $this->config['customer_id'], 'format_filter' => 'gif'] + $params;
		$path .= '?' . http_build_query($params, '', '&');

		require_once(SUBSDIR . '/Package.subs.php');
		$url = $this->baseApiUrl . '/' . $this->apiKey . '/' . ltrim($path, '/');
		$body = fetch_web_data($url);
		if ($body !== false)
		{
			$contents = json_decode($body, true);

			return is_array($contents) ? $contents : [];
		}

		$error = true;
		Errors::instance()->log_error('Klipy API error for URL: ' . $url);

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

		if (!is_array($result))
		{
			Errors::instance()->log_error('Klipy API response is not an array');

			return $images;
		}

		// Support for different response structures
		// 1. result.data.data (Klipy V1)
		// 2. result.results (Tenor-like)
		// 3. result.data (Giphy-like)
		$items = $result['data']['data'] ?? $result['results'] ?? $result['data'] ?? [];

		if (!is_array($items))
		{
			Errors::instance()->log_error('Klipy API response missing results: ' . substr(json_encode($result), 0, 100));

			return $images;
		}

		foreach ($items as $data)
		{
			// Try to find media info in various structures
			// V1 uses 'file' -> 'hd'/'md'/'sm'/'xs' -> 'gif'/'webm' -> 'url'
			// Others use 'media_formats', 'images', 'media'
			$media = $data['file'] ?? $data['media_formats'] ?? $data['images'] ?? $data['media'] ?? [];

			if (isset($data['file']))
			{
				$insert = $media['md']['gif']['url'] ?? $media['hd']['gif']['url'] ?? $media['sm']['gif']['url'] ?? '';
				$thumbnail = $media['xs']['gif']['url'] ?? $media['sm']['gif']['url'] ?? $insert;
			}
			else
			{
				$insert = $media['gif']['url'] ?? $media['original']['url'] ?? '';
				$thumbnail = $media['tinygif']['url'] ?? $media['preview']['url'] ?? $media['fixed_height_small']['url'] ?? $insert;
			}

			if (empty($insert))
			{
				continue;
			}

			$images[$data['id']] = [
				'title' => $data['title'] ?? '',
				'insert' => $insert,
				'src' => $thumbnail,
				'thumbnail' => $thumbnail,
			];
		}

		return $images;
	}
}
