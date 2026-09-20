/*!
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * @version 2.0 Beta 1
 *
 * This registers the service worker for PWA and Push and provides interface functions
 */

const elkPwa = (opt) => {
	let defaults = {
		isEnabled: null,
		swUrl: 'elkServiceWorker.js',
		swOpt: {}
	};

	let settings = Object.assign({}, defaults, opt);

	/**
	 * Initializes the service worker.
	 */
	function init ()
	{
		if (!isEnabled())
		{
			return;
		}

		// Pass swOpt as Query parameters to the service worker
		let params = new URLSearchParams(settings.swOpt).toString();
		let urlWithParams = settings.swUrl + '?' + params;
		let scope = settings.swOpt.sw_scope || '/';

		if ('serviceWorker' in navigator)
		{
			navigator.serviceWorker.getRegistration(scope)
				.then((registration) => {
					if (!registration)
					{
						navigator.serviceWorker.register(urlWithParams, { scope: scope })
							.then(registration => {
								if ('console' in window && console.info)
								{
									console.info('[Info] Service Worker Registered');
								}
							})
							.catch(error => {
								if ('console' in window && console.error)
								{
									console.error('[Error] Service worker registration failed:', error);
								}
							});
					}
				})
				.catch((error) => {
					if ('console' in window && console.error)
					{
						console.error('[Error] During getRegistration', error);
					}
				});
		}
	}

	/**
	 * Get the scope of the service worker based on the provided URL or the current page URL.
	 *
	 * @param {string} checkUrl - The URL to check for the service worker scope. Defaults to the current page URL.
	 * @returns {string} The scope of the service worker.
	 */
	function getScope (checkUrl = '')
	{
		const url = new URL(checkUrl || elk_board_url);

		return url.pathname === '' ? '/' : '/' + url.pathname.replace(/^\/|\/$/g, '');
	}

	/**
	 * Checks if the service worker is enabled.
	 *
	 * @returns {boolean} True if the service worker is enabled, false otherwise.
	 */
	function isEnabled ()
	{
		if (settings.isEnabled === null)
		{
			settings.isEnabled = !!('serviceWorker' in navigator && (settings.swUrl && settings.swUrl !== ''));
		}

		return settings.isEnabled;
	}

	/**
	 * Sends a message to the service worker.
	 *
	 * @param {string} command - The command to send.
	 * @param {Object} opts - Additional options for the command.
	 */
	function sendMessage (command, opts = {})
	{
		if (navigator.serviceWorker.controller)
		{
			navigator.serviceWorker.controller.postMessage({command, opts});
		}
	}

	/**
	 * Removes the service worker.
	 */
	function removeServiceWorker()
	{
		// Remove service worker if found
		if ('serviceWorker' in navigator)
		{
			navigator.serviceWorker.getRegistrations()
				.then(allRegistrations => {
					let scope = getScope();

					Object.values(allRegistrations).forEach(async registration => {
						if (getScope(registration.scope) === scope)
						{
							sendMessage('clearAllCache');
							await registration.unregister();
							if ('console' in window && console.info)
							{
								console.info('[Info] Service worker removed: ', registration.scope);
							}
						}
					});
				});
		}
	}

	return {
		init,
		sendMessage,
		removeServiceWorker
	};
};
