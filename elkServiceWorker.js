/*!
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * @version 2.0 Beta 1
 *
 * This is the service worker for ElkArte PWA and Push.
 *
 * The service worker is a performance-caching tool that intercepts network requests and serves cached
 *  responses when available, while also providing network fallback mechanisms for un-cached items.
 */

const OFFLINE = 'index.php?action=offline';
const pendingRequests = new Map();

// Navigation preload can be toggled via query param `nav_preload=0|1`
let STATIC_CACHE_NAME = 'elk_sw_cache_static',
	PAGES_CACHE_NAME = 'elk_sw_cache_pages',
	IMAGES_CACHE_NAME = 'elk_sw_cache_images',
	CACHE_ID = null,
	SW_SCOPE = '/',
	navigationPreload = true;

// On sw installation cache some defined ASSETS and the OFFLINE page
self.addEventListener('install', event => {
	self.skipWaiting();

	let passedParam = new URL(location);

	// Use a cache id, so we can do pruning/resets from elk_pwa.js messages
	const cid = passedParam.searchParams.get('cache_id') || 'elk20b1';
	const np = passedParam.searchParams.get('nav_preload');

	CACHE_ID = '::' + cid;
	STATIC_CACHE_NAME += CACHE_ID;
	PAGES_CACHE_NAME += CACHE_ID;
	IMAGES_CACHE_NAME += CACHE_ID;
	SW_SCOPE = passedParam.searchParams.get('sw_scope') || '/';

	// Allow runtime toggle of Navigation Preload: nav_preload=0|1 (default 1)
	if (np !== null)
	{
		navigationPreload = np === '1' || np.toLowerCase() === 'true';
	}

	const themeScope = passedParam.searchParams.get('theme_scope') || '/themes/default/',
		defaultThemeScope = passedParam.searchParams.get('default_theme_scope') || '/themes/default/',
		cache_stale = passedParam.searchParams.get('cache_stale') || '?R20B1',
		ASSETS = defineAssets(themeScope, cache_stale, defaultThemeScope);

	event.waitUntil(
		Promise.all([
			caches.open(STATIC_CACHE_NAME).then(cache => {
				// Remove duplicates using Set
				const uniqueAssets = [...new Set(ASSETS)];
				return cache.addAll(uniqueAssets).catch(err => {
					if (console && console.error)
					{
						console.error('[Error] Some assets failed to cache: ', err.message);
					}
				});
			}),
			caches.open(PAGES_CACHE_NAME).then(cache => {
				return cache.add(`${SW_SCOPE}${OFFLINE}`).catch(err => {
					if (console && console.error)
					{
						console.error('[Error] Offline Asset not found. ', err.message);
					}
				});
			})
		])
	);
});

/**
 * After install is complete, enable preloading if available.
 *
 * If navigation preload is enabled, a HEAD request is sent to the page's origin at the same time
 * as the service worker starts up. This way, if the service worker is going to just fetch the page
 * from the network anyway, it can get going without having to wait for the installation.
 *
 * Delete any caches that do not match our current version
 */
self.addEventListener('activate', event => {
	event.waitUntil(
		(async function() {
			if (self.registration.navigationPreload)
			{
				await self.registration.navigationPreload[navigationPreload ? 'enable' : 'disable']();
			}
		})()
			.then(() => deleteOldCache())
			.then(() => self.clients.claim())
	);
});

