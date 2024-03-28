/*!
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * @version 2.0 dev
 *
 * This is the service worker for ElkArte PWA and Push
 */

const OFFLINE = '/index.php?action=offline';
const navigationPreload = true;

let STATIC_CACHE_NAME = 'elk_sw_cache_static',
	PAGES_CACHE_NAME = 'elk_sw_cache_pages',
	IMAGES_CACHE_NAME = 'elk_sw_cache_images',
	CACHE_ID = null;

// On sw installation cache some defined ASSETS and the OFFLINE page
self.addEventListener('install', event => {
	self.skipWaiting();

	let passedParam = new URL(location);

	// Use a cache id, so we can do pruning/resets from elk_pwa.js messages
	CACHE_ID = '::' + passedParam.searchParams.get('cache_id') || 'elk20';
	STATIC_CACHE_NAME += CACHE_ID;
	PAGES_CACHE_NAME += CACHE_ID;
	IMAGES_CACHE_NAME += CACHE_ID;

	const themeScope = passedParam.searchParams.get('theme_scope') || '/themes/default/',
		defaultThemeScope = passedParam.searchParams.get('default_theme_scope') || '/themes/default/',
		swScope = passedParam.searchParams.get('sw_scope') || '/',
		cache_stale = passedParam.searchParams.get('cache_stale') || '?elk20',
		ASSETS = defineAssets(themeScope, cache_stale, defaultThemeScope);

	event.waitUntil(
		Promise.all([
			caches.open(STATIC_CACHE_NAME).then(cache => {
				let assetPromises = ASSETS.map(asset => cache.add(asset).catch(err => {
					if (console && console.error)
					{
						console.error('[Error] Asset not found. ', asset, ' ', err.message);
					}
				}));
				return Promise.all(assetPromises);
			}),
			caches.open(PAGES_CACHE_NAME).then(cache => {
				return cache.add(`${swScope}${OFFLINE}`).catch(err => {
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
	if (request.url.match(/scheduled|api=|dlattach|install|google-ad|adsense|action=admin/))
	{
		event.respondWith(handleNavigationPreload(event));
		return;
	}

	// HTML request, selective Cache first with fallback, all others Network only
	if (accept && request.headers.get('Accept').includes('text/html'))
	{
		// Cache the home page
		if (request.url.endsWith('index.php'))
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
			.then(r => pruneCache(50, IMAGES_CACHE_NAME))
			.then(r => pruneCache(10, PAGES_CACHE_NAME));

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
 * Handles navigation preload for the given event.
 *
 * @param {Event} event - The event object.
 * @returns {Promise<unknown | Response>} - A promise that resolves to the preloaded response or fetch response.
 * @throws {Error} - Throws an error if navigation preload is not available or there is no valid preload response.
 */
function handleNavigationPreload (event)
{
	if (!navigationPreload || !event.preloadResponse)
	{
		throw new Error('Navigation Preload not available');
	}

	return event.preloadResponse.then(preloadedResponse => {
		if (!preloadedResponse)
		{
			throw new Error('No valid preload response');
		}
		return preloadedResponse;
	}).catch(e => {
		return fetch(event.request);
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
	return [
		`${themeScope}css/index.css${cache_stale}`,
		`${defaultThemeScope}css/icons_svg.css${cache_stale}`,
		`${defaultThemeScope}scripts/elk_menu.js${cache_stale}`,
		`${defaultThemeScope}scripts/script.js${cache_stale}`,
		`${defaultThemeScope}scripts/script_elk.js${cache_stale}`,
		`${themeScope}scripts/theme.js${cache_stale}`,
		`${defaultThemeScope}scripts/theme.js${cache_stale}`,
		`${defaultThemeScope}scripts/editor/jquery.sceditor.bbcode.min.js${cache_stale}`,
	];
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
			const networkResponsePromise = await fetch(event.request);
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
			const networkResponsePromise = fetch(event.request).catch(() => null);
			const preloadResponsePromise = event.preloadResponse.catch(() => null);
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
	async function fetchAndUpdate (event)
	{
		let networkResponse = null;
		try
		{
			networkResponse = await fetch(event.request);
			const cache = await caches.open(cache_name);
			cache.put(event.request, networkResponse.clone());
		}
		catch (error)
		{
			const offlineRequest = new Request(OFFLINE);
			networkResponse = await cache.match(offlineRequest);
		}
		return networkResponse || new Response('Sorry, you are offline. Please check your connection.');
	}

	event.waitUntil(fetchAndUpdate(event));

	const cache = await caches.open(cache_name);
	const cachedResponse = await cache.match(event.request);
	// If cachedResponse is available, use it
	if (cachedResponse)
	{
		return cachedResponse;
	}
	// If preloadResponse is usable, use it
	if (event.preloadResponse)
	{
		const preloadResponse = await event.preloadResponse;
		if (preloadResponse)
		{
			cache.put(event.request, preloadResponse.clone());
			return preloadResponse;
		}
	}

	// Lastly try networkResponse or failing that show offline
	return fetchAndUpdate(event);
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
 * Removes the oldest item in the specified cache.
 *
 * @param {string} cache_name - The name of the cache.
 *
 * @returns {Promise<boolean>} - A promise that resolves to true if an item was removed, or false if the cache is empty.
 */
async function removeOldestCacheItem (cache_name)
{
	let cache = await caches.open(cache_name),
		keys = await cache.keys();

	if (keys.length > 0)
	{
		await cache.delete(keys[0]);
		return true;
	}

	return false;
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
	while (true)
	{
		let cache = await caches.open(cache_name),
			keys = await cache.keys();

		if (keys.length > maxItems)
		{
			// Exit if nothing was removed from cache
			if (!(await removeOldestCacheItem(cache_name)))
			{
				break;
			}
		}
		// Exit if cache is already at its maximum size
		else
		{
			break;
		}
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

//
// Below are used by PUSH Notifications API
//
function isFunction (obj)
{
	return obj && {}.toString.call(obj) === '[object Function]';
}

function runFunctionString (funcStr)
{
	if (funcStr.trim().length > 0)
	{
		/*jshint -W054 */
		const func = new Function(funcStr);
		if (isFunction(func))
		{
			func();
		}
	}
}

self.onnotificationclose = ({notification}) => {
	runFunctionString(notification.data.onClose);

	/* Tell Push to execute close callback */
	self.client.postMessage(
		JSON.stringify({
			id: notification.data.id,
			action: 'close'
		})
	);
};

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

		/* Removes prepending slash, as we don't need it */
		if (link[0] === '/')
		{
			link = link.length > 1 ? link.substring(1, link.length) : '';
		}

		event.notification.close();

		/* This looks to see if the current is already open and focuses if it is */
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

						/* Covers case where full_url might be http://example.com/john and the client URL is http://example.com/john/ */
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
