/*!
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * @version 2.0 dev
 *
 * This bits acts as middle-man between the notify (above) and the ElkNotifications
 * providing the interface required by the latter.
 */

(function () {
	var ElkDesktop = (function (opt) {
		'use strict';
		opt = (opt) ? opt : {};

		var send = function (request) {
			if (request.desktop_notifications.new_from_last > 0) {
				if (hasPermissions(request))
				{
					Push.create(request.desktop_notifications.title, {
						body: request.desktop_notifications.message,
						icon: opt.icon,
						link: request.desktop_notifications.link
					});
				}
			}
		};

		var hasPermissions = function () {
			if (Push.Permission.has())
				return true;

			if (Push.Permission.get() === "default") {
				return Push.Permission.request();
			}

			return false;
		};

		return {
			send: send
		};
	});

	this.ElkDesktop = ElkDesktop;
})();