// When the browser makes a request for a resource, determine if its actionable
self.addEventListener('fetch', event => {
	let request = event.request,
		accept = request.headers.get('Accept') || null;

	// Third Party request, POST, non link or address bar
	if (!request.url.startsWith(self.location.origin) || event.request.method !== 'GET')
	{
		event.respondWith(handleNavigationPreload(event));
		return;
	}

	// Admin, tasks, api, install, attachments, other cruft, Network only
	if (request.url.match(/scheduled|api=|dlattach|install|action=credits|action=admin|action=moderate|action=mentions|action=who|action=help|action=search|action=memberlist|action=stats/))
	{
		event.respondWith(handleNavigationPreload(event));
		return;
	}

	// HTML request, selective Cache first with fallback, all others Network only
	if (accept && request.headers.get('Accept').includes('text/html'))
	{
		// Cache the home page
		if (request.url.endsWith('index.php') || request.url.endsWith('?action=forum'))
		{
			return processNetworkFirstRequest(event, PAGES_CACHE_NAME);
		}

		event.respondWith(handleNavigationPreload(event));
		return;
	}

	// CSS Cache first, with a dynamic refresh to account for theme swapping
	if (accept && accept.includes('text/css'))
	{
		return processStaleWhileRevalidateRequest(event, STATIC_CACHE_NAME);
	}

	// JavaScript, Cache first, with a network fallback and cache
	if (accept && accept.includes('text/javascript'))
	{
		return processCacheFirstRequest(event, STATIC_CACHE_NAME);
	}

	// Images Cache first then fallback to Network and cache
	if (accept && accept.includes('image'))
	{
		return processCacheFirstRequest(event, IMAGES_CACHE_NAME);
	}
});

// Message handler, provides a way to interact with the service worker
self.addEventListener('message', function(event) {
	let command = event.data.command || '',
		opts = event.data.opts || {};

	if (command === 'pruneCache')
	{
		pruneCache(25, STATIC_CACHE_NAME)
			.then(() => pruneCache(100, IMAGES_CACHE_NAME))
			.then(() => pruneCache(10, PAGES_CACHE_NAME));

		return;
	}

	if (command === 'deleteOldCache')
	{
		if (opts.cache_id && '::' + opts.cache_id !== CACHE_ID)
		{
			CACHE_ID = '::' + opts.cache_id;
		}

		return deleteOldCache();
	}

	if (command === 'clearAllCache')
	{
		return clearAllCache();
	}

	self.client = event;
});

/**
 * Deduplicates network requests to prevent redundant calls
 *
 * @param {string} requestKey - Unique key for the request
 * @param {function} fetchFunction - Function that returns a promise for the fetch
 * @returns {Promise<Response>} - The response promise
 */
function deduplicateRequest (requestKey, fetchFunction)
{
	// Check if there's already a pending request
	if (pendingRequests.has(requestKey))
	{
		return pendingRequests.get(requestKey);
	}

	// Create new request promise
	const requestPromise = fetchFunction()
		.finally(() => {
			// Clean up after request completes
			pendingRequests.delete(requestKey);
		});

	// Store the promise for deduplication
	pendingRequests.set(requestKey, requestPromise);

	return requestPromise;
}

/**
 * Handles navigation preload for the given event.
 *
 * @param {Event} event - The event object.
 * @returns {Promise<unknown | Response>} - A promise that resolves to the preloaded response or fetch response.
 * @throws {Error} - Throws an error if navigation preload is not available or there is no valid preload response.
 */
function handleNavigationPreload (event)
{
	// Gracefully fall back to network when preload isn't available/enabled
	if (!navigationPreload || !event.preloadResponse)
	{
		return fetch(event.request).catch(async() => {
			const cachedOffline = await caches.open(PAGES_CACHE_NAME).then(c => c.match(`${SW_SCOPE}${OFFLINE}`));
			return cachedOffline || new Response('Sorry, you are offline. Please check your connection.');
		});
	}

	return event.preloadResponse
		.then(preloadedResponse => {
			if (preloadedResponse && preloadedResponse.ok)
			{
				return preloadedResponse;
			}
			return fetch(event.request);
		})
		.catch(async() => {
			const cachedOffline = await caches.open(PAGES_CACHE_NAME).then(c => c.match(`${SW_SCOPE}${OFFLINE}`));
			return cachedOffline || new Response('Sorry, you are offline. Please check your connection.');
		});
}

