<?php

/**
 * Functions to interact with the Tenor API and return JSON results to the tenor plugin
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
 * Functions to interact with the Tenor API and return JSON results to the tenor plugin
 */
class Tenor extends AbstractController
{
	/** @var string $baseApiUrl The base API URL for Tenor v2. */
	protected $baseApiUrl = 'https://tenor.googleapis.com/v2/';

	/** @var string The API key used for authentication. */
	protected $apiKey;

	/** @var array default values to pass to the Tenor API */
	protected $config = [
		'client_key' => 'elkarte',
		'contentfilter' => 'medium',
		'locale' => 'en',
		'limit' => 20,
	];

	/**
	 * Pre-dispatch, called before all other methods.  Sets the Tenor API key for the Dispatch class.
	 *
	 * This method retrieves the Tenor API key from the global $modSettings variable
	 * @return void
	 */
	public function pre_dispatch(): void
	{
		global $modSettings;

		$this->apiKey = $modSettings['tenorApiKey'] ?? '';
	}

	/**
	 * Index action, based on the SA, sends control to the right method.
	 *
	 * @return void
	 */
	public function action_index(): void
	{
		global $context, $modSettings;

		if (empty($modSettings['enableTenor']) || empty($this->apiKey))
		{
			return;
		}

		$this->setConfig();

		$subActions = [
			'search' => [$this, 'action_getSearchResults'],
			'trending' => [$this, 'action_getFeatured'],
		];

		$action = new Action('tenor');
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
			'contentfilter' => $modSettings['tenorRating'] ?? 'medium',
			'locale' => $modSettings['tenorLanguage'] ?? 'en',
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

		$result = $this->request('featured', [
			'contentfilter' => $this->config['contentfilter'],
			'limit' => $this->config['limit'],
			'pos' => $this->_req->getQuery('pos', 'trim', '')
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

		$result = $this->request('search', [
			'q' => $this->_req->getQuery('q', 'trim', ''),
			'contentfilter' => $this->config['contentfilter'],
			'limit' => $this->config['limit'],
			'pos' => $this->_req->getQuery('pos', 'trim', '')
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

		setJsonTemplate();
		$context['json_data'] = [
			'tenor' => $images,
			'data' => $result
		];

		return true;
	}

	/**
	 * Sends a request to the Tenor API.
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
		$params = ['key' => $this->apiKey, 'client_key' => $this->config['client_key']] + $params;
		$path .= '?' . http_build_query($params, '', '&');

		require_once(SUBSDIR . '/Package.subs.php');
		$body = fetch_web_data($this->baseApiUrl . $path);
		if ($body !== false)
		{
			$contents = json_decode($body, true);

			return is_array($contents) ? $contents : [];
		}

		$error = true;
		Errors::instance()->log_error('Tenor API error');

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

		if (is_array($result) && isset($result['results']))
		{
			foreach ($result['results'] as $data)
			{
				// Tenor v2 response structure:
				// media_formats -> tinygif, gif, etc.
				$media = $data['media_formats'];

				$images[$data['id']] = [
					'title' => $data['title'],
					'insert' => $media['gif']['url'],
					'src' => $media['tinygif']['url'],
					'thumbnail' => $media['tinygif']['url'],
				];
			}
		}

		return $images;
	}
}
