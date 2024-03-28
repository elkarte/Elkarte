/*!
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * @version 2.0 dev
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

	function init ()
	{
		if (!isEnabled())
		{
			return;
		}

		// Pass swOpt as Query parameters to the service worker
		let params = new URLSearchParams(settings.swOpt).toString();
		let urlWithParams = settings.swUrl + '?' + params;

		if ('serviceWorker' in navigator)
		{
			navigator.serviceWorker.getRegistration(urlWithParams)
				.then((registration) => {
					if (!registration)
					{
						navigator.serviceWorker.register(urlWithParams)
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

	function getScope (checkUrl = '')
	{
		const url = new URL(checkUrl || elk_board_url);

		return url.pathname === '' ? '/' : '/' + url.pathname.replace(/^\/|\/$/g, '');
	}

	function isEnabled ()
	{
		if (settings.isEnabled === null)
		{
			settings.isEnabled = !!('serviceWorker' in navigator && (settings.swUrl && settings.swUrl !== ''));
		}

		return settings.isEnabled;
	}

	// Service Workers don’t take control of the page immediately but on subsequent page loads
	function sendMessage (command, opts = {})
	{
		if (navigator.serviceWorker.controller)
		{
			navigator.serviceWorker.controller.postMessage({command, opts});
		}
	}

	function removeServiceWorker()
	{
		// Remove service worker if found
		if ('serviceWorker' in navigator)
		{
			navigator.serviceWorker.getRegistrations()
				.then(allRegistrations => {
					let scope = getScope();

					Object.values(allRegistrations).forEach(registration => {
						if (getScope(registration.scope) === scope)
						{
							sendMessage('clearAllCache');
							registration.unregister();
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