/**
 * Defines / resolves the assets for a given theme scope.
 *
 * @param {string} themeScope - The theme scope.
 * @param {string} cache_stale - The cache stale value.
 * @param {string} defaultThemeScope - The default theme scope.
 *
 * @returns {string[]} - An array of asset URLs.
 */
function defineAssets (themeScope, cache_stale, defaultThemeScope)
{
	const assets = [
		`${themeScope}css/index.css${cache_stale}`,
		`${defaultThemeScope}css/icons_svg.css${cache_stale}`,
		`${defaultThemeScope}scripts/elk_menu.js${cache_stale}`,
		`${defaultThemeScope}scripts/script.js${cache_stale}`,
		`${defaultThemeScope}scripts/script_elk.js${cache_stale}`,
		`${defaultThemeScope}scripts/theme.js${cache_stale}`,
		`${defaultThemeScope}scripts/editor/jquery.sceditor.bbcode.min.js${cache_stale}`,
		`${themeScope}scripts/theme.js${cache_stale}`,
		`${themeScope}images/logos/logo.png`,
		`${themeScope}images/logos/icon_pwa_small.png`,
		`${themeScope}images/logos/icon_pwa_large.png`,
	];

	// Add theme fallbacks for theme-specific assets (only those not already in the base list)
	if (themeScope !== defaultThemeScope)
	{
		assets.push(
			`${defaultThemeScope}images/logos/logo.png`,
			`${defaultThemeScope}images/logos/icon_pwa_small.png`,
			`${defaultThemeScope}images/logos/icon_pwa_large.png`
		);
	}

	return assets;
}

/**
 * Processes the first request by checking if the response is available in the cache.
 *
 * If it is, the cached response is returned.
 * If not, it checks if there is a preloaded response available. If yes, it adds the preloaded response to the cache
 * and returns the preloaded response.
 * If neither the cached response nor the preloaded response is available, it makes a network call and returns
 * the network response and saves it to the cache.
 * If an error occurs during the process, it returns an offline page or a fallback response if there is no
 * cached offline response.
 *
 * @param {FetchEvent} event - The event object representing the request.
 * @param {String} cache_name - The name of the cache to be used.
 *
 * @returns {void} - A promise that resolves to the response object.
 */
async function processCacheFirstRequest (event, cache_name)
{
	event.respondWith(
		(async() => {
			// Start both promises at the same time
			const cachePromise = caches.open(cache_name).then(cache => cache.match(event.request));
			const preloadPromise = event.preloadResponse;

			// If cached Response is available, use it
			const cachedResponsePromise = await cachePromise;
			if (cachedResponsePromise)
			{
				return cachedResponsePromise;
			}

			// If preloadResponse is usable, use it
			const preloadResponsePromise = await preloadPromise;
			if (preloadResponsePromise)
			{
				return cacheAndReturnResponse(preloadResponsePromise, event.request, cache_name);
			}

			// No response found in cache or preload, fetch from network
			const requestKey = event.request.url + event.request.method;
			const networkResponsePromise = await deduplicateRequest(requestKey, () => fetch(event.request));

			if (networkResponsePromise && networkResponsePromise.ok)
			{
				return cacheAndReturnResponse(networkResponsePromise, event.request, cache_name);
			}

			// Still nothing, return the offline page
			const offlineRequest = new Request(OFFLINE);
			const cachedResponse = await caches.match(offlineRequest);
			return cachedResponse || new Response('Sorry, you are offline. Please check your connection.');
		})()
	);
}

/**
 * Processes a network-first request.
 *
 * Tries the preloadResponse first
 * If the preloadResponse fails, tries a networkResponse
 * If the networkResponse fails or returns an error status code, it falls back to the cache.
 * If all fail, it returns an offline page.
 * Successful preloadResponse or networkResponse are saved to the cache.
 *
 * @param {FetchEvent} event - The event object for the fetch event.
 * @param {string} cache_name - The cache to look in/open.
 * @return {void} - A promise that resolves to the fetched response or the offline page.
 */
