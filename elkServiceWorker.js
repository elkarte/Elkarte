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

let STATIC_CACHE_NAME = 'elk_sw_cache_static',
	PAGES_CACHE_NAME = 'elk_sw_cache_pages',
	IMAGES_CACHE_NAME = 'elk_sw_cache_images',
	CACHE_ID = null,
	SW_SCOPE = '/';

// On sw installation cache some defined ASSETS and the OFFLINE page
self.addEventListener('install', event => {
	self.skipWaiting();

	let passedParam = new URL(location);

	// Use a cache id, so we can do pruning/resets from elk_pwa.js messages
	const cid = passedParam.searchParams.get('cache_id') || 'elk20b1';

	CACHE_ID = '::' + cid;
	STATIC_CACHE_NAME += CACHE_ID;
	PAGES_CACHE_NAME += CACHE_ID;
	IMAGES_CACHE_NAME += CACHE_ID;
	SW_SCOPE = passedParam.searchParams.get('sw_scope') || '/';

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
 * After install is complete, claim clients.
 * Delete any caches that do not match our current version.
 */
self.addEventListener('activate', event => {
	event.waitUntil(
		deleteOldCache().then(() => self.clients.claim())
	);
});

// When the browser makes a request for a resource, determine if its actionable
self.addEventListener('fetch', event => {
	let request = event.request,
		accept = request.headers.get('Accept') || null;

	// Third Party request, POST, non link or address bar
	if (!request.url.startsWith(self.location.origin) || event.request.method !== 'GET')
	{
		event.respondWith(fetchWithOfflineFallback(event.request));
		return;
	}

	// Admin, tasks, api, install, attachments, other cruft, Network only
	if (request.url.match(/scheduled|api=|dlattach|install|action=credits|action=admin|action=moderate|action=mentions|action=who|action=help|action=search|action=memberlist|action=stats/))
	{
		event.respondWith(fetchWithOfflineFallback(event.request));
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

		event.respondWith(fetchWithOfflineFallback(event.request));
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

	// Catch-all for anything unmatched (e.g. favicon.ico with */* Accept header)
	event.respondWith(fetchWithOfflineFallback(event.request));
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
 * Simple fetch with offline fallback. Replaces handleNavigationPreload now that
 * navigation preload has been removed.
 *
 * @param {Request} request - The request to fetch.
 * @returns {Promise<Response>}
 */
async function fetchWithOfflineFallback (request)
{
	try
	{
		return await fetch(request);
	}
	catch (error)
	{
		const cachedOffline = await caches.open(PAGES_CACHE_NAME).then(c => c.match(`${SW_SCOPE}${OFFLINE}`));
		return cachedOffline || new Response('Sorry, you are offline. Please check your connection.');
	}
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
 * If not, it makes a network call, caches the response, and returns it.
 * If an error occurs during the process, it returns an offline page or a fallback response.
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
			// Check cache first
			const cachedResponse = await caches.open(cache_name).then(cache => cache.match(event.request));
			if (cachedResponse)
			{
				return cachedResponse;
			}

			// Not in cache, fetch from network
			const networkResponse = await fetch(event.request).catch(() => null);

			if (networkResponse && networkResponse.ok)
			{
				return cacheAndReturnResponse(networkResponse, event.request, cache_name);
			}

			// Still nothing, return the offline page
			const offlineRequest = new Request(OFFLINE);
			const cachedOffline = await caches.match(offlineRequest);
			return cachedOffline || new Response('Sorry, you are offline. Please check your connection.');
		})()
	);
}

/**
 * Processes a network-first request.
 *
 * Tries the network first, falls back to cache, then offline page.
 * Successful network responses are saved to the cache.
 *
 * @param {FetchEvent} event - The event object for the fetch event.
 * @param {string} cache_name - The cache to look in/open.
 * @return {void} - A promise that resolves to the fetched response or the offline page.
 */
async function processNetworkFirstRequest (event, cache_name)
{
	event.respondWith(
		(async() => {
			const networkResponse = await fetch(event.request).catch(() => null);

			if (networkResponse && networkResponse.ok)
			{
				return cacheAndReturnResponse(networkResponse, event.request, cache_name);
			}

			// Network failed, try cache
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
 * Returns cached response immediately if available, then updates cache in background.
 * If no cache, fetches from network and caches the result.
 * If all fail, returns the offline page.
 *
 * @param {FetchEvent} event - The fetch event object containing the request.
 * @param {string} cache_name - The cache to look in/open.
 * @return {Promise<Response>} - A promise that resolves to a Response object.
 */
async function processStaleWhileRevalidateRequest (event, cache_name)
{
	async function fetchAndUpdate ()
	{
		try
		{
			const networkResponse = await fetch(event.request);
			if (networkResponse && networkResponse.ok)
			{
				const cache = await caches.open(cache_name);
				cache.put(event.request, networkResponse.clone());
			}
			return networkResponse;
		}
		catch (error)
		{
			const offlineRequest = new Request(OFFLINE);
			const cachedOffline = await caches.match(offlineRequest);
			return cachedOffline || new Response('Sorry, you are offline. Please check your connection.');
		}
	}

	event.respondWith((async() => {
		const cache = await caches.open(cache_name);
		const cachedResponse = await cache.match(event.request);
		if (cachedResponse)
		{
			// Update in background
			event.waitUntil(fetchAndUpdate());
			return cachedResponse;
		}

		// No cache, fetch from network
		return fetchAndUpdate();
	})());
}

/**
 * Caches the response and returns it.
 *
 * @param {Response} response - The response to cache and return.
 * @param {Request} request - The request object.
 * @param {string} cache_name - The name of the cache.
 * @returns {Promise<Response>} - The response that was passed as an argument.
 */
async function cacheAndReturnResponse (response, request, cache_name)
{
	if (response && response.ok)
	{
		let cache = await caches.open(cache_name);
		cache.put(request, response.clone());
	}
	return response;
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