async function processNetworkFirstRequest (event, cache_name)
{
	event.respondWith(
		(async() => {
			const requestKey = event.request.url + event.request.method;
			const networkResponsePromise = deduplicateRequest(requestKey, () => fetch(event.request)).catch(() => null);
			const preloadResponsePromise = (event.preloadResponse || Promise.resolve(null)).catch(() => null);
			const [networkResponse, preloadResponse] = await Promise.all([networkResponsePromise, preloadResponsePromise]);

			// If preloadResponse is usable, use it
			if (preloadResponse && preloadResponse.ok)
			{
				return cacheAndReturnResponse(preloadResponse, event.request, cache_name);
			}

			// If networkResponse is usable, use it
			if (networkResponse && networkResponse.ok)
			{
				return cacheAndReturnResponse(networkResponse, event.request, cache_name);
			}

			// Both failed, so try the cache
			const cachedResponse = await caches.match(event.request);
			if (cachedResponse)
			{
				return cachedResponse;
			}

			// Still nothing, return the offline page
			const offlineRequest = new Request(OFFLINE);
			const cachedOfflineResponse = await caches.match(offlineRequest);
			return cachedOfflineResponse || new Response('Sorry, you are offline. Please check your connection.');
		})()
	);
}

/**
 * Processes a stale-while-revalidate request.
 *
 * When a request is made, this method first checks if there is a cached response for the request.
 * If a cached response is found, it returns the cached response immediately.
 * Meanwhile, it also sends a network request to fetch the latest response from the server.
 * If the network request is successful, the fetched response is stored in the cache for future use.
 * If both the cache and network requests fail, it returns the offline page.
 *
 * @param {FetchEvent} event - The fetch event object containing the request.
 * @param {string} cache_name - The cache to look in/open.
 * @return {Promise<Response>} - A promise that resolves to a Response object.
 */
async function processStaleWhileRevalidateRequest (event, cache_name)
{
	async function fetchAndUpdate ()
	{
		const requestKey = event.request.url + event.request.method;

		return deduplicateRequest(requestKey, async() => {
			try
			{
				const networkResponse = await fetch(event.request);
				const cache = await caches.open(cache_name);
				cache.put(event.request, networkResponse.clone());
				return networkResponse;
			}
			catch (error)
			{
				const offlineRequest = new Request(OFFLINE);
				const cachedOffline = await caches.match(offlineRequest);
				return cachedOffline || new Response('Sorry, you are offline. Please check your connection.');
			}
		});
	}

	// Ensure we respond within the fetch event
	event.respondWith((async() => {
		const cache = await caches.open(cache_name);
		const cachedResponse = await cache.match(event.request);
		if (cachedResponse)
		{
			// Update in background
			event.waitUntil(fetchAndUpdate());
			return cachedResponse;
		}

		if (event.preloadResponse)
		{
			const preloadResponse = await event.preloadResponse;
			if (preloadResponse)
			{
				cache.put(event.request, preloadResponse.clone());
				// Also refresh in background
				event.waitUntil(fetchAndUpdate());
				return preloadResponse;
			}
		}

		// Lastly try network or offline fallback
		return fetchAndUpdate();
	})());
}

/**
 * Caches the response and returns it.
 *
 * @param {Promise<Response>} responsePromise - The promise that resolves to the response.
 * @param {Request} request - The request object.
 * @param {string} cache_name - The name of the cache.
 * @returns {Promise<Response>} - The response promise that was passed as an argument.
 */
async function cacheAndReturnResponse (responsePromise, request, cache_name)
{
	if (responsePromise && responsePromise.ok)
	{
		// Add to cache but don't wait for it to complete
		let cache = await caches.open(cache_name);
		cache.put(request, responsePromise.clone());
		return responsePromise;
	}
}

/**
 * Prunes a cache by removing the oldest items until the maximum item limit is reached.
 *
 * @param {number} maxItems - The maximum number of items to keep in the cache.
 * @param {string} cache_name - The name of the cache to prune.
 * @returns {Promise<void>} - A promise that resolves when the cache has been pruned.
 */
async function pruneCache (maxItems, cache_name)
{
	const cache = await caches.open(cache_name);
	const keys = await cache.keys();

	if (keys.length > maxItems)
	{
		const toDelete = keys.slice(0, keys.length - maxItems);
		await Promise.all(toDelete.map(key => cache.delete(key)));
	}
}

/**
 * Deletes cache buckets that do not have the current cache_ID
 *
 * @returns {Promise<Awaited<boolean>[]>} A promise that resolves to an array of booleans indicating
 * whether each cache entry was successfully deleted.
 */
function deleteOldCache ()
{
	return caches.keys()
		.then(function(keys) {
			return Promise.all(keys
				.filter(function(key) {
					return key.indexOf(CACHE_ID) === -1;
				})
				.map(function(key) {
					return caches.delete(key);
				})
			);
		});
}

/**
 * Clears all cache buckets.
 *
 * @returns {Promise<Awaited<boolean>[]>} A promise that resolves to an array of booleans indicating
 * successful cache deletions.
 */
function clearAllCache ()
{
	return caches.keys()
		.then(function(cacheNames) {
			return Promise.all(
				cacheNames.map(function(cacheName) {
					return caches.delete(cacheName);
				})
			);
		});
}

/**
 * Below is used by PUSH Notifications API.  We override the default elkServiceWorker.min.js
 * used by Push in favor of this full service worker.
 *
 * @param funcStr
 */
function runFunctionString (funcStr)
{
	if (funcStr && funcStr.trim().length > 0)
	{
		// Send structured message to main thread instead of executing arbitrary code
		if (self.client && self.client.postMessage)
		{
			self.client.postMessage(
				JSON.stringify({
					action: 'notification_callback',
					callback: funcStr,
					timestamp: Date.now()
				})
			);
		}
	}
}

/**
 * Handles the close event on a notification. It runs any specified onClose callback and sends a
 * message to the main thread to execute the close callback.
 *
 * @param notification
 */
self.onnotificationclose = ({notification}) => {
	runFunctionString(notification.data.onClose);

	/* Tell Push to execute close callback */
	if (self.client && self.client.postMessage)
	{
		self.client.postMessage(
			JSON.stringify({
				id: notification.data.id,
				action: 'close'
			})
		);
	}
};

/**
 * Handles the click event on a notification. It checks if the notification has a link and focuses an
 * existing client/browser tab with that link or opens a new window if no such client exists. It also runs any
 * specified onClick callback.
 *
 * @param event
 */
self.onnotificationclick = event => {
	let link, origin, href;

	if (
		typeof event.notification.data.link !== 'undefined' &&
		event.notification.data.link !== null
	)
	{
		origin = event.notification.data.origin;
		link = event.notification.data.link;
		href = origin.substring(0, origin.indexOf('/', 8)) + '/';

		// Removes prepending slash, as we don't need it
		if (link[0] === '/')
		{
			link = link.length > 1 ? link.substring(1, link.length) : '';
		}

		event.notification.close();

		// This looks to see if the current is already open and focuses if it is
		event.waitUntil(
			clients
				.matchAll({
					type: 'window'
				})
				.then(clientList => {
					let client, full_url;

					for (let i = 0; i < clientList.length; i++)
					{
						client = clientList[i];
						full_url = href + link;

						// Covers case where full_url might be http://example.com/john and the client URL is http://example.com/john/
						if (
							full_url[full_url.length - 1] !== '/' &&
							client.url[client.url.length - 1] === '/'
						)
						{
							full_url += '/';
						}

						if (client.url === full_url && 'focus' in client)
						{
							return client.focus();
						}
					}

					if (clients.openWindow)
					{
						return clients.openWindow('/' + link);
					}
				})
				.catch(({message}) => {
					throw new Error(
						'A ServiceWorker error occurred: ' + message
					);
				})
		);
	}

	runFunctionString(event.notification.data.onClick);
};
